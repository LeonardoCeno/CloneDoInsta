<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes', 'string', 'max:30', 'regex:/^[a-zA-Z0-9._]+$/',
                Rule::unique('users', 'username')->ignore($this->user()->id),
            ],
            'bio'      => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
