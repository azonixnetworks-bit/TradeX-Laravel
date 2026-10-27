<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDepartment extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'timezone', 'business_hours', 'enabled'];

    protected function casts(): array
    {
        return ['business_hours' => 'array', 'enabled' => 'boolean'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(SupportCategory::class, 'department_id');
    }
}
