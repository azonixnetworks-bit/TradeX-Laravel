<?php

namespace App\Http\Requests\Admin\TradeIdeas;

use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTradeIdeaPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->hasPermission('trade_ideas.publish');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function auditReason(): ?string
    {
        $reason = trim((string) ($this->validated('reason') ?? ''));

        return $reason === '' ? null : $reason;
    }
}
