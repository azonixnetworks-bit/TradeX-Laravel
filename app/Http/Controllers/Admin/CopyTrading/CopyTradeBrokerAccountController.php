<?php

namespace App\Http\Controllers\Admin\CopyTrading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyTrading\CopyTradeBrokerAccountDecisionRequest;
use App\Http\Requests\Admin\CopyTrading\VerifyCopyTradeBrokerAccountRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerAccountReview;
use App\Services\Admin\CopyTrading\CopyTradeVerificationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CopyTradeBrokerAccountController extends Controller
{
    public function verificationIndex(
        Request $request,
        CopyTradeVerificationService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.accounts.view'), 403);

        $filters = $this->filters($request);

        return view('admin.copy_trading.verifications.index', [
            'accounts' => $service->verificationQueue($filters),
            'filters' => $filters,
            'statuses' => [
                CopyTradeBrokerAccount::STATUS_PENDING,
                CopyTradeBrokerAccount::STATUS_NEEDS_RESUBMISSION,
                CopyTradeBrokerAccount::STATUS_ESCALATED,
            ],
            'marketCategories' => (array) config(
                'tradex_copy_trading.market_categories',
                []
            ),
        ]);
    }

    public function accountIndex(
        Request $request,
        CopyTradeVerificationService $service
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.accounts.view'), 403);

        $filters = $this->filters($request);

        return view('admin.copy_trading.accounts.index', [
            'accounts' => $service->accounts($filters),
            'filters' => $filters,
            'statuses' => CopyTradeBrokerAccount::VALID_STATUSES,
            'marketCategories' => (array) config(
                'tradex_copy_trading.market_categories',
                []
            ),
        ]);
    }

    public function show(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('copy_trading.accounts.view'), 403);

        $copyTradeBrokerAccount->load([
            'user',
            'broker',
            'brokerMarket',
            'reviews.adminUser',
        ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'COPY_TRADE_BROKER_ACCOUNT_REVIEW_OPENED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_BROKER_ACCOUNT',
            targetId: $copyTradeBrokerAccount->id,
            newValues: [
                'account_masked' => $copyTradeBrokerAccount->maskedAccountNumber(),
                'status' => $copyTradeBrokerAccount->status,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return view('admin.copy_trading.verifications.review', [
            'account' => $copyTradeBrokerAccount,
            'fullAccountNumber' => $copyTradeBrokerAccount->account_number,
            'permissions' => [
                'verify' => $admin->hasPermission('copy_trading.accounts.verify'),
                'reject' => $admin->hasPermission('copy_trading.accounts.reject'),
                'request_resubmission' => $admin->hasPermission(
                    'copy_trading.accounts.request_resubmission'
                ),
                'escalate' => $admin->hasPermission('copy_trading.accounts.escalate'),
                'view_evidence' => $admin->hasPermission('copy_trading.evidence.view'),
                'super_admin' => $admin->isSuperAdmin(),
            ],
        ]);
    }

    public function verify(
        VerifyCopyTradeBrokerAccountRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeVerificationService $service
    ): RedirectResponse {
        try {
            $service->verify(
                $copyTradeBrokerAccount,
                $this->admin(),
                $request->validated(),
                $request->file('evidence'),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'verification' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Broker account verified. Copy Trade activation remains gated by the remaining eligibility controls.'
        );
    }

    public function reject(
        CopyTradeBrokerAccountDecisionRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeVerificationService $service
    ): RedirectResponse {
        abort_unless(
            $this->admin()->hasPermission('copy_trading.accounts.reject'),
            403
        );

        return $this->runDecision(
            fn () => $service->reject(
                $copyTradeBrokerAccount,
                $this->admin(),
                $request->validated(),
                $request->file('evidence'),
                $request
            ),
            'Broker account rejected.'
        );
    }

    public function requestResubmission(
        CopyTradeBrokerAccountDecisionRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeVerificationService $service
    ): RedirectResponse {
        abort_unless(
            $this->admin()->hasPermission(
                'copy_trading.accounts.request_resubmission'
            ),
            403
        );

        return $this->runDecision(
            fn () => $service->requestResubmission(
                $copyTradeBrokerAccount,
                $this->admin(),
                $request->validated(),
                $request->file('evidence'),
                $request
            ),
            'Broker account marked for customer resubmission.'
        );
    }

    public function escalate(
        CopyTradeBrokerAccountDecisionRequest $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeVerificationService $service
    ): RedirectResponse {
        abort_unless(
            $this->admin()->hasPermission('copy_trading.accounts.escalate'),
            403
        );

        return $this->runDecision(
            fn () => $service->escalate(
                $copyTradeBrokerAccount,
                $this->admin(),
                $request->validated(),
                $request->file('evidence'),
                $request
            ),
            'Broker account escalated for exceptional review.'
        );
    }

    public function evidence(
        Request $request,
        CopyTradeBrokerAccount $copyTradeBrokerAccount,
        CopyTradeBrokerAccountReview $copyTradeBrokerAccountReview
    ): StreamedResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('copy_trading.accounts.view')
            && $admin->hasPermission('copy_trading.evidence.view'),
            403
        );

        abort_unless(
            $copyTradeBrokerAccountReview->broker_account_id
                === $copyTradeBrokerAccount->id,
            404
        );

        abort_unless(
            $copyTradeBrokerAccountReview->hasEvidenceFile(),
            404
        );

        abort_unless(
            in_array(
                $copyTradeBrokerAccountReview->evidence_mime_type,
                [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                ],
                true
            ),
            404
        );

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'COPY_TRADE_BROKER_EVIDENCE_VIEWED',
            module: 'COPY_TRADING',
            targetType: 'COPY_TRADE_BROKER_ACCOUNT_REVIEW',
            targetId: $copyTradeBrokerAccountReview->id,
            newValues: [
                'broker_account_id' => $copyTradeBrokerAccount->id,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return Storage::disk(
            $copyTradeBrokerAccountReview->evidence_disk
        )->response(
            $copyTradeBrokerAccountReview->evidence_path,
            'broker-verification-evidence',
            [
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Content-Type' => $copyTradeBrokerAccountReview->evidence_mime_type,
            ],
            'inline'
        );
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => [
                'nullable',
                Rule::in(CopyTradeBrokerAccount::VALID_STATUSES),
            ],
            'market' => [
                'nullable',
                Rule::in((array) config(
                    'tradex_copy_trading.market_categories',
                    []
                )),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'in:20,50,100',
            ],
        ]);
    }

    private function runDecision(
        callable $callback,
        string $successMessage
    ): RedirectResponse {
        try {
            $callback();
        } catch (DomainException $exception) {
            return back()->withErrors([
                'verification' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', $successMessage);
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
