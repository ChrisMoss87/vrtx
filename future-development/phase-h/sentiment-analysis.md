# H5: Sentiment Analysis

## Overview
AI-powered sentiment detection in emails, calls, and notes to gauge customer mood and relationship health.

## Key Features
- Email sentiment scoring
- Call transcript sentiment
- Sentiment trends over time
- Alert on negative sentiment
- Aggregate account sentiment
- Sentiment in activity timeline

## Sentiment Categories
- Positive / Neutral / Negative
- Emotions (frustrated, happy, confused, etc.)
- Urgency level
- Engagement level

## Technical Requirements
- NLP sentiment model
- Real-time analysis
- Batch processing for history
- Configurable thresholds

## Database Additions
```sql
CREATE TABLE sentiment_scores (id, entity_type, entity_id, score, category, analyzed_at);
CREATE TABLE sentiment_alerts (id, contact_id, trigger_entity, sentiment, created_at);
```

## Components
- `SentimentIndicator.svelte`
- `SentimentTrend.svelte`
- `SentimentAlerts.svelte`
