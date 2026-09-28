<?php

namespace App\Http\Requests\Admin;

use App\Enums\ElectionType;
use App\Models\Election;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces manage_elections
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'auto_open' => $this->boolean('auto_open'),
            'auto_close' => $this->boolean('auto_close'),
            'interim_results_enabled' => $this->boolean('interim_results_enabled'),
        ]);
    }

    public function rules(): array
    {
        /** @var Election|null $election */
        $election = $this->route('election');

        return [
            'name' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9\-]{2,39}$/', Rule::unique('elections', 'code')->ignore($election?->getKey())],
            'description' => ['nullable', 'string', 'max:5000'],
            'election_type' => ['required', Rule::enum(ElectionType::class)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'auto_open' => ['boolean'],
            'auto_close' => ['boolean'],
            'interim_results_enabled' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use capital letters, numbers and hyphens only, e.g. NIMCOS-2026-EC.',
            'ends_at.after' => 'Voting must end after it starts.',
        ];
    }

    /** Validated data with local (Africa/Lagos) times converted to UTC. */
    public function electionData(): array
    {
        $data = $this->validated();
        $data['starts_at'] = to_utc_from_display($data['starts_at']);
        $data['ends_at'] = to_utc_from_display($data['ends_at']);

        return $data;
    }
}
