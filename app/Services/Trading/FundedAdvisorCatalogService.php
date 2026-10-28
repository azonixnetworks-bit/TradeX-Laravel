<?php

namespace App\Services\Trading;

use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class FundedAdvisorCatalogService
{
    /** @return array{version: string, generated_at: string, partners: array<int, array<string, mixed>>} */
    public function snapshot(?CarbonInterface $at = null): array
    {
        $now = $at === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::instance($at)->utc();

        $partners = FundedAccountPartner::query()
            ->where('is_active', true)
            ->with(['noTradeRules' => function ($query) use ($now): void {
                $query
                    ->where('is_active', true)
                    ->where(function ($effective) use ($now): void {
                        $effective
                            ->whereNull('effective_from')
                            ->orWhere('effective_from', '<=', $now);
                    })
                    ->where(function ($expires) use ($now): void {
                        $expires
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now);
                    })
                    ->orderBy('priority')
                    ->orderBy('program_name')
                    ->orderBy('phase')
                    ->orderBy('uuid');
            }])
            ->orderByDesc('is_featured')
            ->orderBy('priority')
            ->orderBy('name')
            ->orderBy('uuid')
            ->get();

        $public = $partners
            ->map(fn (FundedAccountPartner $partner): array => $this->partner($partner))
            ->values()
            ->all();

        $versionProjection = $partners
            ->map(function (FundedAccountPartner $partner): array {
                $public = $this->partner($partner);
                $public['_logo_fingerprint'] = $partner->hasLogo()
                    ? hash('sha256', implode('|', [
                        (string) $partner->logo_disk,
                        (string) $partner->logo_path,
                        (string) $partner->logo_mime_type,
                        (string) $partner->logo_size_bytes,
                        (string) $partner->updated_at?->toJSON(),
                    ]))
                    : null;

                return $public;
            })
            ->values()
            ->all();

        return [
            'version' => hash(
                'sha256',
                json_encode(
                    $versionProjection,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
                )
            ),
            'generated_at' => $now->toIso8601String(),
            'partners' => $public,
        ];
    }

    /** @return array<string, mixed> */
    private function partner(FundedAccountPartner $partner): array
    {
        return [
            'uuid' => (string) $partner->uuid,
            'name' => (string) $partner->name,
            'website_url' => $this->safeUrl($partner->website_url),
            'affiliate_url' => $this->safeUrl($partner->affiliate_url),
            'promo_code' => $partner->promo_code,
            'offer_text' => $partner->offer_text,
            'disclosure' => $partner->disclosure,
            'supported_countries' => $this->strings($partner->supported_countries),
            'account_sizes' => $this->values($partner->account_sizes),
            'challenge_types' => $this->strings($partner->challenge_types),
            'logo_url' => $partner->hasLogo()
                ? route('api.v1.funded-advisor.partners.logo', $partner)
                : null,
            'is_featured' => (bool) $partner->is_featured,
            'priority' => (int) $partner->priority,
            'rules' => $partner->noTradeRules
                ->map(fn (FundedNoTradeRule $rule): array => $this->rule($rule))
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function rule(FundedNoTradeRule $rule): array
    {
        return [
            'uuid' => (string) $rule->uuid,
            'program_name' => $rule->program_name,
            'phase' => (string) $rule->phase,
            'policy' => (string) $rule->policy,
            'enforcement_type' => (string) $rule->enforcement_type,
            'impact_levels' => $this->strings($rule->impact_levels),
            'event_keywords' => $this->strings($rule->event_keywords),
            'minutes_before' => (int) $rule->minutes_before,
            'minutes_after' => (int) $rule->minutes_after,
            'affected_instruments_only' => (bool) $rule->affected_instruments_only,
            'restricted_currencies' => $this->strings($rule->restricted_currencies),
            'restricted_instruments' => $this->strings($rule->restricted_instruments),
            'block_open_new_trades' => (bool) $rule->block_open_new_trades,
            'block_close_trades' => (bool) $rule->block_close_trades,
            'block_pending_orders' => (bool) $rule->block_pending_orders,
            'rule_summary' => $rule->rule_summary,
            'source_url' => $this->safeUrl($rule->source_url),
            'source_reference' => $rule->source_reference,
            'verified_at' => $rule->verified_at?->utc()->toIso8601String(),
            'effective_from' => $rule->effective_from?->utc()->toIso8601String(),
            'expires_at' => $rule->expires_at?->utc()->toIso8601String(),
            'priority' => (int) $rule->priority,
        ];
    }

    private function safeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }

    /** @return array<int, string> */
    private function strings(mixed $value): array
    {
        return array_values(array_map(
            static fn (mixed $item): string => trim((string) $item),
            is_array($value) ? $value : []
        ));
    }

    /** @return array<int, mixed> */
    private function values(mixed $value): array
    {
        return array_values(is_array($value) ? $value : []);
    }
}
