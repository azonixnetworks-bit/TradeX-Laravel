<?php

namespace App\Http\Requests\Admin\Legal;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalPolicyDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'legal.view'
            )
            && $admin->hasPermission(
                'legal.manage'
            );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' =>
                trim(
                    (string) $this->input(
                        'title'
                    )
                ),

            'summary' =>
                trim(
                    (string) $this->input(
                        'summary'
                    )
                ) !== ''
                    ? trim(
                        (string) $this->input(
                            'summary'
                        )
                    )
                    : null,

            'content' =>
                trim(
                    (string) $this->input(
                        'content'
                    )
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'min:3',
                'max:180',
            ],

            'summary' => [
                'nullable',
                'string',
                'max:'
                    .(int) config(
                        'tradex_legal.summary_max_characters',
                        500
                    ),
            ],

            'content' => [
                'required',
                'string',
                'min:20',
                'max:'
                    .(int) config(
                        'tradex_legal.content_max_characters',
                        200000
                    ),
            ],

            'revision_version' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
