<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentOrderService
{
    public function __construct(
        private readonly PaymentApiPresenter $presenter
    ) {
    }

    public function history(
        User $user,
        array $filters
    ): array {
        $query = PaymentOrder::query()
            ->with([
                'payment',
                'bankDeposit',
                'usdtDeposit',
                'cryptoDeposit',
            ])
            ->where(
                'user_id',
                $user->getKey()
            );

        if (filled($filters['provider'] ?? null)) {
            $query->where(
                'provider',
                $filters['provider']
            );
        }

        if (filled($filters['status'] ?? null)) {
            $query->where(
                'status',
                $filters['status']
            );
        }

        $paginator = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                $this->perPage($filters)
            )
            ->withQueryString();

        return [
            'items' =>
                collect($paginator->items())
                    ->map(
                        fn (PaymentOrder $order): array =>
                            $this->presenter->order($order)
                    )
                    ->values()
                    ->all(),
            'pagination' =>
                $this->pagination($paginator),
        ];
    }

    public function show(
        User $user,
        string $uuid
    ): array {
        return $this->presenter->order(
            $this->resolveOwned(
                $user,
                $uuid
            )
        );
    }

    public function resolveOwned(
        User $user,
        string $uuid
    ): PaymentOrder {
        $order = PaymentOrder::query()
            ->with([
                'payment',
                'bankDeposit',
                'usdtDeposit',
                'cryptoDeposit',
            ])
            ->where('uuid', $uuid)
            ->where(
                'user_id',
                $user->getKey()
            )
            ->first();

        if (! $order instanceof PaymentOrder) {
            throw new AuthApiException(
                404,
                'PAYMENT_ORDER_NOT_AVAILABLE',
                'This payment order is not available.'
            );
        }

        return $order;
    }

    public function activationStatus(
        User $user
    ): array {
        $latest = PaymentOrder::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->orderByDesc('created_at')
            ->first();

        return [
            'account_type' =>
                $user->account_type,
            'premium_active' =>
                $user->canAccessPremium(),
            'premium_activated_at' =>
                $user->premium_activated_at
                    ?->toISOString(),
            'latest_payment_order' =>
                $latest instanceof PaymentOrder
                    ? [
                        'uuid' => $latest->uuid,
                        'provider' => $latest->provider,
                        'status' => $latest->status,
                        'created_at' =>
                            $latest->created_at
                                ?->toISOString(),
                    ]
                    : null,
        ];
    }

    public function idempotencyHash(
        string $key
    ): string {
        return hash(
            'sha256',
            strtolower(
                trim($key)
            )
        );
    }

    private function perPage(
        array $filters
    ): int {
        return max(
            1,
            min(
                (int) (
                    $filters['per_page']
                    ?? config(
                        'tradex_payments.pagination.default_per_page',
                        20
                    )
                ),
                max(
                    1,
                    (int) config(
                        'tradex_payments.pagination.max_per_page',
                        50
                    )
                )
            )
        );
    }

    private function pagination(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'current_page' =>
                $paginator->currentPage(),
            'last_page' =>
                $paginator->lastPage(),
            'per_page' =>
                $paginator->perPage(),
            'total' =>
                $paginator->total(),
        ];
    }
}
