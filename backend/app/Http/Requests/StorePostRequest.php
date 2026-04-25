<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'   => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,mp4', 'max:102400'],
            'caption' => ['nullable', 'string', 'max:2200'],
        ];
    }
}
