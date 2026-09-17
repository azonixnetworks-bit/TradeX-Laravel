<?php

namespace App\Http\Controllers\Api\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradingFloorMembershipOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $client = TradingFloorClient::query()
            ->where('user_id', $user->id)
            ->first();

        if ($client === null) {
            return response()->json([
                'success' => true,
                'data' => [
                    'enrolled' => false,
                    'client' => null,
                    'current_membership' => null,
                ],
            ]);
        }

        $membership = TradingFloorMembership::query()
            ->with([
                'client:id,status',
                'package:id,uuid,code,name,description,features',
                'floor:id,uuid,code,name',
                'desk:id,uuid,code,name,zone,status',
                'payments' => fn ($query) => $query
                    ->latest('received_at')
                    ->limit(25),
            ])
            ->where('trading_floor_client_id', $client->id)
            ->whereIn('status', [
                TradingFloorMembership::STATUS_ACTIVE,
                TradingFloorMembership::STATUS_PENDING,
                TradingFloorMembership::STATUS_SUSPENDED,
            ])
            ->orderByRaw("CASE status WHEN 'ACTIVE' THEN 1 WHEN 'PENDING' THEN 2 ELSE 3 END")
            ->latest('starts_at')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'enrolled' => true,
                'client' => [
                    'uuid' => $client->uuid,
                    'client_code' => $client->client_code,
                    'status' => $client->status,
                ],
                'current_membership' => $membership ? $this->presentMembership($membership) : null,
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function presentMembership(TradingFloorMembership $membership): array
    {
        return [
            'uuid' => $membership->uuid,
            'membership_number' => $membership->membership_number,
            'status' => $membership->status,
            'starts_at' => $membership->starts_at?->toDateString(),
            'expires_at' => $membership->expires_at?->toDateString(),
            'due_date' => $membership->due_date?->toDateString(),
            'billing_cycle' => $membership->billing_cycle,
            'auto_renew' => (bool) $membership->auto_renew,
            'package' => $membership->package ? [
                'uuid' => $membership->package->uuid,
                'code' => $membership->package->code,
                'name' => $membership->package->name,
                'description' => $membership->package->description,
                'features' => $membership->package->features ?? [],
            ] : null,
            'allocation' => [
                'floor' => $membership->floor ? [
                    'uuid' => $membership->floor->uuid,
                    'code' => $membership->floor->code,
                    'name' => $membership->floor->name,
                ] : null,
                'desk' => $membership->desk ? [
                    'uuid' => $membership->desk->uuid,
                    'code' => $membership->desk->code,
                    'name' => $membership->desk->name,
                    'zone' => $membership->desk->zone,
                ] : null,
            ],
            'payment' => [
                'currency' => $membership->currency,
                'price_amount' => (float) $membership->price_amount,
                'paid_amount' => (float) $membership->paid_amount,
                'balance_amount' => (float) $membership->balance_amount,
                'status' => $membership->payment_status,
                'history' => $membership->payments->map(fn ($payment) => [
                    'uuid' => $payment->uuid,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'transaction_reference' => $payment->transaction_reference,
                    'status' => $payment->status,
                    'received_at' => $payment->received_at?->toIso8601String(),
                ])->values(),
            ],
            'access_snapshot' => [
                'client_status' => $membership->client?->status,
                'membership_status' => $membership->status,
                'final_access_decision_requires_reader_policy' => true,
            ],
        ];
    }
}
