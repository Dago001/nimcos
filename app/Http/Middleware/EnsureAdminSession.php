<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After `auth:web`: the account must be active, the MFA step completed, MFA
 * enrolled when policy requires it, and any forced password change done.
 */
class EnsureAdminSession
{
    public const MFA_PASSED = 'admin.mfa_passed';

    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user('web');

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors(['email' => 'Your account has been disabled.']);
        }

        $mfaRequired = (bool) config('nimcos.admin.require_mfa') || (bool) $this->settings->get('require_admin_mfa');

        if (! $request->session()->get(self::MFA_PASSED) && ($user->hasMfa() || $mfaRequired)) {
            return redirect()->route('admin.mfa.challenge');
        }
        $exempt = ['admin.profile.mfa', 'admin.profile.mfa.enable', 'admin.profile.password', 'admin.profile.password.update', 'admin.logout'];
        $route = $request->route()?->getName();

        if ($mfaRequired && ! $user->hasMfa() && ! in_array($route, $exempt, true)) {
            return redirect()->route('admin.profile.mfa')->with('warning', 'Multi-factor authentication is required. Set up your authenticator app to continue.');
        }

        if ($user->must_change_password && ! in_array($route, $exempt, true)) {
            return redirect()->route('admin.profile.password')->with('warning', 'You must change your temporary password before continuing.');
        }

        return $next($request);
    }
}
