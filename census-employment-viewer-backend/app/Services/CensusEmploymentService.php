<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Throwable;

class CensusEmploymentService
{
    private const SEX_CODES = [
        'male' => '1',
        'female' => '2',
    ];

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

        $requests = $this->makeRequestPayloads($stateCodes, $quarter, $breakdownSex);
        $responses = $this->fetchEmploymentBatch($requests);

        [$rows, $errors] = $this->buildRows(
            $stateCodes,
            $nameByCode,
            $responses,
            $breakdownSex,
            $context
        );

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

    /**
     * @param  array<int, string>  $stateCodes
     * @return array<int, array{key: string, stateCode: string, quarter: string, sex: string}>
     */
    private function makeRequestPayloads(array $stateCodes, string $quarter, bool $breakdownSex): array
    {
        $requests = [];

        foreach ($stateCodes as $stateCode) {
            if ($breakdownSex) {
                foreach (self::SEX_CODES as $sexCode) {
                    $requests[] = [
                        'key' => $this->makeRequestKey($stateCode, $sexCode),
                        'stateCode' => $stateCode,
                        'quarter' => $quarter,
                        'sex' => $sexCode,
                    ];
                }
            } else {
                $requests[] = [
                    'key' => $this->makeRequestKey($stateCode, '0'),
                    'stateCode' => $stateCode,
                    'quarter' => $quarter,
                    'sex' => '0',
                ];
            }
        }

        return $requests;
    }

    /**
     * @param  array<int, array{key: string, stateCode: string, quarter: string, sex: string}>  $requests
     * @return array<string, array{value?: int, error?: string}>
     */
    private function fetchEmploymentBatch(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($requests) {
            $poolRequests = [];

            foreach ($requests as $request) {
                $context = [
                    'state_code' => $request['stateCode'],
                    'quarter' => $request['quarter'],
                    'sex' => $request['sex'],
                ];

                $this->logger->debug('employment.fetch.request', $context);

                $poolRequests[] = $pool->as($request['key'])->get($this->baseUrl, [
                    'get' => 'Emp',
                    'for' => 'state:'.$request['stateCode'],
                    'time' => $request['quarter'],
                    'sex' => $request['sex'],
                ]);
            }

            return $poolRequests;
        });

        $results = [];

        foreach ($requests as $request) {
            $context = [
                'state_code' => $request['stateCode'],
                'quarter' => $request['quarter'],
                'sex' => $request['sex'],
            ];

            $response = $responses[$request['key']] ?? null;

            if ($response instanceof Response) {
                if ($response->failed()) {
                    $this->logger->error('employment.fetch.failed_status', $context + [
                        'status' => $response->status(),
                    ]);

                    $results[$request['key']] = [
                        'error' => 'Census API error: '.$response->status(),
                    ];

                    continue;
                }

                $data = $response->json();

                if (! isset($data[1])) {
                    $this->logger->warning('employment.fetch.empty_payload', $context);

                    $results[$request['key']] = [
                        'value' => 0,
                    ];

                    continue;
                }

                $value = (int) ($data[1][0] ?? 0);

                $this->logger->debug('employment.fetch.response', $context + [
                    'value' => $value,
                ]);

                $results[$request['key']] = [
                    'value' => $value,
                ];

                continue;
            }

            if ($response instanceof Throwable) {
                $this->logger->error('employment.fetch.failed_connection', $context + [
                    'message' => $response->getMessage(),
                ]);

                $results[$request['key']] = [
                    'error' => $response->getMessage(),
                ];

                continue;
            }

            $results[$request['key']] = [
                'error' => 'Census API error: unknown',
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $stateCodes
     * @param  \Illuminate\Support\Collection<string, string>  $nameByCode
     * @param  array<string, array{value?: int, error?: string}>  $responses
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function buildRows(
        array $stateCodes,
        Collection $nameByCode,
        array $responses,
        bool $breakdownSex,
        array $context
    ): array {
        $rows = [];
        $errors = [];

        foreach ($stateCodes as $code) {
            $stateName = $nameByCode->get($code, $code);

            if ($breakdownSex) {
                $row = [
                    'stateCode' => $code,
                    'stateName' => $stateName,
                    'male' => null,
                    'female' => null,
                ];
                $hasValue = false;

                foreach (self::SEX_CODES as $label => $sexCode) {
                    $key = $this->makeRequestKey($code, $sexCode);
                    $result = $responses[$key] ?? ['error' => 'Census API error: unknown'];

                    if (isset($result['error'])) {
                        $error = [
                            'stateCode' => $code,
                            'sex' => $label,
                            'message' => $result['error'],
                        ];
                        $errors[] = $error;

                        $this->logger->warning('employment.summary.fetch_partial_failure', $context + $error);

                        continue;
                    }

                    $row[$label] = $result['value'] ?? 0;
                    $hasValue = true;
                }

                if ($hasValue) {
                    $row['total'] = (int) (($row['male'] ?? 0) + ($row['female'] ?? 0));
                    $rows[] = $row;
                }

                continue;
            }

            $key = $this->makeRequestKey($code, '0');
            $result = $responses[$key] ?? ['error' => 'Census API error: unknown'];

            if (isset($result['error'])) {
                $error = [
                    'stateCode' => $code,
                    'message' => $result['error'],
                ];
                $errors[] = $error;

                $this->logger->warning('employment.summary.fetch_partial_failure', $context + $error);

                continue;
            }

            $rows[] = [
                'stateCode' => $code,
                'stateName' => $stateName,
                'total' => $result['value'] ?? 0,
            ];
        }

        return [$rows, $errors];
    }

    private function makeRequestKey(string $stateCode, string $sex): string
    {
        return $stateCode.'-'.$sex;
    }
}
