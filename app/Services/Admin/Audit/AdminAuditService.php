<?php

namespace App\Services\Admin\Audit;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class AdminAuditService
{
    private const REDACTED = '[REDACTED]';
    private const MAX_DEPTH = 6;
    private const MAX_ARRAY_ITEMS = 100;
    private const MAX_STRING_LENGTH = 1000;

    public function index(array $filters): array
    {
        $query = AuditLog::query();
        $this->applyFilters($query, $filters);

        return [
            'logs' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($this->perPage($filters))
                ->withQueryString(),
            'stats' => $this->stats(),
            'actions' => $this->options('action'),
            'modules' => $this->options('module'),
            'target_types' => $this->options('target_type'),
            'actor_types' => ['ADMIN', 'USER', 'SYSTEM'],
        ];
    }

    public function detail(
        AuditLog $auditLog,
        AdminUser $admin,
        Request $request
    ): array {
        $actor = $this->resolveActor($auditLog);
        $oldValues = $this->sanitize($auditLog->old_values);
        $newValues = $this->sanitize($auditLog->new_values);

        // Viewing a detail is security-relevant, but the inspected row stays immutable.
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'AUDIT_LOG_DETAIL_ACCESSED',
            module: 'AUDIT',
            targetType: 'AUDIT_LOG',
            targetId: $auditLog->getKey(),
            newValues: [
                'inspected_action' => $this->boundedString((string) $auditLog->action, 120),
                'inspected_module' => $this->nullableBoundedString($auditLog->module, 100),
                'inspected_actor_type' => $this->boundedString((string) $auditLog->actor_type, 30),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'log' => $auditLog,
            'actor' => $actor,
            'old_values_json' => $this->prettyJson($oldValues),
            'new_values_json' => $this->prettyJson($newValues),
            'redaction_notice' => 'Sensitive-looking audit payload keys are redacted before rendering. The immutable database row is not modified.',
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
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
            });
        }

        $actorType = strtoupper(trim((string) ($filters['actor_type'] ?? '')));
        if (in_array($actorType, ['ADMIN', 'USER', 'SYSTEM'], true)) {
            $query->where('actor_type', $actorType);
        }

        if (($actorId = $this->positiveInt($filters['actor_id'] ?? null)) !== null) {
            $query->where('actor_id', $actorId);
        }

        foreach (['action', 'module', 'target_type'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));
            if ($value !== '') {
                $query->where($column, $value);
            }
        }

        if (($targetId = $this->positiveInt($filters['target_id'] ?? null)) !== null) {
            $query->where('target_id', $targetId);
        }

        $from = trim((string) ($filters['from'] ?? ''));
        if (
            $from !== ''
            && ($boundary = $this->dateBoundary($from, false)) !== null
        ) {
            $query->where('created_at', '>=', $boundary);
        }

        $to = trim((string) ($filters['to'] ?? ''));
        if (
            $to !== ''
            && ($boundary = $this->dateBoundary($to, true)) !== null
        ) {
            $query->where('created_at', '<=', $boundary);
        }
    }

    private function stats(): array
    {
        $since = now()->subDay();

        return [
            'total' => AuditLog::query()->count(),
            'last_24h' => AuditLog::query()->where('created_at', '>=', $since)->count(),
            'admin_last_24h' => AuditLog::query()->where('actor_type', 'ADMIN')->where('created_at', '>=', $since)->count(),
            'user_last_24h' => AuditLog::query()->where('actor_type', 'USER')->where('created_at', '>=', $since)->count(),
            'system_last_24h' => AuditLog::query()->where('actor_type', 'SYSTEM')->where('created_at', '>=', $since)->count(),
        ];
    }

    private function options(string $column): array
    {
        if (! in_array($column, ['action', 'module', 'target_type'], true)) {
            return [];
        }

        return AuditLog::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
    }

    private function resolveActor(AuditLog $auditLog): array
    {
        $type = strtoupper(trim((string) $auditLog->actor_type));
        $id = $auditLog->actor_id;

        if ($type === 'ADMIN' && $id !== null) {
            $actor = AdminUser::query()->find($id);
            return [
                'type' => 'ADMIN',
                'id' => $id,
                'name' => $actor?->name,
                'email' => $actor?->email,
                'resolved' => $actor !== null,
            ];
        }

        if ($type === 'USER' && $id !== null) {
            $actor = User::withTrashed()->find($id);
            return [
                'type' => 'USER',
                'id' => $id,
                'name' => $actor?->name,
                'email' => $actor?->email,
                'resolved' => $actor !== null,
            ];
        }

        if ($type === 'SYSTEM') {
            return [
                'type' => 'SYSTEM',
                'id' => null,
                'name' => 'TradeX System',
                'email' => null,
                'resolved' => true,
            ];
        }

        return [
            'type' => $type !== '' ? $type : 'UNKNOWN',
            'id' => $id,
            'name' => null,
            'email' => null,
            'resolved' => false,
        ];
    }

    private function sanitize(mixed $value, int $depth = 0, ?string $key = null): mixed
    {
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

                $stringKey = is_string($childKey) ? $childKey : (string) $childKey;
                $clean[$childKey] = $this->sanitize($childValue, $depth + 1, $stringKey);
                $count++;
            }

            return $clean;
        }

        if (is_string($value)) {
            return $this->boundedString($value, self::MAX_STRING_LENGTH);
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if (is_scalar($value)) {
            return $this->boundedString((string) $value, self::MAX_STRING_LENGTH);
        }

        return '[UNSUPPORTED VALUE]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim(str_replace(['-', ' ', '.'], '_', $key)));

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

    private function prettyJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $encoded = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($encoded) ? $encoded : null;
    }

    private function perPage(array $filters): int
    {
        $requested = (int) ($filters['per_page'] ?? 20);
        return in_array($requested, [10, 20, 50, 100], true) ? $requested : 20;
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

    private function boundedString(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit).'… [TRUNCATED]';
    }

    private function nullableBoundedString(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        return $text !== '' ? $this->boundedString($text, $limit) : null;
    }

    private function dateBoundary(
        string $date,
        bool $endOfDay
    ): ?CarbonImmutable {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $local = CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $date.' 00:00:00',
                (string) config(
                    'tradex_reporting.audit_timezone',
                    'Asia/Colombo'
                )
            );
        } catch (Throwable) {
            return null;
        }

        return ($endOfDay ? $local->endOfDay() : $local->startOfDay())
            ->timezone(
                (string) config('app.timezone', 'UTC')
            );
    }
}
