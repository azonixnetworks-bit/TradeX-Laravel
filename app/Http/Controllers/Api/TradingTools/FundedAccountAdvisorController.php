<?php

namespace App\Http\Controllers\Api\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use App\Models\User;
use App\Services\Trading\FundedAccountAdvisorService;
use App\Services\Trading\FundedAdvisorPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FundedAccountAdvisorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $maxPlannedEntries = $this->maxPlannedEntries();

        $partners = FundedAccountPartner::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (FundedAccountPartner $partner): array => [
                'uuid' => $partner->uuid,
                'name' => $partner->name,
                'website_url' => $partner->website_url,
                'affiliate_url' => $partner->affiliate_url,
                'promo_code' => $partner->promo_code,
                'offer_text' => $partner->offer_text,
                'supported_countries' => $partner->supported_countries,
                'account_sizes' => $partner->account_sizes,
                'challenge_types' => $partner->challenge_types,
                'is_featured' => $partner->is_featured,
                'priority' => $partner->priority,
            ])
            ->values();

        $reports = FundedAdvisorReport::query()
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->limit(10)
            ->get()
            ->map(fn (FundedAdvisorReport $report): array => $this->reportSummary($report))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'partners' => $partners,
                'recent_reports' => $reports,
                'guardrails' => [
                    'max_recommended_risk_percent' => (float) config('funded_account_advisor.max_recommended_risk_percent', 0.50),
                    'max_daily_plan_percent' => (float) config('funded_account_advisor.max_daily_plan_percent', 1.00),
                    'safety_reserve_percent' => (float) config('funded_account_advisor.safety_reserve_percent', 20.0),
                    'max_planned_entries' => $maxPlannedEntries,
                ],
            ],
        ]);
    }

    public function generate(
        Request $request,
        FundedAccountAdvisorService $advisor
    ): JsonResponse {
        $user = $this->user($request);
        $validated = $request->validate($this->rules());

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
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
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
            'generated_by_admin_id' => null,
            'generated_at' => now(),
        ]);

        AuditLog::record(
            'USER',
            $user->id,
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

        return response()->json([
            'success' => true,
            'data' => $this->reportDetail($report),
        ], 201);
    }

    public function show(
        Request $request,
        FundedAdvisorReport $fundedAdvisorReport
    ): JsonResponse {
        $user = $this->user($request);
        $this->ownedBy($fundedAdvisorReport, $user);

        return response()->json([
            'success' => true,
            'data' => $this->reportDetail($fundedAdvisorReport),
        ]);
    }

    public function pdf(
        Request $request,
        FundedAdvisorReport $fundedAdvisorReport,
        FundedAdvisorPdfService $pdfService
    ): SymfonyResponse {
        $user = $this->user($request);
        $this->ownedBy($fundedAdvisorReport, $user);

        $pdf = $pdfService->render($fundedAdvisorReport);
        $filename = 'tradex-funded-account-plan-'.$fundedAdvisorReport->uuid.'.pdf';

        return Response::make($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
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
            'planned_max_entries' => ['required', 'integer', 'min:1', 'max:'.$this->maxPlannedEntries()],
            'minimum_rr' => ['required', 'numeric', 'gt:0', 'max:20'],
            'minimum_trading_days' => ['required', 'integer', 'min:0', 'max:365'],
            'completed_trading_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ];
    }

    /** @return array<string, mixed> */
    private function reportSummary(FundedAdvisorReport $report): array
    {
        $output = (array) $report->output_json;

        return [
            'uuid' => $report->uuid,
            'prop_firm_name' => $report->prop_firm_name,
            'status' => $output['status'] ?? null,
            'recommended_risk_amount' => $output['recommended_risk_amount'] ?? null,
            'recommended_entries' => $output['recommended_entries'] ?? null,
            'generated_at' => $report->generated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function reportDetail(FundedAdvisorReport $report): array
    {
        return [
            'uuid' => $report->uuid,
            'customer' => [
                'name' => $report->customer_name,
                'email' => $report->customer_email,
            ],
            'prop_firm_name' => $report->prop_firm_name,
            'partner' => filled($report->partner_name_snapshot)
                ? [
                    'name' => $report->partner_name_snapshot,
                    'affiliate_url' => $report->affiliate_url_snapshot,
                    'promo_code' => $report->promo_code_snapshot,
                    'offer_text' => $report->partner_offer_snapshot,
                    'disclosure' => $report->affiliate_disclosure_snapshot,
                ]
                : null,
            'inputs' => (array) $report->inputs_json,
            'output' => (array) $report->output_json,
            'generated_at' => $report->generated_at?->toISOString(),
            'pdf_path' => route('api.v1.funded-advisor.reports.pdf', $report, false),
        ];
    }

    private function maxPlannedEntries(): int
    {
        return max(1, (int) config('funded_account_advisor.max_planned_entries', 5));
    }

    private function ownedBy(FundedAdvisorReport $report, User $user): void
    {
        abort_unless((int) $report->user_id === (int) $user->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return $user;
    }
}
