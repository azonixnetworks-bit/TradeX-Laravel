<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminDeviceSession extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['session_hash'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function scopeLive($query)
    {
        return $query->whereNull('ended_at')->where('expires_at', '>', now())
            ->whereExists(fn ($device) => $device->selectRaw('1')->from('admin_devices')
                ->whereColumn('admin_devices.id', 'admin_device_sessions.admin_device_id')
                ->whereColumn('admin_devices.session_version', 'admin_device_sessions.session_version')
                ->where('admin_devices.status', 'ACTIVE'));
    }
}
