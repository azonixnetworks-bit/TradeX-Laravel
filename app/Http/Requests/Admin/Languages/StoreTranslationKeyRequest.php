<?php

namespace App\Http\Requests\Admin\Languages;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTranslationKeyRequest extends FormRequest
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
            'group_name' =>
                strtoupper(
                    trim(
                        (string) $this->input(
                            'group_name'
                        )
                    )
                ),

            'translation_key' =>
                strtolower(
                    trim(
                        (string) $this->input(
                            'translation_key'
                        )
                    )
                ),

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
            'group_name' => [
                'required',
                Rule::in(
                    array_keys(
                        (array) config(
                            'tradex_languages.groups',
                            []
                        )
                    )
                ),
            ],

            'translation_key' => [
                'required',
                'string',
                'min:3',
                'max:180',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
            ],

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
        ];
    }
}
