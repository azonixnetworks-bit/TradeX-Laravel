<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'setting_key',
        'group_name',
        'value',
        'value_type',
        'version',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
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
