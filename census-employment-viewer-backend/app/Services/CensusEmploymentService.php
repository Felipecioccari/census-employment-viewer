<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;

class CensusEmploymentService
{
    public function __construct(private LoggerInterface $logger, private ?string $baseUrl = null)
    {
        $this->baseUrl = $baseUrl ?? config('services.census_qwi.base_url');
    }

    public function getEmploymentSummary(array $stateCodes, string $quarter, bool $breakdownSex): array
    {
        $context = [
            'quarter' => $quarter,
            'state_count' => count($stateCodes),
            'breakdown_sex' => $breakdownSex,
        ];

        $this->logger->info('employment.summary.fetch_started', $context);

        $statesConfig = config('states');
        $nameByCode = collect($statesConfig)->pluck('name', 'code');

        $rows = [];

        foreach ($stateCodes as $code) {
            if ($breakdownSex) {
                $male = $this->fetchEmp($code, $quarter, '1');
                $female = $this->fetchEmp($code, $quarter, '2');

                $rows[] = [
                    'stateCode' => $code,
                    'stateName' => $nameByCode[$code] ?? $code,
                    'male' => $male,
                    'female' => $female,
                    'total' => $male + $female,
                ];
            } else {
                $total = $this->fetchEmp($code, $quarter, '0');

                $rows[] = [
                    'stateCode' => $code,
                    'stateName' => $nameByCode[$code] ?? $code,
                    'total' => $total,
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['stateName'], $b['stateName']));

        $this->logger->info('employment.summary.fetch_succeeded', $context);

        return $rows;
    }

    private function fetchEmp(string $stateCode, string $quarter, string $sex): int
    {
        $context = [
            'state_code' => $stateCode,
            'quarter' => $quarter,
            'sex' => $sex,
        ];

        $this->logger->debug('employment.fetch.request', $context);

        $response = Http::get($this->baseUrl, [
            'get' => 'Emp',
            'for' => 'state:'.$stateCode,
            'time' => $quarter,
            'sex' => $sex,
        ]);

        if ($response->failed()) {
            $this->logger->error('employment.fetch.failed_status', $context + [
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('Census API error: '.$response->status());
        }

        $data = $response->json();

        if (! isset($data[1])) {
            $this->logger->warning('employment.fetch.empty_payload', $context);

            return 0;
        }

        $value = (int) ($data[1][0] ?? 0);

        $this->logger->debug('employment.fetch.response', $context + [
            'value' => $value,
        ]);

        return $value;
    }
}
