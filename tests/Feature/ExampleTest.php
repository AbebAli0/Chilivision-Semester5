<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_api_documentation_page_and_json_spec_work(): void
    {
        $htmlResponse = $this->get('/api');
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('Daftar Lengkap Endpoint API ChiliVision');

        $jsonResponse = $this->getJson('/api');
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertJsonStructure([
            'name',
            'version',
            'total_groups',
            'total_primary_endpoints',
            'groups',
            'endpoints',
        ]);
    }
}
