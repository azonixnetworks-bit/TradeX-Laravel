<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaffManagement();
        $staff = StaffMember::query()->latest()->paginate(25);
        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        $this->authorizeStaffManagement();
        return view('admin.staff.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaffManagement();
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:staff_members,email'],
            'whatsapp_number' => ['nullable', 'string', 'max:32', 'unique:staff_members,whatsapp_number'],
            'job_role' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'joined_on' => ['nullable', 'date'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $staff = StaffMember::query()->create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'staff_number' => 'STF-'.now()->format('Y').'-'.str_pad((string) (StaffMember::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
            'employment_status' => 'PENDING',
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff profile created. Email/WhatsApp verification, KYC and agreement acceptance are required before portal access.');
    }

    private function authorizeStaffManagement(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser && ($admin->isSuperAdmin() || $admin->hasPermission('staff.manage')), 403);
    }
}
