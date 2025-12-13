# A1: Deal Rotting Alerts

**Status: ✅ IMPLEMENTED** (90% Complete)

> **Implementation Date:** December 2025
>
> **What's Done:** Full backend (service, API, jobs, email), all frontend components
>
> **Remaining:** Create dedicated `/deals/rotting` page route, add navigation menu link

## Overview

Automatically notify users when deals have been inactive for a configurable period, preventing opportunities from going stale and ensuring timely follow-up.

## User Stories

1. As a sales rep, I want to be alerted when a deal hasn't been updated in X days so I can follow up before it goes cold
2. As a sales manager, I want to see all rotting deals across my team to identify at-risk pipeline
3. As an admin, I want to configure rotting thresholds per pipeline stage

## Feature Requirements

### Core Functionality
- [x] Configurable "rotting period" per pipeline stage
- [x] Visual indicator on deal cards showing rot status (fresh, warming, rotting)
- [x] Color-coded aging (green → yellow → orange → red)
- [x] In-app notifications when deals start rotting
- [x] Email digest of rotting deals (daily/weekly)
- [x] Dashboard widget showing rotting deals count

### Configuration Options
- Rotting threshold per stage (days)
- Notification channels (in-app, email, both)
- Digest frequency (daily, weekly, none)
- Exclude weekends from calculation (optional)

## Technical Requirements

### Database Schema

```sql
-- Add to pipelines table or create new table
ALTER TABLE pipeline_stages ADD COLUMN rotting_days INTEGER DEFAULT 7;

-- Track last activity per deal
ALTER TABLE module_records ADD COLUMN last_activity_at TIMESTAMP;

-- Notification preferences
CREATE TABLE rotting_alert_settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    pipeline_id INTEGER REFERENCES pipelines(id),
    email_digest BOOLEAN DEFAULT true,
    digest_frequency VARCHAR(20) DEFAULT 'daily',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `DealRottingService` - Calculate rot status, check thresholds
- `RottingNotificationService` - Send alerts and digests

**Jobs:**
- `CheckRottingDealsJob` - Scheduled job to check all deals
- `SendRottingDigestJob` - Send email digests

**API Endpoints:**
```
GET    /api/v1/deals/rotting              # List rotting deals
GET    /api/v1/deals/{id}/rot-status      # Get rot status for deal
PUT    /api/v1/pipelines/{id}/stages/{stageId}/rotting-config
GET    /api/v1/users/rotting-settings     # Get user preferences
PUT    /api/v1/users/rotting-settings     # Update preferences
```

### Frontend Components

**New Components:**
- `RottingIndicator.svelte` - Visual rot status badge
- `RottingDealsWidget.svelte` - Dashboard widget
- `RottingSettingsPanel.svelte` - User preferences
- `StageRottingConfig.svelte` - Admin configuration

**Modifications:**
- `KanbanCard.svelte` - Add rotting indicator
- `DealDetailPage.svelte` - Show rot status
- `PipelineSettings.svelte` - Add rotting configuration

## UI/UX Design

### Rot Status Indicators
```
🟢 Fresh (0-50% of threshold)
🟡 Warming (50-75% of threshold)
🟠 Stale (75-100% of threshold)
🔴 Rotting (>100% of threshold)
```

### Kanban Card Enhancement
```
┌─────────────────────────────┐
│ Acme Corp Deal        🔴 7d │  ← Rotting indicator with days
│ $50,000                     │
│ John Doe                    │
│ Last activity: 14 days ago  │  ← Activity timestamp
└─────────────────────────────┘
```

## Testing Requirements

- [ ] Unit tests for rot calculation logic
- [ ] Test threshold boundary conditions
- [ ] Test notification delivery
- [ ] Test digest email formatting
- [ ] E2E test for configuration flow

## Implementation Files

**Backend:**
- `database/migrations/tenant/2025_12_07_000001_add_deal_rotting_support.php`
- `app/Services/Rotting/DealRottingService.php`
- `app/Models/RottingAlert.php`
- `app/Models/RottingAlertSetting.php`
- `app/Http/Controllers/Api/RottingAlertController.php`
- `app/Jobs/CheckRottingDealsJob.php`
- `app/Jobs/SendRottingDigestJob.php`
- `app/Mail/RottingDealsDigest.php`

**Frontend:**
- `src/lib/api/rotting.ts`
- `src/lib/components/rotting/RottingIndicator.svelte`
- `src/lib/components/rotting/RottingBadge.svelte`
- `src/lib/components/rotting/RottingDealsWidget.svelte`
- `src/lib/components/rotting/RottingSettingsPanel.svelte`
- `src/lib/components/rotting/StageRottingConfig.svelte`

## Rollout Plan

1. Add database migrations
2. Implement backend service and jobs
3. Add API endpoints
4. Build frontend components
5. Add to pipeline settings
6. Deploy and enable scheduled jobs

## Success Metrics

- Reduction in average deal age
- Increase in follow-up activities
- Decrease in deals closed as "lost - no response"
