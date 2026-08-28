<?php

namespace Tests\Feature\Admin\Branding;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminBrandingTest extends TestCase
{
    public function test_public_admin_login_renders_official_tradex_branding(): void
    {
        $this->get(
            route('admin.login')
        )
            ->assertOk()
            ->assertSee(
                'admin/brand/tradex-logo.webp',
                false
            )
            ->assertSee(
                'admin/brand/tradex-favicon.png',
                false
            )
            ->assertSee(
                'admin/css/branding.css',
                false
            )
            ->assertSee(
                'LEARN • TRADE • GROW',
                false
            )
            ->assertSee(
                'Protected TradeX Administration'
            );
    }

    public function test_official_brand_assets_exist_and_are_not_empty(): void
    {
        $logo = public_path(
            'admin/brand/tradex-logo.webp'
        );

        $favicon = public_path(
            'admin/brand/tradex-favicon.png'
        );

        $this->assertFileExists($logo);
        $this->assertFileExists($favicon);

        $this->assertGreaterThan(
            1000,
            File::size($logo)
        );

        $this->assertGreaterThan(
            1000,
            File::size($favicon)
        );
    }

    public function test_shared_admin_layout_wires_favicon_branding_and_non_blocking_loader(): void
    {
        $layout = File::get(
            resource_path(
                'views/admin/layouts/app.blade.php'
            )
        );

        $this->assertStringContainsString(
            "admin/brand/tradex-favicon.png",
            $layout
        );

        $this->assertStringContainsString(
            "admin/css/branding.css",
            $layout
        );

        $this->assertStringContainsString(
            'id="tradexLoader"',
            $layout
        );

        $this->assertStringContainsString(
            "admin/js/branding.js",
            $layout
        );

        $loaderScript = File::get(
            public_path(
                'admin/js/branding.js'
            )
        );

        $this->assertStringContainsString(
            '2200',
            $loaderScript
        );

        $this->assertStringContainsString(
            'loader.remove()',
            $loaderScript
        );
    }

    public function test_sidebar_topbar_and_dashboard_reference_official_brand_assets(): void
    {
        $brandingCss = File::get(
            public_path(
                'admin/css/branding.css'
            )
        );

        $dashboard = File::get(
            resource_path(
                'views/admin/dashboard/index.blade.php'
            )
        );

        $this->assertStringContainsString(
            "url('../brand/tradex-logo.webp')",
            $brandingCss
        );

        $this->assertStringContainsString(
            "url('../brand/tradex-favicon.png')",
            $brandingCss
        );

        $this->assertStringContainsString(
            "admin/brand/tradex-logo.webp",
            $dashboard
        );
    }

    public function test_totp_challenge_keeps_security_contract_while_rendering_branding(): void
    {
        $view = File::get(
            resource_path(
                'views/admin/auth/totp_challenge.blade.php'
            )
        );

        $this->assertStringContainsString(
            "route('admin.totp.challenge.submit')",
            $view
        );

        $this->assertStringContainsString(
            'name="code"',
            $view
        );

        $this->assertStringContainsString(
            "admin/brand/tradex-logo.webp",
            $view
        );

        $this->assertStringContainsString(
            "admin/brand/tradex-favicon.png",
            $view
        );
    }

    public function test_report_brand_header_component_renders_official_identity(): void
    {
        $html = view(
            'admin.components.report-brand-header',
            [
                'title' =>
                    'ADMIN-BRAND-01 Test Report',

                'subtitle' =>
                    'Branding Regression',
            ]
        )->render();

        $this->assertStringContainsString(
            'ADMIN-BRAND-01 Test Report',
            $html
        );

        $this->assertStringContainsString(
            'Branding Regression',
            $html
        );

        $this->assertStringContainsString(
            'admin/brand/tradex-logo.webp',
            $html
        );

        $this->assertStringContainsString(
            'LEARN • TRADE • GROW',
            $html
        );
    }
}
