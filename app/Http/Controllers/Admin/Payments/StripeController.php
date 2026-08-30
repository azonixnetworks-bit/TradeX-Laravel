<?php

namespace App\Http\Controllers\Admin\Payments;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\PaymentOrder;
use App\Services\Payments\PaymentMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StripeController extends Controller
{
    public function index(
        Request $request,
        PaymentMoney $money
    ): View {
        $admin = $this->admin();
        abort_unless($admin->hasPermission('payments.view'), 403);

        $query = PaymentOrder::query()
            ->with([
                'user:id,uuid,name,email,country_code,account_type,status',
                'payment:id,payment_order_id,uuid,status,provider,gross_amount_minor,processor_fee_minor,net_eligible_revenue_minor,affiliate_processing_status,cleared_at,payment_reversed_at,payment_reversal_code',
            ])
            ->where('provider', PaymentOrder::PROVIDER_STRIPE);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $user) use ($search): void {
                        $user
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = trim((string) $request->query('status', ''));
        if (in_array($status, $this->statuses(), true)) {
            $query->where('status', $status);
        }

        $country = strtoupper(trim((string) $request->query('country', '')));
        if (preg_match('/^[A-Z]{2}$/', $country) === 1) {
            $query->where('country_code', $country);
        }

        $from = trim((string) $request->query('from', ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = trim((string) $request->query('to', ''));
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 20);
        if (! in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        return view('admin.payments.stripe', [
            'orders' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString(),
            'money' => $money,
        ]);
    }

    private function statuses(): array
    {
        return [
            PaymentOrder::STATUS_CREATED,
            PaymentOrder::STATUS_AWAITING_USER_ACTION,
            PaymentOrder::STATUS_PROCESSING,
            PaymentOrder::STATUS_CLEARED,
            PaymentOrder::STATUS_FAILED,
            PaymentOrder::STATUS_CANCELLED,
            PaymentOrder::STATUS_PAYMENT_REVERSED,
        ];
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }
}
