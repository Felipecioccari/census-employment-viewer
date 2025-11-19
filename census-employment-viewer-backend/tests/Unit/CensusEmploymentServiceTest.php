<?php

namespace Tests\Unit;

use App\Services\CensusEmploymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class CensusEmploymentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_rows_and_errors_without_sex_breakdown(): void
    {
        config()->set('states', [
            ['code' => '01', 'name' => 'Alpha'],
            ['code' => '02', 'name' => 'Beta'],
        ]);

        Http::fake(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            if (($query['for'] ?? null) === 'state:02') {
                return Http::response([['Emp'], [2000]], 200);
            }

            return Http::response([], 500);
        });

        $service = new CensusEmploymentService($this->makeLogger(), 'https://example.test');

        $result = $service->getEmploymentSummary(['02', '01'], '2024-Q1', false);

        $this->assertSame([
            [
                'stateCode' => '02',
                'stateName' => 'Beta',
                'total' => 2000,
            ],
        ], $result['rows']);

        $this->assertSame([
            [
                'stateCode' => '01',
                'message' => 'Census API error: 500',
            ],
        ], $result['errors']);
    }

    #[Test]
    public function it_keeps_partial_data_when_only_one_sex_fetch_succeeds(): void
    {
        config()->set('states', [
            ['code' => '01', 'name' => 'Alpha'],
        ]);

        Http::fake(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            if (($query['sex'] ?? null) === '1') {
                return Http::response([['Emp'], [300]], 200);
            }

            return Http::response([], 500);
        });

        $service = new CensusEmploymentService($this->makeLogger(), 'https://example.test');

        $result = $service->getEmploymentSummary(['01'], '2024-Q2', true);

        $this->assertSame([
            [
                'stateCode' => '01',
                'stateName' => 'Alpha',
                'male' => 300,
                'female' => null,
                'total' => 300,
            ],
        ], $result['rows']);

        $this->assertSame([
            [
                'stateCode' => '01',
                'sex' => 'female',
                'message' => 'Census API error: 500',
            ],
        ], $result['errors']);
    }

    private function makeLogger(): LoggerInterface
    {
        $logger = Mockery::mock(LoggerInterface::class);

        $logger->shouldReceive('info')->andReturnNull();
        $logger->shouldReceive('warning')->andReturnNull();
        $logger->shouldReceive('error')->andReturnNull();
        $logger->shouldReceive('debug')->andReturnNull();

        return $logger;
    }
}
