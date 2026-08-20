<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\UserIndexRequest;
use App\Http\Requests\Admin\Users\UserStatusActionRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRefreshToken;
use App\Services\Admin\Users\UserAccountStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Users Index
    |--------------------------------------------------------------------------
    */

    public function index(
        UserIndexRequest $request
    ): View {

        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Controller-Level RBAC
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'users.view'
        );


        $filters =
            $request->validated();

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        $accountType =
            $filters['account_type']
            ?? null;

        $status =
            $filters['status']
            ?? null;

        $country =
            isset(
                $filters['country']
            )
                ? strtoupper(
                    $filters['country']
                )
                : null;

        $sort =
            $filters['sort']
            ?? 'newest';

        $perPage =
            (int) (
                $filters['per_page']
                ?? 20
            );


        /*
        |--------------------------------------------------------------------------
        | Users Query
        |--------------------------------------------------------------------------
        */

        $query =
            User::query()
                ->select([
                    'id',
                    'uuid',
                    'name',
                    'email',
                    'email_verified_at',
                    'whatsapp_number',
                    'whatsapp_verified_at',
                    'referral_code',
                    'country_code',
                    'language_code',
                    'account_type',
                    'status',
                    'premium_activated_at',
                    'last_login_at',
                    'created_at',
                ]);


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $query->where(
                function (
                    Builder $query
                ) use ($search) {

                    $query
                        ->where(
                            'name',
                            'like',
                            '%'
                            . $search
                            . '%'
                        )
                        ->orWhere(
                            'email',
                            'like',
                            '%'
                            . $search
                            . '%'
                        )
                        ->orWhere(
                            'whatsapp_number',
                            'like',
                            '%'
                            . $search
                            . '%'
                        )
                        ->orWhere(
                            'referral_code',
                            'like',
                            '%'
                            . $search
                            . '%'
                        )
                        ->orWhere(
                            'uuid',
                            $search
                        );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        if ($accountType) {

            $query->where(
                'account_type',
                $accountType
            );
        }


        if ($status) {

            $query->where(
                'status',
                $status
            );
        }


        if ($country) {

            $query->where(
                'country_code',
                $country
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */

        match ($sort) {

            'oldest' =>
                $query->orderBy(
                    'created_at',
                    'asc'
                ),

            'name_asc' =>
                $query->orderBy(
                    'name',
                    'asc'
                ),

            'name_desc' =>
                $query->orderBy(
                    'name',
                    'desc'
                ),

            default =>
                $query->orderBy(
                    'created_at',
                    'desc'
                ),
        };


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $users =
            $query
                ->paginate(
                    $perPage
                )
                ->withQueryString();


        /*
        |--------------------------------------------------------------------------
        | Summary Cards
        |--------------------------------------------------------------------------
        */

        $stats = [

            'total' =>
                User::count(),

            'active' =>
                User::where(
                    'status',
                    'ACTIVE'
                )->count(),

            'free' =>
                User::where(
                    'account_type',
                    'FREE'
                )->count(),

            'premium' =>
                User::where(
                    'account_type',
                    'PREMIUM'
                )->count(),

            'suspended' =>
                User::where(
                    'status',
                    'SUSPENDED'
                )->count(),

            'blocked' =>
                User::where(
                    'status',
                    'BLOCKED'
                )->count(),
        ];


        return view(
            'admin.users.index',
            compact(
                'users',
                'stats',
                'filters'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | User Details
    |--------------------------------------------------------------------------
    */

    public function show(
        User $user
    ): View {

        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Controller-Level RBAC
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'users.view_details'
        );


        /*
        |--------------------------------------------------------------------------
        | User Information
        |--------------------------------------------------------------------------
        */

        $user->load([

            'referrer' =>
                function ($query) {

                    $query->select([
                        'id',
                        'uuid',
                        'name',
                        'email',
                        'referral_code',
                    ]);
                },

            'statusChangedByAdmin',
        ]);


        $user->loadCount(
            'referrals'
        );


        /*
        |--------------------------------------------------------------------------
        | Device Security
        |--------------------------------------------------------------------------
        */

        $devices =
            collect();

        $activeSessionCount =
            0;

        $canViewDevices =
            $admin->hasPermission(
                'users.devices.view'
            );


        if ($canViewDevices) {

            $devices =
                $user
                    ->devices()
                    ->with([
                        'revokedByAdmin',
                    ])
                    ->withCount([
                        'refreshTokens as active_refresh_tokens_count'
                            => function ($query) {

                                $query
                                    ->whereNull(
                                        'revoked_at'
                                    )
                                    ->where(
                                        'expires_at',
                                        '>',
                                        now()
                                    );
                            },
                    ])
                    ->orderByDesc(
                        'registered_at'
                    )
                    ->orderByDesc(
                        'id'
                    )
                    ->get();


            $activeSessionCount =
                UserRefreshToken::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->active()
                    ->count();
        }


        /*
        |--------------------------------------------------------------------------
        | Audit User Detail Access
        |--------------------------------------------------------------------------
        */

        AuditLog::record(
            actorType:
                'ADMIN',

            actorId:
                $admin->id,

            action:
                'USER_DETAIL_VIEWED',

            module:
                'USERS',

            targetType:
                'USER',

            targetId:
                $user->id,

            ipAddress:
                request()->ip(),

            userAgent:
                request()->userAgent()
        );


        return view(
            'admin.users.show',
            compact(
                'user',
                'devices',
                'activeSessionCount',
                'canViewDevices'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Update User Account Status
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        UserStatusActionRequest $request,
        User $user,
        UserAccountStatusService $statusService
    ): RedirectResponse {

        $validated =
            $request->validated();


        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Authenticated Admin Boundary
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();


        /*
        |--------------------------------------------------------------------------
        | Action-Specific Permission
        |--------------------------------------------------------------------------
        |
        | Never authorize every account lifecycle mutation using one broad
        | permission.
        |
        | ACTIVATE -> users.activate
        | SUSPEND  -> users.suspend
        | BLOCK    -> users.block
        |
        */

        $requiredPermission =
            match (
                $validated['action']
            ) {

                'ACTIVATE' =>
                    'users.activate',

                'SUSPEND' =>
                    'users.suspend',

                'BLOCK' =>
                    'users.block',

                default =>
                    null,
            };


        /*
        |--------------------------------------------------------------------------
        | Unsupported Action Failure Path
        |--------------------------------------------------------------------------
        |
        | UserStatusActionRequest should already reject unsupported values.
        | Keep this controller boundary as defence-in-depth.
        |
        */

        abort_if(
            $requiredPermission === null,
            422,
            'Unsupported account status action.'
        );


        $this->requirePermission(
            $admin,
            $requiredPermission
        );


        /*
        |--------------------------------------------------------------------------
        | Execute Status Change
        |--------------------------------------------------------------------------
        */

        $statusService->changeStatus(
            user:
                $user,

            admin:
                $admin,

            action:
                $validated['action'],

            reason:
                trim(
                    $validated['reason']
                ),

            ipAddress:
                $request->ip(),

            userAgent:
                $request->userAgent()
        );


        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */

        $message =
            match (
                $validated['action']
            ) {

                'ACTIVATE' =>
                    'User account activated successfully.',

                'SUSPEND' =>
                    'User account suspended successfully.',

                'BLOCK' =>
                    'User account blocked successfully.',

                default =>
                    'User account updated successfully.',
            };


        return redirect()
            ->route(
                'admin.users.show',
                $user
            )
            ->with(
                'success',
                $message
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-02E-4C — Authenticated Admin Resolver
    |--------------------------------------------------------------------------
    |
    | routes/admin.php is already protected by the separate admin.auth
    | middleware.
    |
    | This method adds an explicit controller boundary so an unexpected guard
    | or middleware configuration failure cannot result in a null admin being
    | passed into privileged services.
    |
    */

    private function authenticatedAdmin(): AdminUser
    {
        $admin =
            auth('admin')->user();


        abort_unless(
            $admin instanceof AdminUser,
            401
        );


        return $admin;
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-02E-4C — Permission Boundary
    |--------------------------------------------------------------------------
    */

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