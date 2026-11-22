<?php

namespace App\Http\Controllers\Api\Investments;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentProduct;
use App\Models\InvestmentRiskAcceptance;
use App\Models\InvestmentTransaction;
use App\Models\KycSubmission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $investments = Investment::query()
            ->where('user_id', $user->getKey())
            ->with([
                'product',
                'latestAgreement',
                'riskAcceptances' => fn ($query) => $query->latest('accepted_at')->limit(1),
                'profitPeriods' => fn ($query) => $query->orderByDesc('sequence'),
                'transactions' => fn ($query) => $query->latest('occurred_at')->limit(100),
            ])
            ->latest('id')
            ->get();

        $total = $investments->sum(fn (Investment $item) => (float) $item->deposit_amount);
        $verifiedProfit = $investments->flatMap->profitPeriods
            ->whereIn('status', ['APPROVED', 'PAID', 'SETTLED'])
            ->sum(fn ($period) => (float) $period->customer_profit_share_usdt);

        return response()->json([
            'success' => true,
            'data' => [
                'portfolio' => [
                    'total_invested' => $total,
                    'portfolio_value' => $total + $verifiedProfit,
                    'verified_profit' => $verifiedProfit,
                    'currency' => 'USDT',
                    'active_count' => $investments->where('status', Investment::STATUS_ACTIVE)->count(),
                    'valued_at' => now()->toIso8601String(),
                ],
                'products' => InvestmentProduct::query()
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (InvestmentProduct $product) => $this->product($product)),
                'investments' => $investments->map(fn (Investment $item) => $this->investment($item)),
                'onboarding' => $this->onboarding($user),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($user->hasVerifiedKyc(), 422, 'Verified KYC is required.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:3000', 'max:500000'],
            'period_years' => ['required', 'integer', Rule::in([1, 2, 5])],
            'review_interval_months' => ['required', 'integer', Rule::in([2, 4, 6])],
            'accept_risk' => ['accepted'],
            'accept_agreement' => ['accepted'],
        ]);

        $product = InvestmentProduct::query()
            ->where('code', InvestmentProduct::CODE_CRYPTO)
            ->where('status', InvestmentProduct::STATUS_ACTIVE)
            ->firstOrFail();

        $investment = DB::transaction(function () use ($data, $product, $request, $user): Investment {
            $investment = Investment::query()->create([
                'uuid' => Str::uuid()->toString(),
                'investment_code' => $this->nextCode(),
                'user_id' => $user->getKey(),
                'investment_product_id' => $product->getKey(),
                'status' => Investment::STATUS_AWAITING_DEPOSIT,
                'profit_model' => Investment::PROFIT_MODEL_PERFORMANCE_SHARE,
                'deposit_amount' => (string) $data['amount'],
                'deposit_currency' => 'USDT',
                'deposit_network' => 'TRC20',
                'investment_period_years' => (int) $data['period_years'],
                'profit_review_interval_months' => (int) $data['review_interval_months'],
                'customer_profit_share_bps' => $product->customer_profit_share_bps,
                'company_profit_share_bps' => $product->company_profit_share_bps,
                'early_exit_return_bps' => $product->early_exit_return_bps,
                'fixed_return_guaranteed' => false,
                'settlement_mode' => Investment::SETTLEMENT_ON_MATURITY,
                'principal_settlement_status' => Investment::PRINCIPAL_NOT_DUE,
                'high_water_mark_usdt' => '0',
                'loss_carryforward_usdt' => '0',
            ]);

            InvestmentRiskAcceptance::query()->create([
                'uuid' => Str::uuid()->toString(),
                'investment_id' => $investment->getKey(),
                'user_id' => $user->getKey(),
                'disclosure_version' => (string) config('tradex_investments.risk.version'),
                'evidence_source' => InvestmentRiskAcceptance::SOURCE_CUSTOMER_SELF_SERVICE,
                'acceptance_reference' => Str::uuid()->toString(),
                'acceptance_payload' => ['agreement_accepted' => true],
                'accepted_at' => now(),
                'accepted_ip' => $request->ip(),
                'accepted_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            return $investment;
        }, 3);

        $investment->load(['product', 'latestAgreement', 'riskAcceptances']);

        return response()->json([
            'success' => true,
            'data' => $this->investment($investment),
        ], 201);
    }

    public function deposit(Request $request, string $investment): JsonResponse
    {
        $user = $this->user($request);
        $record = Investment::query()
            ->where('uuid', $investment)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        abort_unless(in_array($record->status, [
            Investment::STATUS_AWAITING_DEPOSIT,
            Investment::STATUS_DEPOSIT_VERIFICATION,
        ], true), 409, 'This investment cannot accept a deposit submission.');

        $data = $request->validate([
            'txid' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'sender_address' => ['nullable', 'string', 'regex:/^T[1-9A-HJ-NP-Za-km-z]{33}$/'],
        ]);

        DB::transaction(function () use ($data, $record): void {
            abort_if(InvestmentTransaction::query()
                ->where('network', 'TRC20')
                ->where('txid', strtolower($data['txid']))
                ->exists(), 409, 'This transaction has already been submitted.');

            InvestmentTransaction::query()->create([
                'uuid' => Str::uuid()->toString(),
                'investment_id' => $record->getKey(),
                'direction' => InvestmentTransaction::DIRECTION_IN,
                'type' => InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT,
                'asset_symbol' => 'USDT',
                'amount' => $record->deposit_amount,
                'usdt_equivalent' => $record->deposit_amount,
                'network' => 'TRC20',
                'from_address' => $data['sender_address'] ?? null,
                'txid' => strtolower($data['txid']),
                'status' => InvestmentTransaction::STATUS_PENDING,
                'occurred_at' => now(),
            ]);

            $record->update(['status' => Investment::STATUS_DEPOSIT_VERIFICATION]);
        }, 3);

        return response()->json(['success' => true, 'data' => ['status' => 'PENDING_VERIFICATION']], 202);
    }

    public function agreement(Request $request, string $investment)
    {
        $record = Investment::query()
            ->where('uuid', $investment)
            ->where('user_id', $this->user($request)->getKey())
            ->with('latestAgreement')
            ->firstOrFail();
        $agreement = $record->latestAgreement;
        abort_unless($agreement && $agreement->document_path, 404, 'Agreement is not available yet.');

        $disk = Storage::disk($agreement->document_disk ?: config('tradex_investments.storage_disk'));
        abort_unless($disk->exists($agreement->document_path), 404, 'Agreement file is unavailable.');

        return $disk->download($agreement->document_path, "{$record->investment_code}-agreement.pdf");
    }

    private function investment(Investment $item): array
    {
        $risk = $item->riskAcceptances->first();
        $agreement = $item->latestAgreement;

        return [
            'id' => $item->uuid,
            'code' => $item->investment_code,
            'product_name' => $item->product?->name ?? 'Crypto Investment',
            'status' => $item->status,
            'amount' => (float) $item->deposit_amount,
            'currency' => $item->deposit_currency,
            'network' => $item->deposit_network,
            'start_at' => $item->start_date?->toDateString(),
            'maturity_at' => $item->maturity_date?->toDateString(),
            'period_label' => "{$item->investment_period_years} year(s)",
            'customer_share_percent' => $item->customerProfitSharePercent(),
            'company_share_percent' => $item->companyProfitSharePercent(),
            'high_water_mark' => (float) $item->high_water_mark_usdt,
            'loss_carry_forward' => (float) $item->loss_carryforward_usdt,
            'principal_settlement_status' => $item->principal_settlement_status,
            'agreement' => $agreement ? [
                'risk_accepted' => $risk !== null,
                'disclosure_version' => $risk?->disclosure_version,
                'risk_accepted_at' => $risk?->accepted_at?->toIso8601String(),
                'agreement_version' => (string) $agreement->version,
                'status' => $agreement->status,
                'generated_at' => $agreement->generated_at?->toIso8601String(),
                'signed_at' => $agreement->signed_at?->toIso8601String(),
                'download_path' => "/api/v1/investments/{$item->uuid}/agreement",
            ] : null,
        ];
    }

    private function product(InvestmentProduct $product): array
    {
        return [
            'id' => $product->code,
            'name' => $product->name,
            'availability' => $product->status,
            'currencies' => [$product->deposit_currency],
            'networks' => [$product->deposit_network],
            'profit_sharing_model' => 'Performance share',
            'period_terms' => '1, 2 or 5 years',
            'risk_tier' => 'High',
        ];
    }

    private function onboarding(User $user): array
    {
        return [
            'kyc_verified' => $user->hasVerifiedKyc(),
            'kyc_status' => $user->latestKycSubmission?->status ?? 'NOT_SUBMITTED',
            'minimum_amount' => 3000,
            'maximum_amount' => 500000,
            'currency' => 'USDT',
            'network' => 'TRC20',
            'deposit_address' => null,
            'period_years' => [1, 2, 5],
            'review_intervals_months' => [2, 4, 6],
            'risk_statements' => array_values((array) config('tradex_investments.risk.statements', [])),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');
        $user->loadMissing(['latestKycSubmission']);
        return $user;
    }

    private function nextCode(): string
    {
        do {
            $code = 'TXI-'.now()->format('Ym').'-'.Str::upper(Str::random(8));
        } while (Investment::query()->where('investment_code', $code)->exists());
        return $code;
    }
}
