<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTrade;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvestmentEvidenceService
{
    public function storeForTrade(
        Investment $investment,
        InvestmentTrade $trade,
        UploadedFile $file,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentEvidence {
        abort_unless($admin->hasPermission('investments.evidence.manage'), 403);
        $this->assertTrade($investment, $trade);

        return $this->store(
            investment: $investment,
            period: $trade->profitPeriod,
            trade: $trade,
            cost: null,
            type: InvestmentEvidence::TYPE_TRADE_SCREENSHOT,
            file: $file,
            data: $data,
            admin: $admin,
            request: $request
        );
    }

    public function storeForPeriod(
        Investment $investment,
        InvestmentProfitPeriod $period,
        UploadedFile $file,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentEvidence {
        abort_unless($admin->hasPermission('investments.evidence.manage'), 403);
        $this->assertPeriod($investment, $period);

        return $this->store(
            investment: $investment,
            period: $period,
            trade: null,
            cost: null,
            type: InvestmentEvidence::TYPE_PNL_STATEMENT,
            file: $file,
            data: $data,
            admin: $admin,
            request: $request
        );
    }

    public function storeForCost(
        Investment $investment,
        InvestmentApprovedCost $cost,
        UploadedFile $file,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentEvidence {
        abort_unless($admin->hasPermission('investments.evidence.manage'), 403);
        abort_unless($cost->investment_id === $investment->getKey(), 404);

        return $this->store(
            investment: $investment,
            period: $cost->profitPeriod,
            trade: null,
            cost: $cost,
            type: InvestmentEvidence::TYPE_COST_DOCUMENT,
            file: $file,
            data: $data,
            admin: $admin,
            request: $request
        );
    }

    public function verify(
        Investment $investment,
        InvestmentEvidence $evidence,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.evidence.verify'), 403);
        $this->assertEvidence($investment, $evidence);

        if ($evidence->status !== InvestmentEvidence::STATUS_PENDING) {
            throw new DomainException('Only pending investment evidence can be verified.');
        }

        $this->assertIntegrity($evidence);

        $evidence->forceFill([
            'status' => InvestmentEvidence::STATUS_VERIFIED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_EVIDENCE_VERIFIED',
            $evidence,
            ['sha256' => $evidence->document_sha256]
        );
    }

    public function reject(
        Investment $investment,
        InvestmentEvidence $evidence,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.evidence.verify'), 403);
        $this->assertEvidence($investment, $evidence);

        if ($evidence->status !== InvestmentEvidence::STATUS_PENDING) {
            throw new DomainException('Only pending investment evidence can be rejected.');
        }

        $evidence->forceFill([
            'status' => InvestmentEvidence::STATUS_REJECTED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_EVIDENCE_REJECTED',
            $evidence,
            ['reason' => trim($reason)]
        );
    }

    public function document(
        Investment $investment,
        InvestmentEvidence $evidence,
        AdminUser $admin,
        Request $request
    ): array {
        abort_unless(
            $admin->hasPermission('investments.evidence.view')
                || $admin->hasPermission('investments.evidence.verify'),
            403
        );
        $this->assertEvidence($investment, $evidence);
        $this->assertIntegrity($evidence);

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_EVIDENCE_DOWNLOADED',
            $evidence,
            []
        );

        return [
            'disk' => $evidence->document_disk,
            'path' => $evidence->document_path,
            'mime_type' => $evidence->document_mime_type,
            'name' => $investment->investment_code
                .'-evidence-'.$evidence->uuid.'.'
                .pathinfo($evidence->document_path, PATHINFO_EXTENSION),
        ];
    }

    private function store(
        Investment $investment,
        ?InvestmentProfitPeriod $period,
        ?InvestmentTrade $trade,
        ?InvestmentApprovedCost $cost,
        string $type,
        UploadedFile $file,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentEvidence {
        if ($period?->approved_at !== null) {
            throw new DomainException(
                'An approved/finalized profit period is immutable. New evidence cannot be attached to it.'
            );
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new DomainException('Unable to read investment evidence.');
        }

        $disk = (string) config('tradex_investments.storage_disk', 'private');
        $uuid = Str::uuid()->toString();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = 'investments/'.$investment->uuid
            .'/evidence/'.$uuid.'.'.$extension;

        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw new DomainException('Unable to store investment evidence in private storage.');
        }

        try {
            $evidence = InvestmentEvidence::query()->create([
                'uuid' => $uuid,
                'investment_id' => $investment->getKey(),
                'investment_profit_period_id' => $period?->getKey(),
                'investment_trade_id' => $trade?->getKey(),
                'investment_approved_cost_id' => $cost?->getKey(),
                'evidence_type' => $type,
                'account_reference' => $data['account_reference'] ?? null,
                'source_reference' => $data['source_reference'] ?? null,
                'period_start' => $period?->period_start?->toDateString(),
                'period_end' => $period?->period_end?->toDateString(),
                'document_disk' => $disk,
                'document_path' => $path,
                'document_mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'document_size_bytes' => strlen($bytes),
                'document_sha256' => hash('sha256', $bytes),
                'status' => InvestmentEvidence::STATUS_PENDING,
                'uploaded_by_admin_id' => $admin->getKey(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_EVIDENCE_UPLOADED',
            $evidence,
            [
                'evidence_type' => $type,
                'sha256' => $evidence->document_sha256,
                'size_bytes' => $evidence->document_size_bytes,
                'mime_type' => $evidence->document_mime_type,
            ]
        );

        return $evidence;
    }

    private function assertIntegrity(InvestmentEvidence $evidence): void
    {
        if (
            ! $evidence->document_disk
            || ! $evidence->document_path
            || ! $evidence->document_sha256
            || ! Storage::disk($evidence->document_disk)->exists($evidence->document_path)
        ) {
            throw new DomainException('Investment evidence is unavailable.');
        }

        $bytes = Storage::disk($evidence->document_disk)->get($evidence->document_path);
        if (
            ! is_string($bytes)
            || ! hash_equals($evidence->document_sha256, hash('sha256', $bytes))
        ) {
            throw new DomainException('Investment evidence integrity verification failed.');
        }
    }

    private function assertTrade(Investment $investment, InvestmentTrade $trade): void
    {
        abort_unless($trade->investment_id === $investment->getKey(), 404);
    }

    private function assertPeriod(Investment $investment, InvestmentProfitPeriod $period): void
    {
        abort_unless($period->investment_id === $investment->getKey(), 404);
    }

    private function assertEvidence(Investment $investment, InvestmentEvidence $evidence): void
    {
        abort_unless($evidence->investment_id === $investment->getKey(), 404);
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentEvidence $evidence,
        array $values
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_EVIDENCE',
            targetId: $evidence->getKey(),
            newValues: array_merge([
                'investment_id' => $evidence->investment_id,
                'profit_period_id' => $evidence->investment_profit_period_id,
                'trade_id' => $evidence->investment_trade_id,
                'cost_id' => $evidence->investment_approved_cost_id,
                'status' => $evidence->status,
            ], $values),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
