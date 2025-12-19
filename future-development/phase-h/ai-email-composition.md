# H1: AI Email Composition

## Overview
AI-powered email writing assistance that drafts, improves, and personalizes emails based on context and recipient data.

## Key Features
- Draft email from prompt
- Improve existing email
- Adjust tone (formal, friendly, urgent)
- Personalize with CRM data
- Subject line suggestions
- Reply assistance
- Translation support
- A/B variant generation

## AI Capabilities
- Generate email from context (deal stage, contact history)
- Summarize long threads
- Suggest follow-up timing
- Extract action items from replies
- Detect sentiment in responses

## Technical Requirements
- LLM integration (OpenAI, Anthropic)
- Context injection from CRM
- Token management
- Prompt templates

## Database Additions
```sql
CREATE TABLE ai_email_drafts (id, user_id, context, prompt, generated_content);
CREATE TABLE ai_settings (id, tenant_id, provider, api_key, model, settings);
```

## Components
- `AIEmailAssistant.svelte`
- `ToneSelector.svelte`
- `EmailImprover.svelte`
- `SubjectSuggestions.svelte`
