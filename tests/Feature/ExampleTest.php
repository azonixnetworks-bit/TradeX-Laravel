<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_admin_login(): void
    {
        $response =
            $this->get('/');

        $response->assertRedirect(
            route('admin.login')
        );
    }
}