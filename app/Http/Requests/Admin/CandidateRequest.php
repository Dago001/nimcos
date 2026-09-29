<?php

namespace App\Http\Requests\Admin;

use App\Enums\NisCommand;
use App\Enums\NisRank;
use App\Models\Candidate;
use App\Models\Election;
use App\Support\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sn = $this->input('service_number');
        $this->merge(['service_number' => $sn ? ServiceNumber::normalise($sn) : null]);
    }

    public function rules(): array
    {
        /** @var Candidate|null $candidate */
        $candidate = $this->route('candidate');
        /** @var Election $election */
        $election = $this->route('election') ?? $candidate->election;
        $maxKb = (int) config('nimcos.uploads.photo_max_kb');

        return [
            'election_position_id' => ['required', 'uuid', Rule::exists('election_positions', 'id')->where('election_id', $election->getKey())],
            'candidate_number' => ['nullable', 'integer', 'min:1', 'max:9999',
                Rule::unique('candidates', 'candidate_number')->where('election_id', $election->getKey())->ignore($candidate?->getKey())],
            'surname' => ['required', 'string', 'max:100', "regex:/^[\pL\pM' .\-]+$/u"],
            'first_name' => ['required', 'string', 'max:100', "regex:/^[\pL\pM' .\-]+$/u"],
            'other_names' => ['nullable', 'string', 'max:150', "regex:/^[\pL\pM' .\-]+$/u"],
            'service_number' => ['nullable', 'string', 'max:20',
                Rule::unique('candidates', 'service_number')->where('election_id', $election->getKey())->ignore($candidate?->getKey())],
            'rank' => ['nullable', Rule::enum(NisRank::class)],
            'command' => ['nullable', Rule::enum(NisCommand::class)],
            'biography' => ['nullable', 'string', 'max:3000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            // mimes checks the sniffed content type, not just the extension.
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', "max:{$maxKb}",
                'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
        ];
    }

    public function messages(): array
    {
        return [
            'service_number.unique' => 'This officer is already a candidate in this election.',
            'photo.dimensions' => 'The photograph must be at least 200×200 pixels.',
            '*.regex' => 'Names may contain letters, spaces, apostrophes, full stops and hyphens only.',
        ];
    }
}
