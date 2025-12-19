# C2: Sales Velocity Metrics

## Overview
Calculate and visualize sales velocity: the speed at which deals move through the pipeline and generate revenue.

## Formula
Sales Velocity = (# of Opportunities × Win Rate × Avg Deal Size) / Sales Cycle Length

## Key Features
- Real-time velocity calculation
- Velocity by rep, team, product
- Component breakdown (what's slowing us down?)
- Historical velocity trends
- Velocity improvement recommendations
- Stage duration analysis

## Database Additions
```sql
CREATE TABLE velocity_snapshots (
    id SERIAL PRIMARY KEY,
    user_id INTEGER,
    team_id INTEGER,
    period DATE NOT NULL,
    opportunities INTEGER,
    win_rate DECIMAL(5,4),
    avg_deal_size DECIMAL(15,2),
    avg_cycle_days INTEGER,
    velocity DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT NOW()
);
```

## API Endpoints
```
GET /api/v1/analytics/velocity
GET /api/v1/analytics/velocity/components
GET /api/v1/analytics/velocity/trend
GET /api/v1/analytics/stage-duration
```

## Components
- `VelocityDashboard.svelte`
- `VelocityGauge.svelte`
- `VelocityComponentBreakdown.svelte`
- `StageDurationChart.svelte`
