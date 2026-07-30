<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PrioritiseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Existing app versions may send a short internal product ID when the
            // catalogue response intentionally omits barcodes.
            'barcode' => ['required', 'string', 'regex:/^\d{1,14}$/'],
            'product_name' => 'nullable|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'user_email' => 'nullable|email:rfc|max:255',
            'user_name' => 'nullable|string|max:255',
            'photo' => 'nullable|file|max:5120|mimes:jpg,jpeg,png',
            'type' => 'nullable|in:prioritise,new_product,silent',
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
