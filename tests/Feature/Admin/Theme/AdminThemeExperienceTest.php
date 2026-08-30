<?php

namespace Tests\Feature\Admin\Theme;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminThemeExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_layout_wires_early_theme_init_toggle_and_override_assets(): void
    {
        $admin = $this->adminWithDashboardPermission();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="adminThemeToggle"', false)
            ->assertSee('aria-label="Switch to light theme"', false)
            ->assertSee('admin/js/theme-init.js', false)
            ->assertSee('admin/css/themes.css', false)
            ->assertSee('admin/js/theme.js', false);

        $html = (string) $response->getContent();
        $themeInitPosition = strpos($html, 'admin/js/theme-init.js');
        $adminCssPosition = strpos($html, 'admin/css/admin.css');
        $selectsPosition = strpos($html, 'admin/css/selects.css');
        $themesPosition = strpos($html, 'admin/css/themes.css');

        $this->assertIsInt($themeInitPosition);
        $this->assertIsInt($adminCssPosition);
        $this->assertIsInt($selectsPosition);
        $this->assertIsInt($themesPosition);
        $this->assertLessThan($adminCssPosition, $themeInitPosition);
        $this->assertGreaterThan($selectsPosition, $themesPosition);
    }

    public function test_theme_assets_keep_dark_as_default_and_persist_only_light_or_dark_browser_preference(): void
    {
        $init = (string) file_get_contents(public_path('admin/js/theme-init.js'));
        $theme = (string) file_get_contents(public_path('admin/js/theme.js'));

        $this->assertStringContainsString("const STORAGE_KEY = 'tradex.admin.theme';", $init);
        $this->assertStringContainsString("let theme = 'dark';", $init);
        $this->assertStringContainsString("stored === 'light' || stored === 'dark'", $init);
        $this->assertStringContainsString('root.dataset.theme = theme;', $init);
        $this->assertStringNotContainsString('prefers-color-scheme', $init);

        $this->assertStringContainsString("currentTheme() === 'dark' ? 'light' : 'dark'", $theme);
        $this->assertStringContainsString('window.localStorage.setItem(STORAGE_KEY, normalized);', $theme);
        $this->assertStringContainsString("normalized === 'light' ? '#eef3f8' : '#03070d'", $theme);
        $this->assertStringContainsString("window.addEventListener('storage'", $theme);
    }

    public function test_theme_css_provides_light_glassmorphism_hover_and_reduced_motion_contracts(): void
    {
        $css = (string) file_get_contents(public_path('admin/css/themes.css'));

        $this->assertStringContainsString('html[data-theme="light"]', $css);
        $this->assertStringContainsString('html[data-theme="dark"]', $css);
        $this->assertStringContainsString('backdrop-filter: blur(', $css);
        $this->assertStringContainsString('.quick-card-link:hover', $css);
        $this->assertStringContainsString('.theme-toggle', $css);
        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('html[data-theme="light"] .admin-app select', $css);
        $this->assertStringContainsString('html[data-theme="light"] .admin-toast', $css);
    }

    private function adminWithDashboardPermission(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Theme Experience Admin',
            'email' => 'theme-experience-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'THEME_'.strtoupper(Str::random(8)),
            'name' => 'Theme Experience Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permission = AdminPermission::query()->firstOrCreate(
            ['code' => 'dashboard.view'],
            [
                'name' => 'dashboard.view',
                'module' => 'DASHBOARD',
            ]
        );

        $role->permissions()->sync([$permission->id]);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }
}
