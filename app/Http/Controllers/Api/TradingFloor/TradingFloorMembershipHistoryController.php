<?php

namespace App\Http\Controllers\Api\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradingFloorMembershipHistoryController extends Controller
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
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                ],
            ]);
        }

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $paginator = TradingFloorMembership::query()
            ->with([
                'package:id,uuid,code,name',
                'floor:id,uuid,code,name',
                'desk:id,uuid,code,name,zone',
            ])
            ->where('trading_floor_client_id', $client->id)
            ->latest('starts_at')
            ->latest('id')
            ->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (TradingFloorMembership $membership) => [
            'uuid' => $membership->uuid,
            'membership_number' => $membership->membership_number,
            'status' => $membership->status,
            'starts_at' => $membership->starts_at?->toDateString(),
            'expires_at' => $membership->expires_at?->toDateString(),
            'package' => $membership->package ? [
                'uuid' => $membership->package->uuid,
                'code' => $membership->package->code,
                'name' => $membership->package->name,
            ] : null,
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
            'payment' => [
                'currency' => $membership->currency,
                'price_amount' => (float) $membership->price_amount,
                'paid_amount' => (float) $membership->paid_amount,
                'balance_amount' => (float) $membership->balance_amount,
                'status' => $membership->payment_status,
            ],
            'auto_renew' => (bool) $membership->auto_renew,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
