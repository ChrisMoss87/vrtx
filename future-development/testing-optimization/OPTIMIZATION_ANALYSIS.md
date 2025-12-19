# VRTX CRM Optimization Analysis

## Overview
Comprehensive analysis of performance bottlenecks, memory-intensive patterns, scalability concerns, and optimization recommendations.

---

## Critical Issues (High Priority)

### 1. N+1 Query Problems

#### Issue 1.1: Pipeline Kanban Data
**Location:** `backend/app/Models/Stage.php:60-90`

```php
// CURRENT (N+1 Problem)
public function getRecords(): Collection
{
    $pipeline = $this->pipeline;  // Query 1: Loads pipeline
    return ModuleRecord::where('module_id', $pipeline->module_id)  // Query N
        ->whereJsonContains('data->'. $pipeline->stage_field, $this->name)
        ->get();
}
```

**Impact:** For a pipeline with 6 stages, this executes 7+ queries minimum.

**Solution:**
```php
// OPTIMIZED - Use eager loading in controller
public function kanbanData(Pipeline $pipeline)
{
    $pipeline->load('stages');
    $moduleId = $pipeline->module_id;
    $stageField = $pipeline->stage_field;

    // Single query for all records
    $records = ModuleRecord::where('module_id', $moduleId)->get();

    // Group in PHP (faster than multiple DB queries)
    $byStage = $records->groupBy(fn($r) => $r->data[$stageField] ?? 'unknown');

    return $pipeline->stages->map(fn($stage) => [
        'stage' => $stage,
        'records' => $byStage->get($stage->name, collect()),
        'count' => $byStage->get($stage->name, collect())->count(),
    ]);
}
```

---

#### Issue 1.2: Record Transformation in Loop
**Location:** `backend/app/Http/Controllers/Api/RecordController.php:45-60`

```php
// CURRENT
$result['data'] = array_map(
    fn (ModuleRecord $record) => $this->transformRecord($record, $hiddenFields),
    $result['data']
);
```

**Impact:** If `transformRecord` accesses relationships, N+1 occurs.

**Solution:**
```php
// OPTIMIZED - Eager load in repository
$records = $this->recordService->getRecords($module, $filters)
    ->with(['createdBy:id,name,email', 'updatedBy:id,name,email']);
```

---

#### Issue 1.3: Blueprint Transition Checks
**Location:** `backend/app/Models/BlueprintTransition.php`

```php
// CURRENT
public function requiresApproval(): bool
{
    return $this->approval()->exists();  // Query on every call
}

public function hasRequirements(): bool
{
    return $this->requirements()->exists();  // Query on every call
}
```

**Solution:**
```php
// OPTIMIZED - Load relations upfront
$transitions = $blueprint->transitions()
    ->with(['approval', 'requirements', 'conditions', 'actions'])
    ->get();

// Then check without queries
$transition->relationLoaded('approval') && $transition->approval !== null;
```

---

### 2. Memory-Intensive Operations

#### Issue 2.1: Report Execution Without Pagination
**Location:** `backend/app/Services/Reporting/ReportService.php`

```php
// CURRENT - Loads all matching records into memory
public function execute(Report $report): array
{
    $records = ModuleRecord::where('module_id', $report->module_id)
        ->get();  // Potentially 100k+ records

    // Process all in memory
    return $this->processResults($records, $report);
}
```

**Impact:** Memory exhaustion with large datasets (>10k records).

**Solution:**
```php
// OPTIMIZED - Use chunking and database aggregations
public function execute(Report $report, int $limit = 1000): array
{
    // For aggregation reports, use DB aggregations
    if ($report->type === 'summary') {
        return $this->executeAggregation($report);
    }

    // For detail reports, paginate
    return ModuleRecord::where('module_id', $report->module_id)
        ->limit($limit)
        ->cursor()  // Memory efficient iteration
        ->map(fn($r) => $this->formatRow($r, $report))
        ->toArray();
}

private function executeAggregation(Report $report): array
{
    // Push aggregation to database
    return DB::table('module_records')
        ->where('module_id', $report->module_id)
        ->selectRaw("
            data->>'status' as status,
            COUNT(*) as count,
            SUM((data->>'amount')::numeric) as total
        ")
        ->groupByRaw("data->>'status'")
        ->get()
        ->toArray();
}
```

---

#### Issue 2.2: Import Processing
**Location:** `backend/app/Services/Import/ImportEngine.php`

```php
// CURRENT - Loads entire file into memory
public function process(Import $import): void
{
    $rows = $this->parser->parse($import->file_path);  // All rows in memory

    foreach ($rows as $row) {
        $this->processRow($row);
    }
}
```

