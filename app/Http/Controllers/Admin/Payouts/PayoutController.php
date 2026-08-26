<?php

namespace App\Http\Controllers\Admin\Payouts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payouts\ApprovePayoutRequest;
use App\Http\Requests\Admin\Payouts\MarkPayoutPaidRequest;
use App\Http\Requests\Admin\Payouts\RejectPayoutRequest;
use App\Models\AdminUser;
use App\Models\PayoutRequest;
use App\Services\Admin\Payouts\AdminPayoutService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(
        Request $request,
        AdminPayoutService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'payouts.view');

        $data = $service->dashboard($request->only([
            'search',
            'method',
            'status',
            'from',
            'to',
            'per_page',
        ]));

        $data['permissions'] = $this->permissions($admin);

        return view('admin.payouts.index', $data);
    }

    public function show(
        PayoutRequest $payoutRequest,
        AdminPayoutService $service
    ): View {
        $admin = $this->admin();
        $this->requirePermission($admin, 'payouts.view');

        $data = $service->detail($payoutRequest);
        $data['permissions'] = $this->permissions($admin);

        return view('admin.payouts.show', $data);
    }

    public function destination(
        PayoutRequest $payoutRequest,
        Request $request,
        AdminPayoutService $service
    ): Response {
        $admin = $this->admin();
        $this->requirePermission($admin, 'payouts.view');

        abort_unless(
            $admin->hasPermission('payouts.approve')
            || $admin->hasPermission('payouts.mark_paid'),
            403
        );

        $data = $service->sensitiveDestination(
            $payoutRequest,
            $admin,
            $request
        );

        return response()
            ->view('admin.payouts.destination', $data)
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }

    public function approve(
        ApprovePayoutRequest $request,
        PayoutRequest $payoutRequest,
        AdminPayoutService $service
    ): RedirectResponse {
        try {
            $service->approve(
                $payoutRequest,
                $this->admin(),
                (string) $request->validated('reason'),
                $request->validated('internal_note'),
                $request
            );

            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->with('success', 'Payout approved. The locked affiliate balance remains reserved until payment is completed.');
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->withErrors([
                    'payout_review' => $exception->getMessage(),
                ]);
        }
    }

    public function reject(
        RejectPayoutRequest $request,
        PayoutRequest $payoutRequest,
        AdminPayoutService $service
    ): RedirectResponse {
        try {
            $service->reject(
                $payoutRequest,
                $this->admin(),
                (string) $request->validated('reason'),
                $request->validated('internal_note'),
                $request
            );

            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->with('success', 'Payout rejected. Its locked allocation is now available to the affiliate ledger again.');
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->withErrors([
                    'payout_review' => $exception->getMessage(),
                ]);
        }
    }

    public function markPaid(
        MarkPayoutPaidRequest $request,
        PayoutRequest $payoutRequest,
        AdminPayoutService $service
    ): RedirectResponse {
        try {
            $service->markPaid(
                $payoutRequest,
                $this->admin(),
                (string) $request->validated('provider_reference'),
                $request->validated('internal_note'),
                $request
            );

            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->with('success', 'Payout marked as paid and permanently consumed from the affiliate balance.');
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.payouts.show', $payoutRequest)
                ->withErrors([
                    'payout_review' => $exception->getMessage(),
                ]);
        }
    }

    private function permissions(AdminUser $admin): array
    {
        return [
            'approve' => $admin->hasPermission('payouts.approve'),
            'reject' => $admin->hasPermission('payouts.reject'),
            'mark_paid' => $admin->hasPermission('payouts.mark_paid'),
            'sensitive_destination' =>
                $admin->hasPermission('payouts.approve')
                || $admin->hasPermission('payouts.mark_paid'),
        ];
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
