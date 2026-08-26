<?php

namespace App\Services\Bootstrap;

use App\Exceptions\MobileBootstrapApiException;
use App\Models\SystemSetting;
use App\Services\Languages\RuntimeTranslationService;
use App\Services\Legal\LegalPolicyApiService;
use JsonException;

class MobileBootstrapService
{
    public function __construct(
        private readonly RuntimeTranslationService $translations,
        private readonly LegalPolicyApiService $legal
    ) {
    }

    public function bootstrap(): array
    {
        $payload = [
            'runtime_state' => (string) config(
                'tradex_mobile_bootstrap.runtime_state',
                'WIRED_SAFE_BOOTSTRAP'
            ),
            'schema_version' => (int) config(
                'tradex_mobile_bootstrap.schema_version',
                1
            ),
            'settings' => $this->safeSettings(),
            'languages' => $this->translations->languages(),
            'legal' => $this->legal->currentPolicies(),
        ];

        $payload['bootstrap_sha256'] = $this->hashPayload($payload);

        return $payload;
    }

    private function safeSettings(): array
    {
        $allowedKeys = (array) config(
            'tradex_mobile_bootstrap.safe_general_keys',
            []
        );

        if ($allowedKeys === []) {
            throw new MobileBootstrapApiException(
                503,
                'BOOTSTRAP_SAFE_SETTING_REGISTRY_INVALID',
                'The safe mobile settings registry is not available.'
            );
        }

        $definitions = (array) config('tradex_settings.general', []);

        $rows = SystemSetting::query()
            ->whereIn('setting_key', $allowedKeys)
            ->get()
            ->keyBy('setting_key');

        $resolved = [];

        foreach ($allowedKeys as $settingKey) {
            $definition = $definitions[$settingKey] ?? null;

            if (
                ! is_array($definition)
                || ! isset($definition['field'], $definition['type'])
                || ! array_key_exists('default', $definition)
            ) {
                throw new MobileBootstrapApiException(
                    503,
                    'BOOTSTRAP_SAFE_SETTING_REGISTRY_INVALID',
                    'The safe mobile settings registry is inconsistent.'
                );
            }

            $field = (string) $definition['field'];
            $type = (string) $definition['type'];
            $row = $rows->get($settingKey);

            if ($row instanceof SystemSetting) {
                if (
                    $row->group_name !== 'GENERAL'
                    || $row->value_type !== $type
                ) {
                    throw new MobileBootstrapApiException(
                        503,
                        'BOOTSTRAP_SAFE_SETTING_INTEGRITY_FAILED',
                        'A safe mobile setting failed an integrity check.'
                    );
                }

                $resolved[$field] = $this->decode(
                    $row->value,
                    $type
                );
            } else {
                $resolved[$field] = $definition['default'];
            }
        }

        $platformName = trim((string) ($resolved['platform_name'] ?? ''));
        $maintenanceEnabled =
            (bool) ($resolved['maintenance_notice_enabled'] ?? false);

        return [
            'platform_name' =>
                $platformName !== '' ? $platformName : 'TradeX OS',
            'support_email' =>
                $this->nullableString($resolved['support_email'] ?? null),
            'support_whatsapp' =>
                $this->nullableString($resolved['support_whatsapp'] ?? null),
            'maintenance_notice_enabled' => $maintenanceEnabled,
            'maintenance_notice' =>
                $maintenanceEnabled
                    ? $this->nullableString(
                        $resolved['maintenance_notice'] ?? null
                    )
                    : null,
        ];
    }

    private function decode(?string $value, string $type): mixed
    {
        return match ($type) {
            'BOOL' => $value === '1',
            'STRING' => $value,
            default => throw new MobileBootstrapApiException(
                503,
                'BOOTSTRAP_SAFE_SETTING_INTEGRITY_FAILED',
                'A safe mobile setting uses an unsupported value type.'
            ),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    /**
     * @throws JsonException
     */
    private function hashPayload(array $payload): string
    {
        return hash(
            'sha256',
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            )
        );
    }
}
