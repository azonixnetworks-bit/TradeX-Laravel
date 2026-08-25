<?php

namespace App\Http\Requests\Api\Payouts;

use App\Models\PayoutRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePayoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if($this->has('method'))$this->merge(['method'=>strtoupper(trim((string)$this->input('method')))]); }
    public function rules(): array { return ['method'=>['required','string',Rule::in(PayoutRequest::METHODS)],'amount'=>['required','regex:/^\d{1,10}(?:\.\d{1,2})?$/']]; }
    public function idempotencyKey(): ?string { $value=$this->header('Idempotency-Key'); return is_string($value)?$value:null; }
}
