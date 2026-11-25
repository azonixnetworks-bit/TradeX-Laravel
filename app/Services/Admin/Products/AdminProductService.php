<?php

namespace App\Services\Admin\Products;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\PaymentReceivingAccount;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductService
{
    public function create(
        AdminUser $admin,
        array $data,
        Request $request
    ): Product {
        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request
            ): Product {
                $product = Product::query()->create([
                    'code' => strtoupper(
                        trim((string) $data['code'])
                    ),
                    'name' => trim(
                        (string) $data['name']
                    ),
                    'description' => filled(
                        $data['description'] ?? null
                    )
                        ? trim(
                            (string) $data['description']
                        )
                        : null,
                    'billing_type' => strtoupper(
                        trim((string) $data['billing_type'])
                    ),
                    'currency' => strtoupper(
                        trim((string) $data['currency'])
                    ),
                    'amount_minor' =>
                        (int) $data['amount_minor'],
                    'is_active' =>
                        (bool) $data['is_active'],
                    'created_by_admin_id' =>
                        $admin->getKey(),
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ]);

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action:
                        'PRODUCT_CATALOG_CREATED',
                    module:
                        'PRODUCTS',
                    targetType:
                        'PRODUCT',
                    targetId:
                        $product->getKey(),
                    oldValues: null,
                    newValues: [
                        'product_code' =>
                            $product->code,
                        'name' =>
                            $product->name,
                        'description' =>
                            $product->description,
                        'billing_type' =>
                            $product->billing_type,
                        'currency' =>
                            $product->currency,
                        'amount_minor' =>
                            (int) $product->amount_minor,
                        'is_active' =>
                            (bool) $product->is_active,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $product->fresh();
            },
            3
        );
    }

    public function update(
        Product $product,
        AdminUser $admin,
        array $data,
        Request $request
    ): Product {
        return DB::transaction(
            function () use (
                $product,
                $admin,
                $data,
                $request
            ): Product {
                $locked = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = [
                    'name' =>
                        $locked->name,
                    'description' =>
                        $locked->description,
                    'amount_minor' =>
                        (int) $locked
                            ->amount_minor,
                    'lkr_amount_minor' =>
                        $locked->lkr_amount_minor !== null
                            ? (int) $locked->lkr_amount_minor
                            : null,
                    'affiliate_discount_percent' =>
                        (int) $locked->affiliate_discount_percent,
                    'is_active' =>
                        (bool) $locked
                            ->is_active,
                ];

                $locked->forceFill([
                    'name' =>
                        trim(
                            (string) $data[
                                'name'
                            ]
                        ),
                    'description' =>
                        filled(
                            $data[
                                'description'
                            ] ?? null
                        )
                            ? trim(
                                (string) $data[
                                    'description'
                                ]
                            )
                            : null,
                    'amount_minor' =>
                        (int) $data[
                            'amount_minor'
                        ],
                    'lkr_amount_minor' =>
                        array_key_exists('lkr_amount_minor', $data)
                            ? (int) $data['lkr_amount_minor']
                            : $locked->lkr_amount_minor,
                    'affiliate_discount_percent' =>
                        array_key_exists('affiliate_discount_percent', $data)
                            ? (int) $data['affiliate_discount_percent']
                            : $locked->affiliate_discount_percent,
                    'is_active' =>
                        (bool) $data[
                            'is_active'
                        ],
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                if ($locked->isEducationPremium()) {
                    $this->syncPremiumTenderAmounts($locked);
                }

                $after = [
                    'name' =>
                        $locked->name,
                    'description' =>
                        $locked->description,
                    'amount_minor' =>
                        (int) $locked
                            ->amount_minor,
                    'lkr_amount_minor' =>
                        $locked->lkr_amount_minor !== null
                            ? (int) $locked->lkr_amount_minor
                            : null,
                    'affiliate_discount_percent' =>
                        (int) $locked->affiliate_discount_percent,
                    'is_active' =>
                        (bool) $locked
                            ->is_active,
                ];

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action:
                        'PRODUCT_CATALOG_UPDATED',
                    module:
                        'PRODUCTS',
                    targetType:
                        'PRODUCT',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $before,
                    newValues: [
                        ...$after,
                        'product_code' =>
                            $locked->code,
                        'billing_type' =>
                            $locked
                                ->billing_type,
                        'currency' =>
                            $locked->currency,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $locked->fresh();
            },
            3
        );
    }

    private function syncPremiumTenderAmounts(Product $product): void
    {
        PaymentReceivingAccount::query()
            ->where('currency', 'LKR')
            ->update([
                'tender_amount_minor' => (int) $product->lkr_amount_minor,
                'tender_amount_decimal' => number_format(
                    (int) $product->lkr_amount_minor / 100,
                    2,
                    '.',
                    ''
                ),
                'amount_scale' => 2,
            ]);

        PaymentReceivingAccount::query()
            ->where('coin_symbol', 'USDT')
            ->where('network', 'TRC20')
            ->update([
                'tender_amount_minor' => (int) $product->amount_minor * 10000,
                'tender_amount_decimal' => number_format(
                    (int) $product->amount_minor / 100,
                    6,
                    '.',
                    ''
                ),
                'amount_scale' => 6,
            ]);
    }
}
