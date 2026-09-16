<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingRoutineDailyPlan;
use App\Models\TradingRoutineDelivery;
use App\Models\TradingRoutinePreference;
use App\Services\Trading\TradingRoutinePdfService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TradingRoutineController extends Controller
{
    public function index(): Response
    {
        $this->viewer();
        $today = now()->toDateString();

        return response()->view(
            'admin.trading_tools.trading_routine.index',
            [
                'stats' => [
                    'enabled_customers' => TradingRoutinePreference::query()->where('enabled', true)->count(),
                    'whatsapp_opted_in' => TradingRoutinePreference::query()
                        ->where('enabled', true)
                        ->where('whatsapp_pdf_enabled', true)
                        ->count(),
                    'today_plans' => TradingRoutineDailyPlan::query()->whereDate('plan_date', $today)->count(),
                    'today_accepted' => TradingRoutineDelivery::query()
                        ->where('status', TradingRoutineDelivery::STATUS_ACCEPTED)
                        ->whereDate('accepted_at', $today)
                        ->count(),
                    'failed_deliveries' => TradingRoutineDelivery::query()
                        ->where('status', TradingRoutineDelivery::STATUS_FAILED)
                        ->count(),
                ],
                'recentPlans' => TradingRoutineDailyPlan::query()
                    ->with(['user', 'delivery'])
                    ->latest('generated_at')
                    ->paginate(30)
                    ->withQueryString(),
                'automationEnabled' => (bool) config('trading_routine.enabled', true),
                'whatsappDriver' => (string) config('trading_routine.whatsapp.driver', 'disabled'),
                'templateConfigured' => filled(config('trading_routine.whatsapp.template_name')),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function pdf(
        TradingRoutineDailyPlan $tradingRoutineDailyPlan,
        TradingRoutinePdfService $pdf
    ): SymfonyResponse {
        $this->viewer();
        $filename = 'tradex-daily-trading-plan-'.$tradingRoutineDailyPlan->plan_date?->format('Y-m-d').'.pdf';

        return response($pdf->render($tradingRoutineDailyPlan), 200, array_merge(
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
        abort_unless($admin->hasPermission('trading_routine.view'), 403);

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
