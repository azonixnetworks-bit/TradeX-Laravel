<?php

namespace Tests\Feature\Admin25;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProductManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_admin_can_list_but_cannot_edit_or_update_product(): void
    {
        $admin =
            $this->adminWithPermissions([
                'products.view',
            ]);

        $product =
            Product::query()
                ->where(
                    'code',
                    Product::CODE_EDUCATION_PREMIUM
                )
                ->firstOrFail();

        $this->actingAs(
            $admin,
            'admin'
        );

        $this->get(
            route(
                'admin.products.index'
            )
        )->assertOk();

        $this->get(
            route(
                'admin.products.edit',
                $product
            )
        )->assertForbidden();

        $this->patch(
            route(
                'admin.products.update',
                $product
            ),
            [
                'name' =>
                    'Unauthorized Change',
                'description' =>
                    'Must not persist.',
                'amount_minor' =>
                    9900,
                'is_active' =>
                    1,
            ]
        )->assertForbidden();

        $product->refresh();

        $this->assertSame(
            7900,
            $product->amount_minor
        );
    }

    public function test_manage_admin_can_change_future_price_and_activity_but_immutable_contract_fields_remain(): void
    {
        $admin =
            $this->adminWithPermissions([
                'products.view',
                'products.manage',
            ]);

        $product =
            Product::query()
                ->where(
                    'code',
                    Product::CODE_EDUCATION_PREMIUM
                )
                ->firstOrFail();

        $originalCode =
            $product->code;
        $originalBilling =
            $product->billing_type;
        $originalCurrency =
            $product->currency;

        $this->actingAs(
            $admin,
            'admin'
        );

        $this->patch(
            route(
                'admin.products.update',
                $product
            ),
            [
                'name' =>
                    'TradeX Education Premium',
                'description' =>
                    'Updated commercial copy.',
                'amount_minor' =>
                    8300,
                'is_active' =>
                    0,
            ]
        )
            ->assertRedirect(
                route(
                    'admin.products.index'
                )
            );

        $product->refresh();

        $this->assertSame(
            8300,
            $product->amount_minor
        );
        $this->assertFalse(
            $product->is_active
        );
        $this->assertSame(
            $originalCode,
            $product->code
        );
        $this->assertSame(
            $originalBilling,
            $product->billing_type
        );
        $this->assertSame(
            $originalCurrency,
            $product->currency
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'ADMIN',
                'actor_id' =>
                    $admin->getKey(),
                'action' =>
                    'PRODUCT_CATALOG_UPDATED',
                'module' =>
                    'PRODUCTS',
                'target_type' =>
                    'PRODUCT',
                'target_id' =>
                    $product->getKey(),
            ]
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),
                'name' =>
                    'Product Test Admin',
                'email' =>
                    'product-admin-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@tradex.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'TestingPassword123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'PRODUCT_TEST_'
                    .Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Product Test Role',
                'description' =>
                    'ADMIN-25 product test role.',
                'is_system' =>
                    false,
                'is_active' =>
                    true,
            ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->updateOrCreate(
                        [
                            'code' =>
                                $code,
                        ],
                        [
                            'name' =>
                                $code,
                            'module' =>
                                'PRODUCTS',
                            'description' =>
                                null,
                        ]
                    );

            $permissionIds[] =
                $permission->getKey();
        }

        $role->permissions()
            ->sync(
                $permissionIds
            );

        $admin->roles()
            ->sync([
                $role->getKey(),
            ]);

        return $admin->fresh(
            'roles.permissions'
        );
    }
}
