<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonInterface;

class ExtendVotingTimeRequest extends ReauthenticatedRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'ends_at.required' => 'Specify the new closing date and time.',
            'ends_at.date_format' => 'Enter the closing date and time in the format YYYY-MM-DDTHH:MM.',
        ]);
    }

    public function newEndsAt(): CarbonInterface
    {
        return to_utc_from_display($this->input('ends_at'));
    }
}
