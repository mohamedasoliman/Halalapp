<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MasjidTimingsBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'masjids' => ['required', 'array', 'min:1', 'max:12'],
            'masjids.*' => ['required', 'array:masjid_id,area_id'],
            'masjids.*.masjid_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
            'masjids.*.area_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
        ];
    }
}
