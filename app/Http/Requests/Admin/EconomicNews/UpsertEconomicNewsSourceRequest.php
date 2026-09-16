<?php

namespace App\Http\Requests\Admin\EconomicNews;

use App\Models\AdminUser;
use App\Models\EconomicNewsSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEconomicNewsSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof AdminUser
            && ($admin->isSuperAdmin() || $admin->hasPermission('economic_news.sources.manage'));
    }

    public function rules(): array
    {
        $source = $this->route('economicNewsSource');
        $sourceId = $source instanceof EconomicNewsSource ? $source->getKey() : null;

        return [
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('economic_news_sources', 'code')->ignore($sourceId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'source_type' => ['required', Rule::in([
                EconomicNewsSource::TYPE_MANUAL,
                EconomicNewsSource::TYPE_API,
                EconomicNewsSource::TYPE_RSS,
                EconomicNewsSource::TYPE_WEBHOOK,
            ])],
            'homepage_url' => ['nullable', 'url:http,https', 'max:500'],
            'is_enabled' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'between:1,1000'],
            'reliability_weight' => ['required', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'source_type' => strtoupper(trim((string) $this->input('source_type'))),
            'homepage_url' => filled($this->input('homepage_url')) ? trim((string) $this->input('homepage_url')) : null,
            'is_enabled' => filter_var($this->input('is_enabled', false), FILTER_VALIDATE_BOOL),
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
