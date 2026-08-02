<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DirectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_lat' => ['required', 'numeric', 'between:-48,-33'],
            'from_lon' => ['required', 'numeric', 'between:165,180'],
            'to_lat' => ['required', 'numeric', 'between:-48,-33'],
            'to_lon' => ['required', 'numeric', 'between:165,180'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
