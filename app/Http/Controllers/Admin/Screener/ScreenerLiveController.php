<?php

namespace App\Http\Controllers\Admin\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\ScreenerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ScreenerLiveController extends Controller
{
    public function index(
        ScreenerEngine $engine,
        ScreenerHealthService $healthService
    ): Response {
        $this->viewer();

        $markets = [];

        foreach ($engine->markets() as $key => $market) {
            if (! is_array($market)) {
                continue;
            }

            $markets[] = [
                'key' => strtoupper((string) $key),
                'label' => (string) ($market['label'] ?? $key),
                'enabled' => (bool) ($market['enabled'] ?? false),
                'status' => (string) ($market['status'] ?? 'INACTIVE'),
            ];
        }

        $timeframes = [];

        foreach ($engine->timeframes() as $key => $timeframe) {
            if (! is_array($timeframe)) {
                continue;
            }

            $timeframes[] = [
                'key' => strtoupper((string) $key),
                'label' => (string) ($timeframe['label'] ?? $key),
            ];
        }

        $stockRegions = [];

        foreach ((array) config('tradex_screener_stocks.regions', []) as $key => $region) {
            if (! is_array($region)) {
                continue;
            }

            $exchanges = [];

            foreach ((array) ($region['exchanges'] ?? []) as $exchangeKey => $exchange) {
                if (! is_array($exchange)) {
                    continue;
                }

                $exchanges[] = [
                    'key' => strtoupper((string) $exchangeKey),
                    'label' => (string) ($exchange['label'] ?? $exchangeKey),
                    'enabled' => (bool) ($exchange['enabled'] ?? false),
                ];
            }

            $stockRegions[] = [
                'key' => strtoupper((string) $key),
                'label' => (string) ($region['label'] ?? $key),
                'enabled' => (bool) ($region['enabled'] ?? false),
                'status' => (string) ($region['status'] ?? 'INACTIVE'),
                'data_mode' => (string) ($region['data_mode'] ?? ''),
                'supported_timeframes' => array_values((array) ($region['supported_timeframes'] ?? [])),
                'exchanges' => $exchanges,
            ];
        }

        return response()->view(
            'admin.screener.live',
            [
                'markets' => $markets,
                'timeframes' => $timeframes,
                'stockRegions' => $stockRegions,
                'health' => $healthService->snapshot(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function data(
        Request $request,
        ScreenerEngine $engine
    ): JsonResponse {
        $this->viewer();
        $filters = $this->validatedFilters($request, $engine);

        try {
            $data = $engine->screen($filters);
        } catch (ScreenerProviderException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $exception->providerCode,
                    'message' => $exception->getMessage(),
                ],
            ], 503, $this->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SCREENER_UNSUPPORTED_REQUEST',
                    'message' => $exception->getMessage(),
                ],
            ], 422, $this->noStoreHeaders());
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200, $this->noStoreHeaders());
    }

    /** @return array<string, mixed> */
    private function validatedFilters(
        Request $request,
        ScreenerEngine $engine
    ): array {
        $market = strtoupper(trim((string) $request->query('market', 'FOREX')));
        $timeframe = strtoupper(trim((string) $request->query('timeframe', 'H1')));
        $region = strtoupper(trim((string) $request->query('region', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));
        $symbolsInput = $request->query('symbols', []);

        if ($market === 'STOCKS' && $region === '') {
            $region = 'INTERNATIONAL';
        }

        $symbols = is_array($symbolsInput)
            ? $symbolsInput
            : explode(',', (string) $symbolsInput);

        $symbols = array_values(array_filter(array_map(
            static fn (mixed $symbol): string => strtoupper(trim((string) $symbol)),
            $symbols
        ), static fn (string $symbol): bool => $symbol !== ''));

        $availableConfigs = $engine->symbols($market);

        if ($market === 'STOCKS') {
            $availableConfigs = array_values(array_filter(
                $availableConfigs,
                static function (array $symbol) use ($region, $exchange, $timeframe): bool {
                    if (strtoupper((string) ($symbol['region'] ?? '')) !== $region) {
                        return false;
                    }

                    if (
                        $exchange !== ''
                        && strtoupper((string) ($symbol['exchange'] ?? '')) !== $exchange
                    ) {
                        return false;
                    }

                    $supported = array_map(
                        'strtoupper',
                        (array) ($symbol['supported_timeframes'] ?? [])
                    );

                    return $supported === [] || in_array($timeframe, $supported, true);
                }
            ));
        }

        $availableSymbols = array_values(array_map(
            static fn (array $symbol): string => strtoupper((string) ($symbol['symbol'] ?? '')),
            $availableConfigs
        ));

        $payload = [
            'market' => $market,
            'timeframe' => $timeframe,
            'region' => $region,
            'exchange' => $exchange,
            'symbols' => $symbols,
            'trend' => strtoupper(trim((string) $request->query('trend', ''))),
            'ema_alignment' => strtoupper(trim((string) $request->query('ema_alignment', ''))),
            'rsi_min' => $request->query('rsi_min'),
            'rsi_max' => $request->query('rsi_max'),
            'sort' => strtolower(trim((string) $request->query('sort', 'symbol'))),
            'order' => strtolower(trim((string) $request->query('order', 'asc'))),
        ];

        $validator = Validator::make($payload, [
            'market' => ['required', Rule::in(array_keys($engine->markets()))],
            'timeframe' => ['required', Rule::in(array_keys($engine->timeframes()))],
            'region' => ['nullable', 'string', 'max:32'],
            'exchange' => ['nullable', 'string', 'max:32'],
            'symbols' => ['array', 'max:20'],
            'symbols.*' => ['string', Rule::in($availableSymbols)],
            'trend' => ['nullable', Rule::in(['', 'BULLISH', 'BEARISH', 'SIDEWAYS'])],
            'ema_alignment' => ['nullable', Rule::in(['', 'BULLISH', 'BEARISH', 'MIXED'])],
            'rsi_min' => ['nullable', 'numeric', 'between:0,100'],
            'rsi_max' => ['nullable', 'numeric', 'between:0,100'],
            'sort' => ['required', Rule::in(['symbol', 'change_percent', 'rsi', 'atr_percent', 'trend'])],
            'order' => ['required', Rule::in(['asc', 'desc'])],
        ]);

        $validator->after(static function ($validator) use ($payload, $engine): void {
            $marketConfig = $engine->markets()[$payload['market']] ?? null;

            if (! is_array($marketConfig) || ! (bool) ($marketConfig['enabled'] ?? false)) {
                $validator->errors()->add('market', 'This screener market is not active yet.');
            }

            if (
                $payload['rsi_min'] !== null
                && $payload['rsi_max'] !== null
                && (float) $payload['rsi_min'] > (float) $payload['rsi_max']
            ) {
                $validator->errors()->add('rsi_min', 'The RSI minimum cannot exceed the RSI maximum.');
            }

            if ($payload['market'] !== 'STOCKS') {
                if ($payload['region'] !== '') {
                    $validator->errors()->add('region', 'The region filter is available for stocks only.');
                }

                if ($payload['exchange'] !== '') {
                    $validator->errors()->add('exchange', 'The exchange filter is available for stocks only.');
                }

                return;
            }

            $regions = (array) config('tradex_screener_stocks.regions', []);
            $regionConfig = $regions[$payload['region']] ?? null;

            if (! is_array($regionConfig)) {
                $validator->errors()->add('region', 'Unsupported stocks region.');
                return;
            }

            if (! (bool) ($regionConfig['enabled'] ?? false)) {
                $validator->errors()->add(
                    'region',
                    'This stocks region is waiting for an approved licensed market-data provider.'
                );
                return;
            }

            $supported = array_map(
                'strtoupper',
                (array) ($regionConfig['supported_timeframes'] ?? [])
            );

            if ($supported !== [] && ! in_array($payload['timeframe'], $supported, true)) {
                $validator->errors()->add(
                    'timeframe',
                    'This stocks region does not support the selected timeframe.'
                );
            }

            if ($payload['exchange'] !== '') {
                $exchangeConfig = (array) ($regionConfig['exchanges'][$payload['exchange']] ?? []);

                if ($exchangeConfig === [] || ! (bool) ($exchangeConfig['enabled'] ?? false)) {
                    $validator->errors()->add('exchange', 'Unsupported or inactive stocks exchange.');
                }
            }
        });

        return $validator->validate();
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);
        abort_unless($admin->hasPermission('dashboard.view'), 403);

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
