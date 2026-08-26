<?php

namespace App\Http\Requests\Admin\Languages;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTranslationKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'languages.view'
            )
            && $admin->hasPermission(
                'languages.manage'
            );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source_text' =>
                trim(
                    str_replace(
                        ["\r\n", "\r"],
                        "\n",
                        (string) $this->input(
                            'source_text'
                        )
                    )
                ),
        ]);
    }

    public function rules(): array
    {
        return [
            'source_text' => [
                'required',
                'string',
                'min:1',
                'max:'
                    .(int) config(
                        'tradex_languages.source_text_max_characters',
                        10000
                    ),
            ],

            'version' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }
}
