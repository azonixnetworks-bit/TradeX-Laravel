<?php

namespace App\Http\Controllers\Admin\Activity;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Activity\UserActivityAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserActivityAnalyticsController extends Controller
{
    public function index(
        Request $request,
        UserActivityAnalyticsService $analytics
    ): Response {
        $this->authorizeSuperAdmin();
        $filters = $analytics->filters($request);

        return response()->view('admin.activity.index', [
            'filters' => $filters,
            'summary' => $analytics->summary($filters),
            'events' => $analytics->timeline($filters),
        ], 200, $this->privateHeaders());
    }

    public function export(
        Request $request,
        UserActivityAnalyticsService $analytics
    ): StreamedResponse {
        $this->authorizeSuperAdmin();
        $filters = $analytics->filters($request);
        $fileName = 'tradex-user-activity-'.$filters['from'].'-'.$filters['to'].'.csv';

        return response()->streamDownload(
            function () use ($analytics, $filters): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, [
                    'UTC Time', 'User ID', 'Module', 'Event',
                    'Subject ID', 'Session ID', 'Event UUID',
                ]);

                foreach ($analytics->query($filters)
                    ->orderBy('occurred_at')
                    ->orderBy('id')
                    ->limit(UserActivityAnalyticsService::MAX_EXPORT_ROWS)
                    ->cursor() as $event) {
                    fputcsv($output, [
                        $event->occurred_at->utc()->format('Y-m-d H:i:s'),
                        $event->user_id,
                        $this->safeCsv($event->module),
                        $this->safeCsv($event->event_name),
                        $this->safeCsv($event->subject_id),
                        $this->safeCsv($event->session_id),
                        $this->safeCsv($event->event_id),
                    ]);
                }
                fclose($output);
            },
            $fileName,
            array_merge($this->privateHeaders(), ['Content-Type' => 'text/csv; charset=UTF-8'])
        );
    }

    private function authorizeSuperAdmin(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless($admin->isSuperAdmin(), 403);
    }

    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }

    private function safeCsv(?string $value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+@\\-\\t\\r\\n]/', $value) ? "'".$value : $value;
    }
}
