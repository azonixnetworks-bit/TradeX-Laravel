<?php

namespace App\Http\Controllers\Admin\Kyc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Kyc\ApproveKycRequest;
use App\Http\Requests\Admin\Kyc\RejectKycRequest;
use App\Http\Requests\Admin\Kyc\RequestKycResubmissionRequest;
use App\Models\AdminUser;
use App\Models\KycSubmission;
use App\Services\Admin\Kyc\AdminKycService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class KycReviewController extends Controller
{
    public function approve(
        ApproveKycRequest $request,
        KycSubmission $kycSubmission,
        AdminKycService $service
    ): RedirectResponse {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('kyc.view')
                && $admin->hasPermission('kyc.approve'),
            403
        );

        try {
            $service->approve(
                $kycSubmission,
                $admin,
                $request->internalNotes(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'kyc_review' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.kyc.show', $kycSubmission->getKey())
            ->with('success', 'KYC submission verified successfully.');
    }

    public function reject(
        RejectKycRequest $request,
        KycSubmission $kycSubmission,
        AdminKycService $service
    ): RedirectResponse {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('kyc.view')
                && $admin->hasPermission('kyc.reject'),
            403
        );

        try {
            $service->reject(
                $kycSubmission,
                $admin,
                $request->reason(),
                $request->internalNotes(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'kyc_review' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.kyc.show', $kycSubmission->getKey())
            ->with('success', 'KYC submission rejected.');
    }

    public function requestResubmission(
        RequestKycResubmissionRequest $request,
        KycSubmission $kycSubmission,
        AdminKycService $service
    ): RedirectResponse {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('kyc.view')
                && $admin->hasPermission('kyc.request_resubmission'),
            403
        );

        try {
            $service->requestResubmission(
                $kycSubmission,
                $admin,
                $request->reason(),
                $request->internalNotes(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'kyc_review' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.kyc.show', $kycSubmission->getKey())
            ->with('success', 'KYC resubmission requested.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
