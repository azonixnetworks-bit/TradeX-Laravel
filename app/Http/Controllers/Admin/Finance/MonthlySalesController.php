<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\PaymentOrder;
use App\Services\Admin\Finance\AdminFinanceService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlySalesController extends Controller
{
    public function index(
        Request $request,
        AdminFinanceService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');
        $this->requirePermission($admin, 'finance.reports');

        $validated = $this->validatedFilters($request);

        return view(
            'admin.finance.monthly-sales',
            $service->monthlySales(
                $validated['month'] ?? null,
                $validated['provider'] ?? null,
                $validated['currency'] ?? null
            )
        );
    }

    public function export(
        Request $request,
        AdminFinanceService $service
    ): StreamedResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');
        $this->requirePermission($admin, 'finance.reports');

        $validated = $this->validatedFilters($request);

        $export = $service->monthlySalesExport(
            $validated['month'] ?? null,
            $validated['provider'] ?? null,
            $validated['currency'] ?? null
        );

        return response()->streamDownload(
            function () use ($export): void {
                $stream = fopen('php://output', 'wb');

                fputcsv($stream, [
                    'date',
                    'currency',
                    'payments_count',
                    'gross_minor',
                    'processor_fee_minor',
                    'adjustments_minor',
                    'known_net_minor',
                ]);

                foreach ($export['rows'] as $row) {
                    fputcsv($stream, $row);
                }

                fclose($stream);
            },
            'tradex-monthly-sales-'.$export['month'].'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function validatedFilters(
        Request $request
    ): array {
        return $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'provider' => [
                'nullable',
                'in:'.implode(',', [
                    PaymentOrder::PROVIDER_PAYPAL,
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ]),
            ],
            'currency' => ['nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9]+$/'],
        ]);
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless($admin->hasPermission($permission), 403);
    }
}
