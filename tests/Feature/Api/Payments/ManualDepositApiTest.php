<?php

namespace Tests\Feature\Api\Payments;

use App\Models\BankDeposit;
use App\Models\PaymentOrder;
use App\Models\UsdtDeposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ManualDepositApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_sri_lanka_bank_submission_is_private_and_stays_pending_review(): void
    {
        Storage::fake('local');

        $this->configureLocalBank();

        $user = $this->createApiUser([
            'country_code' =>
                'LK',

            'account_type' =>
                'FREE',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-bank-idempotency-0001'
            )
            ->post(
                '/api/v1/payments/bank-deposits',
                [
                    'depositor_name' =>
                        'TradeX Student',

                    'bank_reference' =>
                        'BANK-REF-API12',

                    'deposit_date' =>
                        now()->toDateString(),

                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'deposit-proof.jpg',
                                64,
                                'image/jpeg'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_LOCAL_BANK
            )
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.manual_submission.type',
                'LOCAL_BANK'
            )
            ->assertJsonPath(
                'data.manual_submission.status',
                BankDeposit::STATUS_SUBMITTED
            )
            ->assertJsonPath(
                'data.manual_submission.proof_received',
                true
            )
            ->assertJsonPath(
                'data.payment',
                null
            );

        $deposit =
            BankDeposit::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->firstOrFail();

        Storage::disk(
            $deposit->proof_disk
        )->assertExists(
            $deposit->proof_path
        );

        $user->refresh();

        $this->assertSame(
            'FREE',
            $user->account_type
        );

        $this->assertNull(
            $user->premium_activated_at
        );

        $payload = json_encode(
            $response->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            (string) $deposit->proof_path,
            $payload
        );

        $this->assertStringNotContainsString(
            'BANK-REF-API12',
            $payload
        );
    }

    public function test_india_usdt_submission_requires_exact_amount_and_duplicate_transaction_is_blocked(): void
    {
        $this->configureUsdt();

        $user = $this->createApiUser([
            'country_code' =>
                'IN',

            'account_type' =>
                'FREE',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $txHash = str_repeat(
            'a',
            64
        );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-usdt-idempotency-0001'
            )
            ->postJson(
                '/api/v1/payments/usdt-deposits',
                [
                    'tx_hash' =>
                        $txHash,

                    'amount' =>
                        '79.000000',

                    'sender_address' =>
                        'TUserSenderAddressAPI12',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_USDT_TRC20
            )
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.manual_submission.type',
                'USDT_TRC20'
            )
            ->assertJsonPath(
                'data.manual_submission.network',
                UsdtDeposit::NETWORK_TRC20
            )
            ->assertJsonPath(
                'data.manual_submission.tx_hash',
                $txHash
            )
            ->assertJsonPath(
                'data.payment',
                null
            );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-usdt-idempotency-0002'
            )
            ->postJson(
                '/api/v1/payments/usdt-deposits',
                [
                    'tx_hash' =>
                        $txHash,

                    'amount' =>
                        '79.000000',
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'error.code',
                'USDT_TRANSACTION_ALREADY_SUBMITTED'
            );

        $user->refresh();

        $this->assertSame(
            'FREE',
            $user->account_type
        );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_usdt_submission_rejects_wrong_amount_before_review(): void
    {
        $this->configureUsdt();

        $user = $this->createApiUser([
            'country_code' =>
                'IN',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'api12-usdt-idempotency-0003'
            )
            ->postJson(
                '/api/v1/payments/usdt-deposits',
                [
                    'tx_hash' =>
                        str_repeat(
                            'b',
                            64
                        ),

                    'amount' =>
                        '78.000000',
                ]
            )
            ->assertStatus(
                422
            )
            ->assertJsonPath(
                'error.code',
                'USDT_AMOUNT_MISMATCH'
            );

        $this->assertDatabaseCount(
            'payment_orders',
            0
        );
    }

    private function configureLocalBank(): void
    {
        config()->set(
            'tradex_payments.local_bank.enabled',
            true
        );

        config()->set(
            'tradex_payments.local_bank.countries',
            ['LK']
        );

        config()->set(
            'tradex_payments.local_bank.bank_name',
            'TradeX Test Bank'
        );

        config()->set(
            'tradex_payments.local_bank.branch',
            'Colombo'
        );

        config()->set(
            'tradex_payments.local_bank.account_holder',
            'TradeX'
        );

        config()->set(
            'tradex_payments.local_bank.account_number',
            '00123456789'
        );

        config()->set(
            'tradex_payments.local_bank.proof_disk',
            'local'
        );

        config()->set(
            'tradex_payments.product.education_premium.local_bank.amount_minor',
            2500000
        );
    }

    private function configureUsdt(): void
    {
        config()->set(
            'tradex_payments.usdt_trc20.enabled',
            true
        );

        config()->set(
            'tradex_payments.usdt_trc20.countries',
            ['IN']
        );

        config()->set(
            'tradex_payments.usdt_trc20.wallet_address',
            'TTradeXMerchantWallet123456789'
        );

        config()->set(
            'tradex_payments.product.education_premium.usdt_trc20.amount_minor',
            79000000
        );
    }
}
