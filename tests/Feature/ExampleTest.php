<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_public_homepage_is_available(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('TradeX OS')
            ->assertSee('Market intelligence');
    }
}
