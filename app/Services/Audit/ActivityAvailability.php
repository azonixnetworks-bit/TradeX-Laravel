<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Schema;

class ActivityAvailability
{
    public function schemaReady(): bool
    {
        return Schema::hasTable('user_activity_events');
    }

    public function isReady(): bool
    {
        return (bool) config('tradex_activity.enabled', false)
            && $this->schemaReady();
    }
}
