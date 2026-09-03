<?php

namespace Tests\Feature\System;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_endpoint_checks_database_and_cache(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ready',
                'checks' => [
                    'database' => true,
                    'cache' => true,
                ],
            ]);
    }
}
