<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Module;
use App\Models\Report;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered report generation from natural language queries.
 *
 * This service converts natural language requests into report configurations.
 * Example: "Show me deals closed this month by sales rep" -> Report JSON config
 */
class ReportGeneratorService
{
    protected AiService $aiService;

    // Feature flag - set to true when AI integration is ready
    protected bool $aiEnabled = false;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Check if AI report generation is available
     */
    public function isAvailable(): bool
    {
        return $this->aiEnabled && $this->aiService->canUse();
    }

    /**
     * Generate a report configuration from natural language
     */
    public function generateFromPrompt(string $prompt, ?int $userId = null): array
    {
        if (!$this->aiEnabled) {
            return $this->generatePlaceholderReport($prompt);
        }

        if (!$this->aiService->canUse()) {
            throw new \Exception('AI service is not available');
        }

        $modules = $this->getAvailableModules();
        $systemPrompt = $this->buildSystemPrompt($modules);

        $response = $this->aiService->complete(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'report_generation',
            2000,
            0.3,
            $userId
        );

        return $this->parseResponse($response['content']);
    }

    /**
     * Get suggestions for report improvements
     */
    public function suggestImprovements(Report $report, ?int $userId = null): array
    {
        if (!$this->aiEnabled) {
            return $this->getPlaceholderSuggestions($report);
        }

        if (!$this->aiService->canUse()) {
            throw new \Exception('AI service is not available');
        }

        $reportConfig = [
            'name' => $report->name,
            'type' => $report->type,
            'module' => $report->module?->api_name,
            'filters' => $report->filters,
            'grouping' => $report->grouping,
            'aggregations' => $report->aggregations,
        ];

        $response = $this->aiService->complete(
            [
                ['role' => 'system', 'content' => $this->buildSuggestionPrompt()],
                ['role' => 'user', 'content' => json_encode($reportConfig)],
            ],
            'report_suggestions',
            1000,
            0.5,
            $userId
        );

        return $this->parseSuggestions($response['content']);
    }

    /**
     * Parse a natural language filter condition
     */
    public function parseFilterCondition(string $condition, string $moduleApiName): array
    {
        if (!$this->aiEnabled) {
            return $this->getPlaceholderFilter($condition);
        }

        $module = Module::where('api_name', $moduleApiName)->first();
        if (!$module) {
            throw new \Exception("Module not found: {$moduleApiName}");
        }

        $fields = $module->fields()->get(['api_name', 'label', 'type'])->toArray();

        $response = $this->aiService->complete(
            [
                ['role' => 'system', 'content' => $this->buildFilterParsePrompt($fields)],
                ['role' => 'user', 'content' => $condition],
            ],
            'filter_parsing',
            500,
            0.2
        );

        return $this->parseFilterResponse($response['content']);
    }

    /**
     * Build system prompt for report generation
     */
    protected function buildSystemPrompt(array $modules): string
    {
        $modulesList = collect($modules)->map(function ($m) {
            $fields = collect($m['fields'])->map(fn($f) => "{$f['api_name']} ({$f['type']})")->implode(', ');
            return "- {$m['api_name']}: {$m['label']} - Fields: {$fields}";
        })->implode("\n");

        return <<<PROMPT
You are a CRM report configuration generator. Convert natural language report requests into JSON report configurations.

Available modules and their fields:
{$modulesList}

Report types: table, chart, summary, matrix, pivot
Chart types: bar, line, pie, doughnut, area, funnel, scatter, gauge, kpi
Aggregations: count, sum, avg, min, max, count_distinct
Filter operators: equals, not_equals, contains, not_contains, starts_with, ends_with, greater_than, less_than, greater_or_equal, less_or_equal, between, is_empty, is_not_empty, in, not_in

Date range presets: today, yesterday, this_week, last_week, this_month, last_month, this_quarter, last_quarter, this_year, last_year, last_7_days, last_30_days, last_90_days, custom

Respond with ONLY valid JSON in this format:
{
  "name": "Report Name",
  "description": "Brief description",
  "module_api_name": "deals",
  "type": "chart",
  "chart_type": "bar",
  "filters": [
    {"field": "field_api_name", "operator": "equals", "value": "some_value"}
  ],
  "grouping": [
    {"field": "field_api_name", "sort": "asc"}
  ],
  "aggregations": [
    {"field": "amount", "function": "sum", "alias": "total_amount"}
  ],
  "date_range": {
    "preset": "this_month",
    "field": "created_at"
  }
}
PROMPT;
    }

