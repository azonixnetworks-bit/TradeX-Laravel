<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentAgreement;
use App\Models\InvestmentRiskAcceptance;
use App\Models\InvestmentTransaction;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvestmentAgreementService
{
    public function generate(
        Investment $investment,
        AdminUser $admin,
        Request $request
    ): InvestmentAgreement {
        abort_unless(
            $admin->hasPermission(
                'investments.agreements.manage'
            ),
            403
        );

        $this->assertAgreementPrerequisites(
            $investment
        );

        return DB::transaction(
            function () use (
                $investment,
                $admin,
                $request
            ): InvestmentAgreement {
                $locked = Investment::query()
                    ->with(['user', 'product'])
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $this->assertAgreementPrerequisites(
                    $locked
                );

                $existingSigned = $locked->agreements()
                    ->whereIn(
                        'status',
                        [
                            InvestmentAgreement::STATUS_SIGNED,
                            InvestmentAgreement::STATUS_VERIFIED,
                        ]
                    )
                    ->exists();

                if ($existingSigned) {
                    throw new DomainException(
                        'A signed or verified agreement already exists. Existing signed agreement versions are immutable.'
                    );
                }

                $version = (
                    (int) $locked->agreements()
                        ->max('version')
                ) + 1;

                $riskVersion = (string) config(
                    'tradex_investments.risk.version'
                );

                $templateVersion = (string) config(
                    'tradex_investments.agreement.template_version'
                );

                $templateStatus = (string) config(
                    'tradex_investments.agreement.template_status'
                );

                $riskAcceptance = $locked->riskAcceptances()
                    ->where(
                        'disclosure_version',
                        $riskVersion
                    )
                    ->latest('id')
                    ->firstOrFail();

                $snapshot = $this->termsSnapshot(
                    $locked,
                    $riskAcceptance
                );

                $agreementUuid = Str::uuid()->toString();

                $html = view(
                    'admin.investments.documents.agreement',
                    [
                        'investment' => $locked,
                        'riskAcceptance' => $riskAcceptance,
                        'riskStatements' =>
                            (array) config(
                                'tradex_investments.risk.statements',
                                []
                            ),
                        'riskTitle' =>
                            (string) config(
                                'tradex_investments.risk.title'
                            ),
                        'riskVersion' => $riskVersion,
                        'templateVersion' =>
                            $templateVersion,
                        'templateStatus' =>
                            $templateStatus,
                        'companyLegalName' =>
                            (string) config(
                                'tradex_investments.agreement.company_legal_name',
                                'TradeX'
                            ),
                        'agreementVersion' => $version,
                        'generatedAt' => now(),
                    ]
                )->render();

                $disk = (string) config(
                    'tradex_investments.storage_disk',
                    'private'
                );

                $path = 'investments/'
                    .$locked->uuid
                    .'/agreements/v'
                    .$version
                    .'/agreement-'
                    .$agreementUuid
                    .'.html';

                if (! Storage::disk($disk)->put(
                    $path,
                    $html
                )) {
                    throw new DomainException(
                        'Unable to store the generated agreement in private storage.'
                    );
                }

                $sha256 = hash(
                    'sha256',
                    $html
                );

                $agreement = InvestmentAgreement::query()
                    ->create([
                        'uuid' => $agreementUuid,
                        'investment_id' =>
                            $locked->getKey(),
                        'version' => $version,
                        'status' =>
                            InvestmentAgreement::STATUS_GENERATED,
                        'template_version' =>
                            $templateVersion,
                        'template_status' =>
                            $templateStatus,
                        'risk_disclosure_version' =>
                            $riskVersion,
                        'terms_snapshot' => $snapshot,
                        'document_disk' => $disk,
                        'document_path' => $path,
                        'document_mime_type' =>
                            'text/html; charset=UTF-8',
                        'document_size_bytes' =>
                            strlen($html),
                        'document_sha256' => $sha256,
                        'generated_at' => now(),
                        'generated_by_admin_id' =>
                            $admin->getKey(),
                    ]);

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action:
                        'INVESTMENT_AGREEMENT_GENERATED',
                    module: 'INVESTMENTS',
                    targetType:
                        'INVESTMENT_AGREEMENT',
                    targetId: $agreement->getKey(),
                    newValues: [
                        'investment_id' =>
                            $locked->getKey(),
                        'version' => $version,
                        'template_version' =>
                            $templateVersion,
                        'template_status' =>
                            $templateStatus,
                        'risk_disclosure_version' =>
                            $riskVersion,
                        'document_sha256' =>
                            $sha256,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $agreement;
            },
            3
        );
    }

    public function uploadSigned(
        Investment $investment,
        InvestmentAgreement $agreement,
        UploadedFile $file,
        string $signedAt,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.agreements.manage'
            ),
            403
        );

        $this->assertAgreementBelongsToInvestment(
            $investment,
            $agreement
        );

        if (! in_array(
            $agreement->status,
            [
                InvestmentAgreement::STATUS_GENERATED,
                InvestmentAgreement::STATUS_SENT,
            ],
            true
        )) {
            throw new DomainException(
                'Only a generated or sent agreement can receive a signed document.'
            );
        }

        if ($agreement->signed_document_path) {
            throw new DomainException(
                'A signed document already exists for this agreement version and cannot be overwritten.'
            );
        }

        $disk = (string) config(
            'tradex_investments.storage_disk',
            'private'
        );

        $bytes = file_get_contents(
            $file->getRealPath()
        );

        if ($bytes === false) {
            throw new DomainException(
                'Unable to read the signed agreement file.'
            );
        }

        $path = 'investments/'
            .$investment->uuid
            .'/agreements/v'
            .$agreement->version
            .'/signed-'
            .Str::uuid()->toString()
            .'.pdf';

        if (! Storage::disk($disk)->put(
            $path,
            $bytes
        )) {
            throw new DomainException(
                'Unable to store the signed agreement in private storage.'
            );
        }

        $sha256 = hash(
            'sha256',
            $bytes
        );

        $agreement->forceFill([
            'status' =>
                InvestmentAgreement::STATUS_SIGNED,
            'signed_document_disk' => $disk,
            'signed_document_path' => $path,
            'signed_document_mime_type' =>
                'application/pdf',
            'signed_document_size_bytes' =>
                strlen($bytes),
            'signed_document_sha256' =>
                $sha256,
            'signed_at' => $signedAt,
        ])->save();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action:
                'INVESTMENT_SIGNED_AGREEMENT_UPLOADED',
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_AGREEMENT',
            targetId: $agreement->getKey(),
            newValues: [
                'investment_id' =>
                    $investment->getKey(),
                'version' => $agreement->version,
                'status' => $agreement->status,
                'signed_document_sha256' =>
                    $sha256,
                'signed_at' =>
                    $agreement->signed_at
                        ?->toIso8601String(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    public function verifySigned(
        Investment $investment,
        InvestmentAgreement $agreement,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.agreements.verify'
            ),
            403
        );

        $this->assertAgreementBelongsToInvestment(
            $investment,
            $agreement
        );

        if (
            $agreement->status
            !== InvestmentAgreement::STATUS_SIGNED
        ) {
            throw new DomainException(
                'Only a signed agreement can be verified.'
            );
        }

        $this->assertStoredDocumentIntegrity(
            $agreement->signed_document_disk,
            $agreement->signed_document_path,
            $agreement->signed_document_sha256
        );

        $agreement->forceFill([
            'status' =>
                InvestmentAgreement::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by_admin_id' =>
                $admin->getKey(),
        ])->save();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action:
                'INVESTMENT_AGREEMENT_VERIFIED',
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_AGREEMENT',
            targetId: $agreement->getKey(),
            oldValues: [
                'status' =>
                    InvestmentAgreement::STATUS_SIGNED,
            ],
            newValues: [
                'status' =>
                    InvestmentAgreement::STATUS_VERIFIED,
                'verified_at' =>
                    $agreement->verified_at
                        ?->toIso8601String(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    public function documentEvidence(
        Investment $investment,
        InvestmentAgreement $agreement,
        bool $signed,
        AdminUser $admin,
        Request $request
    ): array {
        abort_unless(
            $admin->hasPermission(
                'investments.agreements.view'
            ),
            403
        );

        $this->assertAgreementBelongsToInvestment(
            $investment,
            $agreement
        );

        if ($signed) {
            $disk = $agreement->signed_document_disk;
            $path = $agreement->signed_document_path;
            $sha = $agreement->signed_document_sha256;
            $mime = $agreement->signed_document_mime_type;
            $name = $investment->investment_code
                .'-agreement-v'
                .$agreement->version
                .'-signed.pdf';
        } else {
            $disk = $agreement->document_disk;
            $path = $agreement->document_path;
            $sha = $agreement->document_sha256;
            $mime = $agreement->document_mime_type;
            $name = $investment->investment_code
                .'-agreement-v'
                .$agreement->version
                .'.html';
        }

        $this->assertStoredDocumentIntegrity(
            $disk,
            $path,
            $sha
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action:
                $signed
                    ? 'INVESTMENT_SIGNED_AGREEMENT_DOWNLOADED'
                    : 'INVESTMENT_AGREEMENT_DOWNLOADED',
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_AGREEMENT',
            targetId: $agreement->getKey(),
            newValues: [
                'investment_id' =>
                    $investment->getKey(),
                'version' => $agreement->version,
                'signed' => $signed,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'name' => $name,
        ];
    }

    private function assertAgreementPrerequisites(
        Investment $investment
    ): void {
        $verifiedDeposit = $investment->transactions()
            ->where(
                'type',
                InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT
            )
            ->where(
                'status',
                InvestmentTransaction::STATUS_VERIFIED
            )
            ->where('asset_symbol', 'USDT')
            ->where('network', 'TRC20')
            ->exists();

        if (! $verifiedDeposit) {
            throw new DomainException(
                'A verified USDT TRC20 deposit is required before agreement generation.'
            );
        }

        $riskVersion = (string) config(
            'tradex_investments.risk.version'
        );

        $riskAccepted = $investment->riskAcceptances()
            ->where(
                'disclosure_version',
                $riskVersion
            )
            ->exists();

        if (! $riskAccepted) {
            throw new DomainException(
                'The current crypto risk disclosure must be accepted before agreement generation.'
            );
        }

        if (
            $investment->status
            !== Investment::STATUS_AWAITING_AGREEMENT
        ) {
            throw new DomainException(
                'The investment must be waiting for agreement completion before a contract is generated.'
            );
        }
    }

    private function termsSnapshot(
        Investment $investment,
        InvestmentRiskAcceptance $riskAcceptance
    ): array {
        return [
            'investment_code' =>
                $investment->investment_code,
            'customer_id' => $investment->user_id,
            'product_code' =>
                $investment->product?->code,
            'deposit_amount' =>
                (string) $investment->deposit_amount,
            'deposit_currency' =>
                $investment->deposit_currency,
            'deposit_network' =>
                $investment->deposit_network,
            'investment_period_years' =>
                $investment->investment_period_years,
            'profit_model' =>
                $investment->profit_model,
            'profit_review_interval_months' =>
                $investment->profit_review_interval_months,
            'customer_profit_share_bps' =>
                $investment->customer_profit_share_bps,
            'company_profit_share_bps' =>
                $investment->company_profit_share_bps,
            'early_exit_return_bps' =>
                $investment->early_exit_return_bps,
            'fixed_return_guaranteed' => false,
            'settlement_mode' =>
                $investment->settlement_mode,
            'custom_settlement_date' =>
                $investment->custom_settlement_date
                    ?->toDateString(),
            'risk_acceptance_uuid' =>
                $riskAcceptance->uuid,
            'risk_disclosure_version' =>
                $riskAcceptance->disclosure_version,
            'risk_accepted_at' =>
                $riskAcceptance->accepted_at
                    ?->toIso8601String(),
        ];
    }

    private function assertAgreementBelongsToInvestment(
        Investment $investment,
        InvestmentAgreement $agreement
    ): void {
        if (
            $agreement->investment_id
            !== $investment->getKey()
        ) {
            throw new DomainException(
                'Investment agreement not found.'
            );
        }
    }

    private function assertStoredDocumentIntegrity(
        ?string $disk,
        ?string $path,
        ?string $expectedSha256
    ): void {
        if (
            ! $disk
            || ! $path
            || ! $expectedSha256
            || ! Storage::disk($disk)->exists($path)
        ) {
            throw new DomainException(
                'Agreement document is unavailable.'
            );
        }

        $bytes = Storage::disk($disk)->get($path);

        if (
            ! is_string($bytes)
            || ! hash_equals(
                $expectedSha256,
                hash('sha256', $bytes)
            )
        ) {
            throw new DomainException(
                'Agreement document integrity verification failed.'
            );
        }
    }
}
