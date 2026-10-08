<?php

namespace App\Http\Controllers\Admin\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\AdminScreenerWatchlist;
use App\Models\AdminUser;
use App\Services\Screener\BinanceSpotCatalogService;
use App\Services\Screener\BinanceFuturesCatalogService;
use App\Services\Screener\CTraderDerivCatalogService;
use App\Services\Screener\MarketBubbleService;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\ScreenerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ScreenerMonitorController extends Controller
{
    public function index(
        ScreenerHealthService $healthService
    ): Response {
        $this->viewer();

        return response()->view(
            'admin.screener.index',
            [
                'snapshot' => $healthService->snapshot(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function health(
        ScreenerHealthService $healthService
    ): JsonResponse {
        $this->viewer();

        return response()->json(
            [
                'success' => true,
                'data' => $healthService->snapshot(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function markets(
        Request $request,
        ScreenerEngine $engine,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog,
        CTraderDerivCatalogService $derivCatalog
    ): Response {
        $this->viewer();

        $markets = $engine->markets();
        $timeframes = $engine->timeframes();

        $market = strtoupper(
            trim(
                (string) $request->query(
                    'market',
                    'CRYPTO'
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

        if (! array_key_exists($market, $markets)) {
            $market = 'CRYPTO';
        }

        if (! array_key_exists($timeframe, $timeframes)) {
            $timeframe = 'H1';
        }

        $results = null;
        $error = null;
        $symbolPaginator = null;
        $search = trim(
            (string) $request->query(
                'q',
                ''
            )
        );
        $cryptoSegment = $this->cryptoSegment($request);

        try {
            $filters = [
                'market' => $market,
                'timeframe' => $timeframe,
                'sort' => 'symbol',
                'order' => 'asc',
            ];

            /*
            |--------------------------------------------------------------------------
            | CRYPTO
            |--------------------------------------------------------------------------
            */
            if ($market === 'CRYPTO') {
                $page = max(
                    1,
                    (int) $request->query('page', 1)
                );

                /*
                 * A cold Binance catalogue can take several seconds. The first
                 * Crypto page intentionally uses our curated five-pair seed;
                 * the browser warms the full catalogue in the background.
                 */
                $catalog = $this->cryptoCatalog(
                    $cryptoSegment,
                    $binanceCatalog,
                    $binanceFuturesCatalog,
                    $cryptoSegment === 'FUTURES' || $page > 1 || $search !== ''
                );

                $symbols = (array) (
                    $catalog['symbols'] ?? []
                );

                if ($search !== '') {
                    $needle = strtoupper($search);

                    $symbols = array_values(
                        array_filter(
                            $symbols,
                            static fn (
                                array $symbol
                            ): bool =>
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['symbol']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                                ||
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['name']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                                ||
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['base_currency']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                        )
                    );
                }

                $perPage = 5;

                $pageSymbols = array_slice(
                    $symbols,
                    ($page - 1) * $perPage,
                    $perPage
                );

                $symbolPaginator =
                    new LengthAwarePaginator(
                        $pageSymbols,
                        count($symbols),
                        $perPage,
                        $page,
                        [
                            'path' =>
                                route(
                                    'admin.screener.markets'
                                ),

                            'query' =>
                                $request->except(
                                    'page'
                                ),
                        ]
                    );

                /*
                 * Render the five visible pairs with data already present.
                 * Shared-host PHP serialises authenticated AJAX requests, so
                 * asking the browser for five analyses simultaneously left
                 * four rows stuck at "Waiting…".  Pagination still bounds
                 * this work to the visible page only.
                 */
                $results = $engine->screenWithSymbols(
                    [
                        ...$filters,
                        'crypto_segment' => $cryptoSegment,
                    ],
                    $pageSymbols
                );
            }

            /*
            |--------------------------------------------------------------------------
            | DERIV / cTrader
            |--------------------------------------------------------------------------
            */
            elseif ($market === 'DERIV') {
                $catalog = $derivCatalog->catalog();

                $symbols = (array) (
                    $catalog['symbols'] ?? []
                );

                if ($search !== '') {
                    $needle = strtoupper($search);

                    $symbols = array_values(
                        array_filter(
                            $symbols,
                            static fn (
                                array $symbol
                            ): bool =>
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['symbol']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                                ||
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['name']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                                ||
                                str_contains(
                                    strtoupper(
                                        (string) (
                                            $symbol['category']
                                            ?? ''
                                        )
                                    ),
                                    $needle
                                )
                        )
                    );
                }

                $perPage = 20;

                $page = max(
                    1,
                    (int) $request->query(
                        'page',
                        1
                    )
                );

                $pageSymbols = array_slice(
                    $symbols,
                    ($page - 1) * $perPage,
                    $perPage
                );

                $symbolPaginator =
                    new LengthAwarePaginator(
                        $pageSymbols,
                        count($symbols),
                        $perPage,
                        $page,
                        [
                            'path' =>
                                route(
                                    'admin.screener.markets'
                                ),

                            'query' =>
                                $request->except(
                                    'page'
                                ),
                        ]
                    );

                /*
                 * Important:
                 * Only current page symbols are analysed.
                 * This avoids requesting all 59 Deriv instruments
                 * on every page load.
                 */
                $results =
                    $engine->screenWithSymbols(
                        $filters,
                        $pageSymbols
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | OTHER MARKETS
            |--------------------------------------------------------------------------
            */
            else {
                $results =
                    $engine->screen(
                        $filters
                    );
            }

        } catch (ScreenerProviderException $exception) {
            $error = [
                'code' =>
                    $exception->providerCode,

                'message' =>
                    $exception->getMessage(),
            ];

        } catch (InvalidArgumentException $exception) {
            $error = [
                'code' =>
                    'SCREENER_UNAVAILABLE',

                'message' =>
                    $exception->getMessage(),
            ];
        }

        return response()->view(
            'admin.screener.markets',
            compact(
                'markets',
                'timeframes',
                'market',
                'timeframe',
                'results',
                'error',
                'symbolPaginator',
                'search',
                'cryptoSegment'
            ),
            200,
            $this->noStoreHeaders()
        );
    }

    public function bubbles(
        Request $request,
        MarketBubbleService $bubbleService
    ): Response {
        $this->viewer();

        $limit = max(
            20,
            min(
                300,
                (int) $request->query('limit', 100)
            )
        );

        $error = null;
        $dataset = null;

        try {
            $dataset = $bubbleService->crypto24h($limit);
        } catch (ScreenerProviderException $exception) {
            $error = [
                'code' => $exception->providerCode,
                'message' => $exception->getMessage(),
            ];
        }

        return response()->view(
            'admin.screener.bubbles',
            compact(
                'dataset',
                'error',
                'limit'
            ),
            200,
            $this->noStoreHeaders()
        );
    }

    public function marketAnalysis(
        Request $request,
        ScreenerEngine $engine,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog
    ): JsonResponse {
        $this->viewer();

        $symbol = strtoupper(
            trim(
                (string) $request->query(
                    'symbol',
                    ''
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
        $cryptoSegment = $this->cryptoSegment($request);

        if (! preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Invalid Binance symbol.',
                ],
                422,
                $this->noStoreHeaders()
            );
        }

        if (
            ! array_key_exists(
                $timeframe,
                $engine->timeframes()
            )
        ) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Unsupported timeframe.',
                ],
                422,
                $this->noStoreHeaders()
            );
        }

        $symbolConfig = collect($this->cryptoCatalog(
            $cryptoSegment,
            $binanceCatalog,
            $binanceFuturesCatalog
        )['symbols'] ?? [])->first(
            static fn (
                array $item
            ): bool =>
                strtoupper(
                    (string) (
                        $item['symbol']
                        ?? ''
                    )
                )
                ===
                $symbol
        );

        if (! is_array($symbolConfig)) {
            return response()->json(
                [
                    'success' => false,
                    'message' =>
                        'Binance symbol is unavailable.',
                ],
                404,
                $this->noStoreHeaders()
            );
        }

        try {
            $result =
                $engine->screenWithSymbols(
                    [
                        'market' => 'CRYPTO',
                        'crypto_segment' => $cryptoSegment,
                        'timeframe' => $timeframe,
                        'sort' => 'symbol',
                        'order' => 'asc',
                    ],
                    [
                        $symbolConfig,
                    ]
                );

            return response()->json(
                [
                    'success' => true,

                    'data' =>
                        $result['results'][0]
                        ?? null,

                    'generated_at_utc' =>
                        $result[
                            'generated_at_utc'
                        ]
                        ?? null,
                ],
                200,
                $this->noStoreHeaders()
            );

        } catch (ScreenerProviderException $exception) {
            return response()->json(
                [
                    'success' => false,

                    'code' =>
                        $exception->providerCode,

                    'message' =>
                        $exception->getMessage(),
                ],
                503,
                $this->noStoreHeaders()
            );

        } catch (InvalidArgumentException $exception) {
            return response()->json(
                [
                    'success' => false,

                    'code' =>
                        'SCREENER_UNAVAILABLE',

                    'message' =>
                        $exception->getMessage(),
                ],
                422,
                $this->noStoreHeaders()
            );
        }
    }

    public function chart(
        Request $request,
        ScreenerEngine $engine,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog
    ): Response {
        $this->viewer();

        $symbol = strtoupper(
            trim(
                (string) $request->query(
                    'symbol',
                    ''
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
        $cryptoSegment = $this->cryptoSegment($request);

        abort_unless(
            preg_match(
                '/^[A-Z0-9]{3,30}$/',
                $symbol
            ),
            404
        );

        abort_unless(
            array_key_exists(
                $timeframe,
                $engine->timeframes()
            ),
            404
        );

        $symbolConfig = collect($this->cryptoCatalog(
            $cryptoSegment,
            $binanceCatalog,
            $binanceFuturesCatalog
        )['symbols'] ?? [])->first(
            static fn (
                array $item
            ): bool =>
                strtoupper(
                    (string) (
                        $item['symbol']
                        ?? ''
                    )
                )
                ===
                $symbol
        );

        abort_unless(
            is_array($symbolConfig),
            404
        );

        $tradingViewInterval = [
            'M5' => '5',
            'M15' => '15',
            'H1' => '60',
            'H4' => '240',
            'D1' => 'D',
        ][$timeframe];

        return response()->view(
            'admin.screener.chart',
            compact(
                'symbol',
                'symbolConfig',
                'timeframe',
                'tradingViewInterval',
                'cryptoSegment'
            ),
            200,
            $this->noStoreHeaders()
        );
    }

    public function watchlist(Request $request): JsonResponse
    {
        $admin = $this->viewer();

        return response()->json([
            'success' => true,
            'data' => $admin->screenerWatchlist()
                ->where('market', $this->cryptoWatchlistMarket($this->cryptoSegment($request)))
                ->latest()
                ->get([
                    'symbol',
                    'name',
                    'base_currency',
                    'logo_url',
                ]),
            'limit' => AdminScreenerWatchlist::MAX_SYMBOLS,
        ], 200, $this->noStoreHeaders());
    }

    public function warmCryptoCatalog(
        Request $request,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog
    ): JsonResponse {
        $this->viewer();

        $catalog = $this->cryptoCatalog(
            $this->cryptoSegment($request),
            $binanceCatalog,
            $binanceFuturesCatalog
        );

        return response()->json([
            'success' => true,
            'count' => count($catalog['symbols'] ?? []),
        ], 200, $this->noStoreHeaders());
    }

    public function storeWatchlist(
        Request $request,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog
    ): JsonResponse {
        $admin = $this->viewer();

        $validated = $request->validate([
            'symbol' => ['required', 'string', 'regex:/^[A-Za-z0-9]{3,30}$/'],
        ]);

        $symbol = strtoupper($validated['symbol']);
        $cryptoSegment = $this->cryptoSegment($request);
        $watchlistMarket = $this->cryptoWatchlistMarket($cryptoSegment);

        $existing = $admin->screenerWatchlist()
            ->where('market', $watchlistMarket)
            ->where('symbol', $symbol)
            ->first();

        if ($existing instanceof AdminScreenerWatchlist) {
            return response()->json([
                'success' => true,
                'data' => $existing,
                'limit' => AdminScreenerWatchlist::MAX_SYMBOLS,
            ], 200, $this->noStoreHeaders());
        }

        if (
            $admin->screenerWatchlist()
                ->where('market', $watchlistMarket)
                ->count() >= AdminScreenerWatchlist::MAX_SYMBOLS
        ) {
            throw ValidationException::withMessages([
                'symbol' => 'Your Crypto watchlist can contain a maximum of 30 pairs.',
            ]);
        }

        $catalogSymbol = collect($this->cryptoCatalog(
            $cryptoSegment,
            $binanceCatalog,
            $binanceFuturesCatalog
        )['symbols'] ?? [])
            ->first(static fn (array $item): bool =>
                strtoupper((string) ($item['symbol'] ?? '')) === $symbol
            );

        if (! is_array($catalogSymbol)) {
            throw ValidationException::withMessages([
                'symbol' => 'This Binance pair is unavailable.',
            ]);
        }

        $watchlist = $admin->screenerWatchlist()->create([
            'market' => $watchlistMarket,
            'symbol' => $symbol,
            'name' => (string) ($catalogSymbol['name'] ?? $symbol),
            'base_currency' => $catalogSymbol['base_currency'] ?? null,
            'logo_url' => $catalogSymbol['logo_url'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $watchlist,
            'limit' => AdminScreenerWatchlist::MAX_SYMBOLS,
        ], 201, $this->noStoreHeaders());
    }

    public function destroyWatchlist(Request $request, string $symbol): JsonResponse
    {
        $admin = $this->viewer();

        $admin->screenerWatchlist()
            ->where('market', $this->cryptoWatchlistMarket($this->cryptoSegment($request)))
            ->where('symbol', strtoupper($symbol))
            ->delete();

        return response()->json([
            'success' => true,
            'limit' => AdminScreenerWatchlist::MAX_SYMBOLS,
        ], 200, $this->noStoreHeaders());
    }

    private function cryptoSegment(Request $request): string
    {
        return strtoupper((string) $request->input('segment', 'SPOT')) === 'FUTURES'
            ? 'FUTURES'
            : 'SPOT';
    }

    private function cryptoWatchlistMarket(string $segment): string
    {
        return $segment === 'FUTURES' ? 'CRYPTO_FUTURES' : 'CRYPTO';
    }

    /** @return array{symbols: array<int, array<string, mixed>>, source: string, fetched_at_utc: ?string} */
    private function cryptoCatalog(
        string $segment,
        BinanceSpotCatalogService $binanceCatalog,
        BinanceFuturesCatalogService $binanceFuturesCatalog,
        bool $allowRemote = true
    ): array {
        return $segment === 'FUTURES'
            ? $binanceFuturesCatalog->catalog($allowRemote)
            : $binanceCatalog->catalog($allowRemote);
    }

    private function viewer(): AdminUser
    {
        $admin = auth(
            'admin'
        )->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        abort_unless($admin->hasPermission('screener.monitor.view'), 403);

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' =>
                'private, no-store, no-cache, max-age=0, must-revalidate',

            'Pragma' =>
                'no-cache',

            'X-Content-Type-Options' =>
                'nosniff',

            'Referrer-Policy' =>
                'no-referrer',

            'X-Frame-Options' =>
                'DENY',

            'X-Robots-Tag' =>
                'noindex, nofollow, noarchive',
        ];
    }
}
