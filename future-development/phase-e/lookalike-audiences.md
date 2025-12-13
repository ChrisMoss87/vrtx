# E5: Lookalike Audiences

## Overview
AI-powered audience building that finds prospects similar to your best customers based on behavioral and demographic patterns.

## Key Features
- Define source audience (best customers)
- AI-powered similarity scoring
- Configurable match criteria
- Export to ad platforms
- Enrichment integration
- Audience refresh scheduling

## Matching Criteria
- Industry and company size
- Geographic location
- Behavior patterns
- Technology usage
- Engagement levels
- Purchase history patterns

## Database Additions
```sql
CREATE TABLE lookalike_audiences (id, name, source_audience_id, match_criteria, size_limit);
CREATE TABLE lookalike_matches (id, audience_id, contact_id, similarity_score, match_factors);
```

## Components
- `LookalikeBuilder.svelte`
- `SourceAudienceSelector.svelte`
- `MatchCriteriaEditor.svelte`
- `AudiencePreview.svelte`
