<?php

namespace App\Http\Requests\Admin\CopyTrading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCopyTradeBillingCalendarDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null
            && (
                $admin->isSuperAdmin()
                || $admin->hasPermission(
                    'copy_trading.billing.calendar.manage'
                )
            );
    }

    public function rules(): array
    {
        return [
            'calendar_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'is_billable_day' => [
                'required',
                'boolean',
            ],
            'source' => [
                'required',
                Rule::in(
                    (array) config(
                        'tradex_copy_trading.billing.calendar_sources',
                        []
                    )
                ),
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:191',
            ],
        ];
    }
}
