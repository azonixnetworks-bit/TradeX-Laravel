<?php

namespace App\Console\Commands;

use App\Services\EconomicNews\EconomicNewsSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EconomicNewsSyncCommand extends Command
{
    protected $signature = 'tradex:economic-news:sync
        {--from= : Optional UTC start date (YYYY-MM-DD)}
        {--to= : Optional UTC end date (YYYY-MM-DD)}';

    protected $description = 'Synchronize the TradeX economic calendar from the configured provider.';

    public function handle(EconomicNewsSyncService $sync): int
    {
        try {
            $from = $this->dateOption('from', false);
            $to = $this->dateOption('to', true);
            $result = $sync->sync($from, $to);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());
            return self::FAILURE;
        }

        if (! $result['enabled']) {
            $this->components->info('TradeX economic news synchronization is disabled.');
            return self::SUCCESS;
        }

        if (! $result['source_enabled']) {
            $this->components->warn(sprintf(
                '%s source is disabled in Admin > Economic News > Sources.',
                $result['provider']
            ));
            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Economic news sync: %d fetched, %d created, %d updated, %d unchanged, %d failed.',
            $result['fetched'],
            $result['created'],
            $result['updated'],
            $result['unchanged'],
            $result['failed']
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function dateOption(string $name, bool $endOfDay): ?CarbonImmutable
    {
        $value = trim((string) $this->option($name));
        if ($value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException(sprintf('--%s must use YYYY-MM-DD.', $name));
        }

        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