**Solution:**
```php
// OPTIMIZED - Stream processing
public function process(Import $import): void
{
    $this->parser->parseChunked($import->file_path, function($chunk) use ($import) {
        // Process 100 rows at a time
        $this->processChunk($chunk, $import);
    }, chunkSize: 100);
}
```

---

#### Issue 2.3: `with()` Overuse
**Location:** Various controllers

```php
// ANTI-PATTERN - Loading unused relationships
$module = Module::with(['blocks', 'fields', 'records', 'views', 'pipelines'])->find($id);
```

**Impact:** Loads potentially thousands of related records unnecessarily.

**Solution:**
```php
// OPTIMIZED - Load only what's needed
$module = Module::find($id);  // For basic info

// Lazy load only when needed
if ($needsFields) {
    $module->load('fields:id,name,api_name,module_id');
}
```

---

### 3. Missing Database Indexes

#### Identified Missing Indexes

| Table | Column(s) | Query Pattern | Priority |
|-------|-----------|---------------|----------|
| `module_records` | `(module_id, created_by)` | Filter by creator | High |
| `module_records` | `(module_id, updated_at)` | Sort by recent | High |
| `activities` | `(subject_type, subject_id)` | Polymorphic lookup | High |
| `audit_logs` | `(auditable_type, auditable_id)` | Polymorphic lookup | High |
| `email_messages` | `thread_id` | Thread queries | Medium |
| `email_messages` | `(account_id, status)` | Inbox queries | Medium |
| `workflow_executions` | `(workflow_id, status)` | Execution queries | Medium |
| `blueprint_record_states` | `(record_id, record_type)` | State lookups | High |

**Migration to Add:**
```php
// 2025_XX_XX_add_missing_indexes.php
Schema::table('module_records', function (Blueprint $table) {
    $table->index(['module_id', 'created_by']);
    $table->index(['module_id', 'updated_at']);
});

Schema::table('activities', function (Blueprint $table) {
    $table->index(['subject_type', 'subject_id']);
});

Schema::table('audit_logs', function (Blueprint $table) {
    $table->index(['auditable_type', 'auditable_id']);
});

Schema::table('email_messages', function (Blueprint $table) {
    $table->index('thread_id');
    $table->index(['account_id', 'status']);
});

Schema::table('workflow_executions', function (Blueprint $table) {
    $table->index(['workflow_id', 'status']);
});

Schema::table('blueprint_record_states', function (Blueprint $table) {
    $table->index(['record_id', 'record_type']);
});
```

---

### 4. Inefficient JSON Queries

#### Issue 4.1: Multiple whereJsonContains Calls
**Location:** Various locations querying `module_records.data`

```php
// CURRENT
ModuleRecord::where('module_id', $moduleId)
    ->whereJsonContains('data->status', 'open')
    ->whereJsonContains('data->priority', 'high')
    ->get();
```

**Analysis:** PostgreSQL's GIN index on JSONB should handle this efficiently, but verify with `EXPLAIN ANALYZE`.

**Optimization Options:**
```php
// Option 1: Combined JSON path query (more efficient)
ModuleRecord::where('module_id', $moduleId)
    ->whereRaw("data @> ?", [json_encode(['status' => 'open', 'priority' => 'high'])])
    ->get();

// Option 2: For frequently filtered fields, extract to indexed columns
// Add migration:
Schema::table('module_records', function (Blueprint $table) {
    $table->string('status_extracted')->nullable()->index();
    $table->string('owner_extracted')->nullable()->index();
});

// Add observer to sync extracted fields
ModuleRecord::saving(function ($record) {
    $record->status_extracted = $record->data['status'] ?? null;
});
```

---

### 5. Scalability Concerns

#### Issue 5.1: Single Database Queries for All Tenants
**Location:** Global search, cross-module queries

**Current:** Each tenant has separate database (good).
**Concern:** Dashboard widgets may make multiple sequential queries.

**Solution:**
```php
// OPTIMIZED - Parallel database queries for widgets
public function allWidgetData(Dashboard $dashboard)
{
    $promises = $dashboard->widgets->map(function ($widget) {
        return async(fn() => $this->getWidgetData($widget));
    });

    return await($promises);
}
```

---

#### Issue 5.2: Workflow Execution in Request Cycle
**Location:** `backend/app/Application/Services/RecordService.php`

```php
// CURRENT - Workflows run in request
public function createRecord(...): ModuleRecord
{
    DB::transaction(function () {
        $record = $this->repository->create($data);
        $this->workflowEngine->trigger('record_created', $record);  // May be slow
    });
}
```

