<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportCategory extends Model
{
    use HasUuids;

    protected $fillable = ['department_id', 'slug', 'label', 'intake_schema', 'schema_version', 'sort_order', 'enabled'];

    protected function casts(): array
    {
        return ['intake_schema' => 'array', 'enabled' => 'boolean'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }
}
