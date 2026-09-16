<?php

namespace App\Http\Controllers\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Products\StoreProductRequest;
use App\Http\Requests\Admin\Products\UpdateProductRequest;
use App\Models\AdminUser;
use App\Models\Product;
use App\Services\Admin\Products\AdminProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        $admin = $this->viewer();

        return $this->view(
            'admin.products.index',
            [
                'products' =>
                    Product::query()
                        ->orderBy('id')
                        ->get(),
                'canManage' =>
                    $admin->isSuperAdmin()
                    || $admin->hasPermission(
                        'products.manage'
                    ),
            ]
        );
    }

    public function create(): Response
    {
        $admin = $this->admin();

        $this->ensureManager($admin);

        return $this->view(
            'admin.products.create',
            [
                'billingTypes' => [
                    Product::BILLING_ONE_TIME_LIFETIME,
                ],
                'currencies' => ['USD'],
            ]
        );
    }

    public function store(
        StoreProductRequest $request,
        AdminProductService $service
    ): RedirectResponse {
        $admin = $this->admin();

        $this->ensureManager($admin);

        $product = $service->create(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.products.index'
            )
            ->with(
                'success',
                'Product '.$product->name.' added to the TradeX catalog successfully.'
            );
    }

    public function edit(
        Product $product
    ): Response {
        $admin = $this->viewer();

        $this->ensureManager($admin);

        return $this->view(
            'admin.products.edit',
            compact('product')
        );
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        AdminProductService $service
    ): RedirectResponse {
        $admin = $this->admin();

        $this->ensureManager($admin);

        $service->update(
            $product,
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.products.index'
            )
            ->with(
                'success',
                'Product catalog updated securely. Existing payment orders keep their original amount snapshots.'
            );
    }

    private function viewer(): AdminUser
    {
        $admin = $this->admin();

        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'products.view'
            ),
            403
        );

        return $admin;
    }

    private function ensureManager(
        AdminUser $admin
    ): void {
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission(
                'products.manage'
            ),
            403
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth(
            'admin'
        )->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function view(
        string $view,
        array $data
    ): Response {
        return response()->view(
            $view,
            $data,
            200,
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' =>
                    'no-cache',
                'X-Content-Type-Options' =>
                    'nosniff',
                'Referrer-Policy' =>
                    'no-referrer',
                'X-Frame-Options' =>
                    'DENY',
                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
            ]
        );
    }
}