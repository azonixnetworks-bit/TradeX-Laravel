<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\StaffLeaveRequest;
use App\Models\StaffMember;
use App\Models\StaffPayoutChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Staff\StaffOtpService;
use Illuminate\View\View;

class StaffMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaffManagement();
        $staff = StaffMember::query()->latest()->paginate(25);
        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View { $this->authorizeStaffManagement(); return view('admin.staff.create'); }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStaffManagement();
        $data = $request->validate(['full_name'=>['required','string','max:150'],'email'=>['required','email','max:255','unique:staff_members,email'],'whatsapp_number'=>['nullable','string','max:32','unique:staff_members,whatsapp_number'],'job_role'=>['required','string','max:100'],'department'=>['nullable','string','max:100'],'joined_on'=>['nullable','date'],'password'=>['required','string','min:12','confirmed']]);
        StaffMember::query()->create([...$data,'uuid'=>(string) Str::uuid(),'staff_number'=>'STF-'.now()->format('Y').'-'.str_pad((string)(StaffMember::withTrashed()->count()+1),5,'0',STR_PAD_LEFT),'employment_status'=>'PENDING']);
        return redirect()->route('admin.staff.index')->with('success','Staff profile created. Verification, KYC and agreement acceptance are required before portal access.');
    }

    public function show(StaffMember $staffMember): View
    {
        $this->authorizeStaffManagement();
        $staffMember->load(['kycDocuments','leaveRequests.reviewer','salaryPayments','agreements','payoutChangeRequests']);
        return view('admin.staff.show', compact('staffMember'));
    }

    public function sendOtp(StaffMember $staffMember, string $channel, StaffOtpService $otp): RedirectResponse
    {
        $this->authorizeStaffManagement();
        $otp->send($staffMember, $channel);
        return back()->with('success', $channel.' verification code sent.');
    }

    public function verifyOtp(Request $request, StaffMember $staffMember, string $channel, StaffOtpService $otp): RedirectResponse
    {
        $this->authorizeStaffManagement();
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $otp->verify($staffMember, $channel, $data['code']);
        $this->refreshEmploymentStatus($staffMember);
        return back()->with('success', $channel.' contact verified.');
    }

    public function startTermination(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $this->superAdmin();
        $data = $request->validate(['content' => ['required', 'string', 'max:20000']]);
        abort_unless($staffMember->employment_status !== 'TERMINATED', 422, 'Staff employment is already terminated.');
        $staffMember->agreements()->create(['agreement_type' => 'TERMINATION', 'version' => 'v'.now()->format('YmdHis'), 'content' => $data['content']]);
        $staffMember->update(['employment_status' => 'TERMINATION_PENDING']);
        return back()->with('success', 'Termination agreement is ready for staff acknowledgement.');
    }

    public function recordSalary(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $admin = $this->superAdmin();
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $deduction = (float) ($data['deduction_amount'] ?? 0);
        abort_unless((float) $data['gross_amount'] >= $deduction, 422, 'Deductions cannot exceed gross salary.');
        $staffMember->salaryPayments()->create([
            ...$data,
            'deduction_amount' => $deduction,
            'net_amount' => (float) $data['gross_amount'] - $deduction,
            'status' => filled($data['paid_at'] ?? null) ? 'PAID' : 'RECORDED',
            'recorded_by_admin_id' => $admin->id,
        ]);
        return back()->with('success', 'Salary record created.');
    }

    public function reviewKyc(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $admin = $this->superAdmin();
        $data = $request->validate([
            'status' => ['required', 'in:APPROVED,REJECTED'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($staffMember, $admin, $data): void {
            $staff = StaffMember::query()->lockForUpdate()->findOrFail($staffMember->id);
            $documents = $staff->kycDocuments()->lockForUpdate()->get();
            abort_unless($documents->isNotEmpty(), 422, 'Staff must submit KYC documents before review.');

            $staff->kycDocuments()->where('status', 'PENDING')->update([
                'status' => $data['status'],
                'review_note' => $data['note'] ?? null,
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

            $staff->forceFill([
                'kyc_verified_at' => $data['status'] === 'APPROVED' ? now() : null,
                'kyc_verified_by_admin_id' => $data['status'] === 'APPROVED' ? $admin->id : null,
            ])->save();
        });

        return back()->with('success', 'Staff KYC review recorded.');
    }
    public function reviewLeave(Request $request, StaffLeaveRequest $leaveRequest): RedirectResponse
    {
        $admin = $this->authorizedReviewer();
        $data = $request->validate(['status'=>['required','in:APPROVED,REJECTED'],'review_note'=>['nullable','string','max:2000']]);
        abort_unless($leaveRequest->status === 'PENDING', 422, 'This leave request has already been reviewed.');
        $leaveRequest->update([...$data,'reviewed_by_admin_id'=>$admin->id,'reviewed_at'=>now()]);
        return back()->with('success','Leave request '.$data['status'].'.');
    }

    public function reviewPayoutChange(Request $request, StaffPayoutChangeRequest $payoutChangeRequest): RedirectResponse
    {
        $admin = $this->superAdmin();
        $data = $request->validate(['status'=>['required','in:APPROVED,REJECTED'],'review_note'=>['nullable','string','max:2000']]);
        abort_unless($payoutChangeRequest->status === 'PENDING', 422, 'This payout change request has already been reviewed.');
        DB::transaction(function () use ($payoutChangeRequest, $data, $admin): void {
            $request = StaffPayoutChangeRequest::query()->lockForUpdate()->findOrFail($payoutChangeRequest->id);
            abort_unless($request->status === 'PENDING', 422, 'This payout change request has already been reviewed.');
            $staff = StaffMember::query()->lockForUpdate()->findOrFail($request->staff_member_id);
            if ($data['status'] === 'APPROVED') {
                $details = $request->requested_details;
                $staff->forceFill(['bank_account_holder'=>$details['bank_account_holder'] ?? $staff->bank_account_holder,'bank_name'=>$details['bank_name'] ?? $staff->bank_name,'bank_branch'=>$details['bank_branch'] ?? $staff->bank_branch,'bank_account_number_encrypted'=>$details['bank_account_number'] ?? $staff->bank_account_number_encrypted,'usdt_network'=>$details['usdt_network'] ?? $staff->usdt_network,'usdt_address_encrypted'=>$details['usdt_address'] ?? $staff->usdt_address_encrypted,'payout_details_locked_at'=>now()])->save();
            }
            $request->update(['status'=>$data['status'],'review_note'=>$data['review_note'] ?? null,'reviewed_by_admin_id'=>$admin->id,'reviewed_at'=>now()]);
        });
        return back()->with('success','Payout change request '.$data['status'].'.');
    }

    private function refreshEmploymentStatus(StaffMember $staffMember): void
    {
        $staffMember->refresh();
        if (in_array($staffMember->employment_status, ['TERMINATED', 'TERMINATION_PENDING'], true)) {
            return;
        }

        $ready = $staffMember->email_verified_at !== null
            && $staffMember->whatsapp_verified_at !== null
            && $staffMember->kyc_verified_at !== null
            && $staffMember->employment_agreement_accepted_at !== null;

        $staffMember->forceFill(['employment_status' => $ready ? 'ACTIVE' : 'PENDING'])->save();
    }
    private function authorizeStaffManagement(): void { $admin=auth('admin')->user(); abort_unless($admin instanceof AdminUser && ($admin->isSuperAdmin() || $admin->hasPermission('staff.manage')),403); }
    private function superAdmin(): AdminUser { $admin=auth('admin')->user(); abort_unless($admin instanceof AdminUser && $admin->isSuperAdmin(),403); return $admin; }
    private function authorizedReviewer(): AdminUser { $admin=auth('admin')->user(); abort_unless($admin instanceof AdminUser && ($admin->isSuperAdmin() || $admin->hasRole('SUPPORT_ADMIN')),403); return $admin; }
}

