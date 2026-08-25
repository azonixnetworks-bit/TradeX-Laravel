<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payments\RejectManualPaymentRequest;
use App\Http\Requests\Admin\Payments\VerifyManualPaymentRequest;
use App\Models\AdminUser;
use App\Models\BankDeposit;
use App\Services\Admin\Payments\AdminPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankDepositController extends Controller
{
    public function index(
        Request $request,
        AdminPaymentService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('payments.view'), 403);

        $data = $service->bankDeposits($request->only([
            'search',
            'status',
            'from',
            'to',
            'per_page',
        ]));

        $data['permissions'] = [
            'verify' => $admin->hasPermission('payments.verify'),
            'reject' => $admin->hasPermission('payments.reject'),
            'reconcile' => $admin->hasPermission('payments.reconcile'),
        ];

        return view('admin.payments.bank-deposits', $data);
    }

    public function proof(
        BankDeposit $bankDeposit,
        AdminPaymentService $service
    ): StreamedResponse {
        $admin = $this->admin();
        abort_unless(
            $admin->hasPermission('payments.view')
                && (
                    $admin->hasPermission('payments.verify')
                    || $admin->hasPermission('payments.reconcile')
                ),
            403
        );

        try {
            $proof = $service->bankProof($bankDeposit);
        } catch (DomainException) {
            abort(404);
        }

        return Storage::disk($proof['disk'])
            ->download(
                $proof['path'],
                $proof['name'],
                [
                    'Cache-Control' => 'private, no-store, max-age=0',
                    'Pragma' => 'no-cache',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
    }

    public function approve(
        VerifyManualPaymentRequest $request,
        BankDeposit $bankDeposit,
        AdminPaymentService $service
    ): RedirectResponse {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('payments.verify'), 403);

        try {
            $service->approveBankDeposit(
                $bankDeposit,
                $admin,
                (string) $request->validated('reason'),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment_review' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.payments.show', $bankDeposit->payment_order_id)
            ->with('success', 'Bank deposit verified and payment reconciled successfully.');
    }

    public function reject(
        RejectManualPaymentRequest $request,
        BankDeposit $bankDeposit,
        AdminPaymentService $service
    ): RedirectResponse {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('payments.reject'), 403);

        try {
            $service->rejectBankDeposit(
                $bankDeposit,
                $admin,
                (string) $request->validated('reason'),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'payment_review' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.payments.show', $bankDeposit->payment_order_id)
            ->with('success', 'Bank deposit rejected. No entitlement was activated.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
