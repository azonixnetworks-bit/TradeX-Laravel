<?php

namespace App\Http\Controllers\Api\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\RiskRewardSavedCalculation;
use App\Models\User;
use App\Services\Trading\BinanceFuturesRiskRewardCalculatorService;
use App\Services\Trading\DividendCalculatorService;
use App\Services\Trading\RiskRewardCalculatorService;
use App\Services\Trading\RiskRewardLiveQuoteService;
use App\Services\Trading\StockRiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RiskRewardController extends Controller
{
    public function catalog(Request $request, TradingInstrumentCatalog $catalog): JsonResponse
    {
        $this->user($request);

        return response()->json([
            'success' => true,
            'data' => [
                'markets' => $catalog->markets(),
                'stocks' => [
                    'regions' => config('tradex_screener_stocks.regions', []),
                    'symbols' => array_values(array_filter(
                        (array) config('tradex_screener_stocks.symbols', []),
                        static fn (array $stock): bool => (bool) ($stock['enabled'] ?? false)
                    )),
                    'manual_cse_supported' => true,
                    'short_selling_default' => false,
                ],
                'dividends' => [
                    'provider_available' => false,
                    'manual_mode' => true,
                    'message' => 'Verified dividend announcements are not connected. Manual estimates remain available.',
                ],
                'disclaimer' => (string) config('trading_instruments.disclaimer', ''),
                'generated_at_utc' => now()->utc()->toIso8601String(),
            ],
        ], 200, $this->noStoreHeaders());
    }

    public function quote(Request $request, RiskRewardLiveQuoteService $quotes): JsonResponse
    {
        $this->user($request);
        $validated = Validator::make($request->query(), [
            'market' => ['required', Rule::in(['forex', 'deriv', 'crypto', 'binance_futures'])],
            'symbol' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'side' => ['nullable', Rule::in(['BUY', 'SELL'])],
        ])->validate();

        try {
            $quote = $quotes->quote(
                (string) $validated['market'],
                (string) $validated['symbol'],
                (string) ($validated['side'] ?? 'BUY')
            );
        } catch (DomainException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => $quote,
        ], 200, $this->noStoreHeaders());
    }

    public function calculate(
        Request $request,
        RiskRewardCalculatorService $calculator,
        BinanceFuturesRiskRewardCalculatorService $futures,
        StockRiskRewardCalculatorService $stocks,
        TradingInstrumentCatalog $catalog
    ): JsonResponse {
        $this->user($request);
        $validated = $this->validateRiskInput($request->all());

        try {
            $result = $this->calculateRisk($validated, $calculator, $futures, $stocks, $catalog);
        } catch (DomainException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return response()->json(['success' => true, 'data' => $result], 200, $this->noStoreHeaders());
    }

    public function calculateDividend(Request $request, DividendCalculatorService $calculator): JsonResponse
    {
        $this->user($request);
        $validated = Validator::make($request->all(), $this->dividendRules())->validate();

        try {
            $result = $calculator->calculate($validated);
        } catch (DomainException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        return response()->json(['success' => true, 'data' => $result], 200, $this->noStoreHeaders());
    }

    public function dividendHistory(Request $request, string $symbol): JsonResponse
    {
        $this->user($request);
        $normalized = strtoupper(trim($symbol));
        $stock = collect((array) config('tradex_screener_stocks.symbols', []))
            ->first(fn (array $item): bool => strtoupper((string) ($item['symbol'] ?? '')) === $normalized);

        return response()->json([
            'success' => true,
            'data' => [
                'symbol' => $normalized,
                'company_name' => is_array($stock) ? ($stock['name'] ?? $normalized) : $normalized,
                'exchange' => is_array($stock) ? ($stock['exchange'] ?? null) : null,
                'currency' => is_array($stock) ? ($stock['currency'] ?? null) : null,
                'provider_available' => false,
                'manual_mode' => true,
                'source' => null,
                'freshness' => null,
                'upcoming' => null,
                'history' => [],
                'message' => 'No approved dividend provider is connected. Future declarations and payment dates are not estimated.',
            ],
        ], 200, $this->noStoreHeaders());
    }

    public function saved(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $items = RiskRewardSavedCalculation::query()
            ->where('user_id', $user->getKey())
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (RiskRewardSavedCalculation $item): array => $this->savedPayload($item))
            ->values();

        return response()->json(['success' => true, 'data' => ['items' => $items]], 200, $this->noStoreHeaders());
    }

    public function save(
        Request $request,
        RiskRewardCalculatorService $calculator,
        BinanceFuturesRiskRewardCalculatorService $futures,
        StockRiskRewardCalculatorService $stocks,
        DividendCalculatorService $dividends,
        TradingInstrumentCatalog $catalog
    ): JsonResponse {
        $user = $this->user($request);
        $base = Validator::make($request->all(), [
            'calculator_type' => ['required', Rule::in(['risk_reward', 'dividend'])],
            'title' => ['nullable', 'string', 'max:120'],
            'input' => ['required', 'array'],
        ])->validate();

        try {
            if ($base['calculator_type'] === 'dividend') {
                $input = Validator::make($base['input'], $this->dividendRules())->validate();
                $result = $dividends->calculate($input);
            } else {
                $input = $this->validateRiskInput($base['input']);
                $result = $this->calculateRisk($input, $calculator, $futures, $stocks, $catalog);
            }
        } catch (DomainException $exception) {
            return $this->unprocessable($exception->getMessage());
        }

        $item = RiskRewardSavedCalculation::query()->create([
            'user_id' => $user->getKey(),
            'calculator_type' => $base['calculator_type'],
            'title' => trim((string) ($base['title'] ?? '')) ?: $this->defaultTitle($result),
            'input_payload' => $input,
            'result_payload' => $result,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->savedPayload($item),
        ], 201, $this->noStoreHeaders());
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $this->user($request);
        $item = RiskRewardSavedCalculation::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $item->delete();

        return response()->json(['success' => true, 'data' => null], 200, $this->noStoreHeaders());
    }

    /** @param array<string, mixed> $input */
    private function validateRiskInput(array $input): array
    {
        $market = strtolower((string) ($input['market'] ?? ''));
        $isFutures = $market === 'binance_futures';
        $isStocks = $market === 'stocks';
        $mode = strtoupper((string) ($input['calculation_mode'] ?? 'RISK_BASED'));

        return Validator::make($input, [
            'market' => ['required', Rule::in(['forex', 'deriv', 'commodity', 'crypto', 'binance_futures', 'stocks'])],
            'symbol' => ['required', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'side' => ['required', Rule::in(['BUY', 'SELL'])],
            'entry' => ['required', 'numeric', 'gt:0'],
            'stop_loss' => ['required', 'numeric', 'gt:0'],
            'take_profit' => ['nullable', 'numeric', 'gt:0'],
            'risk_amount' => [Rule::requiredIf(! $isFutures || $mode === 'RISK_BASED'), 'nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'margin_amount' => [Rule::requiredIf($isFutures && $mode === 'MARGIN_BASED'), 'nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'calculation_mode' => [Rule::requiredIf($isFutures), 'nullable', Rule::in(['RISK_BASED', 'MARGIN_BASED'])],
            'margin_mode' => ['nullable', Rule::in(['ISOLATED', 'CROSS'])],
            // Mobile Binance Futures calculations omit leverage so the server can
            // derive a conservative value from the Entry -> Stop Loss distance.
            // Keep accepting an explicit value for existing/admin clients.
            'leverage' => ['nullable', 'integer', 'min:1', 'max:125'],
            'quote_to_usd_rate' => ['nullable', 'numeric', 'gt:0'],
            'entry_fee_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'exit_fee_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'slippage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'funding_rate_percent' => ['nullable', 'numeric', 'between:-100,100'],
            'funding_intervals' => ['nullable', 'integer', 'min:0'],
            'maintenance_margin_rate_percent' => ['nullable', 'numeric', 'gt:0', 'lt:100'],
            'maintenance_amount' => ['nullable', 'numeric', 'min:0'],
            'liquidation_wallet_balance' => ['nullable', 'numeric', 'gt:0'],
            'region' => [Rule::requiredIf($isStocks), 'nullable', 'string', 'max:30'],
            'exchange' => [Rule::requiredIf($isStocks), 'nullable', 'string', 'max:30'],
            'currency' => [Rule::requiredIf($isStocks), 'nullable', 'string', 'size:3'],
            'instrument_to_usd_rate' => ['nullable', 'numeric', 'gt:0'],
            'fx_rate_source' => ['nullable', 'string', 'max:120'],
            'fx_rate_observed_at' => ['nullable', 'date'],
            'buying_power' => ['nullable', 'numeric', 'gt:0'],
            'fractional_enabled' => ['nullable', 'boolean'],
            'share_step' => ['nullable', 'numeric', 'gt:0'],
            'short_selling_supported' => ['nullable', 'boolean'],
            'fees_per_share' => ['nullable', 'numeric', 'min:0'],
            'data_mode' => ['nullable', 'string', 'max:40'],
        ])->validate();
    }

    /** @param array<string, mixed> $input */
    private function calculateRisk(
        array $input,
        RiskRewardCalculatorService $calculator,
        BinanceFuturesRiskRewardCalculatorService $futures,
        StockRiskRewardCalculatorService $stocks,
        TradingInstrumentCatalog $catalog
    ): array {
        $market = strtolower((string) $input['market']);
        if ($market === 'stocks') {
            return $stocks->calculate($input);
        }
        if ($market === 'binance_futures') {
            $configuration = Arr::get($catalog->markets(), 'binance_futures');
            if (! is_array($configuration)) {
                throw new DomainException('Binance Futures calculator configuration is unavailable.');
            }

            return $futures->calculate($input, $configuration);
        }

        $result = $calculator->calculate($input);
        if ($market === 'crypto') {
            $result['margin'] = [
                'available' => false,
                'message' => 'Margin unavailable — select broker/contract details with a verified margin specification.',
            ];
        }

        return $result;
    }

    private function dividendRules(): array
    {
        return [
            'symbol' => ['required', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'shares' => ['required', 'numeric', 'gt:0'],
            'share_price' => ['required', 'numeric', 'gt:0'],
            'dividend_per_share' => ['required', 'numeric', 'gt:0'],
            'payments_per_year' => ['required', Rule::in([1, 2, 4, 12])],
            'withholding_tax_percent' => ['nullable', 'numeric', 'between:0,100'],
            'fees_per_payment' => ['nullable', 'numeric', 'min:0'],
            'repeated_payment_assumption' => ['required', 'boolean'],
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return $user;
    }

    private function unprocessable(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => []], 422, $this->noStoreHeaders());
    }

    private function defaultTitle(array $result): string
    {
        return trim((string) ($result['symbol'] ?? 'Calculation')).' '.trim((string) ($result['side'] ?? ''));
    }

    private function savedPayload(RiskRewardSavedCalculation $item): array
    {
        return [
            'uuid' => $item->uuid,
            'calculator_type' => $item->calculator_type,
            'title' => $item->title,
            'input' => $item->input_payload,
            'result' => $item->result_payload,
            'created_at_utc' => $item->created_at?->utc()->toIso8601String(),
        ];
    }

    private function noStoreHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store, no-cache, max-age=0', 'X-Content-Type-Options' => 'nosniff'];
    }
}
