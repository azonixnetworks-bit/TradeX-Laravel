<?php

namespace App\Http\Requests\Api\Education;

use Illuminate\Foundation\Http\FormRequest;

class AskTradeXAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:1000'],
            'lesson_uuid' => ['nullable', 'uuid'],
        ];
    }
}
