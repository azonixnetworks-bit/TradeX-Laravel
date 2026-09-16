<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use App\Services\Trading\FundedAdvisorPdfService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FundedAccountAdvisorController extends Controller
{
    public function index(): Response
    {
        $admin = $this->viewer();

        return response()->view(
            'admin.trading_tools.funded_account.index',
            [
                'recentReports' => FundedAdvisorReport::query()
                    ->with('user')
                    ->latest('generated_at')
                    ->limit(20)
                    ->get(),
                'reportCount' => FundedAdvisorReport::query()->count(),
                'activePartnerCount' => FundedAccountPartner::query()
                    ->where('is_active', true)
                    ->count(),
                'canManagePartners' => $admin->hasPermission('funded_advisor.partners.manage'),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function show(FundedAdvisorReport $fundedAdvisorReport): Response
    {
        $this->viewer();
        $fundedAdvisorReport->loadMissing('user');

        return response()->view(
            'admin.trading_tools.funded_account.show',
            [
                'report' => $fundedAdvisorReport,
                'input' => (array) $fundedAdvisorReport->inputs_json,
                'output' => (array) $fundedAdvisorReport->output_json,
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function pdf(
        FundedAdvisorReport $fundedAdvisorReport,
        FundedAdvisorPdfService $pdfService
    ): SymfonyResponse {
        $this->viewer();

        $pdf = $pdfService->render($fundedAdvisorReport);
        $filename = 'tradex-funded-account-plan-'.$fundedAdvisorReport->uuid.'.pdf';

        return response($pdf, 200, array_merge(
            $this->noStoreHeaders(),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        ));
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);
        abort_unless($admin->hasPermission('funded_advisor.view'), 403);

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
