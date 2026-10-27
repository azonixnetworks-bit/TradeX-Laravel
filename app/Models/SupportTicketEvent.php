<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['ticket_id', 'event_type', 'actor_type', 'actor_id', 'payload', 'created_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }
}
