<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use App\Services\Trading\FundedAccountAdvisorService;
use App\Services\Trading\FundedAdvisorPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FundedAccountAdvisorController extends Controller
{
    public function index(): Response
    {
        $admin = $this->viewer();

        return response()->view(
            'admin.trading_tools.funded_account.index',
            [
                'partners' => FundedAccountPartner::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_featured')
                    ->orderBy('priority')
                    ->orderBy('name')
                    ->get(),
                'recentReports' => FundedAdvisorReport::query()
                    ->where('generated_by_admin_id', $admin->id)
                    ->latest('generated_at')
                    ->limit(10)
                    ->get(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function generate(
        Request $request,
        FundedAccountAdvisorService $advisor
    ): RedirectResponse {
        $admin = $this->viewer();
        $maxPlannedEntries = max(
            1,
            (int) config('funded_account_advisor.max_planned_entries', 5)
        );

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email:rfc', 'max:255'],
            'prop_firm_name' => ['nullable', 'string', 'max:160'],
            'partner_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('funded_account_partners', 'uuid')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'phase' => ['required', Rule::in(['EVALUATION', 'VERIFICATION', 'FUNDED'])],
            'account_size' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'profit_target_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_loss_limit_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'max_drawdown_percent' => ['required', 'numeric', 'gt:0', 'max:100'],
            'daily_loss_basis' => ['required', Rule::in(['BALANCE', 'EQUITY'])],
            'daily_start_reference' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'drawdown_type' => ['required', Rule::in(['STATIC', 'TRAILING'])],
            'drawdown_basis' => ['required', Rule::in(['BALANCE', 'EQUITY'])],
            'trailing_reference_amount' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:1000000000',
                'required_if:drawdown_type,TRAILING',
            ],
            'current_balance' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'current_equity' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'desired_risk_percent' => ['required', 'numeric', 'gt:0', 'max:10'],
            'planned_max_entries' => ['required', 'integer', 'min:1', 'max:'.$maxPlannedEntries],
            'minimum_rr' => ['required', 'numeric', 'gt:0', 'max:20'],
            'minimum_trading_days' => ['required', 'integer', 'min:0', 'max:365'],
            'completed_trading_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        $partner = filled($validated['partner_uuid'] ?? null)
            ? FundedAccountPartner::query()
                ->where('uuid', $validated['partner_uuid'])
                ->where('is_active', true)
                ->firstOrFail()
            : null;

        $inputs = $validated;
        unset($inputs['partner_uuid']);

        $output = $advisor->advise($inputs);

        $report = FundedAdvisorReport::query()->create([
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'] ?? null,
            'prop_firm_name' => $validated['prop_firm_name'] ?? null,
            'partner_id' => $partner?->id,
            'partner_name_snapshot' => $partner?->name,
            'affiliate_url_snapshot' => $partner?->affiliate_url,
            'promo_code_snapshot' => $partner?->promo_code,
            'partner_offer_snapshot' => $partner?->offer_text,
            'affiliate_disclosure_snapshot' => $partner
                ? ($partner->disclosure ?: (string) config('funded_account_advisor.affiliate_disclosure'))
                : null,
            'inputs_json' => $inputs,
            'output_json' => $output,
            'generated_by_admin_id' => $admin->id,
            'generated_at' => now(),
        ]);

        AuditLog::record(
            'ADMIN',
            $admin->id,
            'FUNDED_ADVISOR_REPORT_GENERATED',
            'TRADING_TOOLS',
            FundedAdvisorReport::class,
            $report->id,
            null,
            [
                'phase' => $validated['phase'],
                'account_size' => (float) $validated['account_size'],
                'status' => $output['status'] ?? null,
                'partner_id' => $partner?->id,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return redirect()->route('admin.funded_advisor.reports.show', $report);
    }

    public function show(FundedAdvisorReport $fundedAdvisorReport): Response
    {
        $this->viewer();

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
