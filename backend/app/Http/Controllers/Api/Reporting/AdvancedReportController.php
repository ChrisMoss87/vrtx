<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Services\Reporting\AdvancedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedReportController extends Controller
{
    public function __construct(
        protected AdvancedReportService $advancedReportService
    ) {}

    /**
     * Execute a cross-object report.
     *
     * POST /api/v1/reports/advanced/execute
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
            'type' => 'nullable|string|in:table,chart,summary,matrix,pivot,cohort',
            'joins' => 'nullable|array',
            'joins.*.source_field' => 'required_with:joins|string',
            'joins.*.target_module_id' => 'required_with:joins|integer',
            'joins.*.alias' => 'required_with:joins|string',
            'joins.*.join_type' => 'nullable|string|in:inner,left,right',
            'filters' => 'nullable|array',
            'grouping' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'calculated_fields' => 'nullable|array',
            'calculated_fields.*.name' => 'required_with:calculated_fields|string',
            'calculated_fields.*.formula' => 'required_with:calculated_fields|string',
            'calculated_fields.*.label' => 'nullable|string',
            'sorting' => 'nullable|array',
            'date_range' => 'nullable|array',
            'config' => 'nullable|array',
        ]);

        // Build full config
        $config = [
            'module_id' => $validated['module_id'],
            'type' => $validated['type'] ?? 'table',
            'joins' => $this->buildJoins($validated['joins'] ?? []),
            'filters' => $validated['filters'] ?? [],
            'grouping' => $validated['grouping'] ?? [],
            'aggregations' => $validated['aggregations'] ?? [],
            'calculated_fields' => $validated['calculated_fields'] ?? [],
            'sorting' => $validated['sorting'] ?? [],
            'date_range' => $validated['date_range'] ?? [],
            'config' => $validated['config'] ?? [],
        ];

        $result = $this->advancedReportService->executeCrossObjectReport($config);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get available joins for a module.
     *
     * GET /api/v1/reports/advanced/joins/{moduleId}
     */
    public function getJoins(int $moduleId): JsonResponse
    {
        $joins = $this->advancedReportService->getAvailableJoins($moduleId);

        return response()->json([
            'data' => $joins,
        ]);
    }

    /**
     * Get all fields available in a cross-object report.
     *
     * POST /api/v1/reports/advanced/fields
     */
    public function getFields(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
            'joins' => 'nullable|array',
        ]);

        $fields = $this->advancedReportService->getCrossObjectFields(
            $validated['module_id'],
            $this->buildJoins($validated['joins'] ?? [])
        );

        return response()->json([
            'data' => $fields,
        ]);
    }

    /**
     * Execute a cohort analysis.
     *
     * POST /api/v1/reports/advanced/cohort
     */
    public function cohort(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
            'joins' => 'nullable|array',
            'filters' => 'nullable|array',
            'date_range' => 'nullable|array',
            'cohort_field' => 'nullable|string',
            'cohort_interval' => 'nullable|string|in:day,week,month,quarter,year',
            'period_field' => 'nullable|string',
            'period_interval' => 'nullable|string|in:day,week,month,quarter,year',
            'metric_field' => 'nullable|string',
            'metric_aggregation' => 'nullable|string|in:count,count_distinct,sum,avg,min,max',
        ]);

        $config = [
            'module_id' => $validated['module_id'],
            'type' => 'cohort',
            'joins' => $this->buildJoins($validated['joins'] ?? []),
            'filters' => $validated['filters'] ?? [],
            'date_range' => $validated['date_range'] ?? [],
            'config' => [
                'cohort_field' => $validated['cohort_field'] ?? 'created_at',
                'cohort_interval' => $validated['cohort_interval'] ?? 'month',
                'period_field' => $validated['period_field'] ?? 'created_at',
                'period_interval' => $validated['period_interval'] ?? 'month',
                'metric_field' => $validated['metric_field'] ?? null,
                'metric_aggregation' => $validated['metric_aggregation'] ?? 'count',
            ],
        ];

        $result = $this->advancedReportService->executeCrossObjectReport($config);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Validate a calculated field formula.
     *
     * POST /api/v1/reports/advanced/validate-formula
     */
    public function validateFormula(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'formula' => 'required|string',
            'name' => 'nullable|string',
        ]);

        $calculatedField = \App\Domain\Reporting\ValueObjects\CalculatedField::fromArray([
            'name' => $validated['name'] ?? 'test',
            'formula' => $validated['formula'],
        ]);

        $errors = $calculatedField->validate();

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
            'dependencies' => $calculatedField->dependencies,
            'sql_preview' => empty($errors) ? $calculatedField->toSqlExpression() : null,
        ]);
    }

    /**
     * Build join configurations with defaults.
     */
    protected function buildJoins(array $joins): array
    {
        return array_map(function ($join) {
            return [
                'source_module_id' => $join['source_module_id'] ?? 0,
                'source_field' => $join['source_field'],
                'target_module_id' => $join['target_module_id'],
                'target_field' => $join['target_field'] ?? 'id',
                'alias' => $join['alias'],
                'join_type' => $join['join_type'] ?? 'left',
            ];
        }, $joins);
    }
}
