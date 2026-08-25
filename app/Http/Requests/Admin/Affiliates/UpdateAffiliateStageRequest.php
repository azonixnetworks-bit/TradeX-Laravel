<?php

namespace App\Http\Requests\Admin\Affiliates;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAffiliateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('affiliate_stages.manage');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
            ],
            'standard_commission_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:20',
            ],
            'milestone_gift_usd' => [
                'required',
                'numeric',
                'min:0',
                'max:1000000',
            ],
            'commercial_rule' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
