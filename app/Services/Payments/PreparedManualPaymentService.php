<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\BankDeposit;
use App\Models\CryptoDeposit;
use App\Models\PaymentOrder;
use App\Models\PaymentReceivingAccount;
use App\Models\User;
use App\Models\UsdtDeposit;
use App\Services\Products\ProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PreparedManualPaymentService
{
    public function __construct(
        private readonly ManualPaymentAccountCatalogService $accounts,
        private readonly PaymentOrderService $orders,
        private readonly PaymentApiPresenter $presenter,
        private readonly PaymentMoney $money,
        private readonly ScaledDecimal $decimal,
        private readonly ProductCatalogService $products
    ) {
    }

    public function prepare(
        User $user,
        array $data,
        Request $request
    ): array {
        if ($user->canAccessPremium()) {
            throw new AuthApiException(
                409,
                'PREMIUM_ALREADY_ACTIVE',
                'Premium access is already active for this account.'
            );
        }

        $product = $this->products->educationPremium();

        if (! $product->is_active) {
            throw new AuthApiException(
                422,
                'PRODUCT_INACTIVE',
                'This TradeX product is not currently available for new purchases.'
            );
        }

        $account = $this->accounts->resolveEligible(
            $user,
            (string) $data['receiving_account_uuid']
        );

        $this->assertFeatureEnabled($account);

        $provider = $account->isBank()
            ? PaymentOrder::PROVIDER_LOCAL_BANK
            : (
                $account->isUsdtTrc20()
                    ? PaymentOrder::PROVIDER_USDT_TRC20
                    : PaymentOrder::PROVIDER_CRYPTO_MANUAL
            );

        $idempotencyHash = $this->orders->idempotencyHash(
            (string) $data['idempotency_key']
        );

        $existing = PaymentOrder::query()
            ->where('user_id', $user->getKey())
            ->where('client_idempotency_key_hash', $idempotencyHash)
            ->first();

        if ($existing instanceof PaymentOrder) {
            $this->assertExistingMatches(
                $existing,
                $provider,
                $account
            );

            return $this->presenter->order($existing);
        }

        $copiedQr = null;

        try {
            $order = DB::transaction(
                function () use (
                    $user,
                    $account,
                    $product,
                    $provider,
                    $idempotencyHash,
                    $request,
                    &$copiedQr
                ): PaymentOrder {
                    $concurrent = PaymentOrder::query()
                        ->where('user_id', $user->getKey())
                        ->where(
                            'client_idempotency_key_hash',
                            $idempotencyHash
                        )
                        ->lockForUpdate()
                        ->first();

                    if ($concurrent instanceof PaymentOrder) {
                        $this->assertExistingMatches(
                            $concurrent,
                            $provider,
                            $account
                        );

                        return $concurrent;
                    }

                    $reference = $this->uniqueReference();
                    $snapshot = $this->snapshot($account);

                    $order = PaymentOrder::query()->create([
                        'user_id' => $user->getKey(),
                        'receiving_account_id' => $account->getKey(),
                        'product_code' =>
                            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                        'provider' => $provider,
                        'country_code' =>
                            strtoupper((string) $user->country_code),
                        'currency' =>
                            $provider === PaymentOrder::PROVIDER_CRYPTO_MANUAL
                                ? strtoupper((string) $product->currency)
                                : strtoupper((string) $account->currency),
                        'amount_minor' =>
                            $provider === PaymentOrder::PROVIDER_CRYPTO_MANUAL
                                ? (int) $product->amount_minor
                                : (int) $account->tender_amount_minor,
                        'status' =>
                            PaymentOrder::STATUS_AWAITING_USER_ACTION,
                        'client_idempotency_key_hash' =>
                            $idempotencyHash,
                        'manual_reference' => $reference,
                        'receiving_account_snapshot' => $snapshot,
                        'expires_at' => now()->addMinutes(
                            max(
                                30,
                                (int) config(
                                    'tradex_payments.manual_checkout.order_ttl_minutes',
                                    1440
                                )
                            )
                        ),
                    ]);

                    if (
                        $account->isCrypto()
                        && filled($account->qr_disk)
                        && filled($account->qr_path)
                    ) {
                        $copiedQr = $this->copyQr(
                            $account,
                            $order
                        );

                        $snapshot['qr_disk'] = $copiedQr['disk'];
                        $snapshot['qr_path'] = $copiedQr['path'];

                        $order->forceFill([
                            'receiving_account_snapshot' => $snapshot,
                        ])->save();
                    }

                    AuditLog::record(
                        actorType: 'USER',
                        actorId: $user->getKey(),
                        action: 'PAYMENT_MANUAL_ORDER_PREPARED',
                        module: 'PAYMENTS',
                        targetType: 'PAYMENT_ORDER',
                        targetId: $order->getKey(),
                        newValues: [
                            'order_uuid' => $order->uuid,
                            'manual_reference' => $reference,
                            'provider' => $provider,
                            'receiving_account_uuid' => $account->uuid,
                            'receiving_type' => $account->type,
                            'currency' => $order->currency,
                            'amount_minor' =>
                                (int) $order->amount_minor,
                            'status' => $order->status,
                        ],
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );

                    return $order;
                },
                3
            );
        } catch (Throwable $exception) {
            if (
                is_array($copiedQr)
                && filled($copiedQr['disk'] ?? null)
                && filled($copiedQr['path'] ?? null)
            ) {
                Storage::disk($copiedQr['disk'])
                    ->delete($copiedQr['path']);
            }

            throw $exception;
        }

        return $this->presenter->order(
            $order->fresh([
                'payment',
                'bankDeposit',
                'usdtDeposit',
            ])
        );
    }

    public function submitBankEvidence(
        User $user,
        string $orderUuid,
        array $data,
        UploadedFile $proof,
        Request $request
    ): array {
        $order = $this->ownedPreparedOrder(
            $user,
            $orderUuid,
            PaymentOrder::PROVIDER_LOCAL_BANK
        );

        if ($order->bankDeposit instanceof BankDeposit) {
            return $this->presenter->order($order);
        }

        $disk = (string) config(
            'tradex_payments.local_bank.proof_disk',
            'local'
        );

        $path = $this->storeProof(
            $disk,
            'payments/bank/'.$user->uuid,
            $proof
        );

        $used = false;

        try {
            $order = DB::transaction(
                function () use (
                    $order,
                    $user,
                    $data,
                    $disk,
                    $path,
                    $request,
                    &$used
                ): PaymentOrder {
                    $locked = PaymentOrder::query()
                        ->whereKey($order->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertPreparedState(
                        $locked,
                        PaymentOrder::PROVIDER_LOCAL_BANK
                    );

                    $existing = BankDeposit::query()
                        ->where(
                            'payment_order_id',
                            $locked->getKey()
                        )
                        ->first();

                    if ($existing instanceof BankDeposit) {
                        return $locked;
                    }

                    BankDeposit::query()->create([
                        'payment_order_id' => $locked->getKey(),
                        'user_id' => $user->getKey(),
                        'depositor_name' =>
                            trim((string) $data['depositor_name']),
                        'bank_reference' =>
                            trim((string) $data['bank_reference']),
                        'deposit_date' => $data['deposit_date'],
                        'proof_disk' => $disk,
                        'proof_path' => $path,
                        'status' => BankDeposit::STATUS_SUBMITTED,
                        'submitted_at' => now(),
                    ]);

                    $locked->forceFill([
                        'status' =>
                            PaymentOrder::STATUS_PENDING_REVIEW,
                    ])->save();

                    $used = true;

                    $this->auditEvidence(
                        $user,
                        $locked,
                        'PAYMENT_BANK_DEPOSIT_SUBMITTED',
                        $request,
                        ['proof_received' => true]
                    );

                    return $locked;
                },
                3
            );
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        if (! $used) {
            Storage::disk($disk)->delete($path);
        }

        return $this->presenter->order(
            $order->fresh(['bankDeposit', 'payment'])
        );
    }

    public function submitUsdtEvidence(
        User $user,
        string $orderUuid,
        array $data,
        ?UploadedFile $proof,
        Request $request
    ): array {
        $order = $this->ownedPreparedOrder(
            $user,
            $orderUuid,
            PaymentOrder::PROVIDER_USDT_TRC20
        );

        if ($order->usdtDeposit instanceof UsdtDeposit) {
            return $this->presenter->order($order);
        }

        $txHash = strtolower(
            trim((string) $data['tx_hash'])
        );

        if (preg_match('/^[a-f0-9]{64}$/', $txHash) !== 1) {
            throw new AuthApiException(
                422,
                'USDT_TRANSACTION_HASH_INVALID',
                'A valid 64-character TRC20 transaction hash is required.'
            );
        }

        if (
            UsdtDeposit::query()
                ->where('tx_hash', $txHash)
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'USDT_TRANSACTION_ALREADY_SUBMITTED',
                'This USDT transaction has already been submitted.'
            );
        }

        $submittedMinor = $this->money->decimalToMinor(
            (string) $data['amount'],
            'USDT'
        );

        if ($submittedMinor !== (int) $order->amount_minor) {
            throw new AuthApiException(
                422,
                'USDT_AMOUNT_MISMATCH',
                'The submitted USDT amount does not match the prepared TradeX payment amount.'
            );
        }

        $disk = (string) config(
            'tradex_payments.local_bank.proof_disk',
            'local'
        );

        $path = null;

        if ($proof instanceof UploadedFile) {
            $path = $this->storeProof(
                $disk,
                'payments/crypto/'.$user->uuid,
                $proof
            );
        }

        $used = false;

        try {
            $order = DB::transaction(
                function () use (
                    $order,
                    $user,
                    $data,
                    $txHash,
                    $submittedMinor,
                    $disk,
                    $path,
                    $request,
                    &$used
                ): PaymentOrder {
                    $locked = PaymentOrder::query()
                        ->whereKey($order->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertPreparedState(
                        $locked,
                        PaymentOrder::PROVIDER_USDT_TRC20
                    );

                    $existing = UsdtDeposit::query()
                        ->where(
                            'payment_order_id',
                            $locked->getKey()
                        )
                        ->first();

                    if ($existing instanceof UsdtDeposit) {
                        return $locked;
                    }

                    if (
                        UsdtDeposit::query()
                            ->where('tx_hash', $txHash)
                            ->lockForUpdate()
                            ->exists()
                    ) {
                        throw new AuthApiException(
                            409,
                            'USDT_TRANSACTION_ALREADY_SUBMITTED',
                            'This USDT transaction has already been submitted.'
                        );
                    }

                    UsdtDeposit::query()->create([
                        'payment_order_id' => $locked->getKey(),
                        'user_id' => $user->getKey(),
                        'network' => UsdtDeposit::NETWORK_TRC20,
                        'tx_hash' => $txHash,
                        'sender_address' =>
                            filled($data['sender_address'] ?? null)
                                ? trim(
                                    (string) $data['sender_address']
                                )
                                : null,
                        'amount_micro' => $submittedMinor,
                        'proof_disk' =>
                            $path !== null ? $disk : null,
                        'proof_path' => $path,
                        'status' => UsdtDeposit::STATUS_SUBMITTED,
                        'submitted_at' => now(),
                    ]);

                    $locked->forceFill([
                        'status' =>
                            PaymentOrder::STATUS_PENDING_REVIEW,
                    ])->save();

                    $used = $path !== null;

                    $this->auditEvidence(
                        $user,
                        $locked,
                        'PAYMENT_USDT_DEPOSIT_SUBMITTED',
                        $request,
                        [
                            'network' =>
                                UsdtDeposit::NETWORK_TRC20,
                            'proof_received' => $path !== null,
                        ]
                    );

                    return $locked;
                },
                3
            );
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }

        if ($path !== null && ! $used) {
            Storage::disk($disk)->delete($path);
        }

        return $this->presenter->order(
            $order->fresh(['usdtDeposit', 'payment'])
        );
    }

    public function submitCryptoEvidence(
        User $user,
        string $orderUuid,
        array $data,
        ?UploadedFile $proof,
        Request $request
    ): array {
        $order = $this->ownedPreparedOrder(
            $user,
            $orderUuid,
            PaymentOrder::PROVIDER_CRYPTO_MANUAL
        );

        if (
            $order->cryptoDeposit
            instanceof CryptoDeposit
        ) {
            return $this->presenter
                ->order($order);
        }

        $snapshot = (array) (
            $order->receiving_account_snapshot
            ?? []
        );

        $coin = strtoupper(
            trim(
                (string) (
                    $snapshot['coin_symbol']
                    ?? ''
                )
            )
        );

        $network = strtoupper(
            trim(
                (string) (
                    $snapshot['network']
                    ?? ''
                )
            )
        );

        $scale = (int) (
            $snapshot['amount_scale']
            ?? 0
        );

        $expectedAmount =
            $this->decimal->normalize(
                (string) (
                    $snapshot['amount']
                    ?? ''
                ),
                $scale
            );

        $submittedAmount =
            $this->decimal->normalize(
                (string) $data['amount'],
                $scale
            );

        if (
            $coin === ''
            || $network === ''
            || $submittedAmount
                !== $expectedAmount
        ) {
            throw new AuthApiException(
                422,
                'CRYPTO_PAYMENT_CONTRACT_MISMATCH',
                'The submitted crypto payment does not match the prepared TradeX payment contract.'
            );
        }

        $txHash = trim(
            (string) $data['tx_hash']
        );

        if (
            CryptoDeposit::query()
                ->where(
                    'tx_hash',
                    $txHash
                )
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'CRYPTO_TRANSACTION_ALREADY_SUBMITTED',
                'This crypto transaction has already been submitted.'
            );
        }

        $disk = (string) config(
            'tradex_payments.local_bank.proof_disk',
            'local'
        );

        $path = null;

        if (
            $proof
            instanceof UploadedFile
        ) {
            $path = $this->storeProof(
                $disk,
                'payments/crypto/'
                    .$user->uuid,
                $proof
            );
        }

        $used = false;

        try {
            $order = DB::transaction(
                function () use (
                    $order,
                    $user,
                    $data,
                    $txHash,
                    $coin,
                    $network,
                    $scale,
                    $submittedAmount,
                    $disk,
                    $path,
                    $request,
                    &$used
                ): PaymentOrder {
                    $locked =
                        PaymentOrder::query()
                            ->whereKey(
                                $order->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertPreparedState(
                        $locked,
                        PaymentOrder::PROVIDER_CRYPTO_MANUAL
                    );

                    $existing =
                        CryptoDeposit::query()
                            ->where(
                                'payment_order_id',
                                $locked->getKey()
                            )
                            ->first();

                    if (
                        $existing
                        instanceof CryptoDeposit
                    ) {
                        return $locked;
                    }

                    if (
                        CryptoDeposit::query()
                            ->where(
                                'tx_hash',
                                $txHash
                            )
                            ->lockForUpdate()
                            ->exists()
                    ) {
                        throw new AuthApiException(
                            409,
                            'CRYPTO_TRANSACTION_ALREADY_SUBMITTED',
                            'This crypto transaction has already been submitted.'
                        );
                    }

                    CryptoDeposit::query()
                        ->create([
                            'payment_order_id' =>
                                $locked->getKey(),
                            'user_id' =>
                                $user->getKey(),
                            'coin_symbol' =>
                                $coin,
                            'network' =>
                                $network,
                            'tx_hash' =>
                                $txHash,
                            'sender_address' =>
                                filled(
                                    $data[
                                        'sender_address'
                                    ]
                                    ?? null
                                )
                                    ? trim(
                                        (string) $data[
                                            'sender_address'
                                        ]
                                    )
                                    : null,
                            'amount_decimal' =>
                                $submittedAmount,
                            'amount_scale' =>
                                $scale,
                            'proof_disk' =>
                                $path !== null
                                    ? $disk
                                    : null,
                            'proof_path' =>
                                $path,
                            'status' =>
                                CryptoDeposit::STATUS_SUBMITTED,
                            'submitted_at' =>
                                now(),
                        ]);

                    $locked->forceFill([
                        'status' =>
                            PaymentOrder::STATUS_PENDING_REVIEW,
                    ])->save();

                    $used =
                        $path !== null;

                    $this->auditEvidence(
                        $user,
                        $locked,
                        'PAYMENT_CRYPTO_DEPOSIT_SUBMITTED',
                        $request,
                        [
                            'coin_symbol' =>
                                $coin,
                            'network' =>
                                $network,
                            'amount_decimal' =>
                                $submittedAmount,
                            'amount_scale' =>
                                $scale,
                            'proof_received' =>
                                $path !== null,
                        ]
                    );

                    return $locked;
                },
                3
            );
        } catch (
            Throwable $exception
        ) {
            if (
                $path !== null
            ) {
                Storage::disk(
                    $disk
                )->delete(
                    $path
                );
            }

            throw $exception;
        }

        if (
            $path !== null
            && ! $used
        ) {
            Storage::disk(
                $disk
            )->delete(
                $path
            );
        }

        return $this->presenter
            ->order(
                $order->fresh([
                    'cryptoDeposit',
                    'payment',
                ])
            );
    }

    public function manualQr(
        User $user,
        string $orderUuid
    ): array {
        $order = $this->orders->resolveOwned(
            $user,
            $orderUuid
        );

        $snapshot = (array) (
            $order->receiving_account_snapshot
            ?? []
        );

        if (
            ! in_array(
                $order->provider,
                [
                    PaymentOrder::PROVIDER_USDT_TRC20,
                    PaymentOrder::PROVIDER_CRYPTO_MANUAL,
                ],
                true
            )
        ) {
            throw new AuthApiException(
                404,
                'PAYMENT_QR_NOT_AVAILABLE',
                'A payment QR image is not available for this order.'
            );
        }

        $disk = trim(
            (string) ($snapshot['qr_disk'] ?? '')
        );

        $path = str_replace(
            '\\',
            '/',
            trim((string) ($snapshot['qr_path'] ?? ''))
        );

        $expectedPrefix =
            'payments/manual-orders/'.$order->uuid.'/';

        if (
            $disk === ''
            || $path === ''
            || ! str_starts_with($path, $expectedPrefix)
            || str_contains($path, '../')
            || ! Storage::disk($disk)->exists($path)
        ) {
            throw new AuthApiException(
                404,
                'PAYMENT_QR_NOT_AVAILABLE',
                'A payment QR image is not available for this order.'
            );
        }

        $extension = strtolower(
            (string) pathinfo($path, PATHINFO_EXTENSION)
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'name' =>
                'payment-qr-'
                .$order->manual_reference
                .'.'
                .$extension,
        ];
    }

    private function ownedPreparedOrder(
        User $user,
        string $uuid,
        string $provider
    ): PaymentOrder {
        $order = $this->orders->resolveOwned(
            $user,
            $uuid
        );

        if ($order->provider !== $provider) {
            throw new AuthApiException(
                409,
                'PAYMENT_ORDER_PROVIDER_MISMATCH',
                'This payment order does not accept this evidence type.'
            );
        }

        return $order;
    }

    private function assertPreparedState(
        PaymentOrder $order,
        string $provider
    ): void {
        if (
            $order->provider !== $provider
            || $order->status
                !== PaymentOrder::STATUS_AWAITING_USER_ACTION
            || blank($order->manual_reference)
            || blank($order->receiving_account_snapshot)
        ) {
            throw new AuthApiException(
                409,
                'PAYMENT_ORDER_NOT_AWAITING_EVIDENCE',
                'This manual payment order is not awaiting payment evidence.'
            );
        }

        if (
            $order->expires_at
            && $order->expires_at->isPast()
        ) {
            throw new AuthApiException(
                410,
                'PAYMENT_ORDER_EXPIRED',
                'This manual payment order has expired.'
            );
        }
    }

    private function assertExistingMatches(
        PaymentOrder $order,
        string $provider,
        PaymentReceivingAccount $account
    ): void {
        if (
            $order->provider !== $provider
            || (int) ($order->receiving_account_id ?? 0)
                !== (int) $account->getKey()
        ) {
            throw new AuthApiException(
                409,
                'IDEMPOTENCY_KEY_CONFLICT',
                'This idempotency key has already been used for a different payment operation.'
            );
        }
    }

    private function snapshot(
        PaymentReceivingAccount $account
    ): array {
        $snapshot = [
            'account_uuid' => $account->uuid,
            'type' => $account->type,
            'display_name' => $account->display_name,
            'country_code' => $account->country_code,
            'currency' => $account->currency,
            'amount_minor' =>
                (int) $account->tender_amount_minor,
            'amount_scale' =>
                (int) $account->amount_scale,
            'amount' =>
                $this->decimal->formatMinor(
                    (int) $account->tender_amount_minor,
                    (int) $account->amount_scale
                ),
            'instructions' => $account->instructions,
        ];

        if ($account->isBank()) {
            $snapshot += [
                'bank_name' => $account->bank_name,
                'branch' => $account->bank_branch,
                'account_holder' => $account->account_holder,
                'account_number' => $account->account_number,
            ];
        } else {
            $snapshot += [
                'coin_name' => $account->coin_name,
                'coin_symbol' => $account->coin_symbol,
                'network' => $account->network,
                'wallet_address' => $account->wallet_address,
                'qr_disk' => null,
                'qr_path' => null,
            ];
        }

        return $snapshot;
    }

    private function copyQr(
        PaymentReceivingAccount $account,
        PaymentOrder $order
    ): array {
        $disk = (string) $account->qr_disk;
        $source = (string) $account->qr_path;

        if (! Storage::disk($disk)->exists($source)) {
            throw new AuthApiException(
                503,
                'PAYMENT_QR_STORAGE_UNAVAILABLE',
                'The configured payment QR image is unavailable.'
            );
        }

        $extension = strtolower(
            (string) pathinfo($source, PATHINFO_EXTENSION)
        );

        $extension = preg_match(
            '/^(png|jpg|jpeg|webp)$/',
            $extension
        ) === 1
            ? $extension
            : 'bin';

        $target =
            'payments/manual-orders/'
            .$order->uuid
            .'/qr-'
            .Str::uuid()
            .'.'
            .$extension;

        if (! Storage::disk($disk)->copy($source, $target)) {
            throw new AuthApiException(
                503,
                'PAYMENT_QR_STORAGE_UNAVAILABLE',
                'The configured payment QR image could not be prepared.'
            );
        }

        return [
            'disk' => $disk,
            'path' => $target,
        ];
    }

    private function uniqueReference(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference =
                'TXP-'
                .Str::upper(Str::random(12));

            if (
                ! PaymentOrder::query()
                    ->where('manual_reference', $reference)
                    ->exists()
            ) {
                return $reference;
            }
        }

        throw new AuthApiException(
            503,
            'PAYMENT_REFERENCE_UNAVAILABLE',
            'A TradeX payment reference could not be generated safely.'
        );
    }

    private function storeProof(
        string $disk,
        string $directory,
        UploadedFile $proof
    ): string {
        $extension = strtolower(
            (string) (
                $proof->guessExtension()
                ?: $proof->getClientOriginalExtension()
                ?: 'bin'
            )
        );

        $path =
            trim($directory, '/')
            .'/'
            .Str::uuid()
            .'.'
            .$extension;

        $stored = Storage::disk($disk)->putFileAs(
            dirname($path),
            $proof,
            basename($path)
        );

        if (! $stored) {
            throw new AuthApiException(
                503,
                'PAYMENT_PROOF_STORAGE_FAILED',
                'The payment proof could not be stored securely.'
            );
        }

        return $path;
    }

    private function assertFeatureEnabled(
        PaymentReceivingAccount $account
    ): void {
        $enabled = $account->isBank()
            ? (bool) config(
                'tradex_payments.local_bank.enabled',
                true
            )
            : (
                $account->isUsdtTrc20()
                    ? (bool) config(
                        'tradex_payments.usdt_trc20.enabled',
                        true
                    )
                    : (bool) config(
                        'tradex_payments.crypto_manual.enabled',
                        true
                    )
            );

        if (! $enabled) {
            throw new AuthApiException(
                422,
                'PAYMENT_METHOD_UNAVAILABLE',
                'This payment method is not currently available.'
            );
        }
    }

    private function auditEvidence(
        User $user,
        PaymentOrder $order,
        string $action,
        Request $request,
        array $extra = []
    ): void {
        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: $action,
            module: 'PAYMENTS',
            targetType: 'PAYMENT_ORDER',
            targetId: $order->getKey(),
            newValues: array_merge(
                [
                    'order_uuid' => $order->uuid,
                    'manual_reference' =>
                        $order->manual_reference,
                    'provider' => $order->provider,
                    'currency' => $order->currency,
                    'amount_minor' =>
                        (int) $order->amount_minor,
                    'status' =>
                        PaymentOrder::STATUS_PENDING_REVIEW,
                ],
                $extra
            ),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
