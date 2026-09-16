<?php

namespace App\Services\Trading;

use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use App\Models\FundedNoTradeRule;
use App\Models\User;
use App\Models\UserFundedAccountProfile;
use Illuminate\Support\Facades\DB;

class FundedAccountSelectionService
{
    /**
     * Resolve the customer's explicit funded-account selection. If none exists,
     * reuse the latest customer-generated Funded Account Advisor report so
     * ADMIN-27 and ADMIN-31 remain synchronized without duplicating inputs.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(User $user): ?array
    {
        $profile = UserFundedAccountProfile::query()
            ->with('partner')
            ->where('user_id', $user->id)
            ->first();

        if ($profile !== null) {
            if (! $profile->is_enabled || ! $profile->partner?->is_active) {
                return null;
            }

            return [
                'selection_uuid' => $profile->uuid,
                'partner' => $profile->partner,
                'program_name' => $profile->program_name,
                'phase' => $this->normalizePhase($profile->phase),
                'source' => UserFundedAccountProfile::SOURCE_CUSTOMER_SELECTION,
                'selected_at' => $profile->selected_at?->toIso8601String(),
            ];
        }

        $report = FundedAdvisorReport::query()
            ->with('partner')
            ->where('user_id', $user->id)
            ->whereNotNull('partner_id')
            ->latest('generated_at')
            ->latest('id')
            ->first();

        if ($report === null || ! $report->partner?->is_active) {
            return null;
        }

        $inputs = (array) $report->inputs_json;

        return [
            'selection_uuid' => null,
            'partner' => $report->partner,
            'program_name' => filled($inputs['program_name'] ?? null)
                ? trim((string) $inputs['program_name'])
                : null,
            'phase' => $this->normalizePhase((string) ($inputs['phase'] ?? FundedNoTradeRule::PHASE_FUNDED)),
            'source' => 'FUNDED_ADVISOR_REPORT',
            'selected_at' => $report->generated_at?->toIso8601String(),
        ];
    }

    public function save(
        User $user,
        FundedAccountPartner $partner,
        ?string $programName,
        string $phase
    ): UserFundedAccountProfile {
        return DB::transaction(function () use ($user, $partner, $programName, $phase): UserFundedAccountProfile {
            $profile = UserFundedAccountProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'funded_account_partner_id' => $partner->id,
                'program_name' => filled($programName) ? trim((string) $programName) : null,
                'phase' => $this->normalizePhase($phase),
                'is_enabled' => true,
                'source' => UserFundedAccountProfile::SOURCE_CUSTOMER_SELECTION,
                'selected_at' => now(),
            ];

            if ($profile === null) {
                $profile = UserFundedAccountProfile::query()->create(array_merge(
                    ['user_id' => $user->id],
                    $attributes
                ));
            } else {
                $profile->fill($attributes)->save();
            }

            $profile->load('partner');

            return $profile;
        });
    }

    public function disable(User $user): UserFundedAccountProfile
    {
        return DB::transaction(function () use ($user): UserFundedAccountProfile {
            $profile = UserFundedAccountProfile::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                return UserFundedAccountProfile::query()->create([
                    'user_id' => $user->id,
                    'funded_account_partner_id' => null,
                    'program_name' => null,
                    'phase' => FundedNoTradeRule::PHASE_FUNDED,
                    'is_enabled' => false,
                    'source' => UserFundedAccountProfile::SOURCE_CUSTOMER_SELECTION,
                    'selected_at' => now(),
                ]);
            }

            $profile->fill([
                'is_enabled' => false,
                'selected_at' => now(),
            ])->save();

            return $profile;
        });
    }

    /** @param array<string, mixed>|null $resolved */
    public function payload(?array $resolved): ?array
    {
        if ($resolved === null) {
            return null;
        }

        /** @var FundedAccountPartner $partner */
        $partner = $resolved['partner'];

        return [
            'selection_uuid' => $resolved['selection_uuid'],
            'partner_uuid' => $partner->uuid,
            'partner_name' => $partner->name,
            'program_name' => $resolved['program_name'],
            'phase' => $resolved['phase'],
            'source' => $resolved['source'],
            'selected_at' => $resolved['selected_at'],
        ];
    }

    public function normalizePhase(string $phase): string
    {
        return match (strtoupper(trim($phase))) {
            'EVALUATION', FundedNoTradeRule::PHASE_CHALLENGE => FundedNoTradeRule::PHASE_CHALLENGE,
            FundedNoTradeRule::PHASE_VERIFICATION => FundedNoTradeRule::PHASE_VERIFICATION,
            FundedNoTradeRule::PHASE_FUNDED => FundedNoTradeRule::PHASE_FUNDED,
            default => FundedNoTradeRule::PHASE_FUNDED,
        };
    }
}
