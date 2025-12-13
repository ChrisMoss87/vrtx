# C5: Customer Health Scores

## Overview
Automated health scoring for customers based on engagement, usage, and satisfaction signals to predict churn and identify upsell opportunities.

## Key Features
- Configurable health score formula
- Component scores (engagement, satisfaction, growth)
- Health trend tracking
- Churn risk alerts
- Upsell opportunity identification
- Health score by segment

## Score Components
- **Engagement**: Email opens, meeting frequency, portal logins
- **Satisfaction**: NPS, support tickets, feedback
- **Growth**: Expansion revenue, product usage
- **Relationship**: Champion engagement, stakeholder coverage
- **Financial**: Payment timeliness, contract value

## Database Additions
```sql
CREATE TABLE health_scores (
    id SERIAL PRIMARY KEY,
    account_id INTEGER NOT NULL,
    overall_score INTEGER, -- 0-100
    engagement_score INTEGER,
    satisfaction_score INTEGER,
    growth_score INTEGER,
    relationship_score INTEGER,
    financial_score INTEGER,
    risk_level VARCHAR(20), -- 'healthy', 'at_risk', 'critical'
    calculated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE health_score_rules (
    id SERIAL PRIMARY KEY,
    component VARCHAR(50),
    metric VARCHAR(100),
    weight DECIMAL(3,2),
    thresholds JSONB
);
```

## API Endpoints
```
GET /api/v1/accounts/{id}/health
GET /api/v1/health-scores/overview
GET /api/v1/health-scores/at-risk
PUT /api/v1/health-scores/rules
```

## Components
- `HealthScoreGauge.svelte`
- `HealthScoreBreakdown.svelte`
- `AtRiskAccountsList.svelte`
- `HealthTrendChart.svelte`
- `HealthRuleEditor.svelte`
