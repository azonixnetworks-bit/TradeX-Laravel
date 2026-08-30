<?php

namespace App\Services\Admin\CopyTrading;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerMarket;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CopyTradeBrokerAdminService
{
    public function create(
        array $data,
        ?UploadedFile $logo,
        AdminUser $admin,
        Request $request
    ): CopyTradeBroker {
        $uuid = (string) Str::uuid();
        $logoMeta = $logo
            ? $this->storeLogo($logo, $uuid)
            : null;

        try {
            return DB::transaction(function () use (
                $data,
                $admin,
                $request,
                $uuid,
                $logoMeta
            ): CopyTradeBroker {
                $broker = CopyTradeBroker::query()->create([
                    'uuid' => $uuid,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'logo_disk' => $logoMeta['disk'] ?? null,
                    'logo_path' => $logoMeta['path'] ?? null,
                    'logo_mime_type' => $logoMeta['mime_type'] ?? null,
                    'logo_size_bytes' => $logoMeta['size_bytes'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                CopyTradeBrokerMarket::query()->create([
                    'broker_id' => $broker->id,
                    'market_category' => $data['market_category'],
                    'referral_url' => $data['referral_url'],
                    'required_currency' => $data['required_currency'],
                    'live_account_only' => true,
                    'is_active' => (bool) ($data['market_is_active'] ?? false),
                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                $broker->load('markets');

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'COPY_TRADE_BROKER_CREATED',
                    module: 'COPY_TRADING',
                    targetType: 'COPY_TRADE_BROKER',
                    targetId: $broker->id,
                    newValues: $this->brokerSnapshot($broker),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $broker;
            });
        } catch (\Throwable $exception) {
            $this->deleteFile(
                $logoMeta['disk'] ?? null,
                $logoMeta['path'] ?? null
            );

            throw $exception;
        }
    }

    public function update(
        CopyTradeBroker $broker,
        array $data,
        ?UploadedFile $logo,
        AdminUser $admin,
        Request $request
    ): CopyTradeBroker {
        $broker->loadMissing('markets');
        $before = $this->brokerSnapshot($broker);
        $oldLogo = [
            'disk' => $broker->logo_disk,
            'path' => $broker->logo_path,
        ];

        $newLogo = $logo
            ? $this->storeLogo($logo, $broker->uuid)
            : null;

        try {
            DB::transaction(function () use (
                $broker,
                $data,
                $admin,
                $request,
                $before,
                $newLogo
            ): void {
                $attributes = [
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'updated_by_admin_id' => $admin->id,
                ];

                if ($newLogo !== null) {
                    $attributes = array_merge($attributes, [
                        'logo_disk' => $newLogo['disk'],
                        'logo_path' => $newLogo['path'],
                        'logo_mime_type' => $newLogo['mime_type'],
                        'logo_size_bytes' => $newLogo['size_bytes'],
                    ]);
                }

                $broker->update($attributes);
                $broker->load('markets');

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'COPY_TRADE_BROKER_UPDATED',
                    module: 'COPY_TRADING',
                    targetType: 'COPY_TRADE_BROKER',
                    targetId: $broker->id,
                    oldValues: $before,
                    newValues: $this->brokerSnapshot($broker),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            });
        } catch (\Throwable $exception) {
            if ($newLogo !== null) {
                $this->deleteFile(
                    $newLogo['disk'],
                    $newLogo['path']
                );
            }

            throw $exception;
        }

        if ($newLogo !== null) {
            $this->deleteFile(
                $oldLogo['disk'],
                $oldLogo['path']
            );
        }

        return $broker->fresh('markets');
    }

    public function deleteUnusedBroker(
        CopyTradeBroker $broker,
        AdminUser $admin,
        Request $request
    ): void {
        $logo = DB::transaction(function () use (
            $broker,
            $admin,
            $request
        ): array {
            $locked = CopyTradeBroker::query()
                ->whereKey($broker->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $markets = CopyTradeBrokerMarket::query()
                ->where('broker_id', $locked->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $locked->setRelation('markets', $markets);

            if (
                $locked->accounts()->exists()
                || $locked->onboardingIntents()->exists()
            ) {
                throw new DomainException(
                    'This broker has Copy Trading onboarding or account history and cannot be deleted. Deactivate the broker instead.'
                );
            }

            $before = $this->brokerSnapshot($locked);
            $targetId = $locked->id;
            $logo = [
                'disk' => $locked->logo_disk,
                'path' => $locked->logo_path,
            ];

            CopyTradeBrokerMarket::query()
                ->where('broker_id', $locked->id)
                ->delete();

            $locked->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'COPY_TRADE_BROKER_DELETED',
                module: 'COPY_TRADING',
                targetType: 'COPY_TRADE_BROKER',
                targetId: $targetId,
                oldValues: $before,
                newValues: [
                    'deleted' => true,
                    'deletion_policy' => 'UNUSED_BROKER_ONLY',
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $logo;
        });

        try {
            $this->deleteFile(
                $logo['disk'] ?? null,
                $logo['path'] ?? null
            );
        } catch (\Throwable $exception) {
            /*
             * The database deletion has already committed. Do not report the
             * broker as undeleted because a stale private logo could not be
             * cleaned up; log the storage cleanup failure for operations.
             */
            report($exception);
        }
    }

    public function createMarket(
        CopyTradeBroker $broker,
        array $data,
        AdminUser $admin,
        Request $request
    ): CopyTradeBrokerMarket {
        if (
            $broker->markets()
                ->where('market_category', $data['market_category'])
                ->exists()
        ) {
            throw new DomainException(
                'This broker already has a configuration for the selected market category.'
            );
        }

        $market = CopyTradeBrokerMarket::query()->create([
            'broker_id' => $broker->id,
            'market_category' => $data['market_category'],
            'referral_url' => $data['referral_url'],
            'required_currency' => $data['required_currency'],
            'live_account_only' => true,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'COPY_TRADE_BROKER_MARKET_CREATED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_BROKER_MARKET',
            targetId: $market->id,
            newValues: $this->marketSnapshot($market),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $market;
    }

    public function updateMarket(
        CopyTradeBroker $broker,
        CopyTradeBrokerMarket $market,
        array $data,
        AdminUser $admin,
        Request $request
    ): CopyTradeBrokerMarket {
        if ($market->broker_id !== $broker->id) {
            throw new DomainException('Broker market configuration not found.');
        }

        $before = $this->marketSnapshot($market);

        $market->update([
            'referral_url' => $data['referral_url'],
            'required_currency' => $data['required_currency'],
            'live_account_only' => true,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_by_admin_id' => $admin->id,
        ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'COPY_TRADE_BROKER_MARKET_UPDATED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_BROKER_MARKET',
            targetId: $market->id,
            oldValues: $before,
            newValues: $this->marketSnapshot($market),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $market->fresh();
    }

    private function storeLogo(
        UploadedFile $logo,
        string $brokerUuid
    ): array {
        $disk = 'private';
        $extension = strtolower(
            $logo->guessExtension()
            ?: $logo->getClientOriginalExtension()
            ?: 'bin'
        );

        $path = $logo->storeAs(
            'copy-trading/brokers/'.$brokerUuid,
            'logo-'.Str::uuid().'.'.$extension,
            $disk
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $logo->getMimeType(),
            'size_bytes' => $logo->getSize(),
        ];
    }

    private function deleteFile(
        ?string $disk,
        ?string $path
    ): void {
        if (blank($disk) || blank($path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    private function brokerSnapshot(CopyTradeBroker $broker): array
    {
        $broker->loadMissing('markets');

        return [
            'code' => $broker->code,
            'name' => $broker->name,
            'is_active' => (bool) $broker->is_active,
            'has_logo' => $broker->hasLogo(),
            'markets' => $broker->markets
                ->map(fn (CopyTradeBrokerMarket $market): array =>
                    $this->marketSnapshot($market))
                ->values()
                ->all(),
        ];
    }

    private function marketSnapshot(CopyTradeBrokerMarket $market): array
    {
        return [
            'market_category' => $market->market_category,
            'referral_host' => parse_url(
                $market->referral_url,
                PHP_URL_HOST
            ),
            'required_currency' => $market->required_currency,
            'live_account_only' => (bool) $market->live_account_only,
            'is_active' => (bool) $market->is_active,
        ];
    }
}
