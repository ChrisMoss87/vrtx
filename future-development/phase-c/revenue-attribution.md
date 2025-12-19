# C4: Revenue Attribution

## Overview
Multi-touch attribution engine showing true ROI by marketing channel, campaign, and activity type.

## Key Features
- Multi-touch attribution models
- First-touch, last-touch, linear, time-decay, custom
- Channel performance analysis
- Campaign ROI tracking
- Activity-to-revenue correlation
- Attribution visualization

## Attribution Models
- **First Touch**: 100% to first interaction
- **Last Touch**: 100% to last interaction
- **Linear**: Equal credit to all touches
- **Time Decay**: More credit to recent touches
- **Position Based**: 40/20/40 to first/middle/last
- **Custom**: User-defined weights

## Database Additions
```sql
CREATE TABLE touchpoints (
    id SERIAL PRIMARY KEY,
    contact_id INTEGER,
    deal_id INTEGER,
    channel VARCHAR(100),
    campaign_id INTEGER,
    source VARCHAR(255),
    medium VARCHAR(255),
    touchpoint_type VARCHAR(50),
    occurred_at TIMESTAMP
);

CREATE TABLE attribution_results (
    id SERIAL PRIMARY KEY,
    deal_id INTEGER,
    touchpoint_id INTEGER,
    model VARCHAR(50),
    attributed_revenue DECIMAL(15,2),
    attribution_percent DECIMAL(5,4)
);
```

## API Endpoints
```
GET /api/v1/attribution/by-channel
GET /api/v1/attribution/by-campaign
GET /api/v1/attribution/deal/{id}/journey
POST /api/v1/attribution/calculate
```

## Components
- `AttributionDashboard.svelte`
- `ChannelROIChart.svelte`
- `CustomerJourneyVisualization.svelte`
- `AttributionModelSelector.svelte`
