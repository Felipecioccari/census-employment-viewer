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

        $this->fakeSummary(['06', '12'], '2023-Q4', true, [
            'rows' => $summary,
            'errors' => [],
        ]);

        $response = $this->getJson('/api/employments?quarter=2023-Q4&states=12,06&breakdownSex=1');

        $response->assertOk()->assertJson([
            'data' => $summary,
            'errors' => [],
            'hasErrors' => false,
        ]);
    }

    public function test_returns_partial_rows_when_one_sex_fails(): void
    {
        $rows = [
            ['stateCode' => '06', 'stateName' => 'California', 'male' => 10,
                'female' => null, 'total' => 10],
        ];
        $errors = [
            ['stateCode' => '06', 'sex' => 'female', 'message' => 'boom'],
        ];

        $this->fakeSummary(['06'], '2023-Q4', true, [
            'rows' => $rows,
            'errors' => $errors,
        ]);

        $response = $this->getJson('/api/employments?quarter=2023-Q4&states=06&breakdownSex=1');
        $response->dump();

        $response->assertOk()
            ->assertJson([
                'data' => $rows,
                'errors' => $errors,
                'hasErrors' => true,
            ]);
    }

    public function test_returns_partial_response_when_service_reports_errors(): void
    {
        $rows = [
            ['stateCode' => '06', 'stateName' => 'California', 'total' => 99],
        ];
        $errors = [
            ['stateCode' => '12', 'message' => 'boom'],
        ];

        $this->fakeSummary(['06', '12'], '2023-Q4', false, [
            'rows' => $rows,
            'errors' => $errors,
        ]);

        $response = $this->getJson('/api/employments?quarter=2023-Q4&states=12,06');

        $response->assertOk()->assertJson([
            'data' => $rows,
            'errors' => $errors,
            'hasErrors' => true,
        ]);
    }

    public function test_returns_error_status_when_all_requests_fail(): void
    {
        $errors = [
            ['stateCode' => '06', 'message' => 'boom'],
            ['stateCode' => '12', 'message' => 'boom2'],
        ];

        $this->fakeSummary(['06', '12'], '2023-Q4', false, [
            'rows' => [],
            'errors' => $errors,
        ]);

        $response = $this->getJson('/api/employments?quarter=2023-Q4&states=12,06');

        $response
            ->assertStatus(502)
            ->assertJson([
                'data' => [],
                'errors' => $errors,
                'hasErrors' => true,
                'message' => 'Failed to retrieve employment data for requested states',
            ]);
    }
}
