<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditResult;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminSession;
use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Reauthenticator;
use App\Services\Auth\Totp;
use App\Services\Settings\SettingsService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private const PENDING_SECRET = 'admin.mfa_pending_secret';

    public function __construct(
        private readonly Totp $totp,
        private readonly AuditLogger $audit,
        private readonly Reauthenticator $reauth,
    ) {}

    public function mfa(Request $request, SettingsService $settings): View
    {
        /** @var User $user */
        $user = $request->user();
        $qr = null;
        $secret = null;

        if (! $user->hasMfa()) {
            $secret = $request->session()->get(self::PENDING_SECRET) ?? $this->totp->generateSecret();
            $request->session()->put(self::PENDING_SECRET, $secret);
            $uri = $this->totp->provisioningUri($secret, $user->email, 'NIMCOS E-Voting');
            $qr = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
            $qr = 'data:image/svg+xml;base64,'.base64_encode($qr);
        }

        return view('admin.profile.security', [
            'user' => $user,
            'qr' => $qr,
            'secret' => $secret ? trim(chunk_split($secret, 4, ' ')) : null,
            'mfaRequired' => config('nimcos.admin.require_mfa') || $settings->get('require_admin_mfa'),
        ]);
    }

    public function enableMfa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mfa_code' => ['required', 'string', 'max:10'],
            'confirm_password' => ['required', 'string'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get(self::PENDING_SECRET);

        $this->reauth->confirm($user, $data['confirm_password'], null, 'enable_mfa');
        if (! $secret || ! $this->totp->verify($secret, $data['mfa_code'])) {
            throw ValidationException::withMessages(['mfa_code' => 'That code did not match. Check the time on your phone and try the current code.']);
        }

        $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now()])->save();
        $request->session()->forget(self::PENDING_SECRET);
        $request->session()->put(EnsureAdminSession::MFA_PASSED, true);
        $this->audit->log(AuditAction::ADMIN_MFA_ENABLED, AuditResult::SUCCESS, $user);

        return redirect()->route('admin.profile.mfa')->with('success', 'Multi-factor authentication is now enabled.');
    }

    public function disableMfa(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'confirm_password' => ['required', 'string'],
            'mfa_code' => ['required', 'string', 'max:10'],
        ]);
        /** @var User $user */
        $user = $request->user();
        if (config('nimcos.admin.require_mfa') || $settings->get('require_admin_mfa')) {
            throw ValidationException::withMessages(['mfa_code' => 'MFA is required by policy and cannot be disabled.']);
        }
        $this->reauth->confirm($user, $data['confirm_password'], $data['mfa_code'], 'disable_mfa');

        $user->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null])->save();
        $this->audit->log(AuditAction::ADMIN_MFA_DISABLED, AuditResult::SUCCESS, $user);

        return redirect()->route('admin.profile.mfa')->with('success', 'Multi-factor authentication has been disabled.');
    }

    public function password(Request $request): View
    {
        return view('admin.profile.password', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            $this->audit->failure(AuditAction::REAUTH_FAILED, $user, ['purpose' => 'change_password']);
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }

        $user->password = $data['password'];
        $user->forceFill(['password_changed_at' => now(), 'must_change_password' => false])->save();
        $request->session()->regenerate(true);
        $this->audit->log(AuditAction::ADMIN_PASSWORD_CHANGED, AuditResult::SUCCESS, $user);

        return redirect()->route('admin.dashboard')->with('success', 'Your password has been changed.');
    }
}
