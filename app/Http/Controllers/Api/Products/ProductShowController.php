<?php

namespace App\Http\Controllers\Api\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Services\Products\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductShowController extends Controller
{
    public function __invoke(
        Request $request,
        Product $product,
        ProductCatalogService $catalog
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $catalog->present(
                    $product,
                    $user
                ),
        ]);
    }
}
