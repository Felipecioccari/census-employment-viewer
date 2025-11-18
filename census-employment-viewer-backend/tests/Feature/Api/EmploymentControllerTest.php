<?php

namespace Tests\Feature\Api;

use App\Services\CensusEmploymentService;
use Mockery;
use Tests\TestCase;

class EmploymentControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('states', [
            ['code' => '06', 'name' => 'California'],
            ['code' => '12', 'name' => 'Florida'],
            ['code' => '48', 'name' => 'Texas'],
        ]);
    }

    private function fakeSummary(array $expectedCodes, string $quarter, bool $breakdown, array $payload): void
    {
        $mock = Mockery::mock(CensusEmploymentService::class);
        $mock->shouldReceive('getEmploymentSummary')
            ->once()
            ->with($expectedCodes, $quarter, $breakdown)
            ->andReturn($payload);

        $this->app->instance(CensusEmploymentService::class, $mock);
    }

    public function test_returns_summary_for_requested_states_with_sex_breakdown(): void
    {
        $summary = [
            ['stateCode' => '06', 'stateName' => 'California', 'male' => 10, 'female' => 15, 'total' => 25],
            ['stateCode' => '12', 'stateName' => 'Florida', 'male' => 5, 'female' => 8, 'total' => 13],
        ];

        $this->fakeSummary(['06', '12'], '2023-Q4', true, $summary);

        $response = $this->getJson('/api/employments?quarter=2023-Q4&states=12,06&breakdownSex=1');
        $response->dump();
        $response->dumpHeaders();
        $response->dumpSession();

        $response->assertOk()->assertJson(['data' => $summary]);
    }
}
