<?php

namespace App\Http\Requests\Admin\OurChannel;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class SelectMetaPageRequest extends FormRequest
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
            'page_id' => [
                'required',
                'string',
                'regex:/^\d{5,30}$/',
            ],
        ];
    }
}
