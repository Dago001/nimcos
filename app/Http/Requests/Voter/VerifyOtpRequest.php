<?php

namespace App\Http\Requests\Voter;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => preg_replace('/\D+/', '', (string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:'.config('nimcos.otp.length')],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Enter the code sent to you.',
            'code.digits' => 'The code must be '.config('nimcos.otp.length').' digits.',
        ];
    }
}
