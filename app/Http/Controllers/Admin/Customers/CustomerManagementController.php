<?php

namespace App\Http\Controllers\Admin\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Customers\StoreCustomerOnboardingInvitationRequest;
use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use App\Models\User;
use App\Models\UserRegistrationSession;
use App\Services\Admin\Customers\CustomerOnboardingInvitationDeliveryService;
use App\Services\Admin\Customers\CustomerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerManagementController extends Controller
{
    public function dashboard(
        CustomerOnboardingService $onboarding
    ): View {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.view'
        );

        $onboarding->reconcilePending();

        $recentDays = max(
            1,
            min(
                365,
                (int) config(
                    'tradex_customers.recent_days',
                    30
                )
            )
        );

        $stats = [
            'total' =>
                User::query()->count(),

            'active' =>
                User::query()
                    ->where('status', 'ACTIVE')
                    ->count(),

            'free' =>
                User::query()
                    ->where('account_type', 'FREE')
                    ->count(),

            'premium' =>
                User::query()
                    ->where('account_type', 'PREMIUM')
                    ->count(),

            'pending_accounts' =>
                User::query()
                    ->where('status', 'PENDING')
                    ->count(),

            'restricted' =>
                User::query()
                    ->whereIn(
                        'status',
                        ['SUSPENDED', 'BLOCKED']
                    )
                    ->count(),

            'recent' =>
                User::query()
                    ->where(
                        'created_at',
                        '>=',
                        now()->subDays($recentDays)
                    )
                    ->count(),

            'onboarding_pending' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_PENDING
                    )
                    ->count(),

            'onboarding_matched' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_MATCHED
                    )
                    ->count(),

            'self_registration_pending' =>
                UserRegistrationSession::query()
                    ->where(
                        'status',
                        UserRegistrationSession::STATUS_PENDING
                    )
                    ->where(
                        'expires_at',
                        '>',
                        now()
                    )
                    ->count(),
        ];

        $recentCustomers =
            User::query()
                ->select([
                    'id',
                    'name',
                    'email',
                    'whatsapp_number',
                    'country_code',
                    'account_type',
                    'status',
                    'created_at',
                ])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

        $pendingInvitations =
            CustomerOnboardingInvitation::query()
                ->with([
                    'createdByAdmin:id,name,email',
                ])
                ->where(
                    'status',
                    CustomerOnboardingInvitation::STATUS_PENDING
                )
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

        return view(
            'admin.customers.dashboard',
            compact(
                'stats',
                'recentDays',
                'recentCustomers',
                'pendingInvitations'
            )
        );
    }

    public function create(): View
    {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.invite'
        );

        $languages = config(
            'tradex_registration.allowed_languages',
            ['en']
        );

        return view(
            'admin.customers.create',
            compact('languages')
        );
    }

    public function store(
        StoreCustomerOnboardingInvitationRequest $request,
        CustomerOnboardingService $onboarding,
        CustomerOnboardingInvitationDeliveryService $delivery
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.invite'
        );

        $invitation =
            $onboarding->createInvitation(
                $admin,
                $request->validated(),
                $request
            );

        $sent = $delivery->send(
            $invitation,
            $admin,
            $request
        );

        $redirect = redirect()
            ->route(
                'admin.customers.onboarding'
            )
            ->with(
                'created_invitation_uuid',
                $invitation->uuid
            );

        if ($sent) {
            return $redirect->with(
                'success',
                'Customer onboarding invitation created and secure activation link sent. No password, TOTP secret, premium entitlement, or active login was created.'
            );
        }

        return $redirect->with(
            'warning',
            'Customer onboarding invitation was created, but the activation email could not be delivered. Use Resend Invite after checking mail configuration.'
        );
    }

    public function onboarding(
        Request $request,
        CustomerOnboardingService $onboarding
    ): View {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.view'
        );

        $status = strtoupper(
            trim(
                (string) $request->query(
                    'status',
                    ''
                )
            )
        );

        $allowedStatuses = [
            CustomerOnboardingInvitation::STATUS_PENDING,
            CustomerOnboardingInvitation::STATUS_MATCHED,
            CustomerOnboardingInvitation::STATUS_EXPIRED,
            CustomerOnboardingInvitation::STATUS_CONFLICT,
            CustomerOnboardingInvitation::STATUS_REVOKED,
        ];

        if (
            $status !== ''
            && ! in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            abort(
                422,
                'Unsupported onboarding status filter.'
            );
        }

        $invitations =
            $onboarding->invitations(
                $status !== ''
                    ? $status
                    : null,
                20
            );

        $summary = [
            'pending' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_PENDING
                    )
                    ->count(),

            'matched' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_MATCHED
                    )
                    ->count(),

            'expired' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_EXPIRED
                    )
                    ->count(),

            'conflict' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_CONFLICT
                    )
                    ->count(),

            'revoked' =>
                CustomerOnboardingInvitation::query()
                    ->where(
                        'status',
                        CustomerOnboardingInvitation::STATUS_REVOKED
                    )
                    ->count(),
        ];

        return view(
            'admin.customers.onboarding',
            compact(
                'invitations',
                'summary',
                'status'
            )
        );
    }

    public function resendInvitation(
        CustomerOnboardingInvitation $invitation,
        Request $request,
        CustomerOnboardingInvitationDeliveryService $delivery
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.invite'
        );

        $sent = $delivery->send(
            $invitation,
            $admin,
            $request,
            true
        );

        $redirect = redirect()->route(
            'admin.customers.onboarding'
        );

        if ($sent) {
            return $redirect->with(
                'success',
                'A new secure activation link was sent. Any previously issued activation link is now invalid.'
            );
        }

        return $redirect->with(
            'warning',
            'A new usable activation link could not be confirmed. Refresh the queue and retry after checking mail delivery.'
        );
    }

    public function revokeInvitation(
        CustomerOnboardingInvitation $invitation,
        Request $request,
        CustomerOnboardingInvitationDeliveryService $delivery
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission(
            $admin,
            'users.invite'
        );

        $delivery->revoke(
            $invitation,
            $admin,
            $request
        );

        return redirect()
            ->route(
                'admin.customers.onboarding'
            )
            ->with(
                'success',
                'Customer onboarding invitation revoked. Its activation link can no longer be used.'
            );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }
}