    /**
     * Build prompt for report suggestions
     */
    protected function buildSuggestionPrompt(): string
    {
        return <<<PROMPT
You are a CRM analytics expert. Given a report configuration, suggest improvements to make it more insightful.

Respond with JSON array of suggestions:
[
  {
    "type": "filter|grouping|aggregation|visualization|date_range",
    "title": "Short title",
    "description": "Why this would improve the report",
    "config_change": { ... partial config to merge }
  }
]

Focus on:
- Adding useful comparisons (period over period)
- Better groupings for insights
- Additional aggregations
- More appropriate chart types
- Useful filters to focus the data
PROMPT;
    }

    /**
     * Build prompt for filter parsing
     */
    protected function buildFilterParsePrompt(array $fields): string
    {
        $fieldsList = collect($fields)->map(fn($f) => "- {$f['api_name']}: {$f['label']} ({$f['type']})")->implode("\n");

        return <<<PROMPT
Parse the natural language filter condition into a structured filter object.

Available fields:
{$fieldsList}

Respond with ONLY valid JSON:
{
  "field": "field_api_name",
  "operator": "equals|contains|greater_than|etc",
  "value": "the value"
}

For date values, use ISO format or relative terms like "today", "yesterday", "last_week".
PROMPT;
    }

    /**
     * Parse the AI response into report config
     */
    protected function parseResponse(string $content): array
    {
        // Extract JSON from response (may have markdown code blocks)
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $config = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI report response', ['content' => $content]);
            throw new \Exception('Failed to parse AI response as valid JSON');
        }

