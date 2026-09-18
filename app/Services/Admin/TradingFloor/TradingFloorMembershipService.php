<?php

namespace App\Services\Admin\TradingFloor;

use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorDesk;
use App\Models\TradingFloorFloor;
use App\Models\TradingFloorMembership;
use App\Models\TradingFloorMembershipChange;
use App\Models\TradingFloorMembershipPayment;
use App\Models\TradingFloorPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradingFloorMembershipService
{
    /** @param array<string,mixed> $data */
    public function create(array $data, AdminUser $admin): TradingFloorMembership
    {
        return DB::transaction(function () use ($data, $admin): TradingFloorMembership {
            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_client_id']);
            $package = TradingFloorPackage::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_package_id']);
            $floor = TradingFloorFloor::query()->lockForUpdate()->findOrFail((int) $data['trading_floor_floor_id']);
            $desk = $this->lockedDesk($data['trading_floor_desk_id'] ?? null, $floor);

            $this->assertResourcesActive($package, $floor, $desk);

            $activeStatuses = $this->openStatuses();
            if (TradingFloorMembership::query()->where('trading_floor_client_id', $client->id)->whereIn('status', $activeStatuses)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['trading_floor_client_id' => 'Customer already has an open Trading Floor membership.']);
            }

            $this->assertDeskAvailable($desk, null);

            $startsAt = CarbonImmutable::parse((string) $data['starts_at'])->startOfDay();
            $expiresAt = $this->resolveExpiry($startsAt, $package, $data['expires_at'] ?? null);
            $status = strtoupper((string) ($data['status'] ?? TradingFloorMembership::STATUS_PENDING));
            $price = round((float) $package->price_amount, 2);
            $paid = round((float) ($data['paid_amount'] ?? 0), 2);

            if ($paid < 0 || $paid > $price) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount must be between zero and the package price.']);
            }

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
                'payment_status' => $this->paymentStatus($price, $paid),
                'paid_amount' => $paid,
                'balance_amount' => round($price - $paid, 2),
                'auto_renew' => (bool) ($data['auto_renew'] ?? false),
                'activated_at' => $status === TradingFloorMembership::STATUS_ACTIVE ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);

            if ($paid > 0) {
                TradingFloorMembershipPayment::create([
                    'trading_floor_membership_id' => $membership->id,
                    'amount' => $paid,
                    'currency' => $membership->currency,
                    'payment_method' => 'OPENING_BALANCE',
                    'received_at' => now(),
                    'received_by_admin_id' => $admin->id,
                    'status' => TradingFloorMembershipPayment::STATUS_RECEIVED,
                    'notes' => 'Opening payment captured during membership creation.',
                ]);
            }

            if ($status === TradingFloorMembership::STATUS_ACTIVE) {
                $this->syncClientAllocation($client, $package, $floor, $desk, $admin);
            }

            return $membership->load(['client', 'package', 'floor', 'desk', 'payments']);
        }, 3);
    }

    public function activate(TradingFloorMembership $membership, AdminUser $admin): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $admin): TradingFloorMembership {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless($locked->status === TradingFloorMembership::STATUS_PENDING, 422, 'Only pending memberships can be activated.');

            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($locked->trading_floor_client_id);
            $package = TradingFloorPackage::query()->lockForUpdate()->findOrFail($locked->trading_floor_package_id);
            $floor = TradingFloorFloor::query()->lockForUpdate()->findOrFail($locked->trading_floor_floor_id);
            $desk = $this->lockedDesk($locked->trading_floor_desk_id, $floor);
            $this->assertResourcesActive($package, $floor, $desk);
            $this->assertDeskAvailable($desk, $locked->id);

            $locked->update(['status' => TradingFloorMembership::STATUS_ACTIVE, 'activated_at' => now(), 'updated_by_admin_id' => $admin->id]);
            $this->syncClientAllocation($client, $package, $floor, $desk, $admin);

            return $locked->fresh(['client', 'package', 'floor', 'desk', 'payments', 'changes']);
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
            $this->releaseClientAllocationIfOwned($locked, $admin->id);

            return $locked->fresh(['client', 'package', 'floor', 'desk']);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function renew(TradingFloorMembership $membership, array $data, AdminUser $admin): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $data, $admin): TradingFloorMembership {
            $source = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless(in_array($source->status, [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_EXPIRED], true), 422, 'Only active or expired memberships can be renewed.');

            if (TradingFloorMembership::query()->where('renewed_from_membership_id', $source->id)->whereIn('status', $this->openStatuses())->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['renewal' => 'An open renewal already exists for this membership.']);
            }

            $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($source->trading_floor_client_id);
            $package = TradingFloorPackage::query()->lockForUpdate()->findOrFail((int) ($data['trading_floor_package_id'] ?? $source->trading_floor_package_id));
            $floor = TradingFloorFloor::query()->lockForUpdate()->findOrFail((int) ($data['trading_floor_floor_id'] ?? $source->trading_floor_floor_id));
            $deskId = array_key_exists('trading_floor_desk_id', $data) ? $data['trading_floor_desk_id'] : $source->trading_floor_desk_id;
            $desk = $this->lockedDesk($deskId, $floor);
            $this->assertResourcesActive($package, $floor, $desk);
            $this->assertDeskAvailable($desk, $source->id);

            $defaultStart = CarbonImmutable::parse($source->expires_at)->addDay()->startOfDay();
            if ($defaultStart->lessThan(CarbonImmutable::today())) {
                $defaultStart = CarbonImmutable::today();
            }
            $startsAt = filled($data['starts_at'] ?? null) ? CarbonImmutable::parse((string) $data['starts_at'])->startOfDay() : $defaultStart;
            $expiresAt = $this->resolveExpiry($startsAt, $package, $data['expires_at'] ?? null);
            $uuid = (string) Str::uuid();
            $price = round((float) $package->price_amount, 2);

            $renewal = TradingFloorMembership::create([
                'uuid' => $uuid,
                'membership_number' => 'TFM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 10)),
                'trading_floor_client_id' => $client->id,
                'trading_floor_package_id' => $package->id,
                'trading_floor_floor_id' => $floor->id,
                'trading_floor_desk_id' => $desk?->id,
                'starts_at' => $startsAt->toDateString(),
                'expires_at' => $expiresAt->toDateString(),
                'due_date' => $data['due_date'] ?? $startsAt->toDateString(),
                'status' => TradingFloorMembership::STATUS_PENDING,
                'price_amount' => $price,
                'currency' => $package->currency,
                'billing_cycle' => $package->billing_cycle,
                'payment_status' => TradingFloorMembership::PAYMENT_PENDING,
                'paid_amount' => 0,
                'balance_amount' => $price,
                'auto_renew' => (bool) ($data['auto_renew'] ?? $source->auto_renew),
                'renewed_from_membership_id' => $source->id,
                'notes' => $data['notes'] ?? null,
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]);

            $this->logChange($source, TradingFloorMembershipChange::TYPE_RENEWAL, $admin->id, [
                'old_package_id' => $source->trading_floor_package_id,
                'new_package_id' => $package->id,
                'old_floor_id' => $source->trading_floor_floor_id,
                'new_floor_id' => $floor->id,
                'old_desk_id' => $source->trading_floor_desk_id,
                'new_desk_id' => $desk?->id,
                'old_expires_at' => $source->expires_at?->toDateString(),
                'new_expires_at' => $expiresAt->toDateString(),
                'reason' => $data['reason'] ?? 'Manual membership renewal',
                'metadata' => ['renewal_membership_id' => $renewal->id, 'renewal_membership_number' => $renewal->membership_number],
            ]);

            return $renewal->load(['client', 'package', 'floor', 'desk']);
        }, 3);
    }

    public function changePackage(TradingFloorMembership $membership, TradingFloorPackage $package, AdminUser $admin, string $reason): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $package, $admin, $reason): TradingFloorMembership {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless(in_array($locked->status, $this->openStatuses(), true), 422, 'Closed memberships cannot change package.');
            $target = TradingFloorPackage::query()->lockForUpdate()->findOrFail($package->id);
            abort_unless($target->status === TradingFloorPackage::STATUS_ACTIVE, 422, 'Target package is inactive.');

            $paid = round((float) $locked->paid_amount, 2);
            $price = round((float) $target->price_amount, 2);
            if ($paid > 0 && strtoupper($locked->currency) !== strtoupper($target->currency)) {
                throw ValidationException::withMessages(['trading_floor_package_id' => 'Cannot switch currency after payment has been recorded. Reverse or resolve payments first.']);
            }
            if ($paid > $price) {
                throw ValidationException::withMessages(['trading_floor_package_id' => 'Target package price is below the amount already paid. Resolve the credit before downgrading.']);
            }

            $oldPackageId = $locked->trading_floor_package_id;
            $locked->update([
                'trading_floor_package_id' => $target->id,
                'price_amount' => $price,
                'currency' => $target->currency,
                'billing_cycle' => $target->billing_cycle,
                'balance_amount' => round($price - $paid, 2),
                'payment_status' => $this->paymentStatus($price, $paid),
                'updated_by_admin_id' => $admin->id,
            ]);

            if ($locked->status === TradingFloorMembership::STATUS_ACTIVE) {
                $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($locked->trading_floor_client_id);
                $floor = TradingFloorFloor::query()->findOrFail($locked->trading_floor_floor_id);
                $desk = $locked->trading_floor_desk_id ? TradingFloorDesk::query()->findOrFail($locked->trading_floor_desk_id) : null;
                $this->syncClientAllocation($client, $target, $floor, $desk, $admin);
            }

            $this->logChange($locked, TradingFloorMembershipChange::TYPE_PACKAGE_CHANGE, $admin->id, [
                'old_package_id' => $oldPackageId,
                'new_package_id' => $target->id,
                'reason' => $reason,
                'metadata' => ['new_price_amount' => $price, 'currency' => $target->currency],
            ]);

            return $locked->fresh(['client', 'package', 'floor', 'desk', 'payments', 'changes']);
        }, 3);
    }

    public function transfer(TradingFloorMembership $membership, TradingFloorFloor $floor, ?TradingFloorDesk $desk, AdminUser $admin, string $reason): TradingFloorMembership
    {
        return DB::transaction(function () use ($membership, $floor, $desk, $admin, $reason): TradingFloorMembership {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            abort_unless(in_array($locked->status, $this->openStatuses(), true), 422, 'Closed memberships cannot transfer desks.');
            $targetFloor = TradingFloorFloor::query()->lockForUpdate()->findOrFail($floor->id);
            $targetDesk = $desk ? TradingFloorDesk::query()->lockForUpdate()->findOrFail($desk->id) : null;
            $package = TradingFloorPackage::query()->findOrFail($locked->trading_floor_package_id);
            $this->assertResourcesActive($package, $targetFloor, $targetDesk);
            if ($targetDesk !== null && $targetDesk->trading_floor_floor_id !== $targetFloor->id) {
                throw ValidationException::withMessages(['trading_floor_desk_id' => 'Selected desk does not belong to the selected floor.']);
            }
            $this->assertDeskAvailable($targetDesk, $locked->id);

            $oldFloorId = $locked->trading_floor_floor_id;
            $oldDeskId = $locked->trading_floor_desk_id;
            $locked->update([
                'trading_floor_floor_id' => $targetFloor->id,
                'trading_floor_desk_id' => $targetDesk?->id,
                'updated_by_admin_id' => $admin->id,
            ]);

            if ($locked->status === TradingFloorMembership::STATUS_ACTIVE) {
                $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($locked->trading_floor_client_id);
                $this->syncClientAllocation($client, $package, $targetFloor, $targetDesk, $admin);
            }

            $this->logChange($locked, TradingFloorMembershipChange::TYPE_DESK_TRANSFER, $admin->id, [
                'old_floor_id' => $oldFloorId,
                'new_floor_id' => $targetFloor->id,
                'old_desk_id' => $oldDeskId,
                'new_desk_id' => $targetDesk?->id,
                'reason' => $reason,
            ]);

            return $locked->fresh(['client', 'package', 'floor', 'desk', 'payments', 'changes']);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function recordPayment(TradingFloorMembership $membership, array $data, AdminUser $admin): TradingFloorMembershipPayment
    {
        return DB::transaction(function () use ($membership, $data, $admin): TradingFloorMembershipPayment {
            $locked = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $amount = round((float) $data['amount'], 2);
            abort_if($amount <= 0, 422, 'Payment amount must be greater than zero.');
            abort_if($amount > round((float) $locked->balance_amount, 2), 422, 'Payment amount cannot exceed membership balance.');

            $payment = TradingFloorMembershipPayment::create([
                'trading_floor_membership_id' => $locked->id,
                'amount' => $amount,
                'currency' => $locked->currency,
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'received_by_admin_id' => $admin->id,
                'status' => TradingFloorMembershipPayment::STATUS_RECEIVED,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->reconcilePayments($locked, $admin->id);
            return $payment->fresh(['receivedByAdmin']);
        }, 3);
    }

    public function reversePayment(TradingFloorMembership $membership, TradingFloorMembershipPayment $payment, AdminUser $admin, string $reason): TradingFloorMembershipPayment
    {
        return DB::transaction(function () use ($membership, $payment, $admin, $reason): TradingFloorMembershipPayment {
            $lockedMembership = TradingFloorMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $lockedPayment = TradingFloorMembershipPayment::query()->lockForUpdate()->findOrFail($payment->id);
            abort_unless($lockedPayment->trading_floor_membership_id === $lockedMembership->id, 404);
            abort_unless($lockedPayment->status === TradingFloorMembershipPayment::STATUS_RECEIVED, 422, 'Only received payments can be reversed.');

            $lockedPayment->update([
                'status' => TradingFloorMembershipPayment::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by_admin_id' => $admin->id,
                'reversal_reason' => $reason,
            ]);
            $this->reconcilePayments($lockedMembership, $admin->id);

            return $lockedPayment->fresh(['receivedByAdmin', 'reversedByAdmin']);
        }, 3);
    }

    /** @return array{expired:int,auto_renewals_created:int,auto_renewals_skipped:int} */
    public function sweepExpiries(): array
    {
        $summary = ['expired' => 0, 'auto_renewals_created' => 0, 'auto_renewals_skipped' => 0];
        $ids = TradingFloorMembership::query()
            ->whereIn('status', [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED])
            ->whereDate('expires_at', '<', today()->toDateString())
            ->orderBy('id')->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$summary): void {
                $membership = TradingFloorMembership::query()->lockForUpdate()->find($id);
                if ($membership === null || ! in_array($membership->status, [TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED], true) || ! $membership->expires_at?->isBefore(today())) {
                    return;
                }

                $membership->update(['status' => TradingFloorMembership::STATUS_EXPIRED]);
                $this->logChange($membership, TradingFloorMembershipChange::TYPE_EXPIRED, null, [
                    'old_expires_at' => $membership->expires_at?->toDateString(),
                    'new_expires_at' => $membership->expires_at?->toDateString(),
                    'reason' => 'Membership expired by scheduled sweep.',
                    'metadata' => ['source' => 'SYSTEM_SWEEP'],
                ]);
                $summary['expired']++;
                $this->releaseClientAllocationIfOwned($membership, null);

                if (! $membership->auto_renew) {
                    return;
                }

                if (TradingFloorMembership::query()->where('renewed_from_membership_id', $membership->id)->whereIn('status', $this->openStatuses())->exists()) {
                    $summary['auto_renewals_skipped']++;
                    return;
                }

                $package = TradingFloorPackage::query()->find($membership->trading_floor_package_id);
                $floor = TradingFloorFloor::query()->find($membership->trading_floor_floor_id);
                $desk = $membership->trading_floor_desk_id ? TradingFloorDesk::query()->find($membership->trading_floor_desk_id) : null;
                if ($package === null || $floor === null || $package->status !== TradingFloorPackage::STATUS_ACTIVE || $floor->status !== TradingFloorFloor::STATUS_ACTIVE || ($desk && $desk->status !== TradingFloorDesk::STATUS_ACTIVE)) {
                    $summary['auto_renewals_skipped']++;
                    return;
                }

                if ($desk && TradingFloorMembership::query()->where('trading_floor_desk_id', $desk->id)->whereIn('status', $this->openStatuses())->exists()) {
                    $summary['auto_renewals_skipped']++;
                    return;
                }

                $startsAt = CarbonImmutable::today();
                $expiresAt = $this->resolveExpiry($startsAt, $package, null);
                $uuid = (string) Str::uuid();
                $renewal = TradingFloorMembership::create([
                    'uuid' => $uuid,
                    'membership_number' => 'TFM-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 10)),
                    'trading_floor_client_id' => $membership->trading_floor_client_id,
                    'trading_floor_package_id' => $package->id,
                    'trading_floor_floor_id' => $floor->id,
                    'trading_floor_desk_id' => $desk?->id,
                    'starts_at' => $startsAt->toDateString(),
                    'expires_at' => $expiresAt->toDateString(),
                    'due_date' => $startsAt->toDateString(),
                    'status' => TradingFloorMembership::STATUS_PENDING,
                    'price_amount' => $package->price_amount,
                    'currency' => $package->currency,
                    'billing_cycle' => $package->billing_cycle,
                    'payment_status' => TradingFloorMembership::PAYMENT_PENDING,
                    'paid_amount' => 0,
                    'balance_amount' => $package->price_amount,
                    'auto_renew' => true,
                    'renewed_from_membership_id' => $membership->id,
                    'notes' => 'Pending renewal generated automatically after membership expiry.',
                ]);

                $this->logChange($membership, TradingFloorMembershipChange::TYPE_AUTO_RENEWAL_CREATED, null, [
                    'old_expires_at' => $membership->expires_at?->toDateString(),
                    'new_expires_at' => $expiresAt->toDateString(),
                    'reason' => 'Auto-renewal pending membership created by scheduled sweep.',
                    'metadata' => ['source' => 'SYSTEM_SWEEP', 'renewal_membership_id' => $renewal->id, 'renewal_membership_number' => $renewal->membership_number],
                ]);
                $summary['auto_renewals_created']++;
            }, 3);
        }

        return $summary;
    }

    private function reconcilePayments(TradingFloorMembership $membership, ?int $adminId): void
    {
        $paid = round((float) TradingFloorMembershipPayment::query()
            ->where('trading_floor_membership_id', $membership->id)
            ->where('status', TradingFloorMembershipPayment::STATUS_RECEIVED)
            ->sum('amount'), 2);
        $price = round((float) $membership->price_amount, 2);
        $paid = min($paid, $price);
        $membership->update([
            'paid_amount' => $paid,
            'balance_amount' => round($price - $paid, 2),
            'payment_status' => $this->paymentStatus($price, $paid),
            'updated_by_admin_id' => $adminId,
        ]);
    }

    private function paymentStatus(float $price, float $paid): string
    {
        if ($paid >= $price) {
            return TradingFloorMembership::PAYMENT_PAID;
        }
        return $paid > 0 ? TradingFloorMembership::PAYMENT_PARTIAL : TradingFloorMembership::PAYMENT_PENDING;
    }

    private function lockedDesk(mixed $deskId, TradingFloorFloor $floor): ?TradingFloorDesk
    {
        if (empty($deskId)) {
            return null;
        }
        $desk = TradingFloorDesk::query()->lockForUpdate()->findOrFail((int) $deskId);
        if ($desk->trading_floor_floor_id !== $floor->id) {
            throw ValidationException::withMessages(['trading_floor_desk_id' => 'Selected desk does not belong to the selected floor.']);
        }
        return $desk;
    }

    private function assertResourcesActive(TradingFloorPackage $package, TradingFloorFloor $floor, ?TradingFloorDesk $desk): void
    {
        if ($package->status !== TradingFloorPackage::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['trading_floor_package_id' => 'Selected package is inactive.']);
        }
        if ($floor->status !== TradingFloorFloor::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['trading_floor_floor_id' => 'Selected floor is inactive.']);
        }
        if ($desk !== null && $desk->status !== TradingFloorDesk::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['trading_floor_desk_id' => 'Selected desk is not currently available for allocation.']);
        }
    }

    private function assertDeskAvailable(?TradingFloorDesk $desk, ?int $excludeMembershipId): void
    {
        if ($desk === null) {
            return;
        }
        $query = TradingFloorMembership::query()->where('trading_floor_desk_id', $desk->id)->whereIn('status', $this->openStatuses())->lockForUpdate();
        if ($excludeMembershipId !== null) {
            $query->where('id', '<>', $excludeMembershipId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['trading_floor_desk_id' => 'Selected desk is already allocated to another open membership.']);
        }
    }

    /** @return array<int,string> */
    private function openStatuses(): array
    {
        return [TradingFloorMembership::STATUS_PENDING, TradingFloorMembership::STATUS_ACTIVE, TradingFloorMembership::STATUS_SUSPENDED];
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

    /** @param array<string,mixed> $data */
    private function logChange(TradingFloorMembership $membership, string $type, ?int $adminId, array $data): void
    {
        TradingFloorMembershipChange::create([
            'trading_floor_membership_id' => $membership->id,
            'change_type' => $type,
            'old_package_id' => $data['old_package_id'] ?? null,
            'new_package_id' => $data['new_package_id'] ?? null,
            'old_floor_id' => $data['old_floor_id'] ?? null,
            'new_floor_id' => $data['new_floor_id'] ?? null,
            'old_desk_id' => $data['old_desk_id'] ?? null,
            'new_desk_id' => $data['new_desk_id'] ?? null,
            'old_expires_at' => $data['old_expires_at'] ?? null,
            'new_expires_at' => $data['new_expires_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'reason' => $data['reason'] ?? null,
            'changed_by_admin_id' => $adminId,
            'occurred_at' => now(),
        ]);
    }

    private function releaseClientAllocationIfOwned(TradingFloorMembership $membership, ?int $adminId): void
    {
        $client = TradingFloorClient::query()->lockForUpdate()->findOrFail($membership->trading_floor_client_id);
        $floor = TradingFloorFloor::query()->find($membership->trading_floor_floor_id);
        $desk = $membership->trading_floor_desk_id ? TradingFloorDesk::query()->find($membership->trading_floor_desk_id) : null;
        $package = TradingFloorPackage::query()->find($membership->trading_floor_package_id);

        if (($floor === null || $client->floor_code === $floor->code)
            && ($desk === null || $client->desk_code === $desk->code)
            && ($package === null || $client->package_code === $package->code)) {
            $client->update(['floor_code' => null, 'desk_code' => null, 'package_code' => null, 'updated_by_admin_id' => $adminId]);
        }
    }

    private function syncClientAllocation(TradingFloorClient $client, TradingFloorPackage $package, TradingFloorFloor $floor, ?TradingFloorDesk $desk, AdminUser $admin): void
    {
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
