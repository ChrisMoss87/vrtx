# B6: Competitor Battlecards

## Overview

Dynamic competitive intelligence integrated into deal records, with team-contributed insights, counter-objection scripts, and win/loss patterns by competitor.

## User Stories

1. As a sales rep, I want to see relevant competitor info when I'm working a deal
2. As a team member, I want to contribute insights about competitors I encounter
3. As a manager, I want to analyze win/loss rates against specific competitors
4. As a rep, I want quick access to counter-objection scripts

## Feature Requirements

### Core Functionality
- [ ] Competitor profiles with key information
- [ ] Attach competitors to deals
- [ ] Battlecard content (strengths, weaknesses, counters)
- [ ] Team-contributed notes and updates
- [ ] Win/loss analytics by competitor
- [ ] Counter-objection library
- [ ] Real-time battlecard access from deals
- [ ] Competitor comparison tables

### Battlecard Sections
- **Overview** - Company info, market position
- **Strengths** - What they do well
- **Weaknesses** - Where we beat them
- **Pricing** - Their pricing model/estimates
- **Objection Handlers** - How to counter their claims
- **Win Stories** - Examples of winning against them
- **Resources** - Links, docs, case studies

### Analytics
- Win rate vs competitor
- Average deal size vs competitor
- Common objections by competitor
- Best counter-arguments (by success)
- Trends over time

## Technical Requirements

### Database Schema

```sql
CREATE TABLE competitors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    website VARCHAR(500),
    logo_url VARCHAR(500),
    description TEXT,
    market_position TEXT,
    pricing_info TEXT,
    last_updated_at TIMESTAMP,
    last_updated_by INTEGER REFERENCES users(id),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE battlecard_sections (
    id SERIAL PRIMARY KEY,
    competitor_id INTEGER REFERENCES competitors(id) ON DELETE CASCADE,
    section_type VARCHAR(50) NOT NULL, -- strengths, weaknesses, counters, pricing, etc.
    content TEXT NOT NULL,
    display_order INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE competitor_objections (
    id SERIAL PRIMARY KEY,
    competitor_id INTEGER REFERENCES competitors(id) ON DELETE CASCADE,
    objection TEXT NOT NULL,
    counter_script TEXT NOT NULL,
    effectiveness_score DECIMAL(3,2), -- calculated from usage feedback
    use_count INTEGER DEFAULT 0,
    success_count INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_competitors (
    id SERIAL PRIMARY KEY,
    deal_id INTEGER NOT NULL,
    competitor_id INTEGER REFERENCES competitors(id),
    is_primary BOOLEAN DEFAULT false,
    notes TEXT,
    outcome VARCHAR(20), -- 'won', 'lost', 'unknown'
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE competitor_notes (
    id SERIAL PRIMARY KEY,
    competitor_id INTEGER REFERENCES competitors(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    source VARCHAR(255), -- where the insight came from
    created_by INTEGER REFERENCES users(id),
    is_verified BOOLEAN DEFAULT false,
    verified_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE objection_feedback (
    id SERIAL PRIMARY KEY,
    objection_id INTEGER REFERENCES competitor_objections(id),
    deal_id INTEGER,
    was_successful BOOLEAN,
    feedback TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `CompetitorService` - CRUD for competitors
- `BattlecardService` - Manage battlecard content
- `CompetitorAnalyticsService` - Win/loss analysis

**API Endpoints:**
```
# Competitors
GET    /api/v1/competitors
GET    /api/v1/competitors/{id}
POST   /api/v1/competitors
PUT    /api/v1/competitors/{id}
DELETE /api/v1/competitors/{id}

# Battlecard content
GET    /api/v1/competitors/{id}/battlecard
PUT    /api/v1/competitors/{id}/battlecard/sections/{sectionId}
POST   /api/v1/competitors/{id}/battlecard/sections

# Objections
GET    /api/v1/competitors/{id}/objections
POST   /api/v1/competitors/{id}/objections
PUT    /api/v1/competitors/{id}/objections/{objId}
POST   /api/v1/competitors/{id}/objections/{objId}/feedback

# Deal-competitor links
POST   /api/v1/deals/{dealId}/competitors
DELETE /api/v1/deals/{dealId}/competitors/{competitorId}
PUT    /api/v1/deals/{dealId}/competitors/{competitorId}/outcome

# Analytics
GET    /api/v1/competitors/{id}/analytics
GET    /api/v1/competitors/comparison?ids=1,2,3

