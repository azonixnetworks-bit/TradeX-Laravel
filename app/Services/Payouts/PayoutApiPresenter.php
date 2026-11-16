<?php

namespace App\Services\Payouts;

use App\Models\PayoutRequest;

class PayoutApiPresenter
{
    public function __construct(private readonly PayoutMoney $money, private readonly PayoutDestinationService $destinations) {}

    public function payout(PayoutRequest $payout): array
    {
        $snapshot = is_array($payout->destination_snapshot) ? $payout->destination_snapshot : [];

        return [
            'uuid' => $payout->uuid, 'method' => $payout->method, 'status' => $payout->status,
            'display_status' => $payout->displayStatus(),
            'processing_notice' => 'Withdrawal requests are normally completed within 3 to 5 business days.',
            'timeline' => $this->timeline($payout),
            'amount' => $this->money->present((int) $payout->amount_cents, (string) $payout->currency),
            'destination' => $this->destinations->masked($snapshot),
            'provider_reference' => $payout->isPaid() ? $payout->provider_reference : null,
            'reason' => $payout->user_visible_reason,
            'requested_at' => $payout->requested_at?->toIso8601String(), 'approved_at' => $payout->approved_at?->toIso8601String(), 'processing_at' => $payout->processing_at?->toIso8601String(), 'paid_at' => $payout->paid_at?->toIso8601String(), 'rejected_at' => $payout->rejected_at?->toIso8601String(), 'failed_at' => $payout->failed_at?->toIso8601String(), 'cancelled_at' => $payout->cancelled_at?->toIso8601String(),
        ];
    }

    private function timeline(PayoutRequest $payout): array
    {
        $current = $payout->displayStatus();
        $terminal = in_array($payout->status, [
            PayoutRequest::STATUS_REJECTED,
            PayoutRequest::STATUS_FAILED,
            PayoutRequest::STATUS_CANCELLED,
        ], true);
        $steps = [
            ['code' => 'REQUESTED', 'label' => 'Requested', 'at' => $payout->requested_at?->toIso8601String()],
            ['code' => 'PROCESSING', 'label' => 'Processing', 'at' => $payout->approved_at?->toIso8601String()],
            ['code' => 'ON_QUEUE', 'label' => 'On Queue', 'at' => $payout->processing_at?->toIso8601String()],
            ['code' => 'COMPLETED', 'label' => 'Completed', 'at' => $payout->paid_at?->toIso8601String()],
        ];
        $order = ['REQUESTED' => 0, 'PROCESSING' => 1, 'ON_QUEUE' => 2, 'COMPLETED' => 3];
        $currentIndex = $order[$current] ?? -1;

        return array_map(static function (array $step, int $index) use ($currentIndex, $terminal): array {
            $step['state'] = $terminal && $index > 0
                ? 'STOPPED'
                : ($index < $currentIndex ? 'COMPLETE' : ($index === $currentIndex ? 'CURRENT' : 'UPCOMING'));

            return $step;
        }, $steps, array_keys($steps));
    }
}
