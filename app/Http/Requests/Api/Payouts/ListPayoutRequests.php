<?php

namespace App\Http\Requests\Api\Payouts;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPayoutRequests extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { foreach(['status','method'] as $f)if($this->has($f))$this->merge([$f=>strtoupper(trim((string)$this->input($f)))]); }
    public function rules(): array { return ['status'=>['nullable','string',Rule::in(PayoutRequest::STATUSES)],'method'=>['nullable','string',Rule::in(PayoutRequest::METHODS)],'per_page'=>['nullable','integer','min:1','max:100']]; }
}
