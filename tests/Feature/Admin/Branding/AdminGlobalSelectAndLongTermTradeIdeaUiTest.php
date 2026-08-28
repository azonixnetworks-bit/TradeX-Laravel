<?php

namespace Tests\Feature\Admin\Branding;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminGlobalSelectAndLongTermTradeIdeaUiTest extends TestCase
{
    public function test_shared_admin_layout_loads_global_select_normalization_after_module_styles(): void
    {
        $layout = File::get(
            resource_path('views/admin/layouts/app.blade.php')
        );

        $this->assertStringContainsString(
            "admin/css/selects.css",
            $layout
        );

        $stackPosition = strpos($layout, "@stack('styles')");
        $selectPosition = strpos($layout, "admin/css/selects.css");

        $this->assertNotFalse($stackPosition);
        $this->assertNotFalse($selectPosition);
        $this->assertGreaterThan($stackPosition, $selectPosition);
    }

    public function test_global_select_styles_keep_dark_readable_native_options(): void
    {
        $css = File::get(
            public_path('admin/css/selects.css')
        );

        $this->assertStringContainsString(
            'color-scheme: dark',
            $css
        );

        $this->assertStringContainsString(
            '.admin-app select option',
            $css
        );

        $this->assertStringContainsString(
            'background: #071624',
            $css
        );

        $this->assertStringContainsString(
            '.admin-app .ti-field select',
            $css
        );
    }

    public function test_admin_trade_idea_display_name_is_long_term_without_renaming_backend_contracts(): void
    {
        $sidebar = File::get(
            resource_path('views/admin/layouts/sidebar.blade.php')
        );

        $index = File::get(
            resource_path('views/admin/trade_ideas/index.blade.php')
        );

        $create = File::get(
            resource_path('views/admin/trade_ideas/create.blade.php')
        );

        $edit = File::get(
            resource_path('views/admin/trade_ideas/edit.blade.php')
        );

        $this->assertStringContainsString(
            'Long Term Trade Ideas',
            $sidebar
        );

        $this->assertStringContainsString(
            "@section('page-title', 'Long Term Trade Ideas')",
            $index
        );

        $this->assertStringContainsString(
            'Create Long Term Trade Idea',
            $create
        );

        $this->assertStringContainsString(
            'Edit Long Term Trade Idea',
            $edit
        );

        foreach ([$sidebar, $index, $create, $edit] as $view) {
            $this->assertStringContainsString(
                'admin.trade_ideas',
                $view
            );
        }
    }
}
