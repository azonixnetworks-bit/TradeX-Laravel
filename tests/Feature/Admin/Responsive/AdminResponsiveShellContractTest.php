<?php

namespace Tests\Feature\Admin\Responsive;

use Tests\TestCase;

class AdminResponsiveShellContractTest extends TestCase
{
    public function test_admin_layout_loads_responsive_layer_and_sidebar_backdrop(): void
    {
        $layout = file_get_contents(
            resource_path('views/admin/layouts/app.blade.php')
        );

        $this->assertIsString($layout);
        $this->assertStringContainsString('admin/css/responsive.css', $layout);
        $this->assertStringContainsString('id="sidebarBackdrop"', $layout);
        $this->assertStringContainsString('aria-hidden="true"', $layout);
    }

    public function test_topbar_toggle_has_accessible_mobile_navigation_contract(): void
    {
        $topbar = file_get_contents(
            resource_path('views/admin/layouts/topbar.blade.php')
        );

        $this->assertIsString($topbar);
        $this->assertStringContainsString('aria-controls="adminSidebar"', $topbar);
        $this->assertStringContainsString('aria-expanded="false"', $topbar);
        $this->assertStringContainsString('class="topbar-totp-link"', $topbar);
    }

    public function test_sidebar_script_supports_safe_mobile_open_and_close_paths(): void
    {
        $script = file_get_contents(
            public_path('admin/js/sidebar.js')
        );

        $this->assertIsString($script);
        $this->assertStringContainsString("'sidebarBackdrop'", $script);
        $this->assertStringContainsString("event.key === 'Escape'", $script);
        $this->assertStringContainsString("'sidebar-open'", $script);
        $this->assertStringContainsString("'(max-width: 1000px)'", $script);
        $this->assertStringContainsString("'aria-expanded'", $script);
    }

    public function test_responsive_styles_cover_supported_shell_breakpoints_and_accessibility(): void
    {
        $css = file_get_contents(
            public_path('admin/css/responsive.css')
        );

        $this->assertIsString($css);

        foreach (
            [
                '@media (max-width: 1250px)',
                '@media (max-width: 1000px)',
                '@media (max-width: 820px)',
                '@media (max-width: 640px)',
                '@media (max-width: 520px)',
                '@media (max-width: 430px)',
                '@media (pointer: coarse)',
                '@media (prefers-reduced-motion: reduce)',
            ] as $contract
        ) {
            $this->assertStringContainsString($contract, $css);
        }

        foreach (
            [
                'body.sidebar-open',
                '.sidebar-backdrop',
                '.sidebar-close',
                '.user-stat-grid',
                '.form-grid',
                '.language-card-grid',
                '.integration-card-grid',
                '.users-table.compact-meta-table',
                'min-width: 0;',
            ] as $contract
        ) {
            $this->assertStringContainsString($contract, $css);
        }
    }

    public function test_language_management_uses_responsive_card_grid_and_compact_table_contract(): void
    {
        $view = file_get_contents(
            resource_path('views/admin/languages/index.blade.php')
        );

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'class="language-card-grid"',
            $view
        );
        $this->assertStringContainsString(
            'class="users-table compact-meta-table"',
            $view
        );
        $this->assertStringNotContainsString(
            'grid-template-columns:repeat(auto-fit,minmax(310px,1fr))',
            $view
        );
    }

    public function test_integrations_management_uses_responsive_card_grid_and_compact_table_contract(): void
    {
        $view = file_get_contents(
            resource_path('views/admin/integrations/index.blade.php')
        );

        $this->assertIsString($view);
        $this->assertStringContainsString(
            'class="integration-card-grid"',
            $view
        );
        $this->assertStringContainsString(
            'class="users-table compact-meta-table"',
            $view
        );
        $this->assertStringNotContainsString(
            'grid-template-columns:repeat(auto-fit,minmax(280px,1fr))',
            $view
        );
    }

    public function test_audit_logs_reuse_trade_x_pagination_instead_of_default_laravel_pagination(): void
    {
        $auditView = file_get_contents(
            resource_path('views/admin/audit/index.blade.php')
        );

        $pagination = file_get_contents(
            resource_path('views/admin/components/pagination.blade.php')
        );

        $this->assertIsString($auditView);
        $this->assertIsString($pagination);

        $this->assertStringContainsString(
            "@include('admin.components.pagination'",
            $auditView
        );
        $this->assertStringNotContainsString(
            '$logs->links()',
            $auditView
        );
        $this->assertStringContainsString(
            'style="flex-wrap:wrap;max-width:100%;"',
            $pagination
        );
        $this->assertStringContainsString(
            'aria-label="Previous page"',
            $pagination
        );
        $this->assertStringContainsString(
            'aria-label="Next page"',
            $pagination
        );
    }
}
