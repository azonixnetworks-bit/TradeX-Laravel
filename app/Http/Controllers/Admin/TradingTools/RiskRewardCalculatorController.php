<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Trading\RiskRewardCalculatorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class RiskRewardCalculatorController extends Controller
{
    public function index(): Response
    {
        $this->viewer();

        return response()->view(
            'admin.trading_tools.risk_reward.index',
            [
                'markets' => config('trading_instruments.markets', []),
                'disclaimer' => (string) config('trading_instruments.disclaimer', ''),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function calculate(
        Request $request,
        RiskRewardCalculatorService $calculator
    ): JsonResponse {
        $this->viewer();

        $validator = Validator::make(
            $request->all(),
            [
                'market' => ['required', 'string', 'max:30'],
                'symbol' => ['required', 'string', 'max:40'],
                'side' => ['required', 'in:BUY,SELL'],
                'entry' => ['required', 'numeric', 'gt:0'],
                'stop_loss' => ['required', 'numeric', 'gt:0'],
                'take_profit' => ['required', 'numeric', 'gt:0'],
                'risk_amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
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
            $result = $calculator->calculate(
                $validator->validated()
            );
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
