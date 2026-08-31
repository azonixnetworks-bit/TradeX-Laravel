<?php

namespace Tests\Feature\Api\Payments;

use App\Models\BankDeposit;
use App\Models\PaymentOrder;
use App\Models\PaymentReceivingAccount;
use App\Models\UsdtDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PreparedManualPaymentApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_user_can_prepare_bank_order_with_trade_reference_and_submit_receipt_for_review(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.local_bank.enabled',
            true
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
            'account_type' => 'FREE',
        ]);

        $account = PaymentReceivingAccount::query()->create([
            'type' => 'BANK',
            'display_name' => 'TradeX Commercial Bank',
            'country_code' => 'LK',
            'currency' => 'LKR',
            'tender_amount_minor' => 2500000,
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'account_holder' => 'TradeX',
            'account_number' => '001234567890',
            'instructions' => 'Use the TradeX reference.',
            'is_active' => true,
            'display_order' => 10,
        ]);

        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/payments/manual-accounts')
            ->assertOk()
            ->assertJsonPath(
                'data.accounts.0.uuid',
                $account->uuid
            );

        $prepared = $this->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'admin23b-bank-prepare-0001'
            )
            ->postJson(
                '/api/v1/payments/manual-orders',
                [
                    'receiving_account_uuid' =>
                        $account->uuid,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_LOCAL_BANK
            )
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_AWAITING_USER_ACTION
            )
            ->assertJsonPath(
                'data.manual_payment.type',
                'BANK'
            )
            ->assertJsonPath(
                'data.manual_payment.account_number',
                '001234567890'
            );

        $orderUuid =
            (string) $prepared->json('data.uuid');

        $reference =
            (string) $prepared->json(
                'data.manual_payment.reference'
            );

        $this->assertMatchesRegularExpression(
            '/^TXP-[A-Z0-9]{12}$/',
            $reference
        );

        $this->withToken($token)
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/bank-evidence',
                [
                    'depositor_name' =>
                        'TradeX Student',
                    'bank_reference' =>
                        'BANK-TRANSFER-23B',
                    'deposit_date' =>
                        now()->toDateString(),
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'receipt.pdf',
                                64,
                                'application/pdf'
                            ),
                ],
                ['Accept' => 'application/json']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.manual_submission.type',
                'LOCAL_BANK'
            );

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->firstOrFail();

        $this->assertSame(
            $account->id,
            $order->receiving_account_id
        );

        $this->assertSame(
            $reference,
            $order->manual_reference
        );

        $this->assertSame(
            '001234567890',
            data_get(
                $order->receiving_account_snapshot,
                'account_number'
            )
        );

        $this->assertDatabaseHas(
            'bank_deposits',
            [
                'payment_order_id' => $order->id,
                'status' =>
                    BankDeposit::STATUS_SUBMITTED,
            ]
        );

        $this->assertSame(
            'FREE',
            $user->fresh()->account_type
        );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_user_can_prepare_usdt_order_receive_private_qr_and_submit_txid_with_optional_proof(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.usdt_trc20.enabled',
            true
        );

        $user = $this->createApiUser([
            'country_code' => 'IN',
            'account_type' => 'FREE',
        ]);

        $account = PaymentReceivingAccount::query()->create([
            'type' => 'CRYPTO',
            'display_name' => 'TradeX USDT TRC20',
            'country_code' => 'IN',
            'currency' => 'USDT',
            'tender_amount_minor' => 79000000,
            'coin_name' => 'Tether',
            'coin_symbol' => 'USDT',
            'network' => 'TRC20',
            'wallet_address' =>
                'TTradeXWalletAddress123456789',
            'qr_disk' => 'local',
            'qr_path' =>
                'payments/receiving-accounts/tmp/qr.png',
            'instructions' =>
                'Send USDT only on TRC20.',
            'is_active' => true,
            'display_order' => 10,
        ]);

        $account->forceFill([
            'qr_path' =>
                'payments/receiving-accounts/'
                .$account->uuid
                .'/qr.png',
        ])->save();

        Storage::disk('local')->put(
            $account->qr_path,
            'fake-qr-bytes'
        );

        $token = $this->issueBoundToken($user);

        $prepared = $this->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'admin23b-usdt-prepare-0001'
            )
            ->postJson(
                '/api/v1/payments/manual-orders',
                [
                    'receiving_account_uuid' =>
                        $account->uuid,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_USDT_TRC20
            )
            ->assertJsonPath(
                'data.manual_payment.wallet_address',
                'TTradeXWalletAddress123456789'
            )
            ->assertJsonPath(
                'data.manual_payment.qr_available',
                true
            );

        $orderUuid =
            (string) $prepared->json('data.uuid');

        $this->withToken($token)
            ->get(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/qr'
            )
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $txHash = str_repeat('a', 64);

        $this->withToken($token)
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/usdt-evidence',
                [
                    'tx_hash' => $txHash,
                    'amount' => '79.000000',
                    'sender_address' =>
                        'TStudentSenderAddress23B',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'crypto-proof.pdf',
                                64,
                                'application/pdf'
                            ),
                ],
                ['Accept' => 'application/json']
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.manual_submission.type',
                'USDT_TRC20'
            )
            ->assertJsonPath(
                'data.manual_submission.proof_received',
                true
            );

        $deposit = UsdtDeposit::query()
            ->where('tx_hash', $txHash)
            ->firstOrFail();

        Storage::disk($deposit->proof_disk)
            ->assertExists($deposit->proof_path);

        $this->assertSame(
            'FREE',
            $user->fresh()->account_type
        );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_manual_account_catalog_is_country_scoped_and_includes_generic_crypto_in_b2(): void
    {
        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        PaymentReceivingAccount::query()->create([
            'type' => 'BANK',
            'display_name' => 'LK Bank',
            'country_code' => 'LK',
            'currency' => 'LKR',
            'tender_amount_minor' => 2500000,
            'amount_scale' => 2,
            'bank_name' => 'Bank',
            'bank_branch' => 'Colombo',
            'account_holder' => 'TradeX',
            'account_number' => '12345678',
            'is_active' => true,
            'display_order' => 1,
        ]);

        PaymentReceivingAccount::query()->create([
            'type' => 'CRYPTO',
            'display_name' => 'BTC Main',
            'country_code' => 'LK',
            'currency' => 'BTC',
            'tender_amount_minor' => 100000,
            'amount_scale' => 8,
            'coin_name' => 'Bitcoin',
            'coin_symbol' => 'BTC',
            'network' => 'BITCOIN',
            'wallet_address' =>
                'bc1qtradextestwallet',
            'is_active' => true,
            'display_order' => 2,
        ]);

        PaymentReceivingAccount::query()->create([
            'type' => 'BANK',
            'display_name' => 'India Bank',
            'country_code' => 'IN',
            'currency' => 'INR',
            'tender_amount_minor' => 100000,
            'amount_scale' => 2,
            'bank_name' => 'India Bank',
            'bank_branch' => 'Chennai',
            'account_holder' => 'TradeX',
            'account_number' => '87654321',
            'is_active' => true,
            'display_order' => 3,
        ]);

        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/payments/manual-accounts')
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.accounts'
            )
            ->assertJsonPath(
                'data.accounts.0.display_name',
                'LK Bank'
            )
            ->assertJsonPath(
                'data.accounts.1.display_name',
                'BTC Main'
            )
            ->assertJsonPath(
                'data.accounts.1.coin',
                'BTC'
            )
            ->assertJsonPath(
                'data.accounts.1.network',
                'BITCOIN'
            )
            ->assertJsonPath(
                'data.accounts.1.tender.amount',
                '0.00100000'
            );
    }
}
