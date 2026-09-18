<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use App\Services\Admin\TradingFloor\TradingFloorCommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingFloorCommissionPaymentController extends Controller
{
    public function store(
        Request $request,
        TradingFloorCommissionInvoice $invoice,
        TradingFloorCommissionService $commissions
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.payments.manage');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'payment_method' => ['required', Rule::in([
                'CASH',
                'BANK_TRANSFER',
                'CARD',
                'PAYPAL',
                'CRYPTO',
                'OTHER',
            ])],
            'transaction_reference' => ['nullable', 'string', 'max:190'],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $proofPath = null;

        if ($request->hasFile('proof')) {
            $proofPath = Storage::disk('local')->putFile(
                'trading-floor/commission-proofs',
                $request->file('proof')
            );
        }

        try {
            $payment = DB::transaction(function () use ($invoice, $admin, $data, $proofPath): TradingFloorCommissionPayment {
                $lockedInvoice = TradingFloorCommissionInvoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($lockedInvoice->status, [
                    TradingFloorCommissionInvoice::STATUS_PAID,
                    TradingFloorCommissionInvoice::STATUS_WAIVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'amount' => 'This invoice is already settled and cannot receive another payment.',
                    ]);
                }

                $currentPaid = (float) TradingFloorCommissionPayment::query()
                    ->where('trading_floor_commission_invoice_id', $lockedInvoice->id)
                    ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
                    ->sum('amount');
                $currentBalance = max(0, round((float) $lockedInvoice->commission_amount - $currentPaid, 2));
                $amount = round((float) $data['amount'], 2);

                if ($amount > $currentBalance + 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment cannot be greater than the current invoice balance.',
                    ]);
                }

                return TradingFloorCommissionPayment::create([
                    'trading_floor_commission_invoice_id' => $lockedInvoice->id,
                    'amount' => $amount,
                    'currency' => $lockedInvoice->currency,
                    'payment_method' => $data['payment_method'],
                    'transaction_reference' => filled($data['transaction_reference'] ?? null)
                        ? trim((string) $data['transaction_reference'])
                        : null,
                    'proof_path' => $proofPath,
                    'received_at' => $data['received_at'],
                    'received_by_admin_id' => $admin->id,
                    'status' => TradingFloorCommissionPayment::STATUS_RECEIVED,
                    'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                ]);
            });
        } catch (\Throwable $e) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $e;
        }

        $commissions->reconcileInvoice($invoice);

        return redirect()
            ->route('admin.trading_floor.commissions.show', $invoice)
            ->with('success', 'Commission payment recorded and invoice balance reconciled.');
    }

    public function reverse(
        Request $request,
        TradingFloorCommissionInvoice $invoice,
        TradingFloorCommissionPayment $payment,
        TradingFloorCommissionService $commissions
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.payments.manage');
        abort_unless($payment->trading_floor_commission_invoice_id === $invoice->id, 404);

        $data = $request->validate([
            'reversal_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($payment, $admin, $data): void {
            $lockedPayment = TradingFloorCommissionPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status === TradingFloorCommissionPayment::STATUS_REVERSED) {
                return;
            }

            $lockedPayment->update([
                'status' => TradingFloorCommissionPayment::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by_admin_id' => $admin->id,
                'reversal_reason' => trim((string) $data['reversal_reason']),
            ]);
        });

        $commissions->reconcileInvoice($invoice);

        return redirect()
            ->route('admin.trading_floor.commissions.show', $invoice)
            ->with('success', 'Payment reversed. The payment remains in history and the invoice balance was restored.');
    }

    public function proof(
        TradingFloorCommissionInvoice $invoice,
        TradingFloorCommissionPayment $payment
    ) {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.view');
        abort_unless($payment->trading_floor_commission_invoice_id === $invoice->id, 404);
        abort_unless(filled($payment->proof_path), 404);
        abort_unless(Storage::disk('local')->exists((string) $payment->proof_path), 404);

        return Storage::disk('local')->download(
            (string) $payment->proof_path,
            'commission-proof-'.$invoice->invoice_number.'-'.$payment->uuid.'.'.pathinfo((string) $payment->proof_path, PATHINFO_EXTENSION)
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->isSuperAdmin() || $admin->hasPermission($permission), 403);
    }
}
