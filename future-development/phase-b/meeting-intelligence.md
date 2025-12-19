# B7: Meeting Intelligence Hub

## Overview

Transform calendar data into actionable sales intelligence by analyzing meeting patterns, stakeholder engagement, and correlating meeting activity with deal outcomes.

## User Stories

1. As a sales rep, I want to see how my meeting activity impacts my deals
2. As a manager, I want to understand my team's meeting patterns
3. As a rep, I want to auto-log meetings from my calendar
4. As an executive, I want to see stakeholder engagement across key accounts

## Feature Requirements

### Core Functionality
- [ ] Auto-sync meetings from calendars
- [ ] Associate meetings with CRM records
- [ ] Meeting frequency analytics
- [ ] Stakeholder engagement tracking
- [ ] Meeting-to-close correlation
- [ ] Heat map of meeting activity
- [ ] Meeting outcome tracking
- [ ] Pre-meeting prep summaries

### Calendar Integration
- Google Calendar sync
- Outlook/Office 365 sync
- Two-way sync (read and create)
- Auto-detect meeting participants
- Match participants to contacts

### Analytics
- Meetings per deal/account
- Meeting density heat maps
- Time from first meeting to close
- Stakeholder coverage analysis
- Optimal meeting cadence insights
- Team meeting distribution

### Meeting Insights
- Which stakeholders have you met with
- Who haven't you engaged yet
- Meeting momentum (increasing/decreasing)
- Suggested follow-up meetings

## Technical Requirements

### Database Schema

```sql
CREATE TABLE synced_meetings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    calendar_provider VARCHAR(50) NOT NULL, -- 'google', 'outlook'
    external_event_id VARCHAR(255) NOT NULL,
    title VARCHAR(500),
    description TEXT,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    location VARCHAR(500),
    is_online BOOLEAN DEFAULT false,
    meeting_url VARCHAR(500),
    organizer_email VARCHAR(255),
    attendees JSONB DEFAULT '[]',
    status VARCHAR(20) DEFAULT 'confirmed', -- confirmed, tentative, cancelled
    deal_id INTEGER,
    company_id INTEGER,
    outcome VARCHAR(50), -- 'completed', 'no_show', 'rescheduled', 'cancelled'
    outcome_notes TEXT,
    synced_at TIMESTAMP DEFAULT NOW(),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, calendar_provider, external_event_id)
);

CREATE TABLE meeting_participants (
    id SERIAL PRIMARY KEY,
    meeting_id INTEGER REFERENCES synced_meetings(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    contact_id INTEGER, -- matched CRM contact
    is_organizer BOOLEAN DEFAULT false,
    response_status VARCHAR(20), -- 'accepted', 'declined', 'tentative', 'needsAction'
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE meeting_analytics_cache (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'deal', 'account', 'user'
    entity_id INTEGER NOT NULL,
    period VARCHAR(20) NOT NULL, -- 'week', 'month', 'quarter'
    period_start DATE NOT NULL,
    total_meetings INTEGER DEFAULT 0,
    total_duration_minutes INTEGER DEFAULT 0,
    unique_stakeholders INTEGER DEFAULT 0,
    meetings_per_week DECIMAL(5,2),
    calculated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(entity_type, entity_id, period, period_start)
);
```

### Backend Components

**Services:**
- `CalendarSyncService` - Sync with Google/Outlook
- `MeetingMatchingService` - Match meetings to CRM records
- `MeetingAnalyticsService` - Calculate insights

**Jobs:**
- `SyncCalendarJob` - Periodic calendar sync
- `CalculateMeetingMetricsJob` - Update analytics cache
- `MatchMeetingParticipantsJob` - Match to contacts

**API Endpoints:**
```
# Calendar connection
GET    /api/v1/calendar/connections
POST   /api/v1/calendar/connect/{provider}
DELETE /api/v1/calendar/disconnect/{connectionId}
POST   /api/v1/calendar/sync

# Meetings
GET    /api/v1/meetings
    ?from=2025-01-01&to=2025-01-31
    &deal_id=123
    &user_id=456
GET    /api/v1/meetings/{id}
PUT    /api/v1/meetings/{id}  # Update outcome, link to deal
POST   /api/v1/meetings/{id}/link-deal
POST   /api/v1/meetings/{id}/log-outcome

# Analytics
GET    /api/v1/meetings/analytics/overview
GET    /api/v1/meetings/analytics/by-deal/{dealId}
GET    /api/v1/meetings/analytics/by-account/{accountId}
GET    /api/v1/meetings/analytics/heatmap
GET    /api/v1/meetings/analytics/stakeholder-coverage/{accountId}

# Insights
GET    /api/v1/meetings/insights/deal/{dealId}
    # Recommendations for meeting cadence, missing stakeholders
```

### Frontend Components

**New Components:**
- `MeetingIntelligenceDashboard.svelte` - Main hub
- `MeetingCalendarView.svelte` - Calendar with meetings
- `MeetingList.svelte` - List of synced meetings
- `MeetingDetail.svelte` - Single meeting view
- `MeetingHeatmap.svelte` - Activity heat map
- `StakeholderCoverage.svelte` - Who you've met
- `MeetingToCloseChart.svelte` - Correlation chart
- `DealMeetingPanel.svelte` - Meetings on deal page
- `PreMeetingPrep.svelte` - Context before meeting

