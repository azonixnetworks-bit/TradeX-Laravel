<?php

namespace App\Http\Controllers\Api\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FundedAccountPartner;
use App\Models\TradingRoutinePreference;
use App\Models\User;
use App\Models\UserFundedAccountProfile;
use App\Services\Trading\FundedAccountSelectionService;
use App\Services\Trading\FundedNoTradeZoneCustomerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerFundedAccountController extends Controller
{
    public function show(
        Request $request,
        FundedAccountSelectionService $selections
    ): JsonResponse {
        $user = $this->user($request);

        return response()->json([
            'success' => true,
            'data' => [
                'selected_account' => $selections->payload($selections->resolve($user)),
            ],
        ]);
    }

    public function update(
        Request $request,
        FundedAccountSelectionService $selections
    ): JsonResponse {
        $user = $this->user($request);
        $validated = $request->validate([
            'partner_uuid' => [
                'required',
                'uuid',
                Rule::exists('funded_account_partners', 'uuid')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'program_name' => ['nullable', 'string', 'max:160'],
            'phase' => ['required', Rule::in(['CHALLENGE', 'EVALUATION', 'VERIFICATION', 'FUNDED'])],
        ]);

        $before = $selections->payload($selections->resolve($user));
        $partner = FundedAccountPartner::query()
            ->where('uuid', $validated['partner_uuid'])
            ->where('is_active', true)
            ->firstOrFail();

        $profile = $selections->save(
            $user,
            $partner,
            $validated['program_name'] ?? null,
            (string) $validated['phase']
        );

        $after = $selections->payload($selections->resolve($user));

        AuditLog::record(
            'USER',
            $user->id,
            'FUNDED_ACCOUNT_SELECTION_UPDATED',
            'TRADING_TOOLS',
            UserFundedAccountProfile::class,
            $profile->id,
            $before,
            $after,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'selected_account' => $after,
            ],
        ]);
    }

    public function destroy(
        Request $request,
        FundedAccountSelectionService $selections
    ): JsonResponse {
        $user = $this->user($request);
        $before = $selections->payload($selections->resolve($user));
        $profile = $selections->disable($user);

        AuditLog::record(
            'USER',
            $user->id,
            'FUNDED_ACCOUNT_SELECTION_DISABLED',
            'TRADING_TOOLS',
            UserFundedAccountProfile::class,
            $profile?->id,
            $before,
            null,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => [
                'selected_account' => null,
            ],
        ]);
    }

    public function zones(
        Request $request,
        FundedNoTradeZoneCustomerService $zones
    ): JsonResponse {
        $user = $this->user($request);
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $preference = TradingRoutinePreference::query()
            ->where('user_id', $user->id)
            ->first();

        $timezone = (string) ($validated['timezone']
            ?? $preference?->timezone
            ?? config('trading_routine.defaults.timezone', 'Asia/Colombo'));
        $date = (string) ($validated['date'] ?? CarbonImmutable::now($timezone)->toDateString());

        return response()->json([
            'success' => true,
            'data' => $zones->snapshotForUserDate(
                $user,
                $date,
                $timezone,
                CarbonImmutable::now('UTC')
            ),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return $user;
    }
}
