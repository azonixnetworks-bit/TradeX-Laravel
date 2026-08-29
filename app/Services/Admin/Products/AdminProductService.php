<?php

namespace App\Services\Admin\Products;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductService
{
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
                    'is_active' =>
                        (bool) $data[
                            'is_active'
                        ],
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $after = [
                    'name' =>
                        $locked->name,
                    'description' =>
                        $locked->description,
                    'amount_minor' =>
                        (int) $locked
                            ->amount_minor,
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
}
