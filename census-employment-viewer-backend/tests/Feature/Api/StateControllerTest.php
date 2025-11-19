<?php

namespace Tests\Feature\Api;

use Illuminate\Testing\Fluent\AssertableJson;
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
            ['code' => '01', 'name' => 'Alabama'],
            ['code' => '02', 'name' => 'Alaska'],
        ]]);
        $response->assertJson(function (AssertableJson $json) {
            $json->whereType('data.0.code', 'string')
                ->whereType('data.0.name', 'string');
        });

    }
}
