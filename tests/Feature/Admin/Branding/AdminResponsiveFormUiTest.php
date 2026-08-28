<?php

namespace Tests\Feature\Admin\Branding;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminResponsiveFormUiTest extends TestCase
{
    public function test_shared_admin_layout_loads_responsive_form_system(): void
    {
        $layout = File::get(
            resource_path(
                'views/admin/layouts/app.blade.php'
            )
        );

        $this->assertStringContainsString(
            "admin/css/forms.css",
            $layout
        );

        $css = File::get(
            public_path(
                'admin/css/forms.css'
            )
        );

        $this->assertStringContainsString(
            '.form-grid',
            $css
        );

        $this->assertStringContainsString(
            'grid-template-columns: repeat(2, minmax(0, 1fr))',
            $css
        );

        $this->assertStringContainsString(
            '@media (max-width: 820px)',
            $css
        );

        $this->assertStringContainsString(
            'grid-template-columns: 1fr',
            $css
        );
    }

    public function test_customer_form_preserves_onboarding_contract_and_uses_premium_form_classes(): void
    {
        $view = File::get(
            resource_path(
                'views/admin/customers/create.blade.php'
            )
        );

        $this->assertStringContainsString(
            "route('admin.customers.store')",
            $view
        );

        foreach ([
            'name="name"',
            'name="email"',
            'name="whatsapp_number"',
            'name="country_code"',
            'name="language_code"',
            'name="referral_code"',
            'name="internal_note"',
        ] as $field) {
            $this->assertStringContainsString(
                $field,
                $view
            );
        }

        $this->assertStringContainsString(
            'admin-form-callout',
            $view
        );

        $this->assertStringContainsString(
            'admin-form-panel',
            $view
        );

        $this->assertStringContainsString(
            'admin-form-actions',
            $view
        );

        $this->assertStringContainsString(
            'Initial account type: FREE',
            $view
        );
    }

    public function test_integration_form_preserves_control_plane_contract_and_uses_premium_form_classes(): void
    {
        $view = File::get(
            resource_path(
                'views/admin/integrations/show.blade.php'
            )
        );

        $this->assertStringContainsString(
            "'admin.integrations.update'",
            $view
        );

        foreach ([
            'name="version"',
            'name="is_enabled"',
            'name="environment"',
            'name="countries"',
            'name="eligibility_confirmed"',
            'name="change_reason"',
        ] as $field) {
            $this->assertStringContainsString(
                $field,
                $view
            );
        }

        $this->assertStringContainsString(
            "@method('PATCH')",
            $view
        );

        $this->assertStringContainsString(
            'Control-plane only',
            $view
        );

        $this->assertStringContainsString(
            'admin-form-panel',
            $view
        );

        $this->assertStringContainsString(
            'admin-form-actions',
            $view
        );
    }
}
