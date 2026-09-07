<?php

namespace App\Services\Screener;

use Illuminate\Support\Facades\Cache;
use Throwable;

class ScreenerHealthService
{
    public function __construct(
        private readonly ScreenerEngine $engine
    ) {
    }

    /**
     * Build a secret-safe operational snapshot without making provider network
     * calls. Provider health implementations read their existing local cache and
     * configuration only.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $providers = [];
        $activeStatuses = [];

        foreach ($this->engine->markets() as $market => $marketConfig) {
            if (! is_array($marketConfig)) {
                continue;
            }

            $market = strtoupper(trim((string) $market));
            $marketEnabled = (bool) ($marketConfig['enabled'] ?? false);

            try {
                $health = $this->engine->health($market);
            } catch (Throwable) {
                $health = [
                    'provider' => 'UNAVAILABLE',
                    'market' => $market,
                    'enabled' => $marketEnabled,
                    'configured' => false,
                    'status' => 'DEGRADED',
                    'last_error_code' => 'SCREENER_HEALTH_UNAVAILABLE',
                ];
            }

            $configured = (bool) ($health['configured'] ?? false);
            $status = $this->normaliseStatus(
                $configured,
                (string) ($health['status'] ?? '')
            );

            $provider = [
                'market' => $market,
                'market_label' => (string) (
                    $marketConfig['label'] ?? $market
                ),
                'market_enabled' => $marketEnabled,
                'market_status' => (string) (
                    $marketConfig['status'] ?? ($marketEnabled ? 'ACTIVE' : 'INACTIVE')
                ),
                'provider' => (string) ($health['provider'] ?? 'UNKNOWN'),
                'enabled' => (bool) ($health['enabled'] ?? false),
                'configured' => $configured,
                'status' => $status,
                'last_success_at_utc' =>
                    $health['last_success_at_utc'] ?? null,
                'last_failure_at_utc' =>
                    $health['last_failure_at_utc'] ?? null,
                'last_error_code' =>
                    $health['last_error_code'] ?? null,
                'last_latency_ms' =>
                    $health['last_latency_ms'] ?? null,
                'base_host' => $this->safeHost(
                    $health['base_host'] ?? null
                ),
                'symbol_count' => count(
                    $this->engine->symbols($market)
                ),
                'cache' => $this->cacheSnapshot($market),
            ];

            if (array_key_exists('external_display_approved', $health)) {
                $provider['external_display_approved'] = (bool) (
                    $health['external_display_approved'] ?? false
                );
            }

            if (array_key_exists('authentication_required', $health)) {
                $provider['authentication_required'] = (bool) (
                    $health['authentication_required'] ?? false
                );
            }

            $providers[] = $provider;

            if ($marketEnabled) {
                $activeStatuses[] = $status;
            }
        }

        return [
            'phase' => 'SCREENER-05',
            'overall_status' => $this->overallStatus(
                $activeStatuses
            ),
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'active_market_count' => count($activeStatuses),
            'provider_count' => count($providers),
            'cache_policy' => [
                'fresh_seconds' => max(
                    5,
                    (int) config(
                        'tradex_screener.cache.fresh_seconds',
                        60
                    )
                ),
                'stale_seconds' => max(
                    60,
                    (int) config(
                        'tradex_screener.cache.stale_seconds',
                        900
                    )
                ),
                'stale_fallback_enabled' => true,
            ],
            'providers' => $providers,
        ];
    }

    private function normaliseStatus(
        bool $configured,
        string $rawStatus
    ): string {
        if (! $configured) {
            return 'UNCONFIGURED';
        }

        return strtoupper(trim($rawStatus)) === 'HEALTHY'
            ? 'HEALTHY'
            : 'DEGRADED';
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function overallStatus(array $statuses): string
    {
        if ($statuses === []) {
            return 'UNCONFIGURED';
        }

        if (in_array('DEGRADED', $statuses, true)) {
            return 'DEGRADED';
        }

        if (in_array('UNCONFIGURED', $statuses, true)) {
            return 'UNCONFIGURED';
        }

        return 'HEALTHY';
    }

    /**
     * @return array<string, int|bool>
     */
    private function cacheSnapshot(string $market): array
    {
        $fresh = 0;
        $fallback = 0;
        $timeframes = array_keys($this->engine->timeframes());

        foreach ($timeframes as $timeframe) {
            $timeframe = strtoupper(trim((string) $timeframe));

            if ($timeframe === '') {
                continue;
            }

            if (Cache::has(sprintf(
                'tradex:screener:fresh:v1:%s:%s',
                $market,
                $timeframe
            ))) {
                $fresh++;
            }

            if (Cache::has(sprintf(
                'tradex:screener:stale:v1:%s:%s',
                $market,
                $timeframe
            ))) {
                $fallback++;
            }
        }

        return [
            'timeframe_slots' => count($timeframes),
            'fresh_datasets_cached' => $fresh,
            'fallback_datasets_cached' => $fallback,
            'fallback_available' => $fallback > 0,
        ];
    }

    private function safeHost(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $host = strtolower(trim($value));

        if ($host === '') {
            return null;
        }

        return preg_match(
            '/^[a-z0-9.-]+$/',
            $host
        ) === 1
            ? $host
            : null;
    }
}
