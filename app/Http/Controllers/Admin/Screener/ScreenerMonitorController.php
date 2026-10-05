<?php

namespace App\Http\Controllers\Admin\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\ScreenerHealthService;
use App\Services\Screener\BinanceSpotCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        BinanceSpotCatalogService $binanceCatalog
    ): Response {
        $this->viewer();

        $markets = $engine->markets();
        $timeframes = $engine->timeframes();
        $market = strtoupper(trim((string) $request->query('market', 'CRYPTO')));
        $timeframe = strtoupper(trim((string) $request->query('timeframe', 'H1')));

        if (! array_key_exists($market, $markets)) {
            $market = 'CRYPTO';
        }

        if (! array_key_exists($timeframe, $timeframes)) {
            $timeframe = 'H1';
        }

        $results = null;
        $error = null;
        $symbolPaginator = null;
        $search = trim((string) $request->query('q', ''));

        try {
            $filters = [
                'market' => $market,
                'timeframe' => $timeframe,
                'sort' => 'symbol',
                'order' => 'asc',
            ];

            if ($market === 'CRYPTO') {
                $catalog = $binanceCatalog->catalog();
                $symbols = (array) ($catalog['symbols'] ?? []);

                if ($search !== '') {
                    $needle = strtoupper($search);
                    $symbols = array_values(array_filter(
                        $symbols,
                        static fn (array $symbol): bool =>
                            str_contains(strtoupper((string) ($symbol['symbol'] ?? '')), $needle)
                            || str_contains(strtoupper((string) ($symbol['name'] ?? '')), $needle)
                            || str_contains(strtoupper((string) ($symbol['base_currency'] ?? '')), $needle)
                    ));
                }

                $perPage = 20;
                $page = max(1, (int) $request->query('page', 1));
                $pageSymbols = array_slice($symbols, ($page - 1) * $perPage, $perPage);
                $symbolPaginator = new LengthAwarePaginator(
                    $pageSymbols,
                    count($symbols),
                    $perPage,
                    $page,
                    [
                        'path' => route('admin.screener.markets'),
                        'query' => $request->except('page'),
                    ]
                );

                $results = [
                    'market' => 'CRYPTO',
                    'timeframe' => $timeframe,
                    'provider' => 'BINANCE',
                    'generated_at_utc' => $catalog['fetched_at_utc'] ?? now('UTC')->toIso8601String(),
                    'count' => count($pageSymbols),
                    'cache' => ['hit' => true, 'stale' => ($catalog['source'] ?? '') === 'BINANCE_STALE_CACHE'],
                    'results' => $pageSymbols,
                ];
            } else {
                $results = $engine->screen($filters);
            }
        } catch (ScreenerProviderException $exception) {
            $error = [
                'code' => $exception->providerCode,
                'message' => $exception->getMessage(),
            ];
        } catch (InvalidArgumentException $exception) {
            $error = [
                'code' => 'SCREENER_UNAVAILABLE',
                'message' => $exception->getMessage(),
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
                'search'
            ),
            200,
            $this->noStoreHeaders()
        );
    }

    public function marketAnalysis(
        Request $request,
        ScreenerEngine $engine,
        BinanceSpotCatalogService $binanceCatalog
    ): JsonResponse {
        $this->viewer();

        $symbol = strtoupper(trim((string) $request->query('symbol', '')));
        $timeframe = strtoupper(trim((string) $request->query('timeframe', 'H1')));

        if (! preg_match('/^[A-Z0-9]{3,30}$/', $symbol)) {
            return response()->json(['success' => false, 'message' => 'Invalid Binance symbol.'], 422, $this->noStoreHeaders());
        }

        if (! array_key_exists($timeframe, $engine->timeframes())) {
            return response()->json(['success' => false, 'message' => 'Unsupported timeframe.'], 422, $this->noStoreHeaders());
        }

        $symbolConfig = collect($binanceCatalog->catalog()['symbols'] ?? [])
            ->first(static fn (array $item): bool => strtoupper((string) ($item['symbol'] ?? '')) === $symbol);

        if (! is_array($symbolConfig)) {
            return response()->json(['success' => false, 'message' => 'Binance symbol is unavailable.'], 404, $this->noStoreHeaders());
        }

        try {
            $result = $engine->screenWithSymbols([
                'market' => 'CRYPTO',
                'timeframe' => $timeframe,
                'sort' => 'symbol',
                'order' => 'asc',
            ], [$symbolConfig]);

            return response()->json([
                'success' => true,
                'data' => $result['results'][0] ?? null,
                'generated_at_utc' => $result['generated_at_utc'] ?? null,
            ], 200, $this->noStoreHeaders());
        } catch (ScreenerProviderException $exception) {
            return response()->json([
                'success' => false,
                'code' => $exception->providerCode,
                'message' => $exception->getMessage(),
            ], 503, $this->noStoreHeaders());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'SCREENER_UNAVAILABLE',
                'message' => $exception->getMessage(),
            ], 422, $this->noStoreHeaders());
        }
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        abort_unless(
            $admin->hasPermission('screener.monitor.view'),
            403
        );

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
