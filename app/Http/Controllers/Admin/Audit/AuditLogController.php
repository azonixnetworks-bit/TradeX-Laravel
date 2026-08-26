<?php

namespace App\Http\Controllers\Admin\Audit;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Admin\Audit\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuditLogController extends Controller
{
    public function index(
        Request $request,
        AdminAuditService $service
    ): Response {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('audit.view'),
            403
        );

        $data = $service->index(
            $request->only([
                'search',
                'actor_type',
                'actor_id',
                'action',
                'module',
                'target_type',
                'target_id',
                'from',
                'to',
                'per_page',
            ])
        );

        return response()->view(
            'admin.audit.index',
            $data,
            200,
            $this->headers()
        );
    }

    public function show(
        Request $request,
        AuditLog $auditLog,
        AdminAuditService $service
    ): Response {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('audit.view'),
            403
        );

        $data = $service->detail(
            $auditLog,
            $admin,
            $request
        );

        return response()->view(
            'admin.audit.show',
            $data,
            200,
            $this->headers()
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

    private function headers(): array
    {
        return [
            'Cache-Control' =>
                'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
