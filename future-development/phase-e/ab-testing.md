# E4: A/B Testing

## Overview
Built-in A/B testing framework for emails, landing pages, and campaigns with statistical significance calculation.

## Key Features
- Create test variants
- Traffic splitting
- Statistical significance calculation
- Automatic winner selection
- Test scheduling
- Multi-variant testing (A/B/C/D)
- Goal tracking

## Test Types
- Email subject lines
- Email content
- Landing pages
- CTA buttons
- Send times
- Form layouts

## Database Additions
```sql
CREATE TABLE ab_tests (id, name, type, entity_id, status, winner_variant_id);
CREATE TABLE ab_variants (id, test_id, name, content, traffic_percent);
CREATE TABLE ab_results (id, variant_id, impressions, conversions, conversion_rate);
```

## Components
- `ABTestBuilder.svelte`
- `VariantEditor.svelte`
- `TestResults.svelte`
- `SignificanceIndicator.svelte`
