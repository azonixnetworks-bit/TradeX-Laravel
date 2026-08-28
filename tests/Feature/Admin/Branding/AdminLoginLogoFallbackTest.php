<?php

namespace Tests\Feature\Admin\Branding;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminLoginLogoFallbackTest extends TestCase
{
    public function test_login_keeps_semantic_logo_and_css_background_fallback(): void
    {
        $login = File::get(
            resource_path('views/admin/auth/login.blade.php')
        );

        $branding = File::get(
            public_path('admin/css/branding.css')
        );

        $this->assertStringContainsString(
            "admin/brand/tradex-logo.webp",
            $login
        );

        $this->assertStringContainsString(
            '.brand-logo-wrap',
            $branding
        );

        $this->assertStringContainsString(
            '.login-mobile-brand',
            $branding
        );

        $this->assertStringContainsString(
            "background-image: url('../brand/tradex-logo.webp')",
            $branding
        );
    }
}
