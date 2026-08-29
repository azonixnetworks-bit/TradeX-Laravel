<?php

namespace App\Http\Controllers\Api\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Services\Products\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIndexController extends Controller
{
    public function __invoke(
        Request $request,
        ProductCatalogService $catalog
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $items = Product::query()
            ->orderBy('id')
            ->get()
            ->map(
                fn (Product $product): array =>
                    $catalog->present(
                        $product,
                        $user
                    )
            )
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }
}
