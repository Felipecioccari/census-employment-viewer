<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Validations\NotFutureQuarter;
use App\Services\CensusEmploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmploymentController extends Controller
{
    public function __construct(
        private CensusEmploymentService $censusEmploymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();

        \Log::withContext(['request_id' => $requestId]);

        \Log::info('employment.index.started', [
            'request_id' => $requestId,
            'quarter' => $request->input('quarter'),
            'states_param' => $request->input('states', 'ALL'),
            'breakdown_sex' => $request->input('breakdownSex'),
        ]);

        $validator = \Validator::make($request->all(), [
            'quarter' => ['required', 'string', new NotFutureQuarter()],
            'states' => 'nullable|string',
            'breakdownSex' => 'nullable',
        ]);

        if ($validator->fails()) {
            \Log::warning('employment.index.validation_failed', [
                'request_id' => $requestId,
                'errors' => $validator->errors()->all(),
            ]);

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $quarter = $validated['quarter'];
        $breakdownSex = filter_var($validated['breakdownSex'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $allStates = config('states');
        $allCodes = collect($allStates)->pluck('code')->all();

        $statesParam = $validated['states'] ?? 'ALL';

        if ($statesParam !== 'ALL' && $statesParam !== '') {
            $requestedCodes = explode(',', $statesParam);
            $stateCodes = array_values(array_intersect($allCodes, $requestedCodes));
        } else {
            $stateCodes = $allCodes;
        }

        try {
            $summary = $this->censusEmploymentService->getEmploymentSummary($stateCodes, $quarter, $breakdownSex);
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $errorCount = count($summary['errors']);
            $logContext = [
                'request_id' => $requestId,
                'state_count' => count($stateCodes),
                'duration_ms' => $durationMs,
                'error_count' => $errorCount,
            ];

            if (empty($summary['rows']) && $errorCount > 0) {
                \Log::error('employment.index.failed_all_states', $logContext);

                return response()->json([
                    'data' => [],
                    'errors' => $summary['errors'],
                    'hasErrors' => true,
                    'message' => 'Failed to retrieve employment data for requested states',
                ], 502);
            }

            if ($errorCount > 0) {
                \Log::warning('employment.index.completed_with_errors', $logContext);
            } else {
                \Log::info('employment.index.succeeded', $logContext);
            }

            return response()->json([
                'data' => $summary['rows'],
                'errors' => $summary['errors'],
                'hasErrors' => $errorCount > 0,
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            \Log::error('employment.index.failed', [
                'request_id' => $requestId,
                'state_count' => count($stateCodes),
                'duration_ms' => $durationMs,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['data' => [
                'message' => 'Failed to load employment data',
            ]], 500);
        }
    }
}
