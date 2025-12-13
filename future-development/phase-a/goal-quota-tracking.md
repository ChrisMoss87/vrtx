# A7: Goal & Quota Tracking

## Overview

Set and track sales quotas and goals at individual, team, and company levels with visual progress indicators and attainment reporting.

## User Stories

1. As a sales manager, I want to set monthly/quarterly quotas for my team
2. As a sales rep, I want to see my progress toward my quota
3. As an executive, I want to see overall company goal attainment
4. As a manager, I want to compare team member performance

## Feature Requirements

### Core Functionality
- [ ] Quota setting (individual, team, company)
- [ ] Multiple metric types (revenue, deals, activities)
- [ ] Time periods (monthly, quarterly, yearly)
- [ ] Real-time progress tracking
- [ ] Visual progress indicators
- [ ] Leaderboards
- [ ] Goal achievement notifications
- [ ] Historical quota performance

### Quota Types
- Revenue quota (closed won deals)
- Deal count quota
- New leads quota
- Activity quotas (calls, meetings, emails)
- Custom metric quotas

### Goal Hierarchies
- Individual goals roll up to team goals
- Team goals roll up to company goals
- Weighted goal contributions

## Technical Requirements

### Database Schema

```sql
CREATE TABLE quota_periods (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    period_type VARCHAR(20) NOT NULL, -- 'month', 'quarter', 'year'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE quotas (
    id SERIAL PRIMARY KEY,
    period_id INTEGER REFERENCES quota_periods(id),
    user_id INTEGER REFERENCES users(id),
    team_id INTEGER,
    metric_type VARCHAR(50) NOT NULL, -- 'revenue', 'deals', 'leads', 'calls', 'meetings', 'custom'
    metric_field VARCHAR(100), -- for custom: field API name
    target_value DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    current_value DECIMAL(15,2) DEFAULT 0,
    attainment_percent DECIMAL(5,2) DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE quota_snapshots (
    id SERIAL PRIMARY KEY,
    quota_id INTEGER REFERENCES quotas(id) ON DELETE CASCADE,
    snapshot_date DATE NOT NULL,
    current_value DECIMAL(15,2) NOT NULL,
    attainment_percent DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE goals (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    goal_type VARCHAR(20) NOT NULL, -- 'individual', 'team', 'company'
    user_id INTEGER REFERENCES users(id),
    team_id INTEGER,
    metric_type VARCHAR(50) NOT NULL,
    target_value DECIMAL(15,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    current_value DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'in_progress', -- 'in_progress', 'achieved', 'missed'
    achieved_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE goal_milestones (
    id SERIAL PRIMARY KEY,
    goal_id INTEGER REFERENCES goals(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    target_value DECIMAL(15,2) NOT NULL,
    target_date DATE,
    is_achieved BOOLEAN DEFAULT false,
    achieved_at TIMESTAMP,
    display_order INTEGER DEFAULT 0
);
```

### Backend Components

**Services:**
- `QuotaService` - Manage quotas
- `GoalService` - Manage goals and milestones
- `AttainmentCalculatorService` - Calculate progress

**Jobs:**
- `UpdateQuotaProgressJob` - Recalculate quota progress
- `CreateQuotaSnapshotJob` - Daily snapshots
- `GoalAchievementNotificationJob` - Notify on achievement

**API Endpoints:**
```
# Quotas
GET    /api/v1/quotas                     # List quotas (with filters)
POST   /api/v1/quotas                     # Create quota
PUT    /api/v1/quotas/{id}                # Update quota
DELETE /api/v1/quotas/{id}                # Delete quota
GET    /api/v1/quotas/my-progress         # Current user's quota progress
GET    /api/v1/quotas/team-progress       # Team quota progress
GET    /api/v1/quotas/leaderboard         # Attainment leaderboard

# Goals
GET    /api/v1/goals                      # List goals
POST   /api/v1/goals                      # Create goal
PUT    /api/v1/goals/{id}                 # Update goal
DELETE /api/v1/goals/{id}                 # Delete goal
GET    /api/v1/goals/{id}/progress        # Goal progress details

# Periods
GET    /api/v1/quota-periods              # List periods
POST   /api/v1/quota-periods              # Create period
```

### Frontend Components

**New Components:**
- `QuotaProgressCard.svelte` - Individual quota progress
- `QuotaDashboard.svelte` - Overview of all quotas
- `QuotaEditor.svelte` - Set/edit quotas
- `TeamQuotaManager.svelte` - Assign team quotas
- `Leaderboard.svelte` - Ranked attainment
- `AttainmentChart.svelte` - Progress over time
- `GoalTracker.svelte` - Goal with milestones
- `GoalMilestoneEditor.svelte` - Set milestones

**Dashboard Widgets:**
- `QuotaGaugeWidget.svelte` - Gauge chart showing attainment
- `LeaderboardWidget.svelte` - Mini leaderboard

**New Routes:**
- `/quotas` - Quota management
- `/goals` - Goal tracking
- `/quotas/leaderboard` - Team leaderboard

## UI/UX Design

### Quota Progress Card
```
┌─────────────────────────────────────────────────────────────────────┐
│ Revenue Quota - Q1 2025                                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  $187,500 / $250,000                                               │
│  ███████████████████░░░░░░░░░░░ 75%                                │
│                                                                     │
│  📈 +$32,500 this week                                             │
│  ⏱️ 23 days remaining                                              │
│                                                                     │
│  Pace: $6,250/day needed to hit target                             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Leaderboard
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🏆 Q1 Revenue Leaderboard                                          │
├─────────────────────────────────────────────────────────────────────┤
│ Rank │ Rep              │ Attainment │ Revenue   │ vs Quota        │
├──────┼──────────────────┼────────────┼───────────┼─────────────────┤
│ 🥇 1 │ Sarah Johnson    │    124%    │ $310,000  │ +$60,000        │
│ 🥈 2 │ Mike Chen        │    98%     │ $245,000  │ -$5,000         │
│ 🥉 3 │ John Smith       │    75%     │ $187,500  │ -$62,500        │
│   4  │ Lisa Wang        │    68%     │ $170,000  │ -$80,000        │
│   5  │ Tom Davis        │    52%     │ $130,000  │ -$120,000       │
└─────────────────────────────────────────────────────────────────────┘
```

### Goal with Milestones
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🎯 Goal: $1M ARR by End of Year                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Current: $650,000 / $1,000,000 (65%)                              │
│  ●━━━━━━━━━━━━━●━━━━━━●━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━○  │
│           ✓$250k  ✓$500k  $750k                              $1M   │
│           Mar 31   Jun 30  Sep 30                            Dec 31 │
│                                                                     │
│  ✅ Milestone 1: $250k (Achieved Mar 15)                           │
│  ✅ Milestone 2: $500k (Achieved Jun 22)                           │
│  ⏳ Milestone 3: $750k (Due Sep 30) - $100k to go                  │
│  ○ Milestone 4: $1M (Due Dec 31)                                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test attainment calculations
- [ ] Test rollup from individual to team
- [ ] Test period boundaries
- [ ] Test leaderboard ranking
- [ ] Test achievement notifications
- [ ] E2E test quota setting and tracking

## Success Metrics

- Quota attainment rates
- Goal achievement rates
- User engagement with quota tracking
- Correlation between quota tracking and performance
