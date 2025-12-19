# H2: AI Lead Scoring

## Overview
Machine learning-powered lead scoring that predicts conversion likelihood based on behavioral and demographic signals.

## Key Features
- Automatic lead scoring (0-100)
- Configurable scoring factors
- Real-time score updates
- Score explanation (why this score)
- Conversion prediction
- Ideal Customer Profile (ICP) matching
- Score trending

## Scoring Factors
- Demographic fit (company size, industry)
- Engagement signals (email opens, page views)
- Behavioral patterns (demo requests, pricing views)
- Timing signals (frequency, recency)
- Firmographic data

## Technical Requirements
- ML model training on historical data
- Feature engineering pipeline
- Real-time scoring API
- Model retraining schedule

## Database Additions
```sql
CREATE TABLE lead_scores (id, contact_id, score, factors, calculated_at);
CREATE TABLE scoring_models (id, name, features, weights, accuracy, trained_at);
CREATE TABLE scoring_factors (id, model_id, factor_name, weight, category);
```

## Components
- `LeadScoreIndicator.svelte`
- `ScoreExplainer.svelte`
- `ScoringModelManager.svelte`
- `ScoreTrend.svelte`
