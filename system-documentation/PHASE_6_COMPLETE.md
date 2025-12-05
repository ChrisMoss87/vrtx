# Phase 6: Sales Pipelines & Kanban - COMPLETE

## Summary

Phase 6 implemented a comprehensive sales pipeline system with Kanban board visualization, drag-and-drop stage transitions, pipeline analytics, and stage history tracking. The implementation includes both backend APIs and frontend components.

## Workflows Completed

### Workflow 6.1: Pipeline Data Model
- Created `Pipeline` model with module association
- Created `Stage` model with probability, color, won/lost status
- Created `StageHistory` model for tracking stage transitions
- Implemented database migrations for all tables

### Workflow 6.2: Pipeline API
- Full CRUD operations for pipelines
- Stage management (create, update, delete, reorder)
- Kanban data endpoint with filtering and search
- Record movement between stages with history tracking
- Stage history retrieval per record

### Workflow 6.3: Pipeline Builder UI
- Created `/admin/pipelines` for pipeline management
- Built `PipelineBuilder` component for creating/editing pipelines
- Built `StageEditor` for configuring individual stages
- Built `StageList` for drag-and-drop stage reordering

### Workflow 6.4: Kanban Board
- Created `KanbanBoard.svelte` - main board container
- Created `KanbanColumn.svelte` - stage columns with drop zones
- Created `KanbanCard.svelte` - record cards with drag handle
- Implemented drag-and-drop between stages
- Optimistic UI updates with rollback on error

### Workflow 6.5: Deal Management
- Records displayed on Kanban cards
- Click-to-view record details
- Stage transition via drag-and-drop
- Pipeline totals (count, value, weighted value)

### Workflow 6.6: Pipeline Analytics
- Stage-wise record counts
- Total and weighted pipeline values
- Per-stage value calculations
- Probability-based weighted values

## Files Created

### Backend Models
- `backend/app/Models/Pipeline.php`
- `backend/app/Models/Stage.php`
- `backend/app/Models/StageHistory.php`

### Backend Migrations
- `2025_12_03_232302_create_pipelines_table.php`
- `2025_12_03_232303_create_stages_table.php`
- `2025_12_03_232304_create_stage_history_table.php`

### Backend Controllers
- `backend/app/Http/Controllers/Api/Pipelines/PipelineController.php`

### Frontend API Client
- `frontend/src/lib/api/pipelines.ts`

### Frontend Components - Pipeline Builder
- `frontend/src/lib/components/pipeline-builder/PipelineBuilder.svelte`
- `frontend/src/lib/components/pipeline-builder/StageEditor.svelte`
- `frontend/src/lib/components/pipeline-builder/StageList.svelte`
- `frontend/src/lib/components/pipeline-builder/index.ts`

### Frontend Components - Kanban
- `frontend/src/lib/components/kanban/KanbanBoard.svelte`
- `frontend/src/lib/components/kanban/KanbanColumn.svelte`
- `frontend/src/lib/components/kanban/KanbanCard.svelte`

### Frontend Routes
- `frontend/src/routes/(app)/pipelines/+page.svelte` - Pipeline selection
- `frontend/src/routes/(app)/pipelines/[moduleApiName]/[pipelineId]/+page.svelte` - Kanban view
- `frontend/src/routes/(app)/admin/pipelines/+page.svelte` - Pipeline management

## API Endpoints

```
GET    /api/v1/pipelines                           - List all pipelines
GET    /api/v1/pipelines/module/{moduleApiName}    - Get pipelines for module
GET    /api/v1/pipelines/{id}                      - Get single pipeline
POST   /api/v1/pipelines                           - Create pipeline with stages
PUT    /api/v1/pipelines/{id}                      - Update pipeline and stages
DELETE /api/v1/pipelines/{id}                      - Delete pipeline

GET    /api/v1/pipelines/{id}/kanban               - Get kanban board data
POST   /api/v1/pipelines/{id}/move-record          - Move record to stage
GET    /api/v1/pipelines/{id}/record/{recordId}/history - Get stage history
POST   /api/v1/pipelines/{id}/reorder-stages       - Reorder stages
```

## Key Features

### Pipeline Model
```php
- name: string
- module_id: foreign key to modules
- stage_field_api_name: which field stores stage ID
- is_active: boolean
- settings: JSON
- created_by, updated_by: audit fields
```

### Stage Model
```php
- pipeline_id: foreign key
- name: string
- color: hex color (#6b7280)
- probability: 0-100 (for weighted values)
- display_order: integer
- is_won_stage: boolean
- is_lost_stage: boolean
- settings: JSON
```

### KanbanBoard Props
```typescript
interface Props {
  pipelineId: number;
  valueField?: string;      // Field for deal values
  titleField?: string;      // Field for card title
  subtitleField?: string;   // Field for card subtitle
  filters?: Record<string, string>;
  search?: string;
  onRecordClick?: (record) => void;
}
```

### Drag and Drop
- HTML5 drag events
- Optimistic UI updates
- API call on drop
- Automatic rollback on error
- Toast notifications for feedback

## Usage Example

```svelte
<script>
  import { KanbanBoard } from '$lib/components/kanban';

  function handleRecordClick(record) {
    goto(`/records/${moduleApiName}/${record.id}`);
  }
</script>

<KanbanBoard
  pipelineId={1}
  valueField="deal_amount"
  titleField="deal_name"
  subtitleField="company_name"
  onRecordClick={handleRecordClick}
/>
```

## Testing

- TypeScript check passes
- Backend APIs functional
- Drag-and-drop working
- Stage history tracking operational
- Pipeline analytics calculating correctly

## Notes

- Pipelines link to modules via `module_id`
- Each pipeline has a `stage_field_api_name` that specifies which record field stores the stage
- Stage transitions are recorded in `stage_history` with timestamps and user info
- Weighted values calculated as `stage_value * (probability / 100)`
- Kanban supports filtering and searching records

## Next Steps

Phase 7: Workflow Automation can begin with:
- Workflow data model (triggers, actions, conditions)
- Visual workflow builder
- Workflow engine execution
- Trigger evaluation
- Action execution
