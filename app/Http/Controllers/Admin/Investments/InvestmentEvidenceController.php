<?php

namespace App\Http\Controllers\Admin\Investments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Investments\RejectInvestmentEvidenceRequest;
use App\Http\Requests\Admin\Investments\UploadInvestmentEvidenceRequest;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTrade;
use App\Services\Admin\Investments\InvestmentEvidenceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestmentEvidenceController extends Controller
{
    public function tradeStore(
        UploadInvestmentEvidenceRequest $request,
        Investment $investment,
        InvestmentTrade $trade,
        InvestmentEvidenceService $service
    ): RedirectResponse {
        try {
            $service->storeForTrade(
                $investment,
                $trade,
                $request->file('document'),
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['evidence' => $exception->getMessage()]);
        }
        return back()->with('success', 'Trade screenshot/document evidence uploaded for Finance verification.');
    }

    public function periodStore(
        UploadInvestmentEvidenceRequest $request,
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentEvidenceService $service
    ): RedirectResponse {
        try {
            $service->storeForPeriod(
                $investment,
                $period,
                $request->file('document'),
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['evidence' => $exception->getMessage()]);
        }
        return back()->with('success', 'P&L statement/screenshot uploaded for Finance verification.');
    }

    public function costStore(
        UploadInvestmentEvidenceRequest $request,
        Investment $investment,
        InvestmentApprovedCost $cost,
        InvestmentEvidenceService $service
    ): RedirectResponse {
        try {
            $service->storeForCost(
                $investment,
                $cost,
                $request->file('document'),
                $request->validated(),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['evidence' => $exception->getMessage()]);
        }
        return back()->with('success', 'Cost evidence uploaded for Finance verification.');
    }

    public function verify(
        Request $request,
        Investment $investment,
        InvestmentEvidence $evidence,
        InvestmentEvidenceService $service
    ): RedirectResponse {
        try {
            $service->verify($investment, $evidence, $this->admin(), $request);
        } catch (DomainException $exception) {
            return back()->withErrors(['evidence' => $exception->getMessage()]);
        }
        return back()->with('success', 'Evidence verified.');
    }

    public function reject(
        RejectInvestmentEvidenceRequest $request,
        Investment $investment,
        InvestmentEvidence $evidence,
        InvestmentEvidenceService $service
    ): RedirectResponse {
        try {
            $service->reject(
                $investment,
                $evidence,
                $request->validated('reason'),
                $this->admin(),
                $request
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['evidence' => $exception->getMessage()]);
        }
        return back()->with('success', 'Evidence rejected.');
    }

    public function download(
        Request $request,
        Investment $investment,
        InvestmentEvidence $evidence,
        InvestmentEvidenceService $service
    ): StreamedResponse {
        $document = $service->document($investment, $evidence, $this->admin(), $request);

        return Storage::disk($document['disk'])->download(
            $document['path'],
            $document['name'],
            [
                'Content-Type' => $document['mime_type'],
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        return $admin;
    }
}
