<?php

namespace App\Http\Controllers\Admin\Audit;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Admin\Reporting\AdminAuditReportService;
use App\Support\Reporting\CsvExportSanitizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditReportController extends Controller
{
    public function export(
        Request $request,
        AdminAuditReportService $reports,
        CsvExportSanitizer $csv
    ): StreamedResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('audit.view'),
            403
        );

        $filters = $request->only([
            'search',
            'actor_type',
            'actor_id',
            'action',
            'module',
            'target_type',
            'target_id',
            'from',
            'to',
        ]);

        $prepared = $reports->prepare($filters);
        $range = $prepared['range'];

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'AUDIT_REPORT_EXPORTED',
            module: 'AUDIT',
            targetType: 'AUDIT_REPORT',
            targetId: null,
            newValues: [
                'format' => 'CSV',
                'from' => $range['from'],
                'to' => $range['to'],
                'days' => $range['days'],
                'row_count' => $prepared['row_count'],
                'actor_type' => $prepared['filters']['actor_type'],
                'action' => $prepared['filters']['action'],
                'module' => $prepared['filters']['module'],
                'target_type' => $prepared['filters']['target_type'],
                'has_search_filter' => $prepared['filters']['search'] !== '',
                'has_actor_id_filter' => $prepared['filters']['actor_id'] !== null,
                'has_target_id_filter' => $prepared['filters']['target_id'] !== null,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $filename = sprintf(
            'tradex-audit-report-%s-to-%s.csv',
            $range['from'],
            $range['to']
        );

        return response()->streamDownload(
            function () use (
                $reports,
                $prepared,
                $range,
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
                        'id',
                        'occurred_at',
                        'actor_type',
                        'actor_id',
                        'action',
                        'module',
                        'target_type',
                        'target_id',
                        'ip_address',
                        'user_agent',
                        'old_values_redacted_json',
                        'new_values_redacted_json',
                    ]
                );

                foreach ($reports->cursor($prepared) as $log) {
                    fputcsv(
                        $stream,
                        $csv->row(
                            $reports->row(
                                $log,
                                $range['timezone']
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

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }
}
