<?php

namespace Tests\Feature\Api\Payments;

use App\Models\CryptoDeposit;
use App\Models\PaymentOrder;
use App\Models\PaymentReceivingAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class GenericCryptoManualPaymentApiTest
    extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_user_can_prepare_btc_order_and_submit_exact_tx_for_review_without_premium(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
            'account_type' => 'FREE',
        ]);

        $account =
            PaymentReceivingAccount::query()
                ->create([
                    'type' => 'CRYPTO',
                    'display_name' =>
                        'Bitcoin Main',
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'BTC',
                    'tender_amount_minor' =>
                        1234567,
                    'tender_amount_decimal' =>
                        '0.01234567',
                    'amount_scale' =>
                        8,
                    'coin_name' =>
                        'Bitcoin',
                    'coin_symbol' =>
                        'BTC',
                    'network' =>
                        'BITCOIN',
                    'wallet_address' =>
                        'bc1qtradexmanualwallet123456',
                    'instructions' =>
                        'Send only BTC on Bitcoin network.',
                    'is_active' =>
                        true,
                    'display_order' =>
                        1,
                ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $methods =
            $this->withToken(
                $token
            )
                ->getJson(
                    '/api/v1/payments/methods'
                )
                ->assertOk();

        $this->assertContains(
            PaymentOrder::PROVIDER_CRYPTO_MANUAL,
            collect(
                $methods->json(
                    'data.methods'
                )
            )
                ->pluck(
                    'code'
                )
                ->all()
        );

        $this->withToken($token)
            ->getJson(
                '/api/v1/payments/manual-accounts'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.accounts.0.uuid',
                $account->uuid
            )
            ->assertJsonPath(
                'data.accounts.0.tender.amount',
                '0.01234567'
            )
            ->assertJsonPath(
                'data.accounts.0.tender.scale',
                8
            );

        $prepared =
            $this->withToken($token)
                ->withHeader(
                    'Idempotency-Key',
                    'admin23b2-btc-prepare-0001'
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
                    PaymentOrder::PROVIDER_CRYPTO_MANUAL
                )
                ->assertJsonPath(
                    'data.status',
                    PaymentOrder::STATUS_AWAITING_USER_ACTION
                )
                ->assertJsonPath(
                    'data.manual_payment.coin_symbol',
                    'BTC'
                )
                ->assertJsonPath(
                    'data.manual_payment.network',
                    'BITCOIN'
                )
                ->assertJsonPath(
                    'data.manual_payment.amount.amount',
                    '0.01234567'
                );

        $orderUuid =
            (string) $prepared
                ->json(
                    'data.uuid'
                );

        $txHash =
            'btc_tx_'
            .str_repeat(
                'a',
                48
            );

        $this->withToken($token)
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        $txHash,
                    'amount' =>
                        '0.01234567',
                    'sender_address' =>
                        'bc1qsendertestwallet123456',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'btc-proof.pdf',
                                64,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.manual_submission.type',
                'CRYPTO_MANUAL'
            )
            ->assertJsonPath(
                'data.manual_submission.amount',
                '0.01234567'
            );

        $deposit =
            CryptoDeposit::query()
                ->where(
                    'tx_hash',
                    $txHash
                )
                ->firstOrFail();

        $this->assertSame(
            'BTC',
            $deposit
                ->coin_symbol
        );

        $this->assertSame(
            8,
            $deposit
                ->amount_scale
        );

        Storage::disk(
            $deposit->proof_disk
        )->assertExists(
            $deposit->proof_path
        );

        $this->assertSame(
            'FREE',
            $user->fresh()
                ->account_type
        );

        $this->assertDatabaseCount(
            'payments',
            0
        );
    }

    public function test_generic_crypto_rejects_wrong_amount_and_duplicate_tx(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user =
            $this->createApiUser([
                'country_code' =>
                    'IN',
                'account_type' =>
                    'FREE',
            ]);

        $account =
            PaymentReceivingAccount::query()
                ->create([
                    'type' =>
                        'CRYPTO',
                    'display_name' =>
                        'USDC Polygon',
                    'country_code' =>
                        'IN',
                    'currency' =>
                        'USDC',
                    'tender_amount_minor' =>
                        79000000,
                    'tender_amount_decimal' =>
                        '79.000000',
                    'amount_scale' =>
                        6,
                    'coin_name' =>
                        'USD Coin',
                    'coin_symbol' =>
                        'USDC',
                    'network' =>
                        'POLYGON',
                    'wallet_address' =>
                        '0xTradexUsdcWallet123456789',
                    'is_active' =>
                        true,
                    'display_order' =>
                        1,
                ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $first =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23b2-usdc-prepare-001'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $account->uuid,
                    ]
                )
                ->assertCreated();

        $firstUuid =
            (string) $first
                ->json(
                    'data.uuid'
                );

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$firstUuid
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        'polygon_tx_wrong_amount_123456',
                    'amount' =>
                        '78.999999',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'wrong-amount.pdf',
                                32,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertStatus(
                422
            );

        $txHash =
            'polygon_tx_duplicate_test_123456789';

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$firstUuid
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        $txHash,
                    'amount' =>
                        '79.000000',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'first-usdc.pdf',
                                32,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertOk();

        $second =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23b2-usdc-prepare-002'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $account->uuid,
                    ]
                )
                ->assertCreated();

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$second->json(
                        'data.uuid'
                    )
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        $txHash,
                    'amount' =>
                        '79.000000',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'duplicate-usdc.pdf',
                                32,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertStatus(
                409
            );
    }

    public function test_same_transaction_identifier_can_be_used_on_different_network_contracts(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user =
            $this->createApiUser([
                'country_code' =>
                    'LK',
                'account_type' =>
                    'FREE',
            ]);

        $polygon =
            PaymentReceivingAccount::query()
                ->create([
                    'type' =>
                        'CRYPTO',
                    'display_name' =>
                        'USDC Polygon',
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'USDC',
                    'tender_amount_minor' =>
                        1000000,
                    'tender_amount_decimal' =>
                        '1.000000',
                    'amount_scale' =>
                        6,
                    'coin_name' =>
                        'USD Coin',
                    'coin_symbol' =>
                        'USDC',
                    'network' =>
                        'POLYGON',
                    'wallet_address' =>
                        '0xPolygonTradeXWallet123456',
                    'is_active' =>
                        true,
                    'display_order' =>
                        1,
                ]);

        $arbitrum =
            PaymentReceivingAccount::query()
                ->create([
                    'type' =>
                        'CRYPTO',
                    'display_name' =>
                        'USDC Arbitrum',
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'USDC',
                    'tender_amount_minor' =>
                        1000000,
                    'tender_amount_decimal' =>
                        '1.000000',
                    'amount_scale' =>
                        6,
                    'coin_name' =>
                        'USD Coin',
                    'coin_symbol' =>
                        'USDC',
                    'network' =>
                        'ARBITRUM',
                    'wallet_address' =>
                        '0xArbitrumTradeXWallet123456',
                    'is_active' =>
                        true,
                    'display_order' =>
                        2,
                ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $polygonOrder =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23c-polygon-001'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $polygon->uuid,
                    ]
                )
                ->assertCreated();

        $arbitrumOrder =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23c-arbitrum-001'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $arbitrum->uuid,
                    ]
                )
                ->assertCreated();

        $sharedIdentifier =
            'shared_cross_network_tx_123456789';

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$polygonOrder->json(
                        'data.uuid'
                    )
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        $sharedIdentifier,
                    'amount' =>
                        '1.000000',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'polygon-proof.pdf',
                                32,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertOk();

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$arbitrumOrder->json(
                        'data.uuid'
                    )
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        $sharedIdentifier,
                    'amount' =>
                        '1.000000',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'arbitrum-proof.pdf',
                                32,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertOk();

        $this->assertSame(
            2,
            CryptoDeposit::query()
                ->where(
                    'tx_hash',
                    $sharedIdentifier
                )
                ->count()
        );

        $this->assertSame(
            2,
            CryptoDeposit::query()
                ->where(
                    'tx_hash',
                    $sharedIdentifier
                )
                ->pluck(
                    'tx_hash_digest'
                )
                ->unique()
                ->count()
        );
    }

}
