<?php

namespace App\Http\Controllers\Api\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\ScreenerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ScreenerController extends Controller
{
    public function meta(Request $request, ScreenerEngine $engine): JsonResponse
    {
        $this->requireUser($request);

        $markets = $engine->markets();

        foreach ($markets as $key => &$market) {
            if (is_array($market)) {
                $market['key'] = $key;
            }
        }
        unset($market);

        $stockRegions = (array) config(
            'tradex_screener_stocks.regions',
            []
        );

        foreach ($stockRegions as $key => &$region) {
            if (is_array($region)) {
                $region['key'] = $key;
            }
        }
        unset($region);

        return $this->json([
            'markets' => array_values($markets),
            'timeframes' => $engine->timeframes(),
            'stock_regions' => array_values($stockRegions),
            'filters' => [
                'trend' => ['BULLISH', 'BEARISH', 'SIDEWAYS'],
                'ema_alignment' => ['BULLISH', 'BEARISH', 'MIXED'],
                'rsi_range' => ['min' => 0, 'max' => 100],
                'sort' => [
                    'symbol',
                    'change_percent',
                    'rsi',
                    'atr_percent',
                    'trend',
                ],
            ],
            'capabilities' => [
                'provider_health' => true,
                'stale_fallback' => true,
                'stock_regions' => true,
            ],
            'phase' => 'SCREENER-05',
        ]);
    }

    public function health(
        Request $request,
        ScreenerHealthService $healthService
    ): JsonResponse {
        $this->requireUser($request);

        return $this->json(
            $healthService->snapshot()
        );
    }

