<?php

namespace App\Http\Requests\Api;

class StrictProductSearchRequest extends ProductSearchRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
