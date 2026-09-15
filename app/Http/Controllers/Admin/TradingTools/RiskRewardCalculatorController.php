<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Trading\BinanceFuturesRiskRewardCalculatorService;
use App\Services\Trading\RiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RiskRewardCalculatorController extends Controller
{
    public function index(TradingInstrumentCatalog $catalog): Response
    {
        $this->viewer();

        return response()->view(
            'admin.trading_tools.risk_reward.index',
            [
                'markets' => $catalog->markets(),
                'disclaimer' => (string) config('trading_instruments.disclaimer', ''),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function calculate(
        Request $request,
        RiskRewardCalculatorService $calculator,
        BinanceFuturesRiskRewardCalculatorService $binanceFuturesCalculator,
        TradingInstrumentCatalog $catalog
    ): JsonResponse {
        $this->viewer();

        $isBinanceFutures = strtolower((string) $request->input('market', '')) === 'binance_futures';
        $calculationMode = strtoupper((string) $request->input('calculation_mode', 'RISK_BASED'));

        $validator = Validator::make(
            $request->all(),
            [
                'market' => ['required', 'string', 'max:30'],
                'symbol' => ['required', 'string', 'max:40'],
                'side' => ['required', 'in:BUY,SELL'],
                'entry' => ['required', 'numeric', 'gt:0'],
                'stop_loss' => ['required', 'numeric', 'gt:0'],
                'take_profit' => ['required', 'numeric', 'gt:0'],
                'risk_amount' => [
                    Rule::requiredIf(! $isBinanceFutures || $calculationMode === 'RISK_BASED'),
                    'nullable', 'numeric', 'gt:0', 'max:1000000000',
                ],
                'margin_amount' => [
                    Rule::requiredIf($isBinanceFutures && $calculationMode === 'MARGIN_BASED'),
                    'nullable', 'numeric', 'gt:0', 'max:1000000000',
                ],
                'calculation_mode' => [
                    Rule::requiredIf($isBinanceFutures),
                    'nullable', 'in:RISK_BASED,MARGIN_BASED',
                ],
                // Optional for backward compatibility. The Binance service defaults to ISOLATED.
                'margin_mode' => ['nullable', 'in:ISOLATED,CROSS'],
                'leverage' => [
                    Rule::requiredIf($isBinanceFutures),
                    'nullable', 'integer', 'min:1', 'max:125',
                ],
                'entry_fee_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'exit_fee_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'slippage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'funding_rate_percent' => ['nullable', 'numeric', 'min:-100', 'max:100'],
                'funding_intervals' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'maintenance_margin_rate_percent' => [
                    'nullable', 'numeric', 'gt:0', 'lt:100', 'required_with:maintenance_amount',
                ],
                'maintenance_amount' => [
                    'nullable', 'numeric', 'min:0', 'max:1000000000', 'required_with:maintenance_margin_rate_percent',
                ],
                'liquidation_wallet_balance' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
                'quote_to_usd_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            ]
        );

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Please correct the highlighted calculator inputs.',
                    'errors' => $validator->errors(),
                ],
                422,
                $this->noStoreHeaders()
            );
        }

        try {
            $input = $validator->validated();

            if (strtolower((string) ($input['market'] ?? '')) === 'binance_futures') {
                $market = $catalog->markets()['binance_futures'] ?? null;

                if (! is_array($market)) {
                    throw new DomainException('Binance Futures calculator configuration is unavailable.');
                }

                $result = $binanceFuturesCalculator->calculate($input, $market);
            } else {
                $result = $calculator->calculate($input);
            }
        } catch (DomainException $exception) {
            return response()->json(
                [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'errors' => [],
                ],
                422,
                $this->noStoreHeaders()
            );
        }

        return response()->json(
            [
                'success' => true,
                'data' => $result,
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        abort_unless(
            $admin->hasPermission('risk_reward.view'),
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