    public function symbols(Request $request, ScreenerEngine $engine): JsonResponse
    {
        $this->requireUser($request);

        $market = strtoupper(trim((string) $request->query('market', 'FOREX')));
        $region = strtoupper(trim((string) $request->query('region', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));

        $validator = Validator::make(
            [
                'market' => $market,
                'region' => $region,
                'exchange' => $exchange,
            ],
            [
                'market' => [
                    'required',
                    Rule::in(array_keys($engine->markets())),
                ],
                'region' => ['nullable', 'string', 'max:32'],
                'exchange' => ['nullable', 'string', 'max:32'],
            ]
        );

        $validator->after(function ($validator) use ($market, $region): void {
            if ($market !== 'STOCKS' && $region !== '') {
                $validator->errors()->add(
                    'region',
                    'The region filter is available for stocks only.'
                );

                return;
            }

            if (
                $market === 'STOCKS'
                && $region !== ''
                && ! array_key_exists(
                    $region,
                    (array) config('tradex_screener_stocks.regions', [])
                )
            ) {
                $validator->errors()->add(
                    'region',
                    'Unsupported stocks region.'
                );
            }
        });

        $validator->validate();

        $symbols = $engine->symbols($market);

        if ($market === 'STOCKS') {
            $symbols = array_values(array_filter(
                $symbols,
                static function (array $symbol) use ($region, $exchange): bool {
                    if (
                        $region !== ''
                        && strtoupper((string) ($symbol['region'] ?? '')) !== $region
                    ) {
                        return false;
                    }

                    if (
                        $exchange !== ''
                        && strtoupper((string) ($symbol['exchange'] ?? '')) !== $exchange
                    ) {
                        return false;
                    }

                    return true;
                }
            ));
        }

        return $this->json([
            'market' => $market,
            'region' => $market === 'STOCKS' ? $region : null,
            'exchange' => $market === 'STOCKS' ? $exchange : null,
            'symbols' => $symbols,
        ]);
    }

    public function results(Request $request, ScreenerEngine $engine): JsonResponse
    {
        $this->requireUser($request);
        $filters = $this->validatedFilters($request, $engine);

        try {
            return $this->json($engine->screen($filters));
        } catch (ScreenerProviderException $exception) {
            return $this->providerError($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->unsupportedError($exception);
        }
    }

    public function show(
        Request $request,
        string $symbol,
        ScreenerEngine $engine
    ): JsonResponse {
        $this->requireUser($request);

        $request->query->set(
            'symbols',
            strtoupper(trim($symbol))
        );

        $filters = $this->validatedFilters($request, $engine);

        try {
            $data = $engine->screen($filters);
        } catch (ScreenerProviderException $exception) {
            return $this->providerError($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->unsupportedError($exception);
        }

        $row = $data['results'][0] ?? null;

        if (! is_array($row)) {
            abort(404, 'Screener symbol was not found.');
        }

        return $this->json([
            'market' => $data['market'],
            'timeframe' => $data['timeframe'],
            'provider' => $data['provider'],
            'generated_at_utc' => $data['generated_at_utc'],
            'cache' => $data['cache'],
            'symbol' => $row,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(
        Request $request,
        ScreenerEngine $engine
    ): array {
        $market = strtoupper(trim((string) $request->query('market', 'FOREX')));
        $timeframe = strtoupper(trim((string) $request->query('timeframe', 'H1')));
        $region = strtoupper(trim((string) $request->query('region', '')));
        $exchange = strtoupper(trim((string) $request->query('exchange', '')));
        $symbolsInput = $request->query('symbols', []);

        $symbols = is_array($symbolsInput)
            ? $symbolsInput
            : explode(',', (string) $symbolsInput);

        $symbols = array_values(array_filter(
            array_map(
                static fn (mixed $symbol): string => strtoupper(trim((string) $symbol)),
                $symbols
            ),
            static fn (string $symbol): bool => $symbol !== ''
        ));

        $availableSymbolConfigs = $engine->symbols($market);

        if ($market === 'STOCKS') {
            $availableSymbolConfigs = array_values(array_filter(
                $availableSymbolConfigs,
                static function (array $symbol) use ($region, $exchange): bool {
                    if (
                        $region !== ''
                        && strtoupper((string) ($symbol['region'] ?? '')) !== $region
                    ) {
                        return false;
                    }

                    if (
                        $exchange !== ''
                        && strtoupper((string) ($symbol['exchange'] ?? '')) !== $exchange
                    ) {
                        return false;
                    }

                    return true;
                }
            ));
        }

        $availableSymbols = array_map(
            static fn (array $symbol): string => (string) $symbol['symbol'],
            $availableSymbolConfigs
        );

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

        $validator = Validator::make(
            $payload,
            [
                'market' => [
                    'required',
                    Rule::in(array_keys($engine->markets())),
                ],
                'timeframe' => [
                    'required',
                    Rule::in(array_keys($engine->timeframes())),
                ],
                'region' => ['nullable', 'string', 'max:32'],
                'exchange' => ['nullable', 'string', 'max:32'],
                'symbols' => ['array', 'max:20'],
                'symbols.*' => ['string', Rule::in($availableSymbols)],
                'trend' => [
                    'nullable',
                    Rule::in(['', 'BULLISH', 'BEARISH', 'SIDEWAYS']),
                ],
                'ema_alignment' => [
                    'nullable',
                    Rule::in(['', 'BULLISH', 'BEARISH', 'MIXED']),
                ],
                'rsi_min' => ['nullable', 'numeric', 'between:0,100'],
                'rsi_max' => ['nullable', 'numeric', 'between:0,100'],
                'sort' => [
                    'required',
                    Rule::in([
                        'symbol',
                        'change_percent',
                        'rsi',
                        'atr_percent',
                        'trend',
                    ]),
                ],
                'order' => ['required', Rule::in(['asc', 'desc'])],
            ]
        );

        $validator->after(
            static function ($validator) use ($payload, $engine): void {
                if (
                    $payload['rsi_min'] !== null
                    && $payload['rsi_max'] !== null
                    && (float) $payload['rsi_min'] > (float) $payload['rsi_max']
                ) {
                    $validator->errors()->add(
                        'rsi_min',
                        'The RSI minimum cannot exceed the RSI maximum.'
                    );
                }

                $marketConfig = $engine->markets()[(string) $payload['market']] ?? null;

                if (
                    ! is_array($marketConfig)
                    || ! (bool) ($marketConfig['enabled'] ?? false)
                ) {
                    $validator->errors()->add(
                        'market',
                        'This screener market is not active yet.'
                    );
                }

                if ($payload['market'] !== 'STOCKS') {
                    if ($payload['region'] !== '') {
                        $validator->errors()->add(
                            'region',
                            'The region filter is available for stocks only.'
                        );
                    }

                    if ($payload['exchange'] !== '') {
                        $validator->errors()->add(
                            'exchange',
                            'The exchange filter is available for stocks only.'
                        );
                    }

                    return;
                }

                $regions = (array) config('tradex_screener_stocks.regions', []);
                $region = (string) $payload['region'];

                if ($region === '') {
                    return;
                }

                $regionConfig = $regions[$region] ?? null;

                if (! is_array($regionConfig)) {
                    $validator->errors()->add(
                        'region',
                        'Unsupported stocks region.'
                    );

                    return;
                }

                if (! (bool) ($regionConfig['enabled'] ?? false)) {
                    $validator->errors()->add(
                        'region',
                        'This stocks region is waiting for an approved market-data provider.'
                    );

                    return;
                }

                $supported = array_map(
                    'strtoupper',
                    (array) ($regionConfig['supported_timeframes'] ?? [])
                );

                if (
                    $supported !== []
                    && ! in_array((string) $payload['timeframe'], $supported, true)
                ) {
                    $validator->errors()->add(
                        'timeframe',
                        'This stocks region does not support the selected timeframe.'
                    );
                }
            }
        );

        return $validator->validate();
    }

    private function requireUser(Request $request): void
    {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );
    }

    private function providerError(
        ScreenerProviderException $exception
    ): JsonResponse {
        return response()
            ->json([
                'success' => false,
                'error' => [
                    'code' => $exception->providerCode,
                    'message' => $exception->getMessage(),
                ],
            ], 503)
            ->header('Cache-Control', 'private, no-store');
    }

    private function unsupportedError(
        InvalidArgumentException $exception
    ): JsonResponse {
        return response()
            ->json([
                'success' => false,
                'error' => [
                    'code' => 'SCREENER_UNSUPPORTED_REQUEST',
                    'message' => $exception->getMessage(),
                ],
            ], 422)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): JsonResponse
    {
        return response()
            ->json([
                'success' => true,
                'data' => $data,
            ])
            ->header('Cache-Control', 'private, max-age=15');
    }
}
