<?php

namespace Tests\Feature\Admin\Responsive;

use Tests\TestCase;

class AdminResponsiveShellContractTest extends TestCase
{
    public function test_admin_layout_loads_responsive_layer_and_sidebar_backdrop(): void
    {
        $layout =
            file_get_contents(
                resource_path(
                    'views/admin/layouts/app.blade.php'
                )
            );

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "admin/css/responsive.css",
            $layout
        );
        $this->assertStringContainsString(
            'id="sidebarBackdrop"',
            $layout
        );
        $this->assertStringContainsString(
            'aria-hidden="true"',
            $layout
        );
    }

    public function test_topbar_toggle_has_accessible_mobile_navigation_contract(): void
    {
        $topbar =
            file_get_contents(
                resource_path(
                    'views/admin/layouts/topbar.blade.php'
                )
            );

        $this->assertIsString($topbar);
        $this->assertStringContainsString(
            'aria-controls="adminSidebar"',
            $topbar
        );
        $this->assertStringContainsString(
            'aria-expanded="false"',
            $topbar
        );
        $this->assertStringContainsString(
            'class="topbar-totp-link"',
            $topbar
        );
    }

    public function test_sidebar_script_supports_safe_mobile_open_and_close_paths(): void
    {
        $script =
            file_get_contents(
                public_path(
                    'admin/js/sidebar.js'
                )
            );

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "'sidebarBackdrop'",
            $script
        );
        $this->assertStringContainsString(
            "event.key === 'Escape'",
            $script
        );
        $this->assertStringContainsString(
            "'sidebar-open'",
            $script
        );
        $this->assertStringContainsString(
            "'(max-width: 1000px)'",
            $script
        );
        $this->assertStringContainsString(
            "'aria-expanded'",
            $script
        );
    }

    public function test_responsive_styles_cover_supported_shell_breakpoints_and_accessibility(): void
    {
        $css =
            file_get_contents(
                public_path(
                    'admin/css/responsive.css'
                )
            );

        $this->assertIsString($css);

        foreach (
            [
                '@media (max-width: 1250px)',
                '@media (max-width: 1000px)',
                '@media (max-width: 820px)',
                '@media (max-width: 640px)',
                '@media (max-width: 430px)',
                '@media (pointer: coarse)',
                '@media (prefers-reduced-motion: reduce)',
            ]
            as $contract
        ) {
            $this->assertStringContainsString(
                $contract,
                $css
            );
        }

        $this->assertStringContainsString(
            'body.sidebar-open',
            $css
        );
        $this->assertStringContainsString(
            '.sidebar-backdrop',
            $css
        );
        $this->assertStringContainsString(
            '.sidebar-close',
            $css
        );
    }
}
