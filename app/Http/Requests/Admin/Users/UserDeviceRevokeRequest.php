<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class UserDeviceRevokeRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Only an authenticated admin with the dedicated device-revocation
    | permission may revoke a user's trusted device.
    |
    | Controller-level RBAC remains as defence-in-depth.
    |
    */

    public function authorize(): bool
    {
        $admin =
            auth('admin')->user();

        return $admin !== null
            && $admin->hasPermission(
                'users.devices.revoke'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */

    public function messages(): array
    {
        return [
            'reason.required' =>
                'A reason is required to revoke this device.',

            'reason.min' =>
                'Please provide a meaningful reason of at least 10 characters.',

            'reason.max' =>
                'The reason may not exceed 500 characters.',
        ];
    }
}