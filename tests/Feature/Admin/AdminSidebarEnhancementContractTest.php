<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminSidebarEnhancementContractTest extends TestCase
{
    public function test_sidebar_collapse_and_section_dropdown_assets_are_loaded(): void
    {
        $layout = file_get_contents(
            resource_path(
                'views/admin/layouts/app.blade.php'
            )
        );

        $css = file_get_contents(
            public_path(
                'admin/css/sidebar-enhancements.css'
            )
        );

        $js = file_get_contents(
            public_path(
                'admin/js/sidebar-enhancements.js'
            )
        );

        $this->assertIsString($layout);
        $this->assertIsString($css);
        $this->assertIsString($js);

        $this->assertStringContainsString(
            'admin/css/sidebar-enhancements.css',
            $layout
        );

        $this->assertStringContainsString(
            'admin/js/sidebar-enhancements.js',
            $layout
        );

        $this->assertStringContainsString(
            'body.sidebar-collapsed .sidebar',
            $css
        );

        $this->assertStringContainsString(
            '.sidebar-section-toggle',
            $css
        );

        $this->assertStringContainsString(
            'tradex.admin.sidebar.desktop.collapsed.v1',
            $js
        );

        $this->assertStringContainsString(
            'tradex.admin.sidebar.section.v1.',
            $js
        );

        $this->assertStringContainsString(
            'aria-expanded',
            $js
        );
    }
}
