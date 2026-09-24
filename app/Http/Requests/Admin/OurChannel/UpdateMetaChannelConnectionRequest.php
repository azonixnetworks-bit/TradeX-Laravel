<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaChannelConnectionRequest extends FormRequest
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
            'app_id' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^\d{5,30}$/',
            ],
            'app_secret' => [
                'nullable',
                'string',
                'max:255',
            ],
            'graph_version' => [
                'required',
                'string',
                'regex:/^v\d{1,2}\.0$/',
            ],
            'facebook_enabled' => [
                'required',
                'boolean',
            ],
            'instagram_enabled' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'app_id' => trim(
                (string) $this->input(
                    'app_id',
                    ''
                )
            ),
            'app_secret' => trim(
                (string) $this->input(
                    'app_secret',
                    ''
                )
            ),
            'graph_version' => strtolower(
                trim(
                    (string) $this->input(
                        'graph_version',
                        'v26.0'
                    )
                )
            ),
        ]);
    }
}
