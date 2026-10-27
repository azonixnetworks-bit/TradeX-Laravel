<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportFeedback extends Model
{
    use HasUuids;

    protected $table = 'support_feedback';

    protected $fillable = ['ticket_id', 'user_id', 'resolution_cycle', 'rating', 'comment'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
