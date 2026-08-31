<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payments\SavePaymentReceivingAccountRequest;
use App\Models\AdminUser;
use App\Models\PaymentReceivingAccount;
use App\Services\Admin\Payments\PaymentReceivingAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceivingAccountController extends Controller
{
    public function store(
        SavePaymentReceivingAccountRequest $request,
        PaymentReceivingAccountService $service
    ): RedirectResponse {
        $admin = $this->admin();
        $qr = $request->file('qr_image');

        $service->create(
            $admin,
            $request->validated(),
            $qr instanceof UploadedFile ? $qr : null,
            $request
        );

        return redirect()
            ->route('admin.payments.accounts.index')
            ->with('success', 'Receiving account added securely.');
    }

    public function update(
        SavePaymentReceivingAccountRequest $request,
        PaymentReceivingAccount $paymentReceivingAccount,
        PaymentReceivingAccountService $service
    ): RedirectResponse {
        $admin = $this->admin();
        $qr = $request->file('qr_image');

        $service->update(
            $paymentReceivingAccount,
            $admin,
            $request->validated(),
            $qr instanceof UploadedFile ? $qr : null,
            $request
        );

        return redirect()
            ->route('admin.payments.accounts.index')
            ->with('success', 'Receiving account updated securely.');
    }

    public function qr(
        PaymentReceivingAccount $paymentReceivingAccount,
        PaymentReceivingAccountService $service
    ): StreamedResponse {
        $this->admin();
        $file = $service->qrFile($paymentReceivingAccount);

        return response()->streamDownload(
            static function () use ($file): void {
                $stream = Storage::disk($file['disk'])->readStream($file['path']);

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
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
            ]
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless($admin instanceof AdminUser, 401);
        abort_unless(
            $admin->isSuperAdmin()
            || $admin->hasPermission('payment_accounts.manage'),
            403
        );

        return $admin;
    }
}
