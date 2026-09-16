<?php

namespace App\Services\Trading;

use App\Models\EconomicEvent;
use App\Models\FundedAccountPartner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class FundedNoTradeZoneCustomerService
{
    public function __construct(
        private readonly FundedAccountSelectionService $selections,
        private readonly FundedNoTradeZoneService $engine
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshotForUserDate(
        User $user,
        string $date,
        string $timezone,
        ?CarbonInterface $now = null
    ): array {
        $localStart = CarbonImmutable::parse($date.' 00:00:00', $timezone)->startOfDay();
        $localEnd = $localStart->addDay();

        return $this->snapshotForWindow(
            $user,
            $localStart->utc(),
            $localEnd->utc(),
            $timezone,
            $now
        );
    }

    /** @return array<string, mixed> */
    public function snapshotForWindow(
        User $user,
        CarbonInterface $fromUtc,
        CarbonInterface $toUtc,
        string $timezone,
        ?CarbonInterface $now = null
    ): array {
        $from = CarbonImmutable::parse($fromUtc->toIso8601String())->utc();
        $to = CarbonImmutable::parse($toUtc->toIso8601String())->utc();
        $nowUtc = CarbonImmutable::parse(($now ?: CarbonImmutable::now('UTC'))->toIso8601String())->utc();
        $resolved = $this->selections->resolve($user);

        if ($resolved === null) {
            return $this->emptySnapshot($from, $to, $timezone, $nowUtc);
        }

        /** @var FundedAccountPartner $partner */
        $partner = $resolved['partner'];
        $programName = $resolved['program_name'];
        $phase = (string) $resolved['phase'];

        $events = EconomicEvent::query()
            ->with(['pairs', 'activeOverride'])
            ->where('is_published', true)
            ->whereBetween('scheduled_at_utc', [
                $from->subDay(),
                $to->addDay(),
            ])
            ->orderBy('scheduled_at_utc')
            ->get();

        $zones = [];

        foreach ($events as $event) {
            $effective = $event->effectiveIntelligence();
            if (($effective['status'] ?? $event->status) === EconomicEvent::STATUS_CANCELLED) {
                continue;
            }

            $zone = $this->engine->buildZone(
                $partner,
                $event,
                $programName,
                $phase,
                $nowUtc
            );

            if ($zone === null) {
                continue;
            }

            $startsAt = CarbonImmutable::parse((string) $zone['starts_at_utc'])->utc();
            $endsAt = CarbonImmutable::parse((string) $zone['ends_at_utc'])->utc();

            if ($startsAt->gte($to) || $endsAt->lte($from)) {
                continue;
            }

            $startsLocal = $startsAt->setTimezone($timezone);
            $endsLocal = $endsAt->setTimezone($timezone);
            $verification = $this->verificationStatus($zone['verified_at'] ?? null, $nowUtc);

            $zones[] = array_merge($zone, [
                'starts_at_local' => $startsLocal->toIso8601String(),
                'ends_at_local' => $endsLocal->toIso8601String(),
                'timezone' => $timezone,
                'local_window_label' => $startsLocal->format('H:i').' - '.$endsLocal->format('H:i'),
                'rule_verification_status' => $verification,
            ]);
        }

        usort($zones, static fn (array $left, array $right): int => strcmp(
            (string) $left['starts_at_utc'],
            (string) $right['starts_at_utc']
        ));

        $active = collect($zones)->firstWhere('status', 'ACTIVE');
        $upcoming = collect($zones)->firstWhere('status', 'UPCOMING');
        $nextZone = $active ?: $upcoming;
        $ruleCheckRequired = collect($zones)->contains(
            fn (array $zone): bool => ($zone['rule_verification_status'] ?? 'UNVERIFIED') !== 'VERIFIED'
        );

        return [
            'status' => $active !== null
                ? 'NO_TRADE_ACTIVE'
                : ($upcoming !== null ? 'NO_TRADE_UPCOMING' : 'CLEAR'),
            'selected_account' => $this->selections->payload($resolved),
            'rule_check_required' => $ruleCheckRequired,
            'next_zone' => $nextZone,
            'zones' => $zones,
            'zone_count' => count($zones),
            'window' => [
                'from_utc' => $from->toIso8601String(),
                'to_utc' => $to->toIso8601String(),
                'timezone' => $timezone,
            ],
            'generated_at_utc' => $nowUtc->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptySnapshot(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        CarbonImmutable $nowUtc
    ): array {
        return [
            'status' => 'NO_FUNDED_ACCOUNT',
            'selected_account' => null,
            'rule_check_required' => false,
            'next_zone' => null,
            'zones' => [],
            'zone_count' => 0,
            'window' => [
                'from_utc' => $from->toIso8601String(),
                'to_utc' => $to->toIso8601String(),
                'timezone' => $timezone,
            ],
            'generated_at_utc' => $nowUtc->toIso8601String(),
        ];
    }

    private function verificationStatus(mixed $verifiedAt, CarbonImmutable $nowUtc): string
    {
        if (! is_string($verifiedAt) || trim($verifiedAt) === '') {
            return 'UNVERIFIED';
        }

        $verified = CarbonImmutable::parse($verifiedAt)->utc();

        return $verified->lt($nowUtc->subDays(30)) ? 'STALE' : 'VERIFIED';
    }
}
