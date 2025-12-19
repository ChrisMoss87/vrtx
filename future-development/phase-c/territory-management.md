# C3: Territory Management

## Overview
Define and manage sales territories with assignment rules, coverage analytics, and capacity planning.

## Key Features
- Territory definition (geographic, industry, size)
- Assignment rules (automatic routing)
- Territory hierarchy
- Coverage heat maps
- Capacity planning
- Territory performance comparison
- Reassignment tools

## Database Additions
```sql
CREATE TABLE territories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INTEGER REFERENCES territories(id),
    rules JSONB, -- assignment criteria
    settings JSONB
);

CREATE TABLE territory_assignments (
    id SERIAL PRIMARY KEY,
    territory_id INTEGER REFERENCES territories(id),
    user_id INTEGER REFERENCES users(id),
    role VARCHAR(50), -- 'owner', 'member'
    UNIQUE(territory_id, user_id)
);

ALTER TABLE module_records ADD COLUMN territory_id INTEGER;
```

## API Endpoints
```
GET    /api/v1/territories
POST   /api/v1/territories
PUT    /api/v1/territories/{id}
GET    /api/v1/territories/{id}/analytics
POST   /api/v1/territories/auto-assign
```

## Components
- `TerritoryManager.svelte`
- `TerritoryRuleBuilder.svelte`
- `TerritoryMap.svelte`
- `TerritoryPerformance.svelte`
