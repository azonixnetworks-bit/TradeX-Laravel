<?php

namespace App\Services\Admin\Payments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\BankDeposit;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UsdtDeposit;
use App\Services\Payments\PaymentMoney;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminPaymentService
{
    private const MANUAL_REVIEW_REJECTED = 'MANUAL_REVIEW_REJECTED';

    public function __construct(
        private readonly PaymentMoney $money
    ) {
    }

    public function dashboard(array $filters): array
    {
        $query = PaymentOrder::query()
            ->with([
                'user:id,uuid,name,email,country_code,account_type,status',
                'payment:id,payment_order_id,uuid,status,provider,cleared_at,payment_reversed_at',
                'bankDeposit:id,payment_order_id,uuid,status,submitted_at',
                'usdtDeposit:id,payment_order_id,uuid,status,submitted_at',
            ]);

        $this->applyOrderFilters($query, $filters);

        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        return [
            'orders' => $orders,
            'money' => $this->money,
            'stats' => [
                'orders' => PaymentOrder::query()->count(),
                'pending_manual' => PaymentOrder::query()
                    ->where('status', PaymentOrder::STATUS_PENDING_REVIEW)
                    ->whereIn('provider', [
                        PaymentOrder::PROVIDER_LOCAL_BANK,
                        PaymentOrder::PROVIDER_USDT_TRC20,
                    ])
                    ->count(),
                'cleared' => PaymentOrder::query()
                    ->where('status', PaymentOrder::STATUS_CLEARED)
                    ->count(),
                'failed' => PaymentOrder::query()
                    ->where('status', PaymentOrder::STATUS_FAILED)
                    ->count(),
                'reversed' => PaymentOrder::query()
                    ->where('status', PaymentOrder::STATUS_PAYMENT_REVERSED)
                    ->count(),
                'paypal' => PaymentOrder::query()
                    ->where('provider', PaymentOrder::PROVIDER_PAYPAL)
                    ->count(),
            ],
        ];
    }

    public function orderDetail(PaymentOrder $order): array
    {
        $order->load([
            'user:id,uuid,name,email,whatsapp_number,country_code,account_type,status,premium_activated_at',
            'payment.adjustments',
            'bankDeposit.reviewedByAdmin:id,name,email',
            'usdtDeposit.reviewedByAdmin:id,name,email',
        ]);

        return [
            'order' => $order,
            'money' => $this->money,
        ];
    }

    public function paypalOrders(array $filters): array
    {
        $query = PaymentOrder::query()
            ->with([
                'user:id,uuid,name,email,country_code,account_type,status',
                'payment:id,payment_order_id,uuid,status,gross_amount_minor,processor_fee_minor,net_eligible_revenue_minor,affiliate_processing_status,cleared_at,payment_reversed_at,payment_reversal_code',
            ])
            ->where('provider', PaymentOrder::PROVIDER_PAYPAL);

        $this->applyOrderFilters($query, $filters, false);

        return [
            'orders' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($this->perPage($filters))
                ->withQueryString(),
            'money' => $this->money,
        ];
    }

    public function bankDeposits(array $filters): array
    {
        $query = BankDeposit::query()
            ->with([
                'order:id,uuid,user_id,provider,country_code,currency,amount_minor,status,cleared_at,failure_code,created_at',
                'user:id,uuid,name,email,country_code,account_type,status',
                'reviewedByAdmin:id,name,email',
            ]);

        $this->applyManualDepositFilters($query, $filters, false);

        return [
            'deposits' => $query
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->paginate($this->perPage($filters))
                ->withQueryString(),
            'money' => $this->money,
        ];
    }

    public function usdtDeposits(array $filters): array
    {
        $query = UsdtDeposit::query()
            ->with([
                'order:id,uuid,user_id,provider,country_code,currency,amount_minor,status,cleared_at,failure_code,created_at',
                'user:id,uuid,name,email,country_code,account_type,status',
                'reviewedByAdmin:id,name,email',
            ]);

        $this->applyManualDepositFilters($query, $filters, true);

        return [
            'deposits' => $query
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->paginate($this->perPage($filters))
                ->withQueryString(),
            'money' => $this->money,
        ];
    }

    public function approveBankDeposit(
        BankDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): Payment {
        return DB::transaction(function () use (
            $deposit,
            $admin,
            $reason,
            $request
        ): Payment {
            $lockedDeposit = BankDeposit::query()
                ->whereKey($deposit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = PaymentOrder::query()
                ->whereKey($lockedDeposit->payment_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertManualReviewable(
                $order,
                $lockedDeposit->status,
                PaymentOrder::PROVIDER_LOCAL_BANK
            );

            if ((int) $lockedDeposit->user_id !== (int) $order->user_id) {
                throw new DomainException('Bank evidence ownership does not match the payment order.');
            }

            $user = User::query()
                ->whereKey($order->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $this->createManualPayment(
                $order,
                $user,
                'ADMIN_BANK:'.$lockedDeposit->uuid
            );

            $lockedDeposit->forceFill([
                'status' => BankDeposit::STATUS_APPROVED,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_reason' => trim($reason),
            ])->save();

            $this->clearOrderAndActivateEntitlement(
                $order,
                $user,
                $payment
            );

            $this->auditManualDecision(
                $admin,
                $order,
                'PAYMENT_BANK_DEPOSIT_VERIFIED',
                BankDeposit::STATUS_APPROVED,
                $request
            );

            $this->auditClearedAndEntitlement(
                $admin,
                $order,
                $payment,
                $user,
                $request
            );

            return $payment->fresh();
        }, 3);
    }

    public function rejectBankDeposit(
        BankDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): void {
        DB::transaction(function () use (
            $deposit,
            $admin,
            $reason,
            $request
        ): void {
            $lockedDeposit = BankDeposit::query()
                ->whereKey($deposit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = PaymentOrder::query()
                ->whereKey($lockedDeposit->payment_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertManualReviewable(
                $order,
                $lockedDeposit->status,
                PaymentOrder::PROVIDER_LOCAL_BANK
            );

            if ((int) $lockedDeposit->user_id !== (int) $order->user_id) {
                throw new DomainException('Bank evidence ownership does not match the payment order.');
            }

            $this->assertNoPaymentExists($order);

            $lockedDeposit->forceFill([
                'status' => BankDeposit::STATUS_REJECTED,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_reason' => trim($reason),
            ])->save();

            $order->forceFill([
                'status' => PaymentOrder::STATUS_FAILED,
                'failure_code' => self::MANUAL_REVIEW_REJECTED,
                'cleared_at' => null,
            ])->save();

            $this->auditManualDecision(
                $admin,
                $order,
                'PAYMENT_BANK_DEPOSIT_REJECTED',
                BankDeposit::STATUS_REJECTED,
                $request
            );
        }, 3);
    }

    public function approveUsdtDeposit(
        UsdtDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): Payment {
        return DB::transaction(function () use (
            $deposit,
            $admin,
            $reason,
            $request
        ): Payment {
            $lockedDeposit = UsdtDeposit::query()
                ->whereKey($deposit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = PaymentOrder::query()
                ->whereKey($lockedDeposit->payment_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertManualReviewable(
                $order,
                $lockedDeposit->status,
                PaymentOrder::PROVIDER_USDT_TRC20
            );

            if ((int) $lockedDeposit->user_id !== (int) $order->user_id) {
                throw new DomainException('USDT evidence ownership does not match the payment order.');
            }

            if (
                strtoupper((string) $lockedDeposit->network)
                    !== UsdtDeposit::NETWORK_TRC20
                || (int) $lockedDeposit->amount_micro
                    !== (int) $order->amount_minor
            ) {
                throw new DomainException(
                    'USDT evidence does not match the TradeX order contract.'
                );
            }

            $txHash = strtolower(trim((string) $lockedDeposit->tx_hash));
            if ($txHash === '') {
                throw new DomainException(
                    'USDT transaction hash is missing from the review evidence.'
                );
            }

            $user = User::query()
                ->whereKey($order->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $this->createManualPayment(
                $order,
                $user,
                'ADMIN_USDT_TRC20:'.$txHash
            );

            $lockedDeposit->forceFill([
                'status' => UsdtDeposit::STATUS_APPROVED,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_reason' => trim($reason),
            ])->save();

            $this->clearOrderAndActivateEntitlement(
                $order,
                $user,
                $payment
            );

            $this->auditManualDecision(
                $admin,
                $order,
                'PAYMENT_USDT_DEPOSIT_VERIFIED',
                UsdtDeposit::STATUS_APPROVED,
                $request
            );

            $this->auditClearedAndEntitlement(
                $admin,
                $order,
                $payment,
                $user,
                $request
            );

            return $payment->fresh();
        }, 3);
    }

    public function rejectUsdtDeposit(
        UsdtDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): void {
        DB::transaction(function () use (
            $deposit,
            $admin,
            $reason,
            $request
        ): void {
            $lockedDeposit = UsdtDeposit::query()
                ->whereKey($deposit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $order = PaymentOrder::query()
                ->whereKey($lockedDeposit->payment_order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertManualReviewable(
                $order,
                $lockedDeposit->status,
                PaymentOrder::PROVIDER_USDT_TRC20
            );

            if ((int) $lockedDeposit->user_id !== (int) $order->user_id) {
                throw new DomainException('USDT evidence ownership does not match the payment order.');
            }

            $this->assertNoPaymentExists($order);

            $lockedDeposit->forceFill([
                'status' => UsdtDeposit::STATUS_REJECTED,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => now(),
                'review_reason' => trim($reason),
            ])->save();

            $order->forceFill([
                'status' => PaymentOrder::STATUS_FAILED,
                'failure_code' => self::MANUAL_REVIEW_REJECTED,
                'cleared_at' => null,
            ])->save();

            $this->auditManualDecision(
                $admin,
                $order,
                'PAYMENT_USDT_DEPOSIT_REJECTED',
                UsdtDeposit::STATUS_REJECTED,
                $request
            );
        }, 3);
    }

    public function bankProof(BankDeposit $deposit): array
    {
        $deposit->loadMissing('user:id,uuid');

        $disk = trim((string) $deposit->proof_disk);
        $path = str_replace('\\', '/', trim((string) $deposit->proof_path));

        $configuredDisks = array_keys((array) config('filesystems.disks', []));
        if ($disk === '' || ! in_array($disk, $configuredDisks, true)) {
            throw new DomainException('Protected payment proof is unavailable.');
        }

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, '../')
            || str_contains($path, '/..')
        ) {
            throw new DomainException('Protected payment proof path is invalid.');
        }

        $expectedPrefix = 'payments/bank/'.$deposit->user->uuid.'/';
        if (! str_starts_with($path, $expectedPrefix)) {
            throw new DomainException('Protected payment proof ownership check failed.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new DomainException('Protected payment proof file was not found.');
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1
            ? '.'.$extension
            : '';

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => 'bank-proof-'.$deposit->uuid.$extension,
        ];
    }

    public function configuredAccounts(): array
    {
        $paypalClientId = trim((string) config('tradex_payments.paypal.client_id'));
        $paypalSecret = trim((string) config('tradex_payments.paypal.client_secret'));
        $paypalWebhook = trim((string) config('tradex_payments.paypal.webhook_id'));

        $bankAccount = trim((string) config('tradex_payments.local_bank.account_number'));
        $wallet = trim((string) config('tradex_payments.usdt_trc20.wallet_address'));

        return [
            'paypal' => [
                'enabled' => (bool) config('tradex_payments.paypal.enabled', false),
                'environment' => (string) config('tradex_payments.paypal.environment', 'sandbox'),
                'driver' => (string) config('tradex_payments.paypal.driver', 'http'),
                'countries' => (array) config('tradex_payments.paypal.countries', []),
                'client_id_configured' => $paypalClientId !== '',
                'client_secret_configured' => $paypalSecret !== '',
                'webhook_configured' => $paypalWebhook !== '',
            ],
            'local_bank' => [
                'enabled' => (bool) config('tradex_payments.local_bank.enabled', false),
                'countries' => (array) config('tradex_payments.local_bank.countries', []),
                'bank_name' => (string) config('tradex_payments.local_bank.bank_name', ''),
                'branch' => (string) config('tradex_payments.local_bank.branch', ''),
                'account_holder' => (string) config('tradex_payments.local_bank.account_holder', ''),
                'account_number_masked' => $this->mask($bankAccount),
            ],
            'usdt' => [
                'enabled' => (bool) config('tradex_payments.usdt_trc20.enabled', false),
                'countries' => (array) config('tradex_payments.usdt_trc20.countries', []),
                'network' => (string) config('tradex_payments.usdt_trc20.network', 'TRC20'),
                'token' => (string) config('tradex_payments.usdt_trc20.token', 'USDT'),
                'wallet_masked' => $this->mask($wallet, 6),
            ],
        ];
    }

    private function createManualPayment(
        PaymentOrder $order,
        User $user,
        string $reference
    ): Payment {
        $this->assertNoPaymentExists($order);

        $referenceHash = hash('sha256', $reference);

        if (
            Payment::query()
                ->where('provider_capture_reference_hash', $referenceHash)
                ->lockForUpdate()
                ->exists()
        ) {
            throw new DomainException(
                'This manual payment evidence has already been reconciled.'
            );
        }

        return Payment::query()->create([
            'payment_order_id' => $order->getKey(),
            'user_id' => $user->getKey(),
            'provider' => $order->provider,
            'currency' => strtoupper((string) $order->currency),
            'gross_amount_minor' => (int) $order->amount_minor,
            'processor_fee_minor' => null,
            'net_eligible_revenue_minor' => null,
            'provider_capture_reference_hash' => $referenceHash,
            'provider_capture_reference' => $reference,
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            'affiliate_processed_at' => null,
            'cleared_at' => now(),
        ]);
    }

    private function clearOrderAndActivateEntitlement(
        PaymentOrder $order,
        User $user,
        Payment $payment
    ): void {
        $order->forceFill([
            'status' => PaymentOrder::STATUS_CLEARED,
            'cleared_at' => $payment->cleared_at,
            'failure_code' => null,
        ])->save();

        if (! $user->isPremium()) {
            $user->forceFill([
                'account_type' => 'PREMIUM',
                'premium_activated_at' => $user->premium_activated_at ?? now(),
            ])->save();
        }

        UserEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'product_code' => $order->product_code,
            ],
            [
                'status' => UserEntitlement::STATUS_ACTIVE,
                'activated_at' => now(),
                'payment_id' => $payment->getKey(),
                'revoked_at' => null,
                'revoke_reason' => null,
            ]
        );
    }

    private function assertManualReviewable(
        PaymentOrder $order,
        string $depositStatus,
        string $provider
    ): void {
        if ($order->provider !== $provider) {
            throw new DomainException('Payment provider does not match this review queue.');
        }

        if (
            $order->status !== PaymentOrder::STATUS_PENDING_REVIEW
            || $depositStatus !== 'SUBMITTED'
        ) {
            throw new DomainException('This manual payment has already been reviewed or is no longer pending.');
        }
    }

    private function assertNoPaymentExists(PaymentOrder $order): void
    {
        if (
            Payment::query()
                ->where('payment_order_id', $order->getKey())
                ->lockForUpdate()
                ->exists()
        ) {
            throw new DomainException('A settled payment already exists for this order.');
        }
    }

    private function auditManualDecision(
        AdminUser $admin,
        PaymentOrder $order,
        string $action,
        string $decision,
        Request $request
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'PAYMENTS',
            targetType: 'PAYMENT_ORDER',
            targetId: $order->getKey(),
            newValues: [
                'order_uuid' => $order->uuid,
                'provider' => $order->provider,
                'decision' => $decision,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    private function auditClearedAndEntitlement(
        AdminUser $admin,
        PaymentOrder $order,
        Payment $payment,
        User $user,
        Request $request
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'PAYMENT_CLEARED',
            module: 'PAYMENTS',
            targetType: 'PAYMENT',
            targetId: $payment->getKey(),
            newValues: [
                'payment_uuid' => $payment->uuid,
                'payment_order_uuid' => $order->uuid,
                'provider' => $order->provider,
                'currency' => $order->currency,
                'gross_amount_minor' => (int) $order->amount_minor,
                'manual_reconciliation' => true,
                'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'PREMIUM_ENTITLEMENT_ACTIVATED',
            module: 'PAYMENTS',
            targetType: 'USER',
            targetId: $user->getKey(),
            newValues: [
                'product_code' => $order->product_code,
                'payment_uuid' => $payment->uuid,
                'manual_reconciliation' => true,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    private function applyOrderFilters(
        Builder $query,
        array $filters,
        bool $allowProvider = true
    ): void {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, $this->orderStatuses(), true)) {
            $query->where('status', $status);
        }

        if ($allowProvider) {
            $provider = trim((string) ($filters['provider'] ?? ''));
            if (in_array($provider, $this->providers(), true)) {
                $query->where('provider', $provider);
            }
        }

        $country = strtoupper(trim((string) ($filters['country'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
            $query->where('country_code', $country);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    private function applyManualDepositFilters(
        Builder $query,
        array $filters,
        bool $hasTransactionHash
    ): void {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use (
                $search,
                $hasTransactionHash
            ): void {
                $builder
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function (Builder $order) use ($search): void {
                        $order->where('uuid', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });

                if ($hasTransactionHash) {
                    $builder->orWhere('tx_hash', 'like', '%'.strtolower($search).'%');
                }
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, [
            BankDeposit::STATUS_SUBMITTED,
            BankDeposit::STATUS_APPROVED,
            BankDeposit::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $query->whereDate('submitted_at', '>=', $from);
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $query->whereDate('submitted_at', '<=', $to);
        }
    }

    private function orderStatuses(): array
    {
        return [
            PaymentOrder::STATUS_CREATED,
            PaymentOrder::STATUS_AWAITING_USER_ACTION,
            PaymentOrder::STATUS_PENDING_REVIEW,
            PaymentOrder::STATUS_PROCESSING,
            PaymentOrder::STATUS_CLEARED,
            PaymentOrder::STATUS_FAILED,
            PaymentOrder::STATUS_CANCELLED,
            PaymentOrder::STATUS_PAYMENT_REVERSED,
        ];
    }

    private function providers(): array
    {
        return [
            PaymentOrder::PROVIDER_PAYPAL,
            PaymentOrder::PROVIDER_LOCAL_BANK,
            PaymentOrder::PROVIDER_USDT_TRC20,
        ];
    }

    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return in_array($perPage, [10, 20, 50, 100], true)
            ? $perPage
            : 20;
    }

    private function mask(string $value, int $tail = 4): string
    {
        if ($value === '') {
            return 'Not configured';
        }

        if (mb_strlen($value) <= $tail) {
            return str_repeat('•', mb_strlen($value));
        }

        return str_repeat('•', min(8, mb_strlen($value) - $tail))
            .mb_substr($value, -$tail);
    }
}
