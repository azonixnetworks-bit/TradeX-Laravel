<?php

namespace Tests\Feature\Admin\Payments;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\PaymentReceivingAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReceivingAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_payment_account_management(): void
    {
        $this->get(
            route('admin.payments.accounts.index')
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_manage_permission_is_required_for_receiving_account_create(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.payments.accounts.receiving.store'),
                $this->bankPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'payment_receiving_accounts',
            0
        );
    }

    public function test_payment_accounts_page_exposes_explicit_owner_receiving_account_actions(): void
    {
        $admin = $this->adminWithPermissions([
            'payment_accounts.manage',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(
                route('admin.payments.accounts.index')
            );

        $response
            ->assertOk()
            ->assertSee('Add Bank Account')
            ->assertSee('Add USDT Wallet')
            ->assertSee('Add Other Crypto Wallet')
            ->assertSee('Save Bank Account')
            ->assertSee('Save USDT Wallet')
            ->assertSee('Save Other Crypto Wallet')
            ->assertSee('USDT / TRC20')
            ->assertSee('id="add-bank-account"', false)
            ->assertSee('id="add-usdt-wallet"', false)
            ->assertSee('id="add-other-crypto-wallet"', false)
            ->assertSee('name="bank_name"', false)
            ->assertSee('name="account_number"', false)
            ->assertSee('name="wallet_address"', false)
            ->assertSee('name="qr_image"', false)
            ->assertSee('name="coin_symbol" value="USDT"', false)
            ->assertSee('name="network" value="TRC20"', false);
    }

    public function test_manager_can_add_multiple_bank_accounts_with_sensitive_values_encrypted_at_rest(): void
    {
        $admin = $this->adminWithPermissions([
            'payment_accounts.manage',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.payments.accounts.receiving.store'),
                $this->bankPayload([
                    'display_name' =>
                        'Commercial Bank Main',
                ])
            )
            ->assertRedirect(
                route('admin.payments.accounts.index')
            );

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.payments.accounts.receiving.store'),
                $this->bankPayload([
                    'display_name' =>
                        'Sampath Bank Main',
                    'bank_name' =>
                        'Sampath Bank',
                    'account_number' =>
                        '009876543210',
                    'display_order' => 20,
                ])
            )
            ->assertRedirect(
                route('admin.payments.accounts.index')
            );

        $account = PaymentReceivingAccount::query()
            ->where(
                'display_name',
                'Commercial Bank Main'
            )
            ->firstOrFail();

        $this->assertSame(
            2500000,
            $account->tender_amount_minor
        );

        $this->assertSame(
            '25000.00',
            $account->tender_amount_decimal
        );

        $raw = DB::table(
            'payment_receiving_accounts'
        )
            ->where('id', $account->id)
            ->value('account_number');

        $this->assertNotSame(
            '001234567890',
            $raw
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'PAYMENT_RECEIVING_ACCOUNT_CREATED',
                'module' => 'PAYMENTS',
                'target_id' => $account->id,
            ]
        );
    }

    public function test_manager_can_add_crypto_wallet_with_private_qr_and_update_to_inactive(): void
    {
        Storage::fake('local');

        $admin = $this->adminWithPermissions([
            'payment_accounts.manage',
        ]);

        $payload = $this->cryptoPayload();

        $payload['qr_image'] =
            UploadedFile::fake()
                ->createWithContent(
                    'usdt-qr.png',
                    base64_decode(
                        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                        true
                    )
                );

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.payments.accounts.receiving.store'),
                $payload
            )
            ->assertRedirect(
                route('admin.payments.accounts.index')
            );

        $account = PaymentReceivingAccount::query()
            ->where(
                'type',
                PaymentReceivingAccount::TYPE_CRYPTO
            )
            ->firstOrFail();

        $this->assertSame(
            79000000,
            $account->tender_amount_minor
        );

        $this->assertSame(
            '79.000000',
            $account->tender_amount_decimal
        );

        Storage::disk($account->qr_disk)
            ->assertExists($account->qr_path);

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.payments.accounts.receiving.qr',
                    $account
                )
            )
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.payments.accounts.receiving.update',
                    $account
                ),
                $this->cryptoPayload([
                    'is_active' => '0',
                    'display_order' => 50,
                ])
            )
            ->assertRedirect(
                route('admin.payments.accounts.index')
            );

        $this->assertFalse(
            $account->fresh()->is_active
        );
    }

    public function test_manager_can_store_high_precision_generic_crypto_without_rounding(): void
    {
        $admin =
            $this->adminWithPermissions([
                'payment_accounts.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.payments.accounts.receiving.store'
                ),
                $this->cryptoPayload([
                    'display_name' =>
                        'ETH ERC20',
                    'currency' =>
                        'ETH',
                    'amount_scale' =>
                        18,
                    'tender_amount' =>
                        '0.025000000000000000',
                    'coin_name' =>
                        'Ethereum',
                    'coin_symbol' =>
                        'ETH',
                    'network' =>
                        'ERC20',
                    'wallet_address' =>
                        '0xTradeXEthWallet123456789',
                ])
            )
            ->assertRedirect(
                route(
                    'admin.payments.accounts.index'
                )
            );

        $account =
            PaymentReceivingAccount::query()
                ->where(
                    'coin_symbol',
                    'ETH'
                )
                ->firstOrFail();

        $this->assertSame(
            18,
            $account->amount_scale
        );

        $this->assertSame(
            '0.025000000000000000',
            $account->tender_amount_decimal
        );

        $this->assertNull(
            $account->tender_amount_minor
        );
    }

    public function test_invalid_bank_payload_is_rejected(): void
    {
        $admin = $this->adminWithPermissions([
            'payment_accounts.manage',
        ]);

        $payload = $this->bankPayload();

        unset(
            $payload['bank_name'],
            $payload['bank_branch'],
            $payload['account_holder'],
            $payload['account_number']
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.payments.accounts.receiving.store'),
                $payload
            )
            ->assertSessionHasErrors([
                'bank_name',
                'bank_branch',
                'account_holder',
                'account_number',
            ]);
    }

    public function test_receiving_accounts_have_no_delete_route(): void
    {
        $deleteRoutes = collect(
            Route::getRoutes()->getRoutes()
        )
            ->filter(
                fn ($route): bool =>
                    str_starts_with(
                        $route->getName() ?? '',
                        'admin.payments.accounts.receiving.'
                    )
            )
            ->filter(
                fn ($route): bool =>
                    in_array(
                        'DELETE',
                        $route->methods(),
                        true
                    )
            )
            ->count();

        $this->assertSame(0, $deleteRoutes);
    }

    private function bankPayload(
        array $overrides = []
    ): array {
        return array_merge([
            'type' => 'BANK',
            'display_name' => 'TradeX Bank',
            'country_code' => 'LK',
            'currency' => 'LKR',
            'amount_scale' => 2,
            'tender_amount' => '25000.00',
            'bank_name' => 'Commercial Bank',
            'bank_branch' => 'Colombo',
            'account_holder' => 'TradeX',
            'account_number' => '001234567890',
            'instructions' =>
                'Use the TradeX payment reference.',
            'is_active' => '1',
            'display_order' => 10,
        ], $overrides);
    }

    private function cryptoPayload(
        array $overrides = []
    ): array {
        return array_merge([
            'type' => 'CRYPTO',
            'display_name' => 'USDT TRC20',
            'country_code' => 'IN',
            'currency' => 'USDT',
            'amount_scale' => 6,
            'tender_amount' => '79.000000',
            'coin_name' => 'Tether',
            'coin_symbol' => 'USDT',
            'network' => 'TRC20',
            'wallet_address' =>
                'TTradeXWalletAddress123456789',
            'instructions' =>
                'Send only USDT on TRC20.',
            'is_active' => '1',
            'display_order' => 10,
        ], $overrides);
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Payment Accounts Test',
            'email' =>
                'admin-pay-accounts-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' =>
                'PAYACCT_'
                .strtoupper(Str::random(8)),
            'name' => 'Payment Accounts Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->firstOrCreate(
                        ['code' => $code],
                        [
                            'name' => $code,
                            'module' => 'PAYMENTS',
                        ]
                    );

            $role->permissions()->attach(
                $permission->id
            );
        }

        return $admin->fresh();
    }
}
