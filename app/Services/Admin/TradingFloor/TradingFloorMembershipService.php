<?php

namespace App\Services\Admin\TradingFloor;

use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorDesk;
use App\Models\TradingFloorFloor;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradingFloorMembershipService
{
    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data, AdminUser $admin): TradingFloorMembership
    {
        return DB::transaction(function () use ($data, $admin): TradingFloorMembership {
            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_client_id']);
            $package = TradingFloorPackage::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_package_id']);
            $floor = TradingFloorFloor::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_floor_id']);
            $desk = null;

            if (! empty($data['trading_floor_desk_id'])) {
                $desk = TradingFloorDesk::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_desk_id']);
                if ($desk->trading_floor_floor_id !== $floor->id) {
                    throw ValidationException::withMessages([
                        'trading_floor_desk_id' => 'Selected desk does not belong to the selected floor.',
                    ]);
                }

                if ($desk->status !== TradingFloorDesk::STATUS_ACTIVE) {
                    throw ValidationException::withMessages([
                        'trading_floor_desk_id' => 'Selected desk is not currently available for allocation.',
                    ]);
                }
            }

            if ($package->status !== TradingFloorPackage::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'trading_floor_package_id' => 'Selected package is inactive.',
                ]);
            }

            if ($floor->status !== TradingFloorFloor::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'trading_floor_floor_id' => 'Selected floor is inactive.',
                ]);
            }

            $activeStatuses = [
                TradingFloorMembership::STATUS_PENDING,
                TradingFloorMembership::STATUS_ACTIVE,
                TradingFloorMembership::STATUS_SUSPENDED,
            ];

            $clientConflict = TradingFloorMembership::query()
                ->where('trading_floor_client_id', $client->id)
                ->whereIn('status', $activeStatuses)
                ->lockForUpdate()
                ->exists();

            if ($clientConflict) {
                throw ValidationException::withMessages([
                    'trading_floor_client_id' => 'Customer already has an open Trading Floor membership.',
                ]);
            }

            if ($desk !== null) {
                $deskConflict = TradingFloorMembership::query()
                    ->where('trading_floor_desk_id', $desk->id)
                    ->whereIn('status', $activeStatuses)
                    ->lockForUpdate()
                    ->exists();

                if ($deskConflict) {
                    throw ValidationException::withMessages([
                        'trading_floor_desk_id' => 'Selected desk is already allocated to another open membership.',
                    ]);
                }
            }

            $startsAt = CarbonImmutable::parse((string) $data['starts_at'])->startOfDay();
            $expiresAt = $this->resolveExpiry($startsAt, $package, $data['expires_at'] ?? null);
            $status = strtoupper((string) ($data['status'] ?? TradingFloorMembership::STATUS_PENDING));
            $price = round((float) $package->price_amount, 2);
            $paid = round((float) ($data['paid_amount'] ?? 0), 2);

            if ($paid < 0 || $paid > $price) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Paid amount must be between zero and the package price.',
                ]);
            }

            $balance = round($price - $paid, 2);
            $paymentStatus = $balance <= 0
                ? TradingFloorMembership::PAYMENT_PAID
                : ($paid > 0 ? TradingFloorMembership::PAYMENT_PARTIAL : TradingFloorMembership::PAYMENT_PENDING);

            $uuid = (string) Str::uuid();
            $membership = TradingFloorMembership::create([
                'uuid' => $uuid,
                'membership_number' => 'TFM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 10)),
                'trading_floor_client_id' => $client->id,
                'trading_floor_package_id' => $package->id,
                'trading_floor_floor_id' => $floor->id,
                'trading_floor_desk_id' => $desk?->id,
                'starts_at' => $startsAt->toDateString(),
                'expires_at' => $expiresAt->toDateString(),
                'due_date' => $data['due_date'] ?? $startsAt->toDateString(),
                'status' => $status,
                'price_amount' => $price,
                'currency' => $package->currency,
                'billing_cycle' => $package->billing_cycle,
                'payment_status' => $paymentStatus,
                'paid_amount' => $paid,
                'balance_amount' => $balance,
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'activated_at' => $status === TradingFloorMembership::STATUS_ACTIVE ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);

            if ($status === TradingFloorMembership::STATUS_ACTIVE) {
                $this->syncClientAllocation($client, $package, $floor, $desk, $admin);
            }

            return $membership->load(['client', 'package', 'floor', 'desk']);
        }, 3);
    }

    public function activate(TradingFloorMembership $membership, AdminUser $admin): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $admin): TradingFloorMembership {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless($locked->status === TradingFloorMembership::STATUS_PENDING, 422, 'Only pending memberships can be activated.');

            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($locked->trading_floor_client_id);
            $package = TradingFloorPackage::query()->findOrFail($locked->trading_floor_package_id);
            $floor = TradingFloorFloor::query()->findOrFail($locked->trading_floor_floor_id);
            $desk = $locked->trading_floor_desk_id
                ? TradingFloorDesk::query()->lockForUpdate()->findOrFail($locked->trading_floor_desk_id)
                : null;

            if ($desk !== null) {
                $conflict = TradingFloorMembership::query()
                    ->where('trading_floor_desk_id', $desk->id)
                    ->where('id', '<>', $locked->id)
                    ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
                    ->exists();
                abort_if($conflict, 422, 'Desk is already allocated to another active membership.');
            }

            $locked->update([
                'status' => TradingFloorMembership::STATUS_ACTIVE,
                'activated_at' => now(),
                'updated_by_admin_id' => $admin->id,
            ]);

            $this->syncClientAllocation($client, $package, $floor, $desk, $admin);

            return $locked->fresh(['client', 'package', 'floor', 'desk']);
        }, 3);
    }

    public function cancel(TradingFloorMembership $membership, AdminUser $admin, ?string $reason = null): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $admin, $reason): TradingFloorMembership {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_if(in_array($locked->status, [TradingFloorMembership::STATUS_CANCELLED, TradingFloorMembership::STATUS_EXPIRED], true), 422, 'Membership is already closed.');

            $locked->update([
                'status' => TradingFloorMembership::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'notes' => trim(implode("\n", array_filter([$locked->notes, $reason ? 'Cancellation: '.$reason : null]))),
                'updated_by_admin_id' => $admin->id,
            ]);

            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($locked->trading_floor_client_id);
            $client->update([
                'floor_code' => null,
                'desk_code' => null,
                'package_code' => null,
                'updated_by_admin_id' => $admin->id,
            ]);

            return $locked->fresh(['client', 'package', 'floor', 'desk']);
        }, 3);
    }

    private function resolveExpiry(CarbonImmutable $startsAt, TradingFloorPackage $package, mixed $explicit): CarbonImmutable
    {
        if ($explicit) {
            $date = CarbonImmutable::parse((string) $explicit)->startOfDay();
            if ($date->lessThan($startsAt)) {
                throw ValidationException::withMessages(['expires_at' => 'Expiry date cannot be before the membership start date.']);
            }
            return $date;
        }

        if ($package->duration_days) {
            return $startsAt->addDays(max(1, (int) $package->duration_days) - 1);
        }

        return match ($package->billing_cycle) {
            'WEEKLY' => $startsAt->addWeek()->subDay(),
            'QUARTERLY' => $startsAt->addMonthsNoOverflow(3)->subDay(),
            'YEARLY' => $startsAt->addYear()->subDay(),
            default => $startsAt->addMonthNoOverflow()->subDay(),
        };
    }

    private function syncClientAllocation(
        TradingFloorClient $client,
        TradingFloorPackage $package,
        TradingFloorFloor $floor,
        ?TradingFloorDesk $desk,
        AdminUser $admin
    ): void {
        $updates = [
            'floor_code' => $floor->code,
            'desk_code' => $desk?->code,
            'package_code' => $package->code,
            'updated_by_admin_id' => $admin->id,
        ];

        if ($package->commission_type) {
            $updates['commission_type'] = $package->commission_type;
            $updates['commission_rate'] = $package->commission_rate;
            $updates['fixed_commission_amount'] = $package->fixed_commission_amount;
            $updates['commission_currency'] = $package->currency;
            $updates['billing_cycle'] = $package->billing_cycle;
        }

        $client->update($updates);
    }
}
