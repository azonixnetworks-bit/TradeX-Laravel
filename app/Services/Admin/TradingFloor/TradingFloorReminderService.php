<?php

namespace App\Services\Admin\TradingFloor;

use App\Models\AdminUser;
use App\Models\TradingFloorCommissionInvoice;
use App\Models\TradingFloorCommissionPayment;
use App\Models\TradingFloorReminder;
use Illuminate\Support\Collection;

class TradingFloorReminderService
{
    /** @return array{created:int,existing:int,skipped:int} */
    public function generate(AdminUser $admin): array
    {
        $summary = ['created' => 0, 'existing' => 0, 'skipped' => 0];
        $today = now()->startOfDay();

        $invoiceRules = [
            TradingFloorReminder::TYPE_DUE_7_DAYS => $today->copy()->addDays(7)->toDateString(),
            TradingFloorReminder::TYPE_DUE_3_DAYS => $today->copy()->addDays(3)->toDateString(),
            TradingFloorReminder::TYPE_DUE_TODAY => $today->toDateString(),
        ];

        foreach ($invoiceRules as $type => $dueDate) {
            TradingFloorCommissionInvoice::query()
                ->with('client')
                ->whereDate('due_date', $dueDate)
                ->where('balance_amount', '>', 0)
                ->whereNotIn('status', [
                    TradingFloorCommissionInvoice::STATUS_PAID,
                    TradingFloorCommissionInvoice::STATUS_WAIVED,
                    TradingFloorCommissionInvoice::STATUS_DISPUTED,
                ])
                ->chunkById(200, function (Collection $invoices) use ($admin, $type, &$summary): void {
                    foreach ($invoices as $invoice) {
                        $this->queueInvoiceReminder($invoice, $type, $admin, $summary);
                    }
                });
        }

        TradingFloorCommissionInvoice::query()
            ->with('client')
            ->whereDate('due_date', '<', $today->toDateString())
            ->where('balance_amount', '>', 0)
            ->whereNotIn('status', [
                TradingFloorCommissionInvoice::STATUS_PAID,
                TradingFloorCommissionInvoice::STATUS_WAIVED,
                TradingFloorCommissionInvoice::STATUS_DISPUTED,
            ])
            ->chunkById(200, function (Collection $invoices) use ($admin, &$summary): void {
                foreach ($invoices as $invoice) {
                    $this->queueInvoiceReminder(
                        $invoice,
                        TradingFloorReminder::TYPE_OVERDUE,
                        $admin,
                        $summary
                    );
                }
            });

        TradingFloorCommissionPayment::query()
            ->with('invoice.client')
            ->where('status', TradingFloorCommissionPayment::STATUS_RECEIVED)
            ->where('received_at', '>=', now()->subDays(30))
            ->chunkById(200, function (Collection $payments) use ($admin, &$summary): void {
                foreach ($payments as $payment) {
                    $client = $payment->invoice?->client;
                    if ($client === null) {
                        $summary['skipped']++;
                        continue;
                    }

                    [$channel, $recipient] = $this->contact($client);
                    $dedupe = hash('sha256', implode('|', [
                        'payment', $payment->id, TradingFloorReminder::TYPE_PAYMENT_RECEIVED, $channel,
                    ]));

                    if (TradingFloorReminder::query()->where('dedupe_key', $dedupe)->exists()) {
                        $summary['existing']++;
                        continue;
                    }

                    TradingFloorReminder::create([
                        'trading_floor_client_id' => $client->id,
                        'trading_floor_commission_invoice_id' => $payment->invoice?->id,
                        'trading_floor_commission_payment_id' => $payment->id,
                        'reminder_type' => TradingFloorReminder::TYPE_PAYMENT_RECEIVED,
                        'channel' => $channel,
                        'recipient' => $recipient,
                        'dedupe_key' => $dedupe,
                        'status' => TradingFloorReminder::STATUS_PENDING,
                        'scheduled_for' => now(),
                        'payload' => [
                            'customer_code' => $client->client_code,
                            'customer_name' => $client->full_name,
                            'invoice_number' => $payment->invoice?->invoice_number,
                            'currency' => $payment->currency,
                            'amount' => (float) $payment->amount,
                            'received_at' => $payment->received_at?->toIso8601String(),
                        ],
                        'created_by_admin_id' => $admin->id,
                    ]);
                    $summary['created']++;
                }
            });

        return $summary;
    }

    /** @param array{created:int,existing:int,skipped:int} $summary */
    private function queueInvoiceReminder(
        TradingFloorCommissionInvoice $invoice,
        string $type,
        AdminUser $admin,
        array &$summary
    ): void {
        $client = $invoice->client;
        if ($client === null) {
            $summary['skipped']++;
            return;
        }

        [$channel, $recipient] = $this->contact($client);
        $dedupe = hash('sha256', implode('|', ['invoice', $invoice->id, $type, $channel]));

        if (TradingFloorReminder::query()->where('dedupe_key', $dedupe)->exists()) {
            $summary['existing']++;
            return;
        }

        TradingFloorReminder::create([
            'trading_floor_client_id' => $client->id,
            'trading_floor_commission_invoice_id' => $invoice->id,
            'reminder_type' => $type,
            'channel' => $channel,
            'recipient' => $recipient,
            'dedupe_key' => $dedupe,
            'status' => TradingFloorReminder::STATUS_PENDING,
            'scheduled_for' => now(),
            'payload' => [
                'customer_code' => $client->client_code,
                'customer_name' => $client->full_name,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'currency' => $invoice->currency,
                'balance' => (float) $invoice->balance_amount,
                'reminder_type' => $type,
            ],
            'created_by_admin_id' => $admin->id,
        ]);
        $summary['created']++;
    }

    /** @return array{0:string,1:?string} */
    private function contact($client): array
    {
        if (filled($client->whatsapp_number)) {
            return ['WHATSAPP', trim((string) $client->whatsapp_number)];
        }

        if (filled($client->email)) {
            return ['EMAIL', strtolower(trim((string) $client->email))];
        }

        if (filled($client->mobile)) {
            return ['WHATSAPP', trim((string) $client->mobile)];
        }

        return ['IN_APP', null];
    }
}
