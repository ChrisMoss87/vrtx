<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\ReportGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AiReportController extends Controller
{
    public function __construct(
        protected ReportGeneratorService $reportGenerator
    ) {}

    /**
     * Check if AI report generation is available.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'data' => [
                'available' => $this->reportGenerator->isAvailable(),
                'message' => $this->reportGenerator->isAvailable()
                    ? 'AI report generation is available'
                    : 'AI report generation is not yet enabled. Reports can still be created manually.',
            ],
        ]);
    }

    /**
     * Generate a report configuration from natural language.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:10|max:1000',
        ]);

        try {
            $config = $this->reportGenerator->generateFromPrompt(
                $request->input('prompt'),
                Auth::id()
            );

            return response()->json([
                'data' => $config,
                'message' => $config['ai_enabled'] ?? true
                    ? 'Report configuration generated successfully'
                    : 'Basic template generated. AI enhancement coming soon.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate report: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get suggestions for improving a report.
     */
    public function suggest(Request $request, int $reportId): JsonResponse
    {
        $report = DB::table('reports')->where('id', $reportId)->first();

        $suggestions = $this->reportGenerator->suggestImprovements($report, Auth::id());

        return response()->json([
            'data' => $suggestions,
        ]);
    }

    /**
     * Parse a natural language filter condition.
     */
    public function parseFilter(Request $request): JsonResponse
    {
        $request->validate([
            'condition' => 'required|string|max:500',
            'module_api_name' => 'required|string',
        ]);

        try {
            $filter = $this->reportGenerator->parseFilterCondition(
                $request->input('condition'),
                $request->input('module_api_name')
            );

            return response()->json([
                'data' => $filter,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to parse filter: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a report from AI-generated configuration.
     */
    public function createReport(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|min:10|max:1000',
            'save' => 'boolean',
        ]);

        try {
            $config = $this->reportGenerator->generateFromPrompt(
                $request->input('prompt'),
                Auth::id()
            );

            if ($request->boolean('save', false)) {
                $report = $this->reportGenerator->createReportFromConfig($config, Auth::id());

                return response()->json([
                    'data' => $report,
                    'message' => 'Report created successfully',
                ], 201);
            }

            return response()->json([
                'data' => $config,
                'message' => 'Report configuration generated. Set save=true to create the report.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create report: ' . $e->getMessage(),
            ], 422);
        }
    }
}
