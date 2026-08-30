<?php

namespace Tests\Feature\Admin\Products;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_product_catalog(): void
    {
        $this->get(route('admin.products.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_products_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_view_only_admin_can_browse_catalog_but_cannot_edit(): void
    {
        $admin = $this->adminWithPermissions(['products.view']);
        $product = $this->educationProduct();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Product Catalog')
            ->assertDontSee(route('admin.products.edit', $product), false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.edit', $product))
            ->assertForbidden();
    }

    public function test_manage_admin_can_update_mutable_catalog_fields_without_changing_identity(): void
    {
        $admin = $this->adminWithPermissions([
            'products.view',
            'products.manage',
        ]);
        $product = $this->educationProduct();

        $originalCode = $product->code;
        $originalBilling = $product->billing_type;
        $originalCurrency = $product->currency;

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.products.update', $product), [
                'name' => 'TradeX Education Premium Lifetime',
                'description' => 'Updated catalog description.',
                'amount_minor' => 7950,
                'is_active' => '1',
                'code' => 'MUST_NOT_CHANGE',
                'billing_type' => 'MONTHLY',
                'currency' => 'EUR',
            ])
            ->assertRedirect(route('admin.products.index'));

        $product->refresh();

        $this->assertSame('TradeX Education Premium Lifetime', $product->name);
        $this->assertSame('Updated catalog description.', $product->description);
        $this->assertSame(7950, $product->amount_minor);
        $this->assertTrue($product->is_active);
        $this->assertSame($originalCode, $product->code);
        $this->assertSame($originalBilling, $product->billing_type);
        $this->assertSame($originalCurrency, $product->currency);
        $this->assertSame($admin->id, $product->updated_by_admin_id);

        $audit = AuditLog::query()
            ->where('action', 'PRODUCT_CATALOG_UPDATED')
            ->where('target_type', 'PRODUCT')
            ->where('target_id', $product->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('PRODUCTS', $audit->module);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(7950, $audit->new_values['amount_minor']);
        $this->assertSame($originalCode, $audit->new_values['product_code']);
        $this->assertSame($originalBilling, $audit->new_values['billing_type']);
        $this->assertSame($originalCurrency, $audit->new_values['currency']);
    }

    public function test_manage_admin_can_deactivate_product_without_deleting_it(): void
    {
        $admin = $this->adminWithPermissions([
            'products.view',
            'products.manage',
        ]);
        $product = $this->educationProduct();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.products.update', $product), [
                'name' => $product->name,
                'description' => $product->description,
                'amount_minor' => $product->amount_minor,
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_manage_permission_is_required_to_update_product(): void
    {
        $admin = $this->adminWithPermissions(['products.view']);
        $product = $this->educationProduct();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.products.update', $product), [
                'name' => 'Unauthorized change',
                'description' => null,
                'amount_minor' => 7900,
                'is_active' => '1',
            ])
            ->assertForbidden();
    }

    public function test_product_amount_must_remain_a_positive_minor_unit_integer(): void
    {
        $admin = $this->adminWithPermissions([
            'products.view',
            'products.manage',
        ]);
        $product = $this->educationProduct();

        $this->actingAs($admin, 'admin')
            ->from(route('admin.products.edit', $product))
            ->patch(route('admin.products.update', $product), [
                'name' => $product->name,
                'description' => $product->description,
                'amount_minor' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('amount_minor');

        $this->assertSame(7900, $product->fresh()->amount_minor);
    }

    private function educationProduct(): Product
    {
        return Product::query()->firstOrCreate(
            ['code' => Product::CODE_EDUCATION_PREMIUM],
            [
                'name' => 'TradeX Education Premium',
                'description' => 'One-time lifetime premium education access.',
                'billing_type' => Product::BILLING_ONE_TIME_LIFETIME,
                'currency' => 'USD',
                'amount_minor' => 7900,
                'is_active' => true,
            ]
        );
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Product Test',
            'email' => 'admin-product-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'PRODUCT_TEST_'.Str::upper(Str::random(8)),
            'name' => 'Product Test Role',
            'description' => 'Product Catalog feature test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];
        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'PRODUCTS',
                    'description' => null,
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
