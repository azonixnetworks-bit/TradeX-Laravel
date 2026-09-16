<?php

namespace App\Services\EconomicNews\Contracts;

use Carbon\CarbonImmutable;

interface EconomicCalendarProvider
{
    public function code(): string;

    public function name(): string;

    public function homepageUrl(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(CarbonImmutable $from, CarbonImmutable $to): array;
}
