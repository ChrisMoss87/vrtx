# B3: Scenario Planner (What-If Forecasting)

## Overview

Interactive forecasting tool where users can drag deals, adjust probabilities, and instantly see the impact on projected revenue. Enables "what-if" analysis for sales planning.

## User Stories

1. As a sales manager, I want to model "what if we close these 3 deals early" to see forecast impact
2. As a rep, I want to understand which deals I need to close to hit my quota
3. As an executive, I want to compare optimistic vs pessimistic scenarios
4. As a manager, I want to plan team capacity based on projected deal volume

## Feature Requirements

### Core Functionality
- [ ] Interactive deal manipulation (drag between stages)
- [ ] Real-time forecast recalculation
- [ ] Multiple scenario creation and comparison
- [ ] Save scenarios for later reference
- [ ] Share scenarios with team
- [ ] Gap analysis (what's needed to hit target)
- [ ] Probability adjustment sliders
- [ ] Close date modification
- [ ] Deal amount changes
- [ ] "Commit" deals to scenario

### Scenario Types
- **Current State** - Actual pipeline
- **Best Case** - All deals close at optimistic estimates
- **Worst Case** - Conservative estimates
- **Target Hit** - Auto-calculated scenario to hit quota
- **Custom** - User-defined scenarios

### Analysis Features
- Revenue impact summary
- Probability-weighted vs unweighted comparison
- Time-based revenue curve
- Resource/capacity implications
- Confidence intervals

## Technical Requirements

### Database Schema

```sql
CREATE TABLE forecast_scenarios (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INTEGER REFERENCES users(id),
    pipeline_id INTEGER REFERENCES pipelines(id),
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    is_baseline BOOLEAN DEFAULT false,
    is_shared BOOLEAN DEFAULT false,
    total_weighted DECIMAL(15,2) DEFAULT 0,
    total_unweighted DECIMAL(15,2) DEFAULT 0,
    deal_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE scenario_deals (
    id SERIAL PRIMARY KEY,
    scenario_id INTEGER REFERENCES forecast_scenarios(id) ON DELETE CASCADE,
    deal_record_id INTEGER NOT NULL, -- original deal record
    stage_id INTEGER REFERENCES pipeline_stages(id),
    amount DECIMAL(15,2) NOT NULL,
    probability INTEGER, -- override probability
    close_date DATE,
    is_committed BOOLEAN DEFAULT false,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE scenario_comparisons (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    user_id INTEGER REFERENCES users(id),
    scenario_ids INTEGER[] NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `ScenarioService` - CRUD for scenarios
- `ScenarioCalculatorService` - Compute projections
- `GapAnalysisService` - Calculate quota gaps

**API Endpoints:**
```
GET    /api/v1/scenarios                  # List scenarios
GET    /api/v1/scenarios/{id}             # Get scenario with deals
POST   /api/v1/scenarios                  # Create scenario
PUT    /api/v1/scenarios/{id}             # Update scenario
DELETE /api/v1/scenarios/{id}             # Delete scenario
POST   /api/v1/scenarios/{id}/duplicate   # Copy scenario

GET    /api/v1/scenarios/{id}/deals       # Get deals in scenario
PUT    /api/v1/scenarios/{id}/deals/{dealId} # Update deal in scenario
POST   /api/v1/scenarios/{id}/commit/{dealId} # Commit deal

GET    /api/v1/scenarios/compare
    ?ids=1,2,3                            # Compare multiple scenarios

GET    /api/v1/scenarios/gap-analysis
    ?target=250000
    &period=Q1-2025                       # What's needed to hit target

POST   /api/v1/scenarios/auto-generate
    ?type=best_case|worst_case|target_hit # Generate scenario
```

### Frontend Components

**New Components:**
- `ScenarioPlanner.svelte` - Main planner interface
- `ScenarioKanban.svelte` - Draggable pipeline view
- `ScenarioDealCard.svelte` - Editable deal card
- `ScenarioSummary.svelte` - Forecast totals and charts
- `ScenarioComparison.svelte` - Side-by-side comparison
- `GapAnalysis.svelte` - Quota gap visualization
- `ProbabilitySlider.svelte` - Adjust deal probability
- `ScenarioTimeline.svelte` - Revenue over time chart
- `ScenarioSaver.svelte` - Save/load scenarios

**New Routes:**
- `/forecasts/scenarios` - Scenario planner
- `/forecasts/scenarios/{id}` - Edit specific scenario
- `/forecasts/scenarios/compare` - Comparison view

## UI/UX Design

### Main Planner Interface
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🎯 Scenario Planner: Q1 2025 Forecast                              │
│ Scenario: [Best Case ▼] [+ New] [Compare] [Save]                   │
├─────────────────────────────────────────────────────────────────────┤
│ ┌───────────────────────────────────────────────────────────────┐   │
│ │ Target: $250,000                     Gap: -$45,000 ⚠️         │   │
│ │ Weighted: $205,000 ████████████████████░░░░░ 82%              │   │
│ │ Committed: $125,000 ███████████████░░░░░░░░░ 50%              │   │
│ └───────────────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────────────┤
│ Pipeline (drag deals to adjust)                                    │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│ │Prospecting│Qualification│ Proposal │Negotiation│ Closed    │   │
│ │ $50,000  │ │ $80,000  │ │$120,000 │ │ $55,000 │ │ $125,000 │   │
│ ├──────────┤ ├──────────┤ ├──────────┤ ├──────────┤ ├──────────┤   │
│ │┌────────┐│ │┌────────┐│ │┌────────┐│ │┌────────┐│ │┌────────┐│   │
│ ││ Acme   ││ ││TechCo  ││ ││ BigCorp││ ││StartUp ││ ││ Won A  ││   │
│ ││ $30k   ││ ││ $45k   ││ ││ $75k ✓ ││ ││ $55k   ││ ││ $80k   ││   │
│ ││ 10%    ││ ││ 25%    ││ ││ 50%    ││ ││ 75%    ││ ││ 100%   ││   │
│ │└────────┘│ │└────────┘│ │└────────┘│ │└────────┘│ │└────────┘│   │
│ │┌────────┐│ │┌────────┐│ │┌────────┐│ │          │ │┌────────┐│   │
│ ││ Beta   ││ ││ Gamma  ││ ││ Delta  ││ │          │ ││ Won B  ││   │
│ ││ $20k   ││ ││ $35k   ││ ││ $45k   ││ │          │ ││ $45k   ││   │
│ │└────────┘│ │└────────┘│ │└────────┘│ │          │ │└────────┘│   │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### Deal Editor Panel (on click)
```
┌─────────────────────────────────────────────────────────────────────┐
│ Edit Deal in Scenario: BigCorp Enterprise                         │
├─────────────────────────────────────────────────────────────────────┤
│ Amount: [$75,000____]  (actual: $75,000)                           │
│                                                                     │
│ Stage: [Proposal ▼]                                                │
│                                                                     │
│ Probability: 10% ━━━━━━●━━━━━━━━━━━━━━━━━━ 100%                    │
│              ↑ Drag to adjust (current: 50%)                       │
│                                                                     │
│ Close Date: [Jan 28, 2025]  (actual: Feb 15, 2025)                 │
│                                                                     │
│ [✓] Committed to this scenario                                     │
│                                                                     │
│ Impact: Moving to Negotiation adds +$18,750 weighted               │
│                                                                     │
│ [Apply Changes] [Reset to Actual]                                  │
└─────────────────────────────────────────────────────────────────────┘
```

### Scenario Comparison
```
┌─────────────────────────────────────────────────────────────────────┐
│ Compare Scenarios                                                   │
├─────────────────────────────────────────────────────────────────────┤
│           │ Current State │ Best Case    │ Worst Case  │ Target   │
│───────────┼───────────────┼──────────────┼─────────────┼──────────│
│ Weighted  │ $185,000      │ $245,000     │ $142,000    │ $250,000 │
│ vs Quota  │ 74% ⚠️        │ 98% ✓        │ 57% ❌      │ 100% ✓  │
│ Deal Count│ 12            │ 12           │ 12          │ 14       │
│ Win Rate  │ 35%           │ 50%          │ 25%         │ 42%      │
│ Avg Deal  │ $15,400       │ $20,400      │ $11,800     │ $17,900  │
├───────────┴───────────────┴──────────────┴─────────────┴──────────┤
│ [View Timeline Chart] [Export Comparison]                          │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test drag-and-drop deal movement
- [ ] Test real-time recalculation
- [ ] Test scenario save/load
- [ ] Test comparison calculations
- [ ] Test gap analysis accuracy
- [ ] E2E test full scenario workflow

## Success Metrics

- Scenarios created per user
- Time spent in scenario planner
- Forecast accuracy improvement
- User-reported confidence in forecasts
