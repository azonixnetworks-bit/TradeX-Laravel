<?php

namespace App\Http\Requests\Api\Education;

use App\Models\UserLessonProgress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    UserLessonProgress::STATUS_IN_PROGRESS,
                    UserLessonProgress::STATUS_COMPLETED,
                ]),
            ],

            'last_video_uuid' => [
                'nullable',
                'uuid',
            ],

            'last_position_seconds' => [
                'nullable',
                'integer',
                'min:0',
                'max:86400',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('status')) {
            $this->merge([
                'status' =>
                    strtoupper(
                        trim(
                            (string)
                                $this->input(
                                    'status'
                                )
                        )
                    ),
            ]);
        }
    }
}
