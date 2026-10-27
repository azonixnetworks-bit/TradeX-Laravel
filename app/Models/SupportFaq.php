<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportFaq extends Model
{
    use HasUuids;

    protected $fillable = ['category_id', 'question', 'answer', 'sort_order', 'published'];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