        return $this->validateAndNormalizeConfig($config);
    }

    /**
     * Parse suggestions response
     */
    protected function parseSuggestions(string $content): array
    {
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $suggestions = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($suggestions) ? $suggestions : [];
    }

    /**
     * Parse filter response
     */
    protected function parseFilterResponse(string $content): array
    {
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $filter = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($filter['field'])) {
            throw new \Exception('Failed to parse filter condition');
        }

        return $filter;
    }

    /**
     * Validate and normalize report config
     */
    protected function validateAndNormalizeConfig(array $config): array
    {
        $required = ['name', 'module_api_name', 'type'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Validate module exists
        $module = Module::where('api_name', $config['module_api_name'])->first();
        if (!$module) {
            throw new \Exception("Invalid module: {$config['module_api_name']}");
        }

        // Normalize arrays
        $config['filters'] = $config['filters'] ?? [];
        $config['grouping'] = $config['grouping'] ?? [];
        $config['aggregations'] = $config['aggregations'] ?? [];
        $config['date_range'] = $config['date_range'] ?? [];

        return $config;
    }

    /**
     * Get available modules with their fields
     */
    protected function getAvailableModules(): array
    {
        return Module::with(['fields' => function ($q) {
            $q->where('is_active', true)
              ->select('id', 'module_id', 'api_name', 'label', 'type');
        }])
        ->where('is_active', true)
        ->get()
        ->map(function ($module) {
            return [
                'api_name' => $module->api_name,
                'label' => $module->label,
                'fields' => $module->fields->toArray(),
            ];
        })
        ->toArray();
    }

    /**
     * Generate a placeholder report when AI is disabled
     * This allows the UI to work without AI
     */
    protected function generatePlaceholderReport(string $prompt): array
    {
        // Basic keyword detection for demo purposes
        $prompt = strtolower($prompt);

        $moduleApiName = 'deals';
        if (str_contains($prompt, 'lead') || str_contains($prompt, 'prospect')) {
            $moduleApiName = 'leads';
        } elseif (str_contains($prompt, 'contact') || str_contains($prompt, 'person')) {
            $moduleApiName = 'contacts';
        } elseif (str_contains($prompt, 'account') || str_contains($prompt, 'company')) {
            $moduleApiName = 'accounts';
        } elseif (str_contains($prompt, 'task') || str_contains($prompt, 'todo')) {
            $moduleApiName = 'tasks';
        }

        $type = 'table';
        $chartType = null;
        if (str_contains($prompt, 'chart') || str_contains($prompt, 'graph') || str_contains($prompt, 'visuali')) {
            $type = 'chart';
            $chartType = 'bar';
            if (str_contains($prompt, 'pie') || str_contains($prompt, 'distribution')) {
                $chartType = 'pie';
            } elseif (str_contains($prompt, 'line') || str_contains($prompt, 'trend') || str_contains($prompt, 'over time')) {
                $chartType = 'line';
            }
        }

        $dateRange = [];
        if (str_contains($prompt, 'this month')) {
            $dateRange = ['preset' => 'this_month', 'field' => 'created_at'];
        } elseif (str_contains($prompt, 'last month')) {
            $dateRange = ['preset' => 'last_month', 'field' => 'created_at'];
        } elseif (str_contains($prompt, 'this quarter')) {
            $dateRange = ['preset' => 'this_quarter', 'field' => 'created_at'];
        } elseif (str_contains($prompt, 'this year')) {
            $dateRange = ['preset' => 'this_year', 'field' => 'created_at'];
        }

        return [
            'name' => 'Generated Report',
            'description' => 'AI-generated report based on: ' . substr($prompt, 0, 100),
            'module_api_name' => $moduleApiName,
            'type' => $type,
            'chart_type' => $chartType,
            'filters' => [],
            'grouping' => [],
            'aggregations' => [
                ['field' => '*', 'function' => 'count', 'alias' => 'total_count']
            ],
            'date_range' => $dateRange,
            'ai_generated' => true,
            'ai_enabled' => false,
            'message' => 'AI report generation is not yet enabled. This is a basic template based on your request.',
        ];
    }

    /**
     * Get placeholder suggestions when AI is disabled
     */
    protected function getPlaceholderSuggestions(Report $report): array
    {
        $suggestions = [];

        // Always suggest adding date range if not present
        if (empty($report->date_range)) {
            $suggestions[] = [
                'type' => 'date_range',
                'title' => 'Add Date Range',
                'description' => 'Filter data to a specific time period for more focused insights',
                'config_change' => [
                    'date_range' => ['preset' => 'this_month', 'field' => 'created_at']
                ]
            ];
        }

        // Suggest chart if it's a table
        if ($report->type === 'table') {
            $suggestions[] = [
                'type' => 'visualization',
                'title' => 'Add Visualization',
                'description' => 'Convert to a chart for better visual understanding of data trends',
                'config_change' => [
                    'type' => 'chart',
                    'chart_type' => 'bar'
                ]
            ];
        }

        // Suggest grouping if no grouping
        if (empty($report->grouping)) {
            $suggestions[] = [
                'type' => 'grouping',
                'title' => 'Add Grouping',
                'description' => 'Group data by a field to see patterns and distributions',
                'config_change' => [
                    'grouping' => [['field' => 'owner_id', 'sort' => 'desc']]
                ]
            ];
        }

        return $suggestions;
    }

    /**
     * Get placeholder filter when AI is disabled
     */
    protected function getPlaceholderFilter(string $condition): array
    {
        return [
            'field' => 'name',
            'operator' => 'contains',
            'value' => $condition,
            'ai_enabled' => false,
            'message' => 'AI filter parsing is not yet enabled. Please configure the filter manually.',
        ];
    }

    /**
     * Create a report from AI-generated config
     */
    public function createReportFromConfig(array $config, int $userId): Report
    {
        $module = Module::where('api_name', $config['module_api_name'])->firstOrFail();

        return Report::create([
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'module_id' => $module->id,
            'user_id' => $userId,
            'type' => $config['type'],
            'chart_type' => $config['chart_type'] ?? null,
            'filters' => $config['filters'] ?? [],
            'grouping' => $config['grouping'] ?? [],
            'aggregations' => $config['aggregations'] ?? [],
            'date_range' => $config['date_range'] ?? [],
            'is_public' => false,
        ]);
    }
}
