<?php

namespace App\Http\Requests\Api\Support;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']];
    }
}
