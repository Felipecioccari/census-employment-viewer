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
        $errors = [];

        foreach ($stateCodes as $code) {
            if ($breakdownSex) {
                $row = [
                    'stateCode' => $code,
                    'stateName' => $nameByCode[$code] ?? $code,
                    'male' => null,
                    'female' => null,
                ];
                $hasValue = false;

                foreach (['male' => '1', 'female' => '2'] as $label => $sexCode) {
                    try {
                        $value = $this->fetchEmp($code, $quarter, $sexCode);
                        $row[$label] = $value;
                        $hasValue = true;
                    } catch (\Throwable $e) {
                        $error = [
                            'stateCode' => $code,
                            'sex' => $label,
                            'message' => $e->getMessage(),
                        ];
                        $errors[] = $error;

                        $this->logger->warning('employment.summary.fetch_partial_failure', $context + $error);
                    }
                }

                if ($hasValue) {
                    $row['total'] = (int) (($row['male'] ?? 0) + ($row['female'] ?? 0));
                    $rows[] = $row;
                }
            } else {
                try {
                    // sex -> 0 means total
                    $total = $this->fetchEmp($code, $quarter, '0');
                } catch (\Throwable $e) {
                    $error = [
                        'stateCode' => $code,
                        'message' => $e->getMessage(),
                    ];
                    $errors[] = $error;

                    $this->logger->warning('employment.summary.fetch_partial_failure', $context + $error);

                    continue;
                }

                $rows[] = [
                    'stateCode' => $code,
                    'stateName' => $nameByCode[$code] ?? $code,
                    'total' => $total,
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['stateName'], $b['stateName']));

        $hasData = ! empty($rows);
        $logPayload = $context + [
            'error_count' => count($errors),
            'has_errors' => ! empty($errors),
            'has_data' => $hasData,
        ];

        if ($hasData) {
            $this->logger->info('employment.summary.fetch_succeeded', $logPayload);
        } else {
            $this->logger->error('employment.summary.fetch_failed_all', $logPayload);
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
        ];
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
