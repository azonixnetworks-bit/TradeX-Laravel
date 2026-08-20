<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'admins' => AdminUser::count(),

            'active_admins' => AdminUser::where(
                'is_active',
                true
            )->count(),

            'roles' => AdminRole::where(
                'is_active',
                true
            )->count(),

            'permissions' => AdminPermission::count(),

            'audit_events_today' => AuditLog::whereDate(
                'created_at',
                today()
            )->count(),
        ];

        $recentActivities = AuditLog::query()
            ->latest('created_at')
            ->limit(8)
            ->get();

        $admin = auth('admin')
            ->user()
            ->load('roles');

        return view(
            'admin.dashboard.index',
            compact(
                'stats',
                'recentActivities',
                'admin'
            )
        );
    }
}