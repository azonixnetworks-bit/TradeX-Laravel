<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffLeaveRequest;
use App\Models\StaffMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffPortalController extends Controller
{
    public function showLogin(): View { return view('staff.login'); }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::guard('staff')->attempt($credentials, $request->boolean('remember'))) return back()->withErrors(['email' => 'Invalid staff login details.'])->onlyInput('email');
        $request->session()->regenerate();
        $staff = Auth::guard('staff')->user();
        if (! $staff instanceof StaffMember || ! $staff->canLogin()) { Auth::guard('staff')->logout(); return redirect()->route('staff.login')->with('error', 'Portal access is pending verification, KYC and employment-agreement acceptance.'); }
        return redirect()->route('staff.dashboard');
    }

    public function dashboard(): View
    {
        $staff = Auth::guard('staff')->user();
        abort_unless($staff instanceof StaffMember, 401);
        return view('staff.dashboard', ['staff' => $staff, 'leaveRequests' => $staff->leaveRequests()->latest()->get(), 'salaryPayments' => $staff->salaryPayments()->latest('period')->get()]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $staff = Auth::guard('staff')->user(); abort_unless($staff instanceof StaffMember, 401);
        $data = $request->validate(['starts_on' => ['required','date','after_or_equal:today'], 'ends_on' => ['required','date','after_or_equal:starts_on'], 'leave_type' => ['required','string','max:40'], 'reason' => ['required','string','max:2000']]);
        $staff->leaveRequests()->create($data + ['status' => 'PENDING']);
        return back()->with('success', 'Leave request sent to Super Admin and Support Admin for approval.');
    }

    public function logout(Request $request): RedirectResponse { Auth::guard('staff')->logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect()->route('staff.login'); }
}
