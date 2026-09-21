<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTelegramChannelConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission(
                'our_channel.manage'
            );
    }

    public function rules(): array
    {
        return [
            'version' => [
                'required',
                'integer',
                'min:0',
            ],
            'bot_token' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^\d+:[A-Za-z0-9_-]{20,}$/',
            ],
            'chat_id' => [
                'required',
                'string',
                'max:128',
                'regex:/^(?:@[A-Za-z0-9_]{5,32}|-?\d{5,25})$/',
            ],
            'protect_content' => [
                'required',
                'boolean',
            ],
            'is_enabled' => [
                'required',
                'boolean',
            ],
            'action' => [
                'required',
                Rule::in([
                    'SAVE',
                    'SAVE_TEST',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bot_token' => trim(
                (string) $this->input(
                    'bot_token',
                    ''
                )
            ),
            'chat_id' => trim(
                (string) $this->input(
                    'chat_id',
                    ''
                )
            ),
            'action' => strtoupper(
                trim(
                    (string) $this->input(
                        'action',
                        'SAVE'
                    )
                )
            ),
        ]);
    }
}
