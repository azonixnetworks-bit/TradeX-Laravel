<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateYouTubeChannelConnectionRequest extends FormRequest
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
            'client_id' => [
                'nullable',
                'string',
                'max:255',
            ],
            'client_secret' => [
                'nullable',
                'string',
                'max:512',
            ],
            'privacy_status' => [
                'required',
                Rule::in([
                    'private',
                    'unlisted',
                    'public',
                ]),
            ],
            'category_id' => [
                'required',
                'string',
                'regex:/^\d{1,3}$/',
            ],
            'is_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => trim(
                (string) $this->input(
                    'client_id',
                    ''
                )
            ),
            'client_secret' => trim(
                (string) $this->input(
                    'client_secret',
                    ''
                )
            ),
            'privacy_status' => strtolower(
                trim(
                    (string) $this->input(
                        'privacy_status',
                        'private'
                    )
                )
            ),
            'category_id' => trim(
                (string) $this->input(
                    'category_id',
                    '27'
                )
            ),
        ]);
    }
}
