<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payments\RejectManualPaymentRequest;
use App\Http\Requests\Admin\Payments\VerifyManualPaymentRequest;
use App\Models\AdminUser;
use App\Models\UsdtDeposit;
use App\Services\Admin\Payments\AdminPaymentService;
use App\Services\Admin\Payments\ProtectedManualPaymentProofService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsdtDepositController extends Controller
{
    public function index(
        Request $request,
        AdminPaymentService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('payments.view'),
            403
        );

        $data = $service->usdtDeposits(
            $request->only([
                'search',
                'status',
                'from',
                'to',
                'per_page',
            ])
        );

        $data['permissions'] = [
            'verify' =>
                $admin->hasPermission('payments.verify'),
            'reject' =>
                $admin->hasPermission('payments.reject'),
            'reconcile' =>
                $admin->hasPermission('payments.reconcile'),
        ];

        return view(
            'admin.payments.usdt-deposits',
            $data
        );
    }

    public function proof(
        UsdtDeposit $usdtDeposit,
        ProtectedManualPaymentProofService $service
    ): StreamedResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('payments.verify')
            || $admin->hasPermission('payments.reconcile'),
            403
        );

        $file = $service->usdtProof($usdtDeposit);

        return response()->streamDownload(
            static function () use ($file): void {
                $stream = Storage::disk($file['disk'])
                    ->readStream($file['path']);

                if ($stream === false) {
                    return;
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $file['name'],
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]
        );
    }

    public function approve(
        VerifyManualPaymentRequest $request,
        UsdtDeposit $usdtDeposit,
        AdminPaymentService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('payments.verify'),
            403
        );

        try {
            $service->approveUsdtDeposit(
                $usdtDeposit,
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
            ->route(
                'admin.payments.show',
                $usdtDeposit->payment_order_id
            )
            ->with(
                'success',
                'USDT TRC20 deposit verified and payment reconciled successfully.'
            );
    }

    public function reject(
        RejectManualPaymentRequest $request,
        UsdtDeposit $usdtDeposit,
        AdminPaymentService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('payments.reject'),
            403
        );

        try {
            $service->rejectUsdtDeposit(
                $usdtDeposit,
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
            ->route(
                'admin.payments.show',
                $usdtDeposit->payment_order_id
            )
            ->with(
                'success',
                'USDT TRC20 deposit rejected. No entitlement was activated.'
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
