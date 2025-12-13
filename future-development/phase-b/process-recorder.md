# B5: Process Recorder

## Overview

Record actual user actions in the CRM and automatically generate workflow automations from the captured patterns. Like "macro recording" for CRM processes.

## User Stories

1. As a user, I want to record my repetitive tasks and turn them into automations
2. As an admin, I want to create workflows without complex builders by demonstrating the process
3. As a manager, I want to capture best practices from top performers as templates
4. As a user, I want to edit recorded workflows before activating them

## Feature Requirements

### Core Functionality
- [ ] Start/stop recording mode
- [ ] Capture all CRM actions during recording
- [ ] Generate workflow from recording
- [ ] Edit generated workflow
- [ ] Parameterize values (replace specific values with variables)
- [ ] Test workflow before activation
- [ ] Save as workflow template

### Recordable Actions
- Create record
- Update record/fields
- Change pipeline stage
- Send email
- Create task
- Add note
- Add tag
- Assign to user
- Log activity

### Post-Recording Features
- Preview recorded steps
- Add/remove/reorder steps
- Replace specific values with field references
- Add conditions between steps
- Set trigger type
- Name and describe workflow

## Technical Requirements

### Database Schema

```sql
CREATE TABLE recordings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    name VARCHAR(255),
    status VARCHAR(20) DEFAULT 'recording', -- recording, completed, converted
    started_at TIMESTAMP DEFAULT NOW(),
    ended_at TIMESTAMP,
    module_id INTEGER REFERENCES modules(id),
    initial_record_id INTEGER,
    workflow_id INTEGER REFERENCES workflows(id), -- resulting workflow
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE recording_steps (
    id SERIAL PRIMARY KEY,
    recording_id INTEGER REFERENCES recordings(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    target_module VARCHAR(100),
    target_record_id INTEGER,
    action_data JSONB NOT NULL, -- captured action details
    captured_at TIMESTAMP DEFAULT NOW()
);

-- Example action_data:
-- {
--   "action": "update_field",
--   "field": "stage",
--   "old_value": "proposal",
--   "new_value": "negotiation"
-- }
-- {
--   "action": "send_email",
--   "template_id": 5,
--   "recipient": "john@acme.com",
--   "subject": "Follow up on proposal"
-- }
```

### Backend Components

**Services:**
- `RecordingService` - Manage recording sessions
- `ActionCaptureService` - Capture and store actions
- `WorkflowGeneratorService` - Convert recording to workflow

**Middleware:**
- `RecordingMiddleware` - Intercept actions when recording

**API Endpoints:**
```
POST   /api/v1/recordings/start           # Start recording
POST   /api/v1/recordings/stop            # Stop recording
GET    /api/v1/recordings/{id}            # Get recording with steps
DELETE /api/v1/recordings/{id}            # Discard recording

GET    /api/v1/recordings/{id}/steps      # List captured steps
DELETE /api/v1/recordings/{id}/steps/{stepId}  # Remove step
PUT    /api/v1/recordings/{id}/steps/reorder   # Reorder steps

POST   /api/v1/recordings/{id}/generate-workflow  # Convert to workflow
GET    /api/v1/recordings/{id}/preview    # Preview as workflow

GET    /api/v1/recordings                 # List all recordings
```

### Frontend Components

**New Components:**
- `RecordingIndicator.svelte` - Floating indicator during recording
- `RecordingControls.svelte` - Start/stop/pause controls
- `RecordingSummary.svelte` - Review captured steps
- `StepEditor.svelte` - Edit individual step
- `ParameterizeModal.svelte` - Replace values with variables
- `WorkflowPreview.svelte` - Preview generated workflow
- `RecordingsList.svelte` - View past recordings

**Global Integration:**
- Recording indicator in header when active
- Visual feedback on each captured action

## UI/UX Design

### Recording Indicator
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🔴 Recording in progress... (5 actions captured)                   │
│ [Pause] [Stop Recording]                                           │
└─────────────────────────────────────────────────────────────────────┘
```

### Step Capture Feedback
```
┌──────────────────────────────────────┐
│ ✓ Captured: Updated stage to        │
│   "Negotiation"                      │
│   on Deal: Acme Enterprise          │
└──────────────────────────────────────┘
  ↑ Toast notification on each action
```

### Recording Summary
```
┌─────────────────────────────────────────────────────────────────────┐
│ 📹 Recording Complete: 8 actions captured                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ 1. ✏️ Updated field "stage" → "negotiation"                        │
│    └─ [Parameterize Value] [Remove]                                │
│                                                                     │
│ 2. 📧 Sent email "Follow up on proposal"                           │
│    └─ To: john@acme.com                                            │
│    └─ [Use Template] [Parameterize Recipient] [Remove]             │
│                                                                     │
│ 3. ✅ Created task "Schedule demo call"                            │
│    └─ Due: 3 days from now                                         │
│    └─ [Parameterize Due Date] [Remove]                             │
│                                                                     │
│ 4. 🏷️ Added tag "high-priority"                                    │
│    └─ [Remove]                                                      │
│                                                                     │
│ ... (4 more actions)                                               │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│ Trigger this workflow when: [Stage changes to Negotiation ▼]       │
│                                                                     │
│ [Preview Workflow] [Generate Workflow] [Discard]                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Parameterize Value Modal
```
┌─────────────────────────────────────────────────────────────────────┐
│ Parameterize Value                                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│ Recorded value: "john@acme.com"                                    │
│                                                                     │
│ Replace with:                                                       │
│ ○ Keep exact value (always use "john@acme.com")                    │
│ ● Field reference: [Contact Email ▼]                               │
│ ○ Current user's email                                             │
│ ○ Deal owner's email                                               │
│                                                                     │
│ [Cancel] [Apply]                                                    │
└─────────────────────────────────────────────────────────────────────┘
```

## Implementation Considerations

### Action Detection
- Hook into form submissions
- Listen for API calls
- Track navigation events
- Monitor email composer

### Context Preservation
- Track which record was being viewed
- Capture relationship context
- Note timing between actions

### Security
- Don't record sensitive data (passwords)
- Respect field-level permissions
- Allow admin control over recording

## Testing Requirements

- [ ] Test action capture accuracy
- [ ] Test recording start/stop
- [ ] Test workflow generation
- [ ] Test parameterization
- [ ] Test generated workflow execution
- [ ] E2E test record → workflow → execute

## Success Metrics

- Recordings created per month
- Workflows generated from recordings
- Time saved per automation
- User adoption of recording feature
