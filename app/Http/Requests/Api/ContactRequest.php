<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ContactRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'requester_name' => 'nullable|string|max:255',
            'body' => 'required|string|max:10000',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf',
            'category' => 'nullable|string|in:general_inquiry,product_issue,masjid_update,restaurant_update,bug_report,feature_request,advertising,barcode_submission,muslim_business_network,event_submission,other,privacy_security,business',
            'submission_uuid' => 'nullable|uuid',
            'client_submission_uuid' => 'nullable|uuid',
            'app_version' => 'nullable|string|max:50',
            'app_build' => 'nullable|string|max:50',
            'platform' => 'nullable|string|max:50',
            'device_model' => 'nullable|string|max:255',
            'os_version' => 'nullable|string|max:100',
            'context_type' => 'nullable|string|max:40|in:app,advertising,business_network,restaurant_suggestion,muslim_guide,product,prioritisation_request,restaurant,masjid,business,development_issue,other',
            'context_id' => 'nullable|string|max:255',
            'barcode' => ['nullable', 'string', 'regex:/^[0-9]{8,14}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $primary = strtolower(trim((string) $this->input('submission_uuid')));
            $legacy = strtolower(trim((string) $this->input('client_submission_uuid')));
            if ($primary !== '' && $legacy !== '' && $primary !== $legacy) {
                $validator->errors()->add(
                    'client_submission_uuid',
                    'The submission UUID aliases must match when both are supplied.',
                );
            }
        });
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
