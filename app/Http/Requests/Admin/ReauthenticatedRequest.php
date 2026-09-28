<?php

namespace App\Http\Requests\Admin;

use App\Services\Auth\Reauthenticator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Any privileged action form (open/close election, publish results, admin
 * management, settings). Validates the password (and TOTP code when the admin
 * uses MFA) as part of request validation.
 */
class ReauthenticatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_password' => ['required', 'string', 'max:200'],
            'confirm_mfa' => [$this->user()?->hasMfa() ? 'required' : 'nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_password.required' => 'Enter your password to confirm this action.',
            'confirm_mfa.required' => 'Enter your current authenticator code to confirm this action.',
        ];
    }

    protected function passedValidation(): void
    {
        app(Reauthenticator::class)->confirm(
            $this->user(),
            $this->input('confirm_password'),
            $this->input('confirm_mfa'),
            (string) $this->route()?->getName(),
        );
    }
}
