<?php

namespace Tests\Feature\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class StateControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_state_endpoint_get(): void
    {
        $response = $this->getJson('/api/states');

        $response->assertStatus(200);
          $response->assertJson(['data' => [
            ["code" => "01", "name" => "Alabama"],
            ["code" => "02", "name" => "Alaska"],
        ]]);
    }
}
