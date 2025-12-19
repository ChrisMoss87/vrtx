# C1: Win/Loss Analysis

## Overview
Comprehensive analysis of closed deals to understand patterns in wins and losses, including reason tracking, trend analysis, and actionable insights.

## Key Features
- Win/loss reason capture on deal close
- Configurable reason categories
- Trend analysis over time
- Breakdown by rep, product, competitor, source
- Post-mortem workflow for large deals
- Export for review meetings

## Database Additions
```sql
CREATE TABLE close_reasons (
    id SERIAL PRIMARY KEY,
    type VARCHAR(10) NOT NULL, -- 'win', 'loss'
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true
);

ALTER TABLE module_records ADD COLUMN close_reason_id INTEGER;
ALTER TABLE module_records ADD COLUMN close_notes TEXT;
```

## API Endpoints
```
GET    /api/v1/analytics/win-loss
GET    /api/v1/analytics/win-loss/reasons
POST   /api/v1/deals/{id}/close
```

## Components
- `WinLossReasonSelector.svelte`
- `WinLossAnalyticsDashboard.svelte`
- `WinLossTrendChart.svelte`
- `CloseReasonManager.svelte`
