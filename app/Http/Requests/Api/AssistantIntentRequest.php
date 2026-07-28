<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AssistantIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model' => 'nullable|in:gemini-3.5-flash-lite',
            'query' => 'required|string|min:2|max:300',
            'has_product_context' => 'required|boolean',
            'assistant_context' => 'nullable|in:general,restaurants,masjids,halal_list,product,businesses',
            'conversation_context' => 'nullable|array|max:4',
            'conversation_context.*' => 'string|min:1|max:300',
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
