<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\PaymentOrder;
use App\Services\Admin\Reporting\AdminSalesReportService;
use App\Support\Reporting\CsvExportSanitizer;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SalesReportController extends Controller
{
    public function index(
        Request $request,
        AdminSalesReportService $reports
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');
        $this->requirePermission($admin, 'finance.reports');

        $validated = $this->validatedFilters($request);

        return view(
            'admin.finance.sales-report',
            $reports->report(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
                $validated['provider'] ?? null,
                $validated['currency'] ?? null
            )
        );
    }

    public function export(
        Request $request,
        AdminSalesReportService $reports,
        CsvExportSanitizer $csv
    ): StreamedResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'finance.view');
        $this->requirePermission($admin, 'finance.reports');

        $validated = $this->validatedFilters($request);

        $prepared = $reports->prepare(
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['provider'] ?? null,
            $validated['currency'] ?? null
        );

        $range = $prepared['range'];

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'FINANCE_SALES_REPORT_EXPORTED',
            module: 'FINANCE',
            targetType: 'SALES_REPORT',
            targetId: null,
            newValues: [
                'format' => 'CSV',
                'from' => $range['from'],
                'to' => $range['to'],
                'days' => $range['days'],
                'provider' => $prepared['provider'],
                'currency' => $prepared['currency'],
                'payments_count' => $prepared['payments_count'],
                'adjustments_count' => $prepared['adjustments_count'],
                'row_count' => $prepared['row_count'],
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $filename = sprintf(
            'tradex-sales-report-%s-to-%s.csv',
            $range['from'],
            $range['to']
        );

        return response()->streamDownload(
            function () use (
                $reports,
                $prepared,
                $csv
            ): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");

                fputcsv(
                    $stream,
                    [
                        'record_type',
                        'local_date',
                        'uuid',
                        'order_uuid',
                        'user_email',
                        'provider',
                        'currency',
                        'gross_minor',
                        'processor_fee_minor',
                        'adjustment_minor',
                        'status_or_type',
                        'occurred_at',
                    ]
                );

                foreach ($reports->paymentCursor($prepared) as $payment) {
                    fputcsv(
                        $stream,
                        $csv->row(
                            $reports->paymentRow(
                                $payment,
                                $prepared['timezone']
                            )
                        )
                    );
                }

                foreach ($reports->adjustmentCursor($prepared) as $adjustment) {
                    fputcsv(
                        $stream,
                        $csv->row(
                            $reports->adjustmentRow(
                                $adjustment,
                                $prepared['timezone']
                            )
                        )
                    );
                }

                fclose($stream);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ]
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'provider' => [
                'nullable',
                'in:'.implode(',', [
                    PaymentOrder::PROVIDER_PAYPAL,
                    PaymentOrder::PROVIDER_LOCAL_BANK,
                    PaymentOrder::PROVIDER_USDT_TRC20,
                ]),
            ],
            'currency' => [
                'nullable',
                'string',
                'max:8',
                'regex:/^[A-Za-z0-9]+$/',
            ],
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