**Dashboard Widgets:**
- `MeetingActivityWidget.svelte`
- `UpcomingMeetingsWidget.svelte`
- `StakeholderGapsWidget.svelte`

**New Routes:**
- `/meetings` - Meeting intelligence hub
- `/meetings/analytics` - Meeting analytics

## UI/UX Design

### Meeting Intelligence Dashboard
```
┌─────────────────────────────────────────────────────────────────────┐
│ 📅 Meeting Intelligence                                            │
├─────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────┐ ┌─────────────────────────────────┐ │
│ │ This Month                  │ │ Upcoming Meetings               │ │
│ │                             │ │                                 │ │
│ │ 32 Meetings                 │ │ Today:                          │ │
│ │ 48 Hours in meetings        │ │ • 10:00 Demo - Acme Corp       │ │
│ │ 24 Unique stakeholders      │ │ • 14:00 Follow-up - TechCo     │ │
│ │                             │ │                                 │ │
│ │ vs Last Month: +15% ↑       │ │ Tomorrow:                       │ │
│ │                             │ │ • 09:00 Discovery - BigCorp     │ │
│ └─────────────────────────────┘ └─────────────────────────────────┘ │
│                                                                     │
│ ┌───────────────────────────────────────────────────────────────┐   │
│ │ Meeting Activity Heatmap                                      │   │
│ │                                                               │   │
│ │       Mon   Tue   Wed   Thu   Fri                            │   │
│ │ 9AM   ░░    ██    ██    ░░    ██                             │   │
│ │ 10AM  ██    ██    ██    ██    ░░                             │   │
│ │ 11AM  ██    ░░    ██    ██    ██                             │   │
│ │ 12PM  ░░    ░░    ░░    ░░    ░░                             │   │
│ │ 1PM   ░░    ░░    ░░    ░░    ░░                             │   │
│ │ 2PM   ██    ██    ░░    ██    ░░                             │   │
│ │ 3PM   ██    ██    ██    ░░    ██                             │   │
│ │ 4PM   ░░    ██    ██    ██    ░░                             │   │
│ │                                                               │   │
│ │ Peak times: Tue-Wed 10AM-11AM                                │   │
│ └───────────────────────────────────────────────────────────────┘   │
│                                                                     │
│ ┌───────────────────────────────────────────────────────────────┐   │
│ │ Stakeholder Coverage Gaps                         [View All]  │   │
│ │                                                               │   │
│ │ Acme Corp ($75k deal):                                       │   │
│ │ ✅ Mike Chen (Decision Maker) - Met 3x                       │   │
│ │ ✅ Sarah Johnson (Champion) - Met 5x                          │   │
│ │ ⚠️ Tom Davis (CFO) - Never met                               │   │
│ │ ⚠️ Legal Team - Never met                                    │   │
│ │                                                               │   │
│ │ 💡 Recommendation: Schedule intro with CFO before proposal    │   │
│ └───────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### Deal Meeting Panel
```
┌─────────────────────────────────────────────────────────────────────┐
│ Meeting History: Acme Enterprise Deal                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ 📊 8 meetings │ 12 hours │ 5 stakeholders                          │
│                                                                     │
│ Timeline:                                                          │
│ ●━━━━━●━━━━━●━━━━━●━━━━━●━━━━━●━━━━━●━━━━━●━━━━━○                 │
│ Dec 1  Dec 8  Dec 15 Dec 22 Jan 5  Jan 12 Jan 19 Close?            │
│                                                                     │
│ Average: 1.5 meetings/week (healthy momentum)                      │
│                                                                     │
│ Recent Meetings:                                                   │
│ • Jan 12 - Technical Review (2 hrs) - Mike, Sarah, Tech Team      │
│ • Jan 5 - Pricing Discussion (1 hr) - Mike                         │
│ • Dec 22 - Demo (1.5 hrs) - Mike, Sarah, John                      │
│                                                                     │
│ [Schedule Next Meeting] [View All Meetings]                        │
└─────────────────────────────────────────────────────────────────────┘
```

### Pre-Meeting Prep
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🎯 Pre-Meeting Prep: Demo with Acme Corp (in 30 min)               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ Attendees:                                                         │
│ • Mike Chen (VP Sales) - Met 3x, last: Jan 5                       │
│ • Sarah Johnson (Manager) - Met 2x, last: Dec 22                   │
│ • NEW: Tom Davis (CFO) - First meeting ⭐                          │
│                                                                     │
│ Deal Context:                                                      │
│ • Stage: Proposal | Amount: $75,000 | Close: Jan 28               │
│ • Last activity: Sent revised pricing (yesterday)                  │
│                                                                     │
│ Previous Meeting Notes:                                            │
│ • "Need ROI justification for CFO" - Jan 5                        │
│ • "Concerns about implementation timeline" - Dec 22                │
│                                                                     │
│ Suggested Talking Points:                                          │
│ • Address CFO - have ROI calculations ready                        │
│ • Clarify implementation timeline (2-3 weeks)                      │
│ • Discuss next steps toward contract                               │
│                                                                     │
│ [Open Deal Record] [View Full History]                             │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test calendar sync (Google, Outlook)
- [ ] Test participant matching
- [ ] Test analytics calculations
- [ ] Test heatmap generation
- [ ] Test stakeholder coverage
- [ ] E2E test meeting workflow

## Success Metrics

- Calendar connection rate
- Meetings auto-linked to deals
- Stakeholder gap identification
- Correlation with win rate
