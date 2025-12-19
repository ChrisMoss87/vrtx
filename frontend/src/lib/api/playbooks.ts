import { apiClient, type PaginatedResponse } from './client';

// Types
export interface PlaybookPhase {
  id: number;
  playbook_id: number;
  name: string;
  description?: string;
  target_days?: number;
  display_order: number;
  tasks?: PlaybookTask[];
  created_at: string;
  updated_at: string;
}

export interface PlaybookTask {
  id: number;
  playbook_id: number;
  phase_id?: number;
  phase?: PlaybookPhase;
  title: string;
  description?: string;
  task_type: 'manual' | 'automated' | 'milestone';
  task_config?: Record<string, unknown>;
  due_days?: number;
  duration_estimate?: number;
  is_required: boolean;
  is_milestone: boolean;
  assignee_type: 'owner' | 'specific_user' | 'role';
  assignee_id?: number;
  assignee_role?: string;
  dependencies?: number[];
  checklist?: string[];
  resources?: Array<{ title: string; url: string; type?: string }>;
  display_order: number;
  created_at: string;
  updated_at: string;
}

export interface Playbook {
  id: number;
  name: string;
  slug: string;
  description?: string;
  trigger_module?: string;
  trigger_condition?: string;
  trigger_config?: Record<string, unknown>;
  estimated_days?: number;
  is_active: boolean;
  auto_assign: boolean;
  default_owner_id?: number;
  default_owner?: { id: number; name: string };
  tags?: string[];
  display_order: number;
  created_by?: number;
  creator?: { id: number; name: string };
  phases?: PlaybookPhase[];
  tasks?: PlaybookTask[];
  tasks_count?: number;
  instances_count?: number;
  created_at: string;
  updated_at: string;
}

export interface PlaybookGoal {
  id: number;
  playbook_id: number;
  name: string;
  metric_type: 'field_value' | 'task_completion' | 'time_to_complete';
  target_module?: string;
  target_field?: string;
  comparison_operator: '>=' | '<=' | '=' | '>' | '<';
  target_value?: number;
  target_days?: number;
  description?: string;
  created_at: string;
  updated_at: string;
}

export interface PlaybookTaskInstance {
  id: number;
  instance_id: number;
  task_id: number;
  task?: PlaybookTask;
  instance?: PlaybookInstance;
  status: 'pending' | 'in_progress' | 'completed' | 'skipped' | 'blocked';
  due_at?: string;
  started_at?: string;
  completed_at?: string;
  assigned_to?: number;
  assignee?: { id: number; name: string };
  completed_by?: number;
  notes?: string;
  checklist_status?: boolean[];
  time_spent?: number;
  created_at: string;
  updated_at: string;
}

export interface PlaybookInstance {
  id: number;
  playbook_id: number;
  playbook?: Playbook;
  related_module: string;
  related_id: number;
  status: 'active' | 'paused' | 'completed' | 'cancelled';
  started_at: string;
  target_completion_at?: string;
  completed_at?: string;
  paused_at?: string;
  owner_id?: number;
  owner?: { id: number; name: string };
  progress_percent: number;
  metadata?: Record<string, unknown>;
  task_instances?: PlaybookTaskInstance[];
  created_at: string;
  updated_at: string;
}

export interface PlaybookActivity {
  id: number;
  instance_id: number;
  task_instance_id?: number;
  task_instance?: PlaybookTaskInstance;
  action: string;
  details?: Record<string, unknown>;
  user_id?: number;
  user?: { id: number; name: string };
  created_at: string;
}

export interface PlaybookGoalResult {
  id: number;
  instance_id: number;
  goal_id: number;
  goal?: PlaybookGoal;
  actual_value?: number;
  achieved: boolean;
  achieved_at?: string;
  created_at: string;
  updated_at: string;
}

export interface PlaybookStats {
  total_instances: number;
  active: number;
  completed: number;
  cancelled: number;
  average_completion_days?: number;
  task_count: number;
}