**Solution:**
```php
// OPTIMIZED - Queue workflow execution
public function createRecord(...): ModuleRecord
{
    $record = DB::transaction(fn() => $this->repository->create($data));

    // Dispatch to queue (non-blocking)
    TriggerWorkflowsJob::dispatch($record, 'record_created');

    return $record;
}
```

---

## Medium Priority Issues

### 6. Frontend Bundle Size

#### Analysis Needed:
- Check for unused dependencies
- Verify tree-shaking is working
- Consider code splitting for routes

**Commands to run:**
```bash
cd frontend
pnpm build --analyze  # Generate bundle analysis
```

### 7. API Response Size

#### Issue: Large Payload Responses
**Location:** Record list endpoints

```php
// CURRENT - Returns all fields
return response()->json([
    'data' => $records->map(fn($r) => $r->toArray()),
]);
```

**Solution:**
```php
// OPTIMIZED - Sparse fieldsets (JSON:API style)
// Request: GET /records/contacts?fields[contacts]=name,email,phone

return response()->json([
    'data' => $records->map(fn($r) => [
        'id' => $r->id,
        ...Arr::only($r->data, $requestedFields),
    ]),
]);
```

### 8. Caching Strategy

#### Current State:
- Report caching exists but limited
- No query result caching
- No view/config caching

#### Recommendations:
```php
// Add to RecordController
public function index(Module $module)
{
    $cacheKey = "records.{$module->id}." . md5(request()->fullUrl());

    return Cache::remember($cacheKey, 60, function () use ($module) {
        return $this->recordService->getRecords($module, request()->all());
    });
}

// Invalidate on record changes
ModuleRecord::saved(fn($r) => Cache::tags(["module.{$r->module_id}"])->flush());
```

---

## Low Priority Issues

### 9. Code Patterns to Refactor

#### Issue 9.1: Repeated Permission Checks
```php
// CURRENT - Multiple permission checks
if (!$user->can('view', $module)) abort(403);
if (!$user->can('view', $record)) abort(403);
```

**Solution:** Use Policy with caching
```php
// Create RecordPolicy with cached checks
public function view(User $user, ModuleRecord $record): bool
{
    return Cache::remember(
        "permissions.{$user->id}.{$record->module_id}.view",
        300,
        fn() => $user->hasModulePermission($record->module_id, 'view')
    );
}
```

#### Issue 9.2: Hardcoded Limits
```php
// CURRENT
$records = $query->paginate(15);
$maxResults = 1000;
```

**Solution:** Config-driven limits
```php
// config/crm.php
return [
    'pagination' => [
        'default' => 25,
        'max' => 100,
    ],
    'reports' => [
        'max_rows' => 10000,
    ],
];
```

---

## Performance Benchmarks to Establish

| Endpoint | Current | Target | Method |
|----------|---------|--------|--------|
| `GET /records/{module}` (100) | ? | < 150ms | API timing |
| `GET /records/{module}` (1000) | ? | < 400ms | API timing |
| `GET /pipelines/{id}/kanban` | ? | < 250ms | API timing |
| `POST /records/{module}` | ? | < 200ms | API timing |
| `PUT /records/{module}/{id}` | ? | < 200ms | API timing |
| `POST /reports/{id}/execute` | ? | < 1.5s | API timing |
| Frontend initial load | ? | < 2s | Lighthouse |
| DataTable render (100 rows) | ? | < 100ms | Browser perf |

---

## Optimization Implementation Order

### Phase 1: Critical Fixes (1-2 days)
1. Fix N+1 in kanban data endpoint
2. Add missing database indexes
3. Fix eager loading in record transformation

### Phase 2: Memory Optimization (1-2 days)
1. Implement chunked report execution
2. Implement chunked import processing
3. Review and reduce `with()` overuse

### Phase 3: Query Optimization (1 day)
1. Optimize JSON queries
2. Add query caching for hot paths
3. Implement sparse fieldsets

### Phase 4: Async Processing (1 day)
1. Move workflow execution to queue
2. Add parallel widget data fetching

### Phase 5: Frontend Optimization (1 day)
1. Analyze bundle size
2. Implement code splitting
3. Add API response caching

---

## Monitoring Recommendations

### Laravel Telescope (Development)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

### Query Logging
```php
// Add to AppServiceProvider
if (config('app.debug')) {
    DB::listen(function ($query) {
        if ($query->time > 100) { // Log slow queries > 100ms
            Log::warning('Slow query', [
                'sql' => $query->sql,
                'time' => $query->time,
            ]);
        }
    });
}
```

### APM Integration
- Consider Laravel Pulse or external APM (New Relic, Datadog)
- Track endpoint response times
- Monitor database query patterns
