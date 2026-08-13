<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class EventsContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255',
            'contact' => 'nullable|string|max:255',
            'eventName' => 'required|string|max:255',
            'date' => 'nullable|string|max:255',
            'time' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'link' => 'nullable|url|max:2048',
            'description' => 'nullable|string|max:10000',
            'submission_uuid' => 'nullable|uuid',
            'category' => 'nullable|string|in:event_submission',
            'context_type' => 'nullable|string|in:event',
            'context_id' => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:50',
            'app_build' => 'nullable|string|max:50',
            'platform' => 'nullable|string|max:50',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
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
