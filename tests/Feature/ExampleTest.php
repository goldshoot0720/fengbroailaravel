<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_workspace_route_is_not_missing(): void
    {
        $response = $this->get('/');

        $this->assertNotSame(404, $response->status());
    }

    public function test_legacy_page_query_is_not_missing(): void
    {
        $response = $this->get('/index.php?page=about');

        $this->assertNotSame(404, $response->status());
    }
}
