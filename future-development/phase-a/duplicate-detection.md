# A3: Duplicate Detection

**Status: ✅ IMPLEMENTED** (95% Complete)

> **Implementation Date:** December 2025
>
> **What's Done:** Full backend (services, API, jobs, models), all frontend components (warning modal, merge wizard, rule builder, candidates list)
>
> **Remaining:** Create dedicated `/admin/duplicates` management page

## Overview

Automatically detect and help users merge duplicate records, maintaining data quality and preventing duplicate outreach.

## User Stories

1. As a user, I want to be warned when creating a record that may be a duplicate
2. As an admin, I want to define matching rules for detecting duplicates
3. As a user, I want to merge duplicate records while preserving important data
4. As a manager, I want to see duplicate detection reports

## Feature Requirements

### Core Functionality
- [x] Real-time duplicate detection on record creation
- [x] Batch duplicate scanning for existing data
- [x] Configurable matching rules per module
- [x] Duplicate merge wizard with field-by-field selection
- [x] Automatic redirect of relationships to merged record
- [x] Duplicate prevention (block vs warn)

### Matching Rules
- Exact match on field
- Fuzzy match (Levenshtein distance)
- Phonetic match (Soundex/Metaphone for names)
- Email domain matching
- Combination rules (AND/OR logic)

### Merge Options
- Keep value from Record A
- Keep value from Record B
- Keep both (for multi-value fields)
- Custom value
- Preserve all activity/history from both records

## Technical Requirements

### Database Schema

```sql
-- Duplicate detection rules
CREATE TABLE duplicate_rules (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id),
    name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    action VARCHAR(20) DEFAULT 'warn', -- 'warn', 'block'
    conditions JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Example conditions JSONB:
-- {
--   "logic": "or",
--   "rules": [
--     {"field": "email", "match_type": "exact"},
--     {"logic": "and", "rules": [
--       {"field": "first_name", "match_type": "fuzzy", "threshold": 0.8},
--       {"field": "last_name", "match_type": "exact"},
--       {"field": "company_name", "match_type": "fuzzy", "threshold": 0.7}
--     ]}
--   ]
-- }

-- Detected duplicates queue
CREATE TABLE duplicate_candidates (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id),
    record_id_a INTEGER NOT NULL,
    record_id_b INTEGER NOT NULL,
    match_score DECIMAL(5,4) NOT NULL,
    matched_rules JSONB,
    status VARCHAR(20) DEFAULT 'pending', -- 'pending', 'merged', 'dismissed'
    reviewed_by INTEGER REFERENCES users(id),
    reviewed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Merge audit log
CREATE TABLE merge_logs (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id),
    surviving_record_id INTEGER NOT NULL,
    merged_record_ids INTEGER[] NOT NULL,
    field_selections JSONB NOT NULL,
    merged_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `DuplicateDetectionService` - Find duplicates, calculate match scores
- `DuplicateMergeService` - Merge records, update relationships

**Jobs:**
- `ScanDuplicatesJob` - Batch scan for duplicates
- `CleanupMergedRecordsJob` - Clean up after merges

**API Endpoints:**
```
GET    /api/v1/duplicates/check           # Check for duplicates (real-time)
GET    /api/v1/duplicates/candidates      # List duplicate candidates
POST   /api/v1/duplicates/merge           # Merge records
POST   /api/v1/duplicates/dismiss         # Dismiss false positive
GET    /api/v1/duplicates/rules           # List detection rules
POST   /api/v1/duplicates/rules           # Create rule
PUT    /api/v1/duplicates/rules/{id}      # Update rule
DELETE /api/v1/duplicates/rules/{id}      # Delete rule
POST   /api/v1/duplicates/scan            # Trigger batch scan
```

### Frontend Components

**New Components:**
- `DuplicateWarningModal.svelte` - Shown when duplicate detected
- `DuplicateMergeWizard.svelte` - Step-by-step merge UI
- `DuplicateCandidatesList.svelte` - Review pending duplicates
- `DuplicateRuleBuilder.svelte` - Configure matching rules
- `FieldMergeSelector.svelte` - Choose which value to keep

**New Routes:**
- `/settings/duplicate-rules` - Manage detection rules
- `/data/duplicates` - Review duplicate candidates

## UI/UX Design

### Duplicate Warning on Create
```
┌─────────────────────────────────────────────────────────────┐
│ ⚠️ Possible Duplicate Detected                              │
├─────────────────────────────────────────────────────────────┤
│ The record you're creating may be a duplicate of:           │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ John Smith                                    95% match │ │
│ │ john.smith@acme.com | Acme Corporation                  │ │
│ │ Created: Jan 15, 2025 by Sarah Jones                    │ │
│ │ [View Record] [Merge with this]                         │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ [Create Anyway] [Cancel]                                    │
└─────────────────────────────────────────────────────────────┘
```

### Merge Wizard
```
Step 1: Select Primary Record
Step 2: Review Field-by-Field
┌───────────────┬───────────────────┬───────────────────┬─────────┐
│ Field         │ Record A          │ Record B          │ Keep    │
├───────────────┼───────────────────┼───────────────────┼─────────┤
│ Name          │ John Smith        │ Johnny Smith      │ ○ A ● B │
│ Email         │ john@acme.com     │ j.smith@acme.com  │ ● A ○ B │
│ Phone         │ (555) 123-4567    │ -                 │ ● A ○ B │
│ Company       │ Acme Corp         │ Acme Corporation  │ ○ A ● B │
└───────────────┴───────────────────┴───────────────────┴─────────┘
Step 3: Confirm & Merge
```

## Testing Requirements

- [ ] Unit tests for matching algorithms
- [ ] Test fuzzy matching thresholds
- [ ] Test merge with relationships
- [ ] Test batch scanning performance
- [ ] E2E test for merge workflow

## Implementation Files

**Backend:**
- `database/migrations/tenant/2025_12_07_000003_add_duplicate_detection_support.php`
- `app/Services/Duplicates/DuplicateDetectionService.php`
- `app/Services/Duplicates/DuplicateMergeService.php`
- `app/Models/DuplicateRule.php`
- `app/Models/DuplicateCandidate.php`
- `app/Models/MergeLog.php`
- `app/Http/Controllers/Api/DuplicateController.php`
- `app/Jobs/ScanDuplicatesJob.php`

**Frontend:**
- `src/lib/api/duplicates.ts`
- `src/lib/components/duplicates/DuplicateWarningModal.svelte`
- `src/lib/components/duplicates/DuplicateMergeWizard.svelte`
- `src/lib/components/duplicates/DuplicateCandidatesList.svelte`
- `src/lib/components/duplicates/DuplicateRuleBuilder.svelte`

## Success Metrics

- Number of duplicates detected and merged
- Duplicate rate over time (should decrease)
- User engagement with merge suggestions