# Notes
GET    /api/v1/competitors/{id}/notes
POST   /api/v1/competitors/{id}/notes
```

### Frontend Components

**New Components:**
- `CompetitorList.svelte` - Manage competitors
- `CompetitorProfile.svelte` - Full competitor detail
- `Battlecard.svelte` - Quick-access battlecard view
- `BattlecardSection.svelte` - Editable section
- `ObjectionHandler.svelte` - Objection with counter
- `ObjectionFeedback.svelte` - Rate effectiveness
- `DealCompetitors.svelte` - Add to deal
- `CompetitorAnalytics.svelte` - Win/loss charts
- `CompetitorComparison.svelte` - Side-by-side table
- `CompetitorNotes.svelte` - Team insights

**Integration:**
- Battlecard panel on deal detail page
- Quick-access modal from any record

**New Routes:**
- `/competitors` - Competitor list
- `/competitors/{id}` - Competitor detail/battlecard

## UI/UX Design

### Battlecard (Quick View)
```
┌─────────────────────────────────────────────────────────────────────┐
│ ⚔️ Battlecard: Salesforce                      [Edit] [Full View]  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ 📊 Your Win Rate: 42% (23/55 deals)                                │
│                                                                     │
│ 💪 Our Strengths                                                   │
│ • 50% lower total cost of ownership                                │
│ • Faster implementation (weeks vs months)                          │
│ • No per-user pricing model                                        │
│ • Superior customization without consultants                        │
│                                                                     │
│ ⚠️ Their Strengths                                                  │
│ • Brand recognition and trust                                      │
│ • Larger ecosystem and integrations                                │
│ • More enterprise features                                         │
│                                                                     │
│ 🎯 Counter-Objections                                              │
│ ┌───────────────────────────────────────────────────────────────┐  │
│ │ "Salesforce is the industry standard"                         │  │
│ │ Counter: "Standards change. Many companies are moving away... │  │
│ │ [See full script] [Used it: ✓ Worked | ✗ Didn't]             │  │
│ └───────────────────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────────────────┐  │
│ │ "We need all the integrations"                                │  │
│ │ Counter: "Let's identify your specific needs. We offer..."   │  │
│ │ [See full script] [Used it: ✓ Worked | ✗ Didn't]             │  │
│ └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│ 📝 Latest Intel (from team)                                        │
│ • "They're raising prices 20% next quarter" - Sarah, Jan 10        │
│ • "New VP of Sales at SF is aggressive" - Mike, Jan 8              │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Deal Competitor Panel
```
┌─────────────────────────────────────────────────────────────────────┐
│ Competitors on this Deal                                           │
├─────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────┐ ┌─────────────────┐                            │
│ │ 🏢 Salesforce   │ │ 🏢 HubSpot      │ [+ Add Competitor]        │
│ │ Primary ⭐       │ │                 │                            │
│ │ [View Battlecard]│ │ [View Battlecard]│                           │
│ └─────────────────┘ └─────────────────┘                            │
│                                                                     │
│ 💡 Quick tip: Against Salesforce, emphasize our pricing model      │
└─────────────────────────────────────────────────────────────────────┘
```

### Win/Loss Analytics
```
┌─────────────────────────────────────────────────────────────────────┐
│ Win/Loss Analysis: Salesforce                                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ Overall: 42% win rate │ 55 competitive deals │ $2.3M won          │
│                                                                     │
│ By Deal Size:                                                      │
│ <$10k:   ████████████████████ 65% (13/20)                         │
│ $10-50k: █████████████░░░░░░░ 45% (9/20)                          │
│ >$50k:   ████░░░░░░░░░░░░░░░░ 20% (3/15)                          │
│                                                                     │
│ Top Win Reasons:          │ Top Loss Reasons:                      │
│ 1. Pricing (45%)          │ 1. Brand preference (38%)              │
│ 2. Ease of use (30%)      │ 2. Existing contract (25%)             │
│ 3. Customization (15%)    │ 3. Integration needs (20%)             │
│                                                                     │
│ Most Effective Counters:                                           │
│ 1. "TCO comparison" - 78% success                                  │
│ 2. "Implementation speed" - 65% success                            │
│ 3. "No consultant dependency" - 55% success                        │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test competitor CRUD
- [ ] Test battlecard editing
- [ ] Test objection feedback
- [ ] Test analytics calculations
- [ ] Test deal-competitor linking
- [ ] E2E test competitive deal workflow

## Success Metrics

- Battlecard usage frequency
- Counter-objection effectiveness
- Win rate improvement vs competitors
- Team contribution rate
