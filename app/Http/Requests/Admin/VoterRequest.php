<?php

namespace App\Http\Requests\Admin;

use App\Enums\MembershipStatus;
use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Models\Voter;
use App\Support\PhoneNumber;
use App\Support\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VoterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = (string) $this->input('phone');
        $this->merge([
            'service_number' => ServiceNumber::normalise($this->input('service_number')),
            'membership_id' => $this->input('membership_id') ? mb_strtoupper(trim((string) $this->input('membership_id'))) : null,
            'gender' => $this->input('gender') ? mb_strtoupper(trim((string) $this->input('gender'))) : null,
            'phone_normalised' => PhoneNumber::normalise($phone),
            'email' => $this->input('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
            'surname' => mb_strtoupper(trim((string) $this->input('surname'))),
        ]);
    }

    public function rules(): array
    {
        /** @var Voter|null $voter */
        $voter = $this->route('voter');
        $name = "regex:/^[\pL\pM' .\-]+$/u";

        return [
            'service_number' => ['required', 'string', 'max:5', 'regex:'.config('nimcos.voters.service_number_pattern'),
                Rule::unique('voters', 'service_number')->ignore($voter?->getKey())],
            'membership_id' => ['nullable', 'string', 'max:50'],
            'surname' => ['required', 'string', 'max:100', $name],
            'first_name' => ['required', 'string', 'max:100', $name],
            'other_names' => ['nullable', 'string', 'max:150', $name],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date', 'before:today'],
            'rank' => ['nullable', Rule::enum(NisRank::class)],
            'command' => ['nullable', Rule::enum(NisCommand::class)],
            'formation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'membership_status' => ['required', Rule::enum(MembershipStatus::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('phone') && trim((string) $this->input('phone')) !== '' && $this->input('phone_normalised') === null) {
                $validator->errors()->add('phone', 'Enter a valid Nigerian mobile number, e.g. 0803 123 4567, or leave it blank.');
            }
            if ($validator->errors()->has('email')) {
                return;
            }
            $owner = Voter::query()->where('email', $this->input('email'))
                ->when($this->route('voter'), fn ($q, $v) => $q->whereKeyNot($v->getKey()))
                ->value('service_number');
            if ($owner) {
                $validator->errors()->add('email', "This email is already registered to Service Number {$owner}. Each voter needs their own email address for verification codes.");
            }
        }];
    }

    public function messages(): array
    {
        return [
            'service_number.regex' => 'Service Number is not in a valid format.',
            'service_number.unique' => 'A voter with this Service Number is already registered.',
        ];
    }

    public function voterData(): array
    {
        $data = $this->safe()->except('phone');
        $data['phone'] = $this->input('phone_normalised');
        foreach (['formation'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== '' ? mb_strtoupper(trim($data[$field])) : null;
        }
        $data['membership_id'] = $this->input('membership_id') ?: null;
        $data['gender'] = $this->input('gender') ?: null;
        $data['dob'] = $this->input('dob') ?: null;
        $data['rank'] = $data['rank'] ?? null ?: null;
        $data['command'] = $data['command'] ?? null ?: null;

        return $data;
    }
}
