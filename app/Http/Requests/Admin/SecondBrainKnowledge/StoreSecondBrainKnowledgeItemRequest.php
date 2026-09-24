<?php

namespace App\Http\Requests\Admin\SecondBrainKnowledge;

use App\Models\AdminUser;
use App\Models\SecondBrainKnowledgeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSecondBrainKnowledgeItemRequest extends FormRequest
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
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('second_brain_knowledge_subjects', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'heading' => [
                'nullable',
                'string',
                'max:160',
            ],
            'title' => [
                'required',
                'string',
                'max:200',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:200000',
                'required_without_all:images,pdfs,videos',
            ],
            'market' => [
                'required',
                Rule::in(SecondBrainKnowledgeItem::MARKETS),
            ],
            'timeframes' => [
                'nullable',
                'array',
                'max:13',
            ],
            'timeframes.*' => [
                'string',
                Rule::in(SecondBrainKnowledgeItem::TIMEFRAMES),
            ],
            'knowledge_type' => [
                'required',
                Rule::in(SecondBrainKnowledgeItem::KNOWLEDGE_TYPES),
            ],
            'tags' => [
                'nullable',
                'array',
                'max:30',
            ],
            'tags.*' => [
                'string',
                'max:60',
            ],
            'images' => [
                'nullable',
                'array',
                'max:20',
            ],
            'images.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:15360',
            ],
            'pdfs' => [
                'nullable',
                'array',
                'max:5',
            ],
            'pdfs.*' => [
                'file',
                'mimes:pdf',
                'max:51200',
            ],
            'videos' => [
                'nullable',
                'array',
                'max:3',
            ],
            'videos.*' => [
                'file',
                'mimes:mp4,mov,webm,m4v',
                'max:512000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');

        if (is_string($tags)) {
            $tags = array_values(array_unique(array_filter(array_map(
                static fn (string $tag): string => trim($tag),
                preg_split('/[,\n]+/', $tags) ?: []
            ))));
        }

        $this->merge([
            'heading' => trim((string) $this->input('heading')),
            'title' => trim((string) $this->input('title')),
            'market' => strtoupper(trim((string) $this->input('market', 'COMMON'))),
            'knowledge_type' => strtoupper(trim((string) $this->input('knowledge_type', 'NOTE'))),
            'timeframes' => array_values(array_unique((array) $this->input('timeframes', []))),
            'tags' => $tags ?: [],
        ]);
    }
}
