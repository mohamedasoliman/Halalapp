<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MasjidTimingCorrectionRequest extends FormRequest
{
    private const TIME_PATTERN = '/^(0[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/';

    private const PRAYERS = [
        'fajr',
        'zohar',
        'asr',
        'magrib',
        'isha',
        'jumma',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'masjid_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
            'area_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
            'confirmed' => ['required', 'accepted'],
            'current_times' => [
                'required',
                'array:'.implode(',', self::PRAYERS),
            ],
            'changes' => [
                'required',
                'array:'.implode(',', self::PRAYERS),
                'min:1',
            ],
        ];

        foreach (self::PRAYERS as $prayer) {
            $rules["current_times.$prayer"] = [
                'present',
                'nullable',
                'string',
                'regex:'.self::TIME_PATTERN,
            ];
            $rules["changes.$prayer"] = [
                'sometimes',
                'required',
                'string',
                'regex:'.self::TIME_PATTERN,
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Please confirm that the corrected times are accurate.',
            'changes.min' => 'Change at least one jamaat time.',
            'changes.*.regex' => 'Prayer times must use the format 06:15 AM.',
            'current_times.*.regex' => 'The displayed prayer times are invalid. Refresh and try again.',
        ];
    }
}
