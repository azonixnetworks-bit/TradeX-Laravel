<?php

namespace Tests\Unit\Admin\Navigation;

use Tests\TestCase;

class AdminSidebarScrollPersistenceTest extends TestCase
{
    public function test_sidebar_script_persists_and_restores_navigation_scroll_position(): void
    {
        $script = file_get_contents(
            public_path('admin/js/sidebar.js')
        );

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "'.sidebar-nav'",
            $script
        );
        $this->assertStringContainsString(
            'tradex.admin.sidebar.scroll.v1',
            $script
        );
        $this->assertStringContainsString(
            'window.sessionStorage.getItem',
            $script
        );
        $this->assertStringContainsString(
            'window.sessionStorage.setItem',
            $script
        );
        $this->assertStringContainsString(
            'nav.scrollTop',
            $script
        );
        $this->assertStringContainsString(
            "'pagehide'",
            $script
        );
        $this->assertStringContainsString(
            "'visibilitychange'",
            $script
        );
    }

    public function test_active_menu_remains_discoverable_when_no_scroll_position_exists(): void
    {
        $script = file_get_contents(
            public_path('admin/js/sidebar.js')
        );

        $this->assertIsString($script);
        $this->assertStringContainsString(
            "'.nav-link.active'",
            $script
        );
        $this->assertStringContainsString(
            'activeLink.scrollIntoView',
            $script
        );
        $this->assertStringContainsString(
            "block: 'nearest'",
            $script
        );
    }

    public function test_scroll_persistence_does_not_depend_on_mobile_toggle_controls(): void
    {
        $script = file_get_contents(
            public_path('admin/js/sidebar.js')
        );

        $this->assertIsString($script);

        $restorePosition = strpos(
            $script,
            'restoreSidebarScroll();'
        );

        $mobileGuardPosition = strpos(
            $script,
            'if (! toggle || ! backdrop)'
        );

        $this->assertNotFalse($restorePosition);
        $this->assertNotFalse($mobileGuardPosition);
        $this->assertLessThan(
            $mobileGuardPosition,
            $restorePosition
        );
    }
}
