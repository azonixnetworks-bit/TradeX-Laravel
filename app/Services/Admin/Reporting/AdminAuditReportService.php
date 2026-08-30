<?php

namespace App\Services\Admin\Reporting;

use App\Models\AuditLog;
use App\Support\Reporting\ReportDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

final class AdminAuditReportService
{
    private const REDACTED = '[REDACTED]';
    private const MAX_DEPTH = 6;
    private const MAX_ARRAY_ITEMS = 100;
    private const MAX_STRING_LENGTH = 1000;

    public function __construct(
        private readonly ReportDateRange $ranges
    ) {
    }

    public function prepare(array $filters): array
    {
        $timezone = (string) config(
            'tradex_reporting.audit_timezone',
            'Asia/Colombo'
        );

        $range = $this->ranges->resolve(
            $filters['from'] ?? null,
            $filters['to'] ?? null,
            $timezone
        );

        $normalized = $this->normalizeFilters($filters);
        $maxId = (int) (AuditLog::query()->max('id') ?? 0);

        $query = $this->query(
            $normalized,
            $range,
            $maxId
        );

        return [
            'range' => $range,
            'filters' => $normalized,
            'max_id' => $maxId,
            'row_count' => (clone $query)->count(),
        ];
    }

    public function cursor(array $prepared): LazyCollection
    {
        return $this->query(
            $prepared['filters'],
            $prepared['range'],
            (int) $prepared['max_id']
        )
            ->orderBy('id')
            ->cursor();
    }

    public function row(
        AuditLog $log,
        string $timezone
    ): array {
        return [
            $log->getKey(),
            $log->created_at?->timezone($timezone)->toIso8601String() ?? '',
            $log->actor_type,
            $log->actor_id,
            $log->action,
            $log->module,
            $log->target_type,
            $log->target_id,
            $log->ip_address,
            $log->user_agent,
            $this->json($this->sanitize($log->old_values)),
            $this->json($this->sanitize($log->new_values)),
        ];
    }

    private function query(
        array $filters,
        array $range,
        int $maxId
    ): Builder {
        $query = AuditLog::query()
            ->where('id', '<=', $maxId)
            ->whereBetween(
                'created_at',
                [$range['start'], $range['end']]
            );

        $search = $filters['search'];

        if ($search !== '') {
            $query->where(
                function (Builder $builder) use ($search): void {
                    $builder
                        ->where('action', 'like', '%'.$search.'%')
                        ->orWhere('module', 'like', '%'.$search.'%')
                        ->orWhere('actor_type', 'like', '%'.$search.'%')
                        ->orWhere('target_type', 'like', '%'.$search.'%')
                        ->orWhere('ip_address', 'like', '%'.$search.'%')
                        ->orWhere('user_agent', 'like', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $builder
                            ->orWhere('id', (int) $search)
                            ->orWhere('actor_id', (int) $search)
                            ->orWhere('target_id', (int) $search);
                    }
                }
            );
        }

        if ($filters['actor_type'] !== null) {
            $query->where('actor_type', $filters['actor_type']);
        }

        if ($filters['actor_id'] !== null) {
            $query->where('actor_id', $filters['actor_id']);
        }

        foreach (['action', 'module', 'target_type'] as $column) {
            if ($filters[$column] !== null) {
                $query->where($column, $filters[$column]);
            }
        }

        if ($filters['target_id'] !== null) {
            $query->where('target_id', $filters['target_id']);
        }

        return $query;
    }

    private function normalizeFilters(array $filters): array
    {
        $actorType = strtoupper(
            trim((string) ($filters['actor_type'] ?? ''))
        );

        return [
            'search' => Str::limit(
                trim((string) ($filters['search'] ?? '')),
                191,
                ''
            ),
            'actor_type' => in_array(
                $actorType,
                ['ADMIN', 'USER', 'SYSTEM'],
                true
            ) ? $actorType : null,
            'actor_id' => $this->positiveInt($filters['actor_id'] ?? null),
            'action' => $this->nullableString($filters['action'] ?? null, 191),
            'module' => $this->nullableString($filters['module'] ?? null, 100),
            'target_type' => $this->nullableString($filters['target_type'] ?? null, 100),
            'target_id' => $this->positiveInt($filters['target_id'] ?? null),
        ];
    }

    private function sanitize(
        mixed $value,
        int $depth = 0,
        ?string $key = null
    ): mixed {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[TRUNCATED: MAX DEPTH]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;

            foreach ($value as $childKey => $childValue) {
                if ($count >= self::MAX_ARRAY_ITEMS) {
                    $clean['_truncated'] = '[TRUNCATED: TOO MANY ITEMS]';
                    break;
                }

                $stringKey = is_string($childKey)
                    ? $childKey
                    : (string) $childKey;

                $clean[$childKey] = $this->sanitize(
                    $childValue,
                    $depth + 1,
                    $stringKey
                );

                $count++;
            }

            return $clean;
        }

        if (is_string($value)) {
            return Str::limit(
                $value,
                self::MAX_STRING_LENGTH,
                '… [TRUNCATED]'
            );
        }

        if (
            is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        return is_scalar($value)
            ? Str::limit(
                (string) $value,
                self::MAX_STRING_LENGTH,
                '… [TRUNCATED]'
            )
            : '[UNSUPPORTED VALUE]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(
            trim(
                str_replace(
                    ['-', ' ', '.'],
                    '_',
                    $key
                )
            )
        );

        $exact = [
            'password', 'passcode', 'secret', 'authorization', 'cookie',
            'otp', 'totp', 'recovery_code', 'recovery_codes', 'credential',
            'credentials', 'private_key', 'public_key', 'api_key',
            'client_secret', 'access_key', 'identity_number', 'account_number',
            'bank_account', 'card_number', 'cvv', 'cvc', 'destination_snapshot',
            'provider_reference', 'capture_reference', 'storage_path',
            'original_name', 'wallet_address', 'usdt_address', 'trc20_address',
        ];

        if (in_array($normalized, $exact, true)) {
            return true;
        }

        foreach ([
            '_password', 'password_', '_secret', 'secret_', '_token', 'token_',
            '_hash', 'hash_', '_hmac', 'hmac_', '_signature', 'signature_',
            'authorization', 'cookie', 'private_key', 'public_key',
            'identity_number', 'account_number', 'provider_reference',
            'capture_reference', 'storage_path', 'wallet_address',
        ] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function json(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($encoded) ? $encoded : '';
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '' || ! ctype_digit($text)) {
            return null;
        }

        $number = (int) $text;

        return $number > 0 ? $number : null;
    }

    private function nullableString(
        mixed $value,
        int $limit
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === ''
            ? null
            : Str::limit($text, $limit, '');
    }
}
