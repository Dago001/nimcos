<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlertSeverity;
use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminSession;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Totp;
use App\Services\Security\SecurityAlertService;
use App\Services\Settings\SettingsService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const FAILED = 'The email address or password is incorrect.';

    public const PENDING_SECRET = 'admin.mfa_pending_secret';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SecurityAlertService $alerts,
        private readonly Totp $totp,
        private readonly SettingsService $settings,
    ) {}

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $email = mb_strtolower(trim($credentials['email']));

        /** @var User|null $user */
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();
        $actor = ['type' => 'ANONYMOUS', 'id' => $user?->getKey(), 'label' => $email];

        if ($user && $user->isLocked()) {
            $this->audit->denied(AuditAction::ADMIN_LOGIN_FAILED, $user, ['reason' => 'locked'], $actor);

            return back()->onlyInput('email')->withErrors(['email' => 'This account is temporarily locked after repeated failed sign-in attempts. Try again later or contact a Super Admin.']);
        }

        // Constant-work check even for unknown accounts to avoid user enumeration by timing.
        $valid = $user
            ? Hash::check($credentials['password'], $user->password)
            : (Hash::make($credentials['password']) && false);

        if (! $valid || ! $user || ! $user->isActive()) {
            if ($user) {
                $this->registerFailure($user, $request);
            }
            $this->audit->failure(AuditAction::ADMIN_LOGIN_FAILED, $user, ['reason' => $user && ! $user->isActive() ? 'disabled' : 'bad_credentials'], $actor);

            return back()->onlyInput('email')->withErrors(['email' => self::FAILED]);
        }

        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate(true);
        Auth::guard('web')->login($user);

        $mfaRequired = (bool) config('nimcos.admin.require_mfa') || (bool) $this->settings->get('require_admin_mfa');

        if ($user->hasMfa() || $mfaRequired) {
            $request->session()->put(EnsureAdminSession::MFA_PASSED, false);
            $this->audit->log(AuditAction::ADMIN_LOGIN, AuditResult::SUCCESS, $user, ['mfa_pending' => true]);

            return redirect()->route('admin.mfa.challenge');
        }

        $request->session()->put(EnsureAdminSession::MFA_PASSED, true);
        $this->audit->log(AuditAction::ADMIN_LOGIN, AuditResult::SUCCESS, $user, ['mfa_pending' => false]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function mfaChallenge(Request $request): View|RedirectResponse
    {
        if ($request->session()->get(EnsureAdminSession::MFA_PASSED)) {
            return redirect()->route('admin.dashboard');
        }

        /** @var User|null $user */
        $user = $request->user('web');
        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->hasMfa()) {
            $secret = $request->session()->get(self::PENDING_SECRET) ?? $this->totp->generateSecret();
            $request->session()->put(self::PENDING_SECRET, $secret);

            $uri = $this->totp->provisioningUri($secret, $user->email, 'NIMCOS E-Voting');
            $qr = (new Writer(new ImageRenderer(new RendererStyle(140, 1), new SvgImageBackEnd)))->writeString($uri);
            $qr = 'data:image/svg+xml;base64,'.base64_encode($qr);

            return view('admin.auth.mfa-setup', [
                'user' => $user,
                'qr' => $qr,
                'secret' => trim(chunk_split($secret, 4, ' ')),
            ]);
        }

        return view('admin.auth.mfa');
    }

    public function verifyMfa(Request $request): RedirectResponse
    {
        $data = $request->validate(['mfa_code' => ['required', 'string', 'max:10']]);
        /** @var User|null $user */
        $user = $request->user('web');
        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->hasMfa()) {
            $secret = $request->session()->get(self::PENDING_SECRET);

            if (! $secret || ! $this->totp->verify($secret, $data['mfa_code'])) {
                $this->audit->failure(AuditAction::ADMIN_MFA_FAILED, $user, ['stage' => 'setup']);

                return back()->withErrors(['mfa_code' => 'The authenticator code is incorrect. Check the code in Google Authenticator and try again.']);
            }

            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_confirmed_at' => now(),
            ])->save();

            $request->session()->forget(self::PENDING_SECRET);
            $request->session()->regenerate(true);
            $request->session()->put(EnsureAdminSession::MFA_PASSED, true);
            $this->audit->log(AuditAction::ADMIN_MFA_ENABLED, AuditResult::SUCCESS, $user);

            if ($user->must_change_password) {
                return redirect()->route('admin.profile.password')
                    ->with('warning', 'Two-factor authentication configured. You must change your temporary password before continuing.');
            }

            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Two-factor authentication configured successfully. Welcome to NIMCOS.');
        }

        if ($this->totp->verify($user->mfa_secret, $data['mfa_code'])) {
            $request->session()->regenerate(true);
            $request->session()->put(EnsureAdminSession::MFA_PASSED, true);

            if ($user->must_change_password) {
                return redirect()->route('admin.profile.password')
                    ->with('warning', 'You must change your temporary password before continuing.');
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        $this->audit->failure(AuditAction::ADMIN_MFA_FAILED, $user);
        $this->registerFailure($user, $request);
        if ($user->fresh()->isLocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors(['email' => 'Too many failed attempts. The account is temporarily locked.']);
        }

        return back()->withErrors(['mfa_code' => 'The authenticator code is incorrect.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log(AuditAction::ADMIN_LOGOUT);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been signed out.');
    }

    private function registerFailure(User $user, Request $request): void
    {
        $max = (int) config('nimcos.admin.max_failed_logins');
        $count = $user->failed_login_count + 1;
        $user->failed_login_count = $count;

        if ($count >= $max) {
            $user->locked_until = now()->addMinutes((int) config('nimcos.admin.lockout_minutes'));
            $user->failed_login_count = 0;
            $this->audit->log(AuditAction::ADMIN_LOCKED, AuditResult::DENIED, $user, [], ['type' => 'SYSTEM', 'id' => null, 'label' => 'lockout']);
            $this->alerts->raise(
                SecurityAlertService::ADMIN_LOCKOUT,
                AlertSeverity::HIGH,
                'An administrator account was locked after repeated failed sign-in attempts.',
                $request->ip(),
                $user->email,
            );
        }
        $user->save();
    }
}
