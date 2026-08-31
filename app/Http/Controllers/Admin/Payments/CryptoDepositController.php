<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payments\RejectManualPaymentRequest;
use App\Http\Requests\Admin\Payments\VerifyManualPaymentRequest;
use App\Models\AdminUser;
use App\Models\CryptoDeposit;
use App\Services\Admin\Payments\GenericCryptoPaymentService;
use App\Services\Admin\Payments\ProtectedCryptoProofService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CryptoDepositController
    extends Controller
{
    public function index(
        Request $request,
        GenericCryptoPaymentService $service
    ): View {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'payments.view'
            ),
            403
        );

        return view(
            'admin.payments.crypto-deposits',
            [
                'deposits' =>
                    $service->deposits(
                        $request->only([
                            'search',
                            'status',
                        ])
                    ),
                'permissions' => [
                    'verify' =>
                        $admin
                            ->hasPermission(
                                'payments.verify'
                            ),
                    'reject' =>
                        $admin
                            ->hasPermission(
                                'payments.reject'
                            ),
                    'reconcile' =>
                        $admin
                            ->hasPermission(
                                'payments.reconcile'
                            ),
                ],
            ]
        );
    }

    public function proof(
        CryptoDeposit $cryptoDeposit,
        ProtectedCryptoProofService $service
    ): StreamedResponse {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'payments.verify'
            )
            || $admin->hasPermission(
                'payments.reconcile'
            ),
            403
        );

        $file =
            $service->file(
                $cryptoDeposit
            );

        return response()->streamDownload(
            static function () use (
                $file
            ): void {
                $stream =
                    Storage::disk(
                        $file['disk']
                    )->readStream(
                        $file['path']
                    );

                if (
                    $stream
                    === false
                ) {
                    return;
                }

                fpassthru(
                    $stream
                );

                if (
                    is_resource(
                        $stream
                    )
                ) {
                    fclose(
                        $stream
                    );
                }
            },
            $file['name'],
            [
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' =>
                    'no-cache',
                'X-Content-Type-Options' =>
                    'nosniff',
                'Referrer-Policy' =>
                    'no-referrer',
                'X-Frame-Options' =>
                    'DENY',
            ]
        );
    }

    public function approve(
        VerifyManualPaymentRequest $request,
        CryptoDeposit $cryptoDeposit,
        GenericCryptoPaymentService $service
    ): RedirectResponse {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'payments.verify'
            ),
            403
        );

        try {
            $service->approve(
                $cryptoDeposit,
                $admin,
                (string) $request
                    ->validated(
                        'reason'
                    ),
                $request
            );
        } catch (
            DomainException $exception
        ) {
            return back()
                ->withErrors([
                    'payment_review' =>
                        $exception
                            ->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.payments.crypto.index'
            )
            ->with(
                'success',
                'Crypto deposit verified, payment cleared, and premium entitlement reconciled.'
            );
    }

    public function reject(
        RejectManualPaymentRequest $request,
        CryptoDeposit $cryptoDeposit,
        GenericCryptoPaymentService $service
    ): RedirectResponse {
        $admin =
            $this->admin();

        abort_unless(
            $admin->hasPermission(
                'payments.reject'
            ),
            403
        );

        try {
            $service->reject(
                $cryptoDeposit,
                $admin,
                (string) $request
                    ->validated(
                        'reason'
                    ),
                $request
            );
        } catch (
            DomainException $exception
        ) {
            return back()
                ->withErrors([
                    'payment_review' =>
                        $exception
                            ->getMessage(),
                ]);
        }

        return redirect()
            ->route(
                'admin.payments.crypto.index'
            )
            ->with(
                'success',
                'Crypto deposit rejected. No entitlement was activated.'
            );
    }

    private function admin(): AdminUser
    {
        $admin =
            auth(
                'admin'
            )->user();

        abort_unless(
            $admin
            instanceof AdminUser,
            401
        );

        return $admin;
    }
}
