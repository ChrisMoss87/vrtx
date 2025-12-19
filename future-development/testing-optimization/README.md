# Testing, Optimization & Code Quality Initiative

## Overview
Comprehensive plan to fully test all features, optimize performance, and clean up redundant code in VRTX CRM.

---

## Documents

| Document | Purpose |
|----------|---------|
| [TESTING_PLAN.md](./TESTING_PLAN.md) | Full testing strategy, factories, seeders, API tests, E2E tests |
| [OPTIMIZATION_ANALYSIS.md](./OPTIMIZATION_ANALYSIS.md) | Performance issues, N+1 queries, memory usage, scalability |
| [REDUNDANT_FILES.md](./REDUNDANT_FILES.md) | Potentially unused files for removal |

---

## Current State Summary

### Backend
- **56 Models** with relationships and scopes
- **27 Controllers** handling 150+ API endpoints
- **33 Services** for business logic
- **23 Test Files** (moderate coverage)
- **9 Factories** (core models only)
- **7 Seeders** (basic setup)

### Frontend
- **45+ Routes** (app, auth, admin, demos)
- **200+ Components** (datatable, forms, workflow-builder, etc.)
- **20+ API Clients** for backend communication
- **10 E2E Tests** (auth, records, pipelines)

---

## Critical Findings

### Performance Issues (Fix First)
1. **N+1 Query in Kanban** - `Stage.php` methods query pipeline repeatedly
2. **N+1 in Record Transform** - Missing eager loading in RecordController
3. **Memory in Reports** - No pagination for large report execution
4. **Memory in Imports** - Full file loaded into memory

### Missing Test Coverage
- Workflows (0% API coverage)
- Blueprints (0% API coverage)
- Email system (0% coverage)
- Dashboards/Reports (0% coverage)
- RBAC endpoints (0% coverage)

### Code Quality Issues
- 3 duplicate field type constant files
- 2 duplicate formula calculator files
- 2 duplicate DND utility files
- 9 demo routes in production
- Orphaned model (ScheduledDataJob)

---

## Implementation Phases

### Phase 1: Foundation (Week 1)
- [ ] Create missing factories (18 new)
- [ ] Create TestDataSeeder with 1000+ records
- [ ] Add missing database indexes
- [ ] Remove high-priority redundant files

### Phase 2: Backend Tests (Weeks 2-3)
- [ ] Auth API tests
- [ ] Workflow API tests
- [ ] Blueprint API tests
- [ ] Activity/Audit tests
- [ ] Report/Dashboard tests
- [ ] Email tests
- [ ] RBAC tests
- [ ] Integration tests

### Phase 3: Performance Fixes (Week 4)
- [ ] Fix N+1 in kanban data
- [ ] Fix eager loading in RecordController
- [ ] Implement chunked report execution
- [ ] Implement chunked import processing
- [ ] Add query caching

### Phase 4: Frontend Tests (Weeks 5-6)
- [ ] DataTable: sorting, filtering, pagination
- [ ] DataTable: column management, inline editing
- [ ] Forms: validation, conditional visibility
- [ ] Forms: formulas, lookups
- [ ] Kanban: drag-drop, stage management
- [ ] Workflow builder tests
- [ ] Dashboard/Report tests

### Phase 5: Code Cleanup (Week 7)
- [ ] Consolidate duplicate files
- [ ] Remove demo routes (or gate behind env)
- [ ] Remove unused models/components
- [ ] Update imports to canonical locations

### Phase 6: Performance Validation (Week 8)
- [ ] Establish baseline benchmarks
- [ ] Run load tests
- [ ] Profile frontend bundle
- [ ] Verify optimizations effective

---

## Quick Wins (Can Do Now)

### 1. Add Missing Indexes
```bash
php artisan make:migration add_missing_indexes --path=database/migrations/tenant
```

### 2. Remove Backup File
```bash
rm backend/.env.backup
```

### 3. Consolidate Field Types
```bash
# Verify no imports then remove
grep -r "constants/field-types" frontend/src/
rm frontend/src/lib/constants/field-types.ts
```

### 4. Create Basic Workflow Factory
```bash
php artisan make:factory WorkflowFactory
php artisan make:factory WorkflowStepFactory
```

---

## Metrics to Track

| Metric | Current | Target |
|--------|---------|--------|
| Backend test coverage | ~30% | >80% |
| Frontend E2E coverage | ~20% | >70% |
| API response time (avg) | TBD | <200ms |
| Kanban load time | TBD | <300ms |
| Report execution time | TBD | <1.5s |
| Frontend bundle size | TBD | <500KB |

---

## Commands

```bash
# Backend Tests
php artisan test --testsuite=Feature
php artisan test --filter=WorkflowApiTest

# Frontend Tests
pnpm test:e2e
pnpm test:e2e -- --grep "DataTable"

# Performance
php artisan telescope:install  # Add debugging
php artisan optimize:clear     # Clear caches

# Bundle Analysis
cd frontend && pnpm build --analyze
```

---

## Related Documentation
- [Main CRM Documentation](../../system-documentation/VRTX_CRM_DOCUMENTATION.md)
- [Future Development Roadmap](../ROADMAP.md)
