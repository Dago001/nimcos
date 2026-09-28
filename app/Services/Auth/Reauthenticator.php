<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Step-up authentication for highly privileged actions (spec §27): opening/closing
 * elections, publishing results, creating administrators, changing roles/settings.
 * Checked on every such request; there is no reusable "sudo" window.
 */
class Reauthenticator
{
    public function __construct(
        private readonly Totp $totp,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function confirm(User $user, ?string $password, ?string $mfaCode, string $purpose): void
    {
        $passwordOk = is_string($password) && $password !== '' && Hash::check($password, $user->password);
        $mfaOk = ! $user->hasMfa() || (is_string($mfaCode) && $this->totp->verify($user->mfa_secret, $mfaCode));

        if ($passwordOk && $mfaOk) {
            return;
        }

        $this->audit->failure(AuditAction::REAUTH_FAILED, $user, ['purpose' => $purpose]);

        throw ValidationException::withMessages([
            'confirm_password' => $passwordOk
                ? 'The authenticator code is incorrect.'
                : 'Your password is incorrect. Re-enter it to confirm this action.',
        ]);
    }
}
