<?php

namespace Tests\Feature\Admin\Customers;

use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use App\Models\User;
use App\Services\Admin\Customers\CustomerOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerOnboardingConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_identity_match_is_conflict_not_automatic_match(): void
    {
        $admin = $this->createAdmin();

        $invitation =
            $this->createInvitation(
                $admin,
                'partial@example.test',
                '+94770000201'
            );

        $this->createCustomer([
            'email' =>
                'partial@example.test',
            'whatsapp_number' =>
                '+94770000299',
        ]);

        app(
            CustomerOnboardingService::class
        )->reconcilePending();

        $invitation->refresh();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_CONFLICT,
            $invitation->status
        );

        $this->assertNull(
            $invitation->matched_user_id
        );

        $this->assertNull(
            $invitation->active_email_hash
        );

        $this->assertNull(
            $invitation->active_whatsapp_hash
        );
    }

    public function test_email_and_whatsapp_resolving_to_different_customers_is_conflict(): void
    {
        $admin = $this->createAdmin();

        $invitation =
            $this->createInvitation(
                $admin,
                'split@example.test',
                '+94770000301'
            );

        $this->createCustomer([
            'email' =>
                'split@example.test',
            'whatsapp_number' =>
                '+94770000302',
        ]);

        $this->createCustomer([
            'email' =>
                'other@example.test',
            'whatsapp_number' =>
                '+94770000301',
        ]);

        app(
            CustomerOnboardingService::class
        )->reconcilePending();

        $invitation->refresh();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_CONFLICT,
            $invitation->status
        );

        $this->assertNull(
            $invitation->matched_user_id
        );
    }

    private function createInvitation(
        AdminUser $admin,
        string $email,
        string $whatsapp
    ): CustomerOnboardingInvitation {
        return CustomerOnboardingInvitation::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),
                'name' =>
                    'Conflict Test Customer',
                'email' =>
                    $email,
                'whatsapp_number' =>
                    $whatsapp,
                'country_code' =>
                    'LK',
                'language_code' =>
                    'en',
                'status' =>
                    CustomerOnboardingInvitation::STATUS_PENDING,
                'active_email_hash' =>
                    hash('sha256', $email),
                'active_whatsapp_hash' =>
                    hash('sha256', $whatsapp),
                'created_by_admin_id' =>
                    $admin->id,
                'expires_at' =>
                    now()->addDays(7),
            ]);
    }

    private function createCustomer(
        array $overrides = []
    ): User {
        return User::factory()
            ->create(
                array_merge(
                    [
                        'uuid' =>
                            (string) Str::uuid(),
                        'whatsapp_number' =>
                            '+9477'
                            .random_int(
                                1000000,
                                9999999
                            ),
                        'whatsapp_verified_at' =>
                            now(),
                        'referral_code' =>
                            'TX'
                            .Str::upper(
                                Str::random(10)
                            ),
                        'country_code' =>
                            'LK',
                        'language_code' =>
                            'en',
                        'account_type' =>
                            'FREE',
                        'status' =>
                            'ACTIVE',
                    ],
                    $overrides
                )
            );
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::query()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),
                'name' =>
                    'ADMIN-20 Conflict Test',
                'email' =>
                    'admin20-conflict-'
                    .Str::lower(
                        Str::random(8)
                    )
                    .'@example.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'Testing-Admin-Pass-123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);
    }
}
