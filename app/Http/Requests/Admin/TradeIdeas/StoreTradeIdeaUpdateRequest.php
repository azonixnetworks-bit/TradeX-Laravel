<?php

namespace App\Http\Requests\Admin\TradeIdeas;

use App\Models\AdminUser;
use App\Models\TradeIdeaUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTradeIdeaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('trade_ideas.update');
    }

    public function rules(): array
    {
        $type = strtoupper(trim((string) $this->input('type')));

        return [
            'type' => [
                'required',
                Rule::in([
                    TradeIdeaUpdate::TYPE_NOTE,
                    TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED,
                    TradeIdeaUpdate::TYPE_STOP_MOVED,
                    TradeIdeaUpdate::TYPE_TARGET_HIT,
                ]),
            ],

            'message' => [
                Rule::requiredIf($type === TradeIdeaUpdate::TYPE_NOTE),
                'nullable',
                'string',
                'max:5000',
            ],

            'price' => [
                Rule::requiredIf(
                    in_array(
                        $type,
                        [
                            TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED,
                            TradeIdeaUpdate::TYPE_STOP_MOVED,
                        ],
                        true
                    )
                ),
                'nullable',
                'numeric',
                'gt:0',
            ],

            'target_id' => [
                Rule::requiredIf($type === TradeIdeaUpdate::TYPE_TARGET_HIT),
                Rule::prohibitedIf($type !== TradeIdeaUpdate::TYPE_TARGET_HIT),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtoupper(trim((string) $this->input('type'))),
        ]);
    }
}
