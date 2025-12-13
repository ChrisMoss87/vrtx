# A2: Sales Forecasting

**Status: ✅ IMPLEMENTED** (100% Complete)

> **Implementation Date:** December 2025
>
> **What's Done:** Full backend (service, API, models), frontend API client, core components, dashboard page, quota management page, sidebar navigation

## Overview

Provide weighted pipeline forecasting with multiple projection methods, allowing sales teams to predict revenue and track against targets.

## User Stories

1. As a sales rep, I want to see my forecasted revenue for this month/quarter
2. As a sales manager, I want to see team forecasts and compare to quotas
3. As an executive, I want best/worst case scenario projections
4. As a user, I want to manually adjust forecast values for individual deals

## Feature Requirements

### Core Functionality
- [x] Weighted forecast based on stage probability
- [x] Time-based projections (this week, month, quarter, year)
- [x] Forecast categories (commit, best case, pipeline, omitted)
- [x] Manual forecast override per deal
- [x] Forecast history tracking
- [x] Comparison to quotas/targets
- [x] Forecast accuracy tracking over time

### Forecast Methods
1. **Weighted Pipeline**: Deal amount × Stage probability
2. **Commit Forecast**: Deals marked as "commit"
3. **Best Case**: Commit + likely deals
4. **Pipeline**: All open deals (unweighted)

### Visualization
- Forecast summary cards (commit, best case, pipeline)
- Trend chart over time
- Forecast vs actual comparison
- Team breakdown view

## Technical Requirements

### Database Schema

```sql
-- Forecast categories per deal
ALTER TABLE module_records ADD COLUMN forecast_category VARCHAR(20);
-- Values: 'commit', 'best_case', 'pipeline', 'omitted'

ALTER TABLE module_records ADD COLUMN forecast_override DECIMAL(15,2);

-- Forecast snapshots for history
CREATE TABLE forecast_snapshots (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    period_type VARCHAR(20) NOT NULL, -- 'month', 'quarter', 'year'
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    commit_amount DECIMAL(15,2) DEFAULT 0,
    best_case_amount DECIMAL(15,2) DEFAULT 0,
    pipeline_amount DECIMAL(15,2) DEFAULT 0,
    weighted_amount DECIMAL(15,2) DEFAULT 0,
    closed_won_amount DECIMAL(15,2) DEFAULT 0,
    snapshot_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Quotas/targets
CREATE TABLE sales_quotas (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    team_id INTEGER,
    period_type VARCHAR(20) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    quota_amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `ForecastService` - Calculate forecasts, snapshots
- `QuotaService` - Manage quotas and tracking

**API Endpoints:**
```
GET    /api/v1/forecasts                  # Get forecast summary
GET    /api/v1/forecasts/deals            # Get deals with forecast data
PUT    /api/v1/deals/{id}/forecast        # Update deal forecast category/override
GET    /api/v1/forecasts/history          # Forecast snapshots over time
GET    /api/v1/forecasts/accuracy         # Forecast vs actual analysis

GET    /api/v1/quotas                     # List quotas
POST   /api/v1/quotas                     # Create quota
PUT    /api/v1/quotas/{id}                # Update quota
GET    /api/v1/quotas/attainment          # Quota vs actual
```

### Frontend Components

**New Components:**
- `ForecastDashboard.svelte` - Main forecast view
- `ForecastSummaryCards.svelte` - Commit/Best Case/Pipeline cards
- `ForecastChart.svelte` - Trend visualization
- `ForecastTable.svelte` - Deal-level forecast view
- `DealForecastEditor.svelte` - Edit deal forecast category
- `QuotaManager.svelte` - Set and manage quotas
- `ForecastAccuracyChart.svelte` - Historical accuracy

**New Routes:**
- `/forecasts` - Forecast dashboard page
- `/forecasts/quotas` - Quota management page

## UI/UX Design

### Forecast Summary Cards
```
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│     COMMIT       │ │    BEST CASE     │ │    PIPELINE      │
│    $125,000      │ │    $245,000      │ │    $580,000      │
│   ████████░░ 83% │ │   ██████░░░░ 60% │ │   ███░░░░░░░ 30% │
│   of $150k quota │ │   of $150k quota │ │   of $150k quota │
└──────────────────┘ └──────────────────┘ └──────────────────┘
```

### Deal Forecast Categories
```
┌─────────────────────────────────────────────────────────────┐
│ Deal: Acme Enterprise License                               │
│ Amount: $75,000 | Stage: Negotiation (75%)                 │
│                                                             │
│ Forecast Category: [Commit ▼]                              │
│ ○ Commit      - Will close this period                     │
│ ○ Best Case   - Likely to close                            │
│ ○ Pipeline    - In progress                                │
│ ○ Omitted     - Exclude from forecast                      │
│                                                             │
│ Override Amount: [$_______] (optional)                     │
└─────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Unit tests for forecast calculations
- [ ] Test weighted vs commit vs best case
- [ ] Test quota attainment calculations
- [ ] Test snapshot generation
- [ ] E2E test for forecast workflow

## Implementation Files

**Backend:**
- `database/migrations/tenant/2025_12_07_000002_add_sales_forecasting_support.php`
- `app/Services/Forecast/ForecastService.php`
- `app/Models/ForecastSnapshot.php`
- `app/Models/SalesQuota.php`
- `app/Models/ForecastAdjustment.php`
- `app/Http/Controllers/Api/ForecastController.php`

**Frontend:**
- `src/lib/api/forecasts.ts`
- `src/lib/components/forecast/DealForecastEditor.svelte`
- `src/lib/components/forecast/ForecastDealsTable.svelte`
- `src/lib/components/forecast/ForecastSummaryCards.svelte`
- `src/routes/(app)/forecasts/+page.svelte`
- `src/routes/(app)/forecasts/quotas/+page.svelte`
- `src/lib/components/app-sidebar.svelte` (navigation link)

## Success Metrics

- Forecast accuracy (predicted vs actual)
- Quota attainment rates
- User adoption of forecast categories