// API Functions - Playbooks
export const playbooksApi = {
  list: (params?: {
    active_only?: boolean;
    module?: string;
    search?: string;
    page?: number;
    per_page?: number;
  }) => apiClient.get<PaginatedResponse<Playbook>>('/playbooks', { params }),

  get: (id: number) =>
    apiClient.get<{ playbook: Playbook; stats: PlaybookStats }>(`/playbooks/${id}`),

  create: (data: {
    name: string;
    description?: string;
    trigger_module?: string;
    trigger_condition?: string;
    trigger_config?: Record<string, unknown>;
    estimated_days?: number;
    is_active?: boolean;
    auto_assign?: boolean;
    default_owner_id?: number;
    tags?: string[];
  }) => apiClient.post<{ playbook: Playbook; message: string }>('/playbooks', data),

  update: (id: number, data: Partial<Playbook>) =>
    apiClient.put<{ playbook: Playbook }>(`/playbooks/${id}`, data),

  delete: (id: number) =>
    apiClient.delete<{ message: string }>(`/playbooks/${id}`),

  duplicate: (id: number) =>
    apiClient.post<{ playbook: Playbook; message: string }>(`/playbooks/${id}/duplicate`),

  // Phases
  addPhase: (playbookId: number, data: { name: string; description?: string; target_days?: number }) =>
    apiClient.post<{ phase: PlaybookPhase; message: string }>(`/playbooks/${playbookId}/phases`, data),

  updatePhase: (playbookId: number, phaseId: number, data: Partial<PlaybookPhase>) =>
    apiClient.put<{ phase: PlaybookPhase }>(`/playbooks/${playbookId}/phases/${phaseId}`, data),

  deletePhase: (playbookId: number, phaseId: number) =>
    apiClient.delete<{ message: string }>(`/playbooks/${playbookId}/phases/${phaseId}`),

  // Tasks
  addTask: (playbookId: number, data: {
    phase_id?: number;
    title: string;
    description?: string;
    task_type?: string;
    task_config?: Record<string, unknown>;
    due_days?: number;
    duration_estimate?: number;
    is_required?: boolean;
    is_milestone?: boolean;
    assignee_type?: string;
    assignee_id?: number;
    assignee_role?: string;
    dependencies?: number[];
    checklist?: string[];
    resources?: Array<{ title: string; url: string; type?: string }>;
  }) => apiClient.post<{ task: PlaybookTask; message: string }>(`/playbooks/${playbookId}/tasks`, data),

  updateTask: (playbookId: number, taskId: number, data: Partial<PlaybookTask>) =>
    apiClient.put<{ task: PlaybookTask }>(`/playbooks/${playbookId}/tasks/${taskId}`, data),

  deleteTask: (playbookId: number, taskId: number) =>
    apiClient.delete<{ message: string }>(`/playbooks/${playbookId}/tasks/${taskId}`),

  reorderTasks: (playbookId: number, tasks: Array<{ id: number; phase_id?: number; display_order: number }>) =>
    apiClient.post<{ message: string }>(`/playbooks/${playbookId}/tasks/reorder`, { tasks }),
};

// API Functions - Playbook Instances
export const playbookInstancesApi = {
  list: (params?: {
    playbook_id?: number;
    status?: string;
    owner_id?: number;
    related_module?: string;
    related_id?: number;
    page?: number;
    per_page?: number;
  }) => apiClient.get<PaginatedResponse<PlaybookInstance>>('/playbook-instances', { params }),

  get: (id: number) =>
    apiClient.get<{ instance: PlaybookInstance }>(`/playbook-instances/${id}`),

  start: (data: {
    playbook_id: number;
    related_module: string;
    related_id: number;
    owner_id?: number;
  }) => apiClient.post<{ instance: PlaybookInstance; message: string }>('/playbook-instances/start', data),

  pause: (id: number, reason?: string) =>
    apiClient.post<{ instance: PlaybookInstance; message: string }>(`/playbook-instances/${id}/pause`, { reason }),

  resume: (id: number) =>
    apiClient.post<{ instance: PlaybookInstance; message: string }>(`/playbook-instances/${id}/resume`),

  cancel: (id: number, reason?: string) =>
    apiClient.post<{ instance: PlaybookInstance; message: string }>(`/playbook-instances/${id}/cancel`, { reason }),

  tasks: (id: number) =>
    apiClient.get<{ tasks: Record<string, PlaybookTaskInstance[]> }>(`/playbook-instances/${id}/tasks`),

  activities: (id: number) =>
    apiClient.get<PaginatedResponse<PlaybookActivity>>(`/playbook-instances/${id}/activities`),

  // Task operations
  startTask: (instanceId: number, taskInstanceId: number) =>
    apiClient.post<{ task: PlaybookTaskInstance; message: string }>(
      `/playbook-instances/${instanceId}/tasks/${taskInstanceId}/start`
    ),

  completeTask: (instanceId: number, taskInstanceId: number, data?: { notes?: string; time_spent?: number }) =>
    apiClient.post<{ task: PlaybookTaskInstance; instance: PlaybookInstance; message: string }>(
      `/playbook-instances/${instanceId}/tasks/${taskInstanceId}/complete`,
      data
    ),

  skipTask: (instanceId: number, taskInstanceId: number, reason?: string) =>
    apiClient.post<{ task: PlaybookTaskInstance; instance: PlaybookInstance; message: string }>(
      `/playbook-instances/${instanceId}/tasks/${taskInstanceId}/skip`,
      { reason }
    ),

  reassignTask: (instanceId: number, taskInstanceId: number, userId: number) =>
    apiClient.post<{ task: PlaybookTaskInstance; message: string }>(
      `/playbook-instances/${instanceId}/tasks/${taskInstanceId}/reassign`,
      { user_id: userId }
    ),

  updateTaskChecklist: (instanceId: number, taskInstanceId: number, index: number, completed: boolean) =>
    apiClient.post<{ task: PlaybookTaskInstance; checklist_progress: { total: number; completed: number; percent: number } }>(
      `/playbook-instances/${instanceId}/tasks/${taskInstanceId}/checklist`,
      { index, completed }
    ),

  // Utilities
  forRecord: (module: string, recordId: number) =>
    apiClient.get<{
      active_instances: PlaybookInstance[];
      past_instances: PlaybookInstance[];
      available_playbooks: Playbook[];
    }>('/playbook-instances/for-record', { params: { module, record_id: recordId } }),

  myTasks: (days?: number) =>
    apiClient.get<{
      overdue: PlaybookTaskInstance[];
      in_progress: PlaybookTaskInstance[];
      upcoming: PlaybookTaskInstance[];
    }>('/playbook-instances/my-tasks', { params: { days } }),
};
