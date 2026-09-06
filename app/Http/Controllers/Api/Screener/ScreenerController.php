<?php

namespace App\Http\Controllers\Api\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Screener\ScreenerEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ScreenerController extends Controller
{
    public function meta(
        Request $request,
        ScreenerEngine $engine
    ): JsonResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        $markets = $engine->markets();

        foreach ($markets as $key => &$market) {
            if (is_array($market)) {
                $market['key'] = $key;
            }
        }
        unset($market);

        return $this->json([
            'markets' => array_values($markets),
            'timeframes' => $engine->timeframes(),
            'filters' => [
                'trend' => [
                    'BULLISH',
                    'BEARISH',
                    'SIDEWAYS',
                ],
                'ema_alignment' => [
                    'BULLISH',
                    'BEARISH',
                    'MIXED',
                ],
                'rsi_range' => [
                    'min' => 0,
                    'max' => 100,
                ],
                'sort' => [
                    'symbol',
                    'change_percent',
                    'rsi',
                    'atr_percent',
                    'trend',
                ],
            ],
            'phase' => 'SCREENER-01',
        ]);
    }

    public function symbols(
        Request $request,
        ScreenerEngine $engine
    ): JsonResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        $market = strtoupper(
            trim(
                (string) $request->query(
                    'market',
                    'FOREX'
                )
            )
        );

        $validator = Validator::make(
            ['market' => $market],
            [
                'market' => [
                    'required',
                    Rule::in(
                        array_keys(
                            $engine->markets()
                        )
                    ),
                ],
            ]
        );

        $validator->validate();

        return $this->json([
            'market' => $market,
            'symbols' => $engine->symbols($market),
        ]);
    }

    public function results(
        Request $request,
        ScreenerEngine $engine
    ): JsonResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        $filters = $this->validatedFilters(
            $request,
            $engine
        );

        try {
            return $this->json(
                $engine->screen($filters)
            );
        } catch (ScreenerProviderException $exception) {
            return response()
                ->json([
                    'success' => false,
                    'error' => [
                        'code' =>
                            $exception->providerCode,
                        'message' =>
                            $exception->getMessage(),
                    ],
                ], 503)
                ->header(
                    'Cache-Control',
                    'private, no-store'
                );
        } catch (InvalidArgumentException $exception) {
            return response()
                ->json([
                    'success' => false,
                    'error' => [
                        'code' =>
                            'SCREENER_UNSUPPORTED_REQUEST',
                        'message' =>
                            $exception->getMessage(),
                    ],
                ], 422)
                ->header(
                    'Cache-Control',
                    'private, no-store'
                );
        }
    }

    public function show(
        Request $request,
        string $symbol,
        ScreenerEngine $engine
    ): JsonResponse {
        abort_unless(
            $request->user() instanceof User,
            401,
            'Unauthenticated.'
        );

        $request->query->set(
            'symbols',
            strtoupper(trim($symbol))
        );

        $filters = $this->validatedFilters(
            $request,
            $engine
        );

        try {
            $data = $engine->screen($filters);
        } catch (ScreenerProviderException $exception) {
            return response()
                ->json([
                    'success' => false,
                    'error' => [
                        'code' =>
                            $exception->providerCode,
                        'message' =>
                            $exception->getMessage(),
                    ],
                ], 503)
                ->header(
                    'Cache-Control',
                    'private, no-store'
                );
        }

        $row = $data['results'][0] ?? null;

        if (! is_array($row)) {
            abort(
                404,
                'Screener symbol was not found.'
            );
        }

        return $this->json([
            'market' => $data['market'],
            'timeframe' => $data['timeframe'],
            'provider' => $data['provider'],
            'generated_at_utc' =>
                $data['generated_at_utc'],
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
        $market = strtoupper(
            trim(
                (string) $request->query(
                    'market',
                    'FOREX'
                )
            )
        );

        $timeframe = strtoupper(
            trim(
                (string) $request->query(
                    'timeframe',
                    'H1'
                )
            )
        );

        $symbolsInput = $request->query(
            'symbols',
            []
        );

        $symbols = is_array($symbolsInput)
            ? $symbolsInput
            : explode(
                ',',
                (string) $symbolsInput
            );

        $symbols = array_values(array_filter(
            array_map(
                static fn (mixed $symbol): string =>
                    strtoupper(
                        trim((string) $symbol)
                    ),
                $symbols
            ),
            static fn (string $symbol): bool =>
                $symbol !== ''
        ));

        $availableSymbols = array_map(
            static fn (array $symbol): string =>
                (string) $symbol['symbol'],
            $engine->symbols('FOREX')
        );

        $payload = [
            'market' => $market,
            'timeframe' => $timeframe,
            'symbols' => $symbols,
            'trend' => strtoupper(
                trim(
                    (string) $request->query(
                        'trend',
                        ''
                    )
                )
            ),
            'ema_alignment' => strtoupper(
                trim(
                    (string) $request->query(
                        'ema_alignment',
                        ''
                    )
                )
            ),
            'rsi_min' => $request->query(
                'rsi_min'
            ),
            'rsi_max' => $request->query(
                'rsi_max'
            ),
            'sort' => strtolower(
                trim(
                    (string) $request->query(
                        'sort',
                        'symbol'
                    )
                )
            ),
            'order' => strtolower(
                trim(
                    (string) $request->query(
                        'order',
                        'asc'
                    )
                )
            ),
        ];

        $validator = Validator::make(
            $payload,
            [
                'market' => [
                    'required',
                    Rule::in(
                        array_keys(
                            $engine->markets()
                        )
                    ),
                ],
                'timeframe' => [
                    'required',
                    Rule::in(
                        array_keys(
                            $engine->timeframes()
                        )
                    ),
                ],
                'symbols' => [
                    'array',
                    'max:20',
                ],
                'symbols.*' => [
                    'string',
                    Rule::in(
                        $availableSymbols
                    ),
                ],
                'trend' => [
                    'nullable',
                    Rule::in([
                        '',
                        'BULLISH',
                        'BEARISH',
                        'SIDEWAYS',
                    ]),
                ],
                'ema_alignment' => [
                    'nullable',
                    Rule::in([
                        '',
                        'BULLISH',
                        'BEARISH',
                        'MIXED',
                    ]),
                ],
                'rsi_min' => [
                    'nullable',
                    'numeric',
                    'between:0,100',
                ],
                'rsi_max' => [
                    'nullable',
                    'numeric',
                    'between:0,100',
                ],
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
                'order' => [
                    'required',
                    Rule::in([
                        'asc',
                        'desc',
                    ]),
                ],
            ]
        );

        $validator->after(
            static function ($validator) use ($payload): void {
                if (
                    $payload['rsi_min'] !== null
                    && $payload['rsi_max'] !== null
                    && (float) $payload['rsi_min']
                        > (float) $payload['rsi_max']
                ) {
                    $validator->errors()->add(
                        'rsi_min',
                        'The RSI minimum cannot exceed the RSI maximum.'
                    );
                }

                if ($payload['market'] !== 'FOREX') {
                    $validator->errors()->add(
                        'market',
                        'This screener phase currently supports FOREX only.'
                    );
                }
            }
        );

        return $validator->validate();
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
            ->header(
                'Cache-Control',
                'private, max-age=15'
            );
    }
}
