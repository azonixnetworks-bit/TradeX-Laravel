<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentProduct;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvestmentAdminService
{
    public function create(
        array $data,
        AdminUser $admin,
        Request $request
    ): Investment {
        abort_unless(
            $admin->hasPermission('investments.create'),
            403
        );

        $email = Str::lower(
            trim((string) $data['customer_email'])
        );

        $user = User::query()
            ->with('profile')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'ACTIVE')
            ->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'customer_email' =>
                    'An active TradeX customer with this email was not found.',
            ]);
        }

        $product = InvestmentProduct::query()
            ->where('code', (string) $data['product_code'])
            ->first();

        if (! $product instanceof InvestmentProduct) {
            throw ValidationException::withMessages([
                'product_code' => 'The selected investment product is unavailable.',
            ]);
        }

        if (! $product->isActive()) {
            throw ValidationException::withMessages([
                'product_code' =>
                    'This investment product is COMING SOON and cannot accept investments yet.',
            ]);
        }

        if ($product->code !== InvestmentProduct::CODE_CRYPTO) {
            throw ValidationException::withMessages([
                'product_code' =>
                    'Only Crypto Investment is available in Investment V1.',
            ]);
        }

        $wallet = trim(
            (string) ($data['payout_wallet_address'] ?? '')
        );

        if ($wallet === '') {
            $wallet = trim(
                (string) ($user->profile?->usdt_trc20_address ?? '')
            );
        }

        return DB::transaction(
            function () use (
                $data,
                $admin,
                $request,
                $user,
                $product,
                $wallet
            ): Investment {
                $investment = Investment::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'investment_code' =>
                        $this->nextInvestmentCode(),
                    'user_id' => $user->getKey(),
                    'investment_product_id' => $product->getKey(),
                    'status' => Investment::STATUS_AWAITING_DEPOSIT,
                    'profit_model' =>
                        Investment::PROFIT_MODEL_PERFORMANCE_SHARE,
                    'deposit_amount' => (string) $data['deposit_amount'],
                    'deposit_currency' => 'USDT',
                    'deposit_network' => 'TRC20',
                    'investment_period_years' =>
                        (int) $data['investment_period_years'],
                    'profit_review_interval_months' =>
                        (int) $data['profit_review_interval_months'],
                    'customer_profit_share_bps' =>
                        (int) $product->customer_profit_share_bps,
                    'company_profit_share_bps' =>
                        (int) $product->company_profit_share_bps,
                    'early_exit_return_bps' =>
                        (int) $product->early_exit_return_bps,
                    'fixed_return_guaranteed' => false,
                    'settlement_mode' =>
                        (string) $data['settlement_mode'],
                    'custom_settlement_date' =>
                        $data['custom_settlement_date'] ?? null,
                    'principal_settlement_status' =>
                        Investment::PRINCIPAL_NOT_DUE,
                    'payout_wallet_address' =>
                        $wallet !== '' ? $wallet : null,
                    'payout_wallet_network' => 'TRC20',
                    'high_water_mark_usdt' => '0',
                    'loss_carryforward_usdt' => '0',
                    'created_by_admin_id' => $admin->getKey(),
                    'updated_by_admin_id' => $admin->getKey(),
                ]);

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'INVESTMENT_CREATED',
                    module: 'INVESTMENTS',
                    targetType: 'INVESTMENT',
                    targetId: $investment->getKey(),
                    newValues: [
                        'investment_code' =>
                            $investment->investment_code,
                        'product_code' => $product->code,
                        'customer_id' => $user->getKey(),
                        'deposit_amount' =>
                            (string) $investment->deposit_amount,
                        'deposit_currency' => 'USDT',
                        'deposit_network' => 'TRC20',
                        'investment_period_years' =>
                            $investment->investment_period_years,
                        'profit_review_interval_months' =>
                            $investment->profit_review_interval_months,
                        'customer_profit_share_bps' =>
                            $investment->customer_profit_share_bps,
                        'company_profit_share_bps' =>
                            $investment->company_profit_share_bps,
                        'fixed_return_guaranteed' => false,
                        'status' => $investment->status,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $investment;
            },
            3
        );
    }

    private function nextInvestmentCode(): string
    {
        do {
            $code = 'TXI-'
                .now()->format('Ym')
                .'-'
                .Str::upper(Str::random(8));
        } while (
            Investment::query()
                ->where('investment_code', $code)
                ->exists()
        );

        return $code;
    }
}
