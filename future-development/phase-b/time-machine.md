# B1: Time Machine (Historical State Viewer)

## Overview

Allow users to view any record as it existed at any point in time, with visual timeline scrubbing and before/after comparisons. This is a unique differentiating feature not found in major CRMs.

## User Stories

1. As a sales manager, I want to see what a deal looked like before it was lost to understand what went wrong
2. As an auditor, I want to view the exact state of a record at a specific date
3. As a user, I want to compare how a record has changed over a time period
4. As a support agent, I want to see what information a customer had when they complained

## Feature Requirements

### Core Functionality
- [ ] Time slider to scrub through record history
- [ ] View complete record state at any point in time
- [ ] Before/after comparison mode
- [ ] Visual diff highlighting changes
- [ ] Timeline markers for significant events
- [ ] Export historical snapshots
- [ ] Activity correlation (what activities happened at each point)

### Timeline Markers
- Field value changes
- Stage transitions
- Owner changes
- Significant activities (emails, calls, meetings)
- Created/updated timestamps

### Comparison Features
- Side-by-side view of two dates
- Field-by-field diff with highlighting
- Aggregate change summary
- Change author attribution

## Technical Requirements

### Database Schema

```sql
-- Record snapshots (stored on significant changes)
CREATE TABLE record_snapshots (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id),
    record_id INTEGER NOT NULL,
    snapshot_data JSONB NOT NULL,
    snapshot_type VARCHAR(50), -- 'field_change', 'stage_change', 'daily', 'manual'
    change_summary JSONB, -- what changed from previous
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Index for fast time-based queries
CREATE INDEX idx_snapshots_record_time
ON record_snapshots(module_id, record_id, created_at);

-- Efficient time-series lookup
CREATE INDEX idx_snapshots_time
ON record_snapshots(created_at DESC);

-- Field change log (granular)
CREATE TABLE field_change_log (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id),
    record_id INTEGER NOT NULL,
    field_api_name VARCHAR(100) NOT NULL,
    old_value JSONB,
    new_value JSONB,
    changed_by INTEGER REFERENCES users(id),
    changed_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_field_changes
ON field_change_log(module_id, record_id, changed_at);
```

### Backend Components

**Services:**
- `RecordHistoryService` - Retrieve historical states
- `SnapshotService` - Create and manage snapshots
- `DiffService` - Calculate differences between states

**Listeners:**
- `RecordChangeListener` - Capture changes on save
- `DailySnapshotJob` - Create daily snapshots for active records

**API Endpoints:**
```
GET /api/v1/records/{module}/{id}/history
    ?start_date=2025-01-01
    &end_date=2025-01-31
    # Returns list of snapshots/changes in range

GET /api/v1/records/{module}/{id}/at/{timestamp}
    # Returns record state at specific time

GET /api/v1/records/{module}/{id}/diff
    ?from=2025-01-01T00:00:00Z
    &to=2025-01-15T00:00:00Z
    # Returns diff between two points

GET /api/v1/records/{module}/{id}/timeline
    # Returns timeline events for visualization

POST /api/v1/records/{module}/{id}/snapshot
    # Create manual snapshot
```

### Frontend Components

**New Components:**
- `TimeMachine.svelte` - Main time machine interface
- `TimeSlider.svelte` - Draggable timeline scrubber
- `HistoricalRecordView.svelte` - Display past record state
- `RecordDiff.svelte` - Side-by-side comparison
- `FieldDiff.svelte` - Individual field change display
- `TimelineMarkers.svelte` - Visual markers on timeline
- `ComparisonDatePicker.svelte` - Select two dates to compare

**Integration Points:**
- Add "View History" button to record detail pages
- Add timeline toggle to activity timeline

## UI/UX Design

### Time Machine Interface
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🕐 Time Machine: Acme Enterprise Deal                   [Exit]     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Viewing: January 15, 2025 at 2:30 PM                              │
│                                                                     │
│  ◀ ─────●─────●─────●─────○─────○─────●─────●─────○───── ▶         │
│    Jan 1    Jan 8   Jan 12  Jan 15  Jan 18  Jan 22  Jan 28  Today  │
│         ↑        ↑         ↑                    ↑                   │
│      Created  Stage    Amount               Owner                   │
│               Change   Changed               Changed                │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                    Record at Jan 15, 2:30 PM                        │
│ ┌───────────────────────────────────────────────────────────────┐   │
│ │ Opportunity Name: Acme Enterprise Deal                        │   │
│ │ Amount: $75,000 ← (was $50,000 on Jan 12)                     │   │
│ │ Stage: Proposal                                                │   │
│ │ Close Date: Feb 28, 2025                                      │   │
│ │ Owner: John Smith                                             │   │
│ │ Probability: 50%                                              │   │
│ └───────────────────────────────────────────────────────────────┘   │
│                                                                     │
│ [Compare Dates] [Export Snapshot] [View Current]                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Comparison View
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🔄 Compare: Acme Enterprise Deal                                   │
├─────────────────────────────────────────────────────────────────────┤
│       January 1, 2025              →        January 28, 2025       │
├────────────────────────────────────┬────────────────────────────────┤
│ Amount: $50,000                    │ Amount: $95,000 ⬆ +$45,000    │
│ Stage: Qualification               │ Stage: Negotiation            │
│ Close Date: Jan 31, 2025          │ Close Date: Feb 28, 2025       │
│ Owner: Sarah Jones                 │ Owner: John Smith ⚠️ Changed   │
│ Probability: 25%                   │ Probability: 75% ⬆ +50%       │
│ Competitors: -                     │ Competitors: Salesforce, Zoho │
│ Notes: Initial discovery call      │ Notes: Final pricing review   │
├────────────────────────────────────┴────────────────────────────────┤
│ Summary: 6 fields changed | Amount +90% | Stage advanced 2 steps   │
└─────────────────────────────────────────────────────────────────────┘
```

## Data Retention

- Store snapshots for configurable period (default: 2 years)
- Compress old snapshots
- Option to export before deletion
- GDPR considerations for deleted records

## Testing Requirements

- [ ] Test snapshot creation on changes
- [ ] Test historical state reconstruction
- [ ] Test diff calculation accuracy
- [ ] Test timeline with many events
- [ ] Test performance with large history
- [ ] E2E test time scrubbing

## Success Metrics

- Time machine feature usage
- User satisfaction with audit capabilities
- Support for compliance requirements
