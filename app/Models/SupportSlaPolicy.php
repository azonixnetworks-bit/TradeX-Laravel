<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSlaPolicy extends Model
{
    protected $fillable = ['department_id', 'priority', 'first_response_minutes', 'resolution_minutes', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
