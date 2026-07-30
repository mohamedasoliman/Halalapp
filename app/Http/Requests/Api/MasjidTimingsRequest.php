<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MasjidTimingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'masjid_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
            'area_id' => ['required', 'string', 'regex:/^\d{1,20}$/'],
        ];
    }
}
