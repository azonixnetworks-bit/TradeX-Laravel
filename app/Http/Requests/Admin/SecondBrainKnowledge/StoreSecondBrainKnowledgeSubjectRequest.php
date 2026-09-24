<?php

namespace App\Http\Requests\Admin\SecondBrainKnowledge;

use App\Models\AdminUser;
use App\Models\SecondBrainKnowledgeSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecondBrainKnowledgeSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && $admin->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('second_brain_knowledge_subjects', 'name')
                    ->where(fn ($query) => $query->where(
                        'market_scope',
                        strtoupper(trim((string) $this->input('market_scope')))
                    )),
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'market_scope' => [
                'required',
                Rule::in(SecondBrainKnowledgeSubject::MARKET_SCOPES),
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'market_scope' => strtoupper(trim((string) $this->input('market_scope', 'COMMON'))),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }
}
