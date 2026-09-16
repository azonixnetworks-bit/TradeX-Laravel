<?php

namespace App\Services\Products;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\User;
use App\Services\Entitlements\GenericProductEntitlementService;
use App\Services\Payments\PaymentMethodService;
use App\Services\Payments\PaymentMoney;
use App\Services\Payments\PaymentProviderRuntimeGate;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductPurchaseEligibilityService
{
    public function __construct(
        private readonly PaymentMethodService $methods,
        private readonly PaymentProviderRuntimeGate $runtimeGate,
        private readonly PaymentMoney $money,
        private readonly GenericProductEntitlementService $genericEntitlements
    ) {
    }

    public function resolve(?string $productUuid): Product
    {
        if (blank($productUuid)) {
            $product = Product::query()
                ->where('code', Product::CODE_EDUCATION_PREMIUM)
                ->first();
        } else {
            $product = Product::query()
                ->where('uuid', trim((string) $productUuid))
                ->first();
        }

        if (! $product instanceof Product) {
            throw (new ModelNotFoundException())
                ->setModel(Product::class);
        }

        return $product;
    }

    public function assertPayPal(
        User $user,
        Product $product
    ): array {
        if ($product->isEducationPremium()) {
            return $this->methods->assertAvailable(
                $user,
                PaymentOrder::PROVIDER_PAYPAL
            );
        }

        if (
            ! $product->is_active
            || $product->billing_type
                !== Product::BILLING_ONE_TIME_LIFETIME
            || strtoupper((string) $product->currency) !== 'USD'
            || (int) $product->amount_minor <= 0
        ) {
            throw new AuthApiException(
                422,
                'PRODUCT_NOT_PURCHASABLE',
                'This product is not currently available for purchase.'
            );
        }

        if ($this->genericEntitlements->hasActive(
            $user,
            (string) $product->code
        )) {
            throw new AuthApiException(
                409,
                'PRODUCT_ALREADY_OWNED',
                'This account already has an active lifetime entitlement for the selected product.'
            );
        }

        $country = strtoupper(trim(
            (string) $user->country_code
        ));

        $this->runtimeGate->assertAvailable(
            PaymentOrder::PROVIDER_PAYPAL,
            $country
        );

        return [
            'code' => PaymentOrder::PROVIDER_PAYPAL,
            'label' => 'PayPal',
            'mode' => 'REDIRECT',
            'amount' => $this->money->present(
                (int) $product->amount_minor,
                (string) $product->currency
            ),
        ];
    }
}
