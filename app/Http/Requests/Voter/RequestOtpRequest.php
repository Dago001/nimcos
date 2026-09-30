<?php

namespace App\Http\Requests\Voter;

use App\Models\Election;
use App\Services\Voting\HumanChallenge;
use App\Support\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // Only required while the sign-in form is actually shown (an open
            // election): with no form on screen there is nothing to protect,
            // and requiring it anyway would let a direct POST tell open from
            // closed by which error comes back.
            'human_check' => [$this->humanCheckRequired() ? 'accepted' : 'nullable'],
        ];
    }

    private function humanCheckRequired(): bool
    {
        return Election::query()->acceptingVotes()->exists();
    }

    public function messages(): array
    {
        return [
            'service_number.required' => 'Enter your Service Number.',
            'service_number.regex' => 'That Service Number is not valid. Check it and try again.',
            'human_check.accepted' => 'Please confirm you are not a robot before continuing.',
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->humanCheckRequired()) {
                return;
            }
            // Checked separately from the "accepted" rule above so a ticked box with a
            // wrong or expired answer gets its own, more specific message.
            if ($validator->errors()->has('human_check')) {
                return;
            }
            $verified = app(HumanChallenge::class)->verify(
                $this,
                $this->input('human_check_token'),
                $this->input('human_check_answer'),
            );
            if (! $verified) {
                $validator->errors()->add('human_check', 'That was not quite right. Please answer the new question below and try again.');
            }
        }];
    }
}
