<?php

namespace App\Services\Products;

use App\Models\Product;
use App\Models\User;
use App\Services\Entitlements\EducationPremiumEntitlementService;
use App\Services\Payments\PaymentMoney;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductCatalogService
{
    public function __construct(
        private readonly PaymentMoney $money,
        private readonly EducationPremiumEntitlementService
            $educationPremium
    ) {
    }

    public function educationPremium(): Product
    {
        $product = Product::query()
            ->where(
                'code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->first();

        if (! $product instanceof Product) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                Product::class,
                [
                    Product::CODE_EDUCATION_PREMIUM,
                ]
            );
        }

        return $product;
    }

    public function present(
        Product $product,
        User $user
    ): array {
        $activeEntitlement =
            $product->isEducationPremium()
                && $this->educationPremium
                    ->hasActive($user);

        return [
            'uuid' =>
                $product->uuid,
            'code' =>
                $product->code,
            'name' =>
                $product->name,
            'description' =>
                $product->description,
            'billing_type' =>
                $product->billing_type,
            'price' =>
                $this->money->present(
                    (int) $product
                        ->amount_minor,
                    (string) $product
                        ->currency
                ),
            'is_active' =>
                (bool) $product
                    ->is_active,
            'entitlement' => [
                'active' =>
                    $activeEntitlement,
                'lifetime' =>
                    $product
                        ->billing_type
                        === Product::BILLING_ONE_TIME_LIFETIME,
            ],
            'purchase_allowed' =>
                (bool) $product
                    ->is_active
                && ! $activeEntitlement,
            'payment_methods_endpoint' =>
                '/api/v1/payments/methods',
        ];
    }
}
