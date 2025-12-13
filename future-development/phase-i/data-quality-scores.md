# I2: Data Quality Scores

## Overview
Automatic scoring of record completeness and data quality with recommendations for improvement.

## Key Features
- Per-record quality score (0-100)
- Module-level quality dashboards
- Missing field indicators
- Data freshness tracking
- Duplicate detection flags
- Validation rule violations
- Quality improvement suggestions

## Quality Factors
- Required field completeness
- Optional field completeness
- Data freshness (last updated)
- Email/phone validation status
- Duplicate likelihood
- Relationship completeness
- Activity recency

## Technical Requirements
- Scoring algorithm
- Real-time calculation on save
- Batch recalculation jobs
- Configurable weights
- Quality trend tracking

## Database Additions
```sql
CREATE TABLE data_quality_scores (id, record_type, record_id, score, factors, calculated_at);
CREATE TABLE quality_rules (id, module_id, field, weight, validation_type);
CREATE TABLE quality_trends (id, module_id, avg_score, period_start, period_end);
```

## Components
- `QualityScoreBadge.svelte`
- `QualityDashboard.svelte`
- `QualityRuleConfig.svelte`
- `QualityImprovementPanel.svelte`
