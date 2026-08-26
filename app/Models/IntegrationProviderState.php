<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationProviderState extends Model
{
    protected $table = 'integration_provider_states';

    protected $fillable = [
        'provider_code',
        'is_enabled',
        'environment',
        'countries',
        'eligibility_confirmed',
        'version',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'countries' => 'array',
            'eligibility_confirmed' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }
}
