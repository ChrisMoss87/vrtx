# G3: Onboarding Playbooks

## Overview
Structured customer onboarding workflows with task checklists, milestones, and progress tracking.

## Key Features
- Playbook templates
- Task checklists
- Milestone tracking
- Automated task assignment
- Due date management
- Progress visualization
- Customer-visible tasks
- Completion notifications

## Playbook Structure
- Phases (e.g., Setup, Training, Go-Live)
- Tasks within phases
- Owner assignment (internal/customer)
- Dependencies between tasks
- Documentation links

## Database Additions
```sql
CREATE TABLE onboarding_playbooks (id, name, description, phases, default_duration);
CREATE TABLE playbook_phases (id, playbook_id, name, order, duration_days);
CREATE TABLE playbook_tasks (id, phase_id, name, description, owner_type, order);
CREATE TABLE customer_onboardings (id, deal_id, playbook_id, start_date, status);
CREATE TABLE onboarding_task_status (id, onboarding_id, task_id, status, completed_at);
```

## Components
- `PlaybookBuilder.svelte`
- `OnboardingTracker.svelte`
- `TaskChecklist.svelte`
- `OnboardingTimeline.svelte`
