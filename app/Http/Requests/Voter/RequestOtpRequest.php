<?php

namespace App\Http\Requests\Voter;

use App\Support\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['service_number' => ServiceNumber::normalise($this->input('service_number'))]);
    }

    public function rules(): array
    {
        return [
            'service_number' => ['required', 'string', 'max:30', 'regex:'.config('nimcos.voters.service_number_pattern')],
        ];
    }

    public function messages(): array
    {
        return [
            'service_number.required' => 'Enter your Service Number.',
            'service_number.regex' => 'That Service Number is not valid. Check it and try again.',
        ];
    }
}
