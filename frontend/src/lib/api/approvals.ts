import { apiClient } from './client';

export interface ApprovalRule {
  id: number;
  name: string;
  description: string | null;
  entity_type: 'quote' | 'proposal' | 'discount' | 'contract' | 'expense' | 'custom';
  module_id: number | null;
  conditions: ApprovalCondition[] | null;
  approver_chain: ApproverChainItem[];
  approval_type: 'sequential' | 'parallel' | 'any';
  allow_self_approval: boolean;
  require_comments: boolean;
  sla_hours: number | null;
  escalation_rules: EscalationRules | null;
  notification_settings: Record<string, any> | null;
  is_active: boolean;
  priority: number;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  steps?: ApprovalStep[];
}

export interface ApprovalCondition {
  field: string;
  operator: '=' | '!=' | '>' | '>=' | '<' | '<=' | 'in' | 'not_in';
  value: any;
}

export interface ApproverChainItem {
  type: 'user' | 'role' | 'manager' | 'custom';
  user_id?: number;
  role_id?: number;
}

export interface EscalationRules {
  auto_approve?: boolean;
  escalate_to?: number;
  notify_manager?: boolean;
}

export interface ApprovalRequest {
  id: number;
  uuid: string;
  rule_id: number | null;
  entity_type: string;
  entity_id: number;
  title: string;
  description: string | null;
  details: string | null;
  status: 'pending' | 'in_progress' | 'approved' | 'rejected' | 'cancelled' | 'expired';
  snapshot_data: Record<string, any> | null;
  entity_data: Record<string, any> | null;
  value: number | null;
  currency: string | null;
  submitted_at: string | null;
  completed_at: string | null;
  expires_at: string | null;
  due_date: string | null;
  requested_by: number | null;
  final_approver_id: number | null;
  final_comments: string | null;
  created_at: string;
  updated_at: string;
  rule?: ApprovalRule;
  requester?: { id: number; name: string; email: string };
  requested_by_user?: { id: number; name: string; email: string };
  final_approver?: { id: number; name: string; email: string };
  steps?: ApprovalStep[];
}

export interface ApprovalStep {
  id: number;
  request_id: number;
  approver_id: number | null;
  role_id: number | null;
  approver_type: 'user' | 'role' | 'manager' | 'custom';
  step_order: number;
  status: 'pending' | 'approved' | 'rejected' | 'skipped' | 'delegated';
  comments: string | null;
  comment: string | null;
  notified_at: string | null;
  viewed_at: string | null;
  decided_at: string | null;
  acted_at: string | null;
  due_at: string | null;
  is_current: boolean;
  can_delegate: boolean;
  delegated_to_id: number | null;
  delegated_by_id: number | null;
  approver?: { id: number; name: string; email: string };
  delegated_to?: { id: number; name: string; email: string };
}

export interface ApprovalDelegation {
  id: number;
  delegator_id: number;
  delegate_id: number;
  delegation_type: 'all' | 'specific_rules';
  rule_ids: number[] | null;
  start_date: string;
  end_date: string | null;
  reason: string | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
  delegator?: { id: number; name: string; email: string };
  delegate?: { id: number; name: string; email: string };
}

export interface ApprovalHistory {
  id: number;
  request_id: number;
  step_id: number | null;
  user_id: number | null;
  action: 'submitted' | 'approved' | 'rejected' | 'delegated' | 'escalated' | 'commented' | 'recalled' | 'cancelled' | 'step_approved' | 'step_rejected' | 'step_skipped';
  comments: string | null;
  comment: string | null;
  changes: Record<string, any> | null;
  ip_address: string | null;
  created_at: string;
  user?: { id: number; name: string; email: string };
  step?: ApprovalStep;
}

export interface ApprovalQuickAction {
  id: number;
  user_id: number;
  name: string;
  action_type: 'approve' | 'reject';
  default_comment: string | null;
  is_active: boolean;
  created_at: string;
}

export interface CreateApprovalRuleData {
  name: string;
  description?: string;
  entity_type: string;
  module_id?: number;
  conditions?: ApprovalCondition[];
  approver_chain: ApproverChainItem[];
  approval_type?: string;
  allow_self_approval?: boolean;
  require_comments?: boolean;
  sla_hours?: number;
  escalation_rules?: EscalationRules;
  notification_settings?: Record<string, any>;
  priority?: number;
}

export interface SubmitApprovalData {
  entity_type: string;
  entity_id: number;
  title?: string;
  description?: string;
  value?: number;
  currency?: string;
  data?: Record<string, any>;
}

// Approval Requests API
export const approvalsApi = {
  list: (params?: { status?: string; entity_type?: string; pending_only?: boolean }) =>
    apiClient.get<{ data: ApprovalRequest[] }>('/approvals', { params }),

  getPending: () =>
    apiClient.get<ApprovalRequest[]>('/approvals/pending'),

  getMyRequests: () =>
    apiClient.get<ApprovalRequest[]>('/approvals/my-requests'),

  get: (id: number) =>
    apiClient.get<ApprovalRequest>(`/approvals/${id}`),

  submit: (data: SubmitApprovalData) =>
    apiClient.post<{ message: string; requires_approval: boolean; request?: ApprovalRequest }>('/approvals/submit', data),

  approve: (id: number, comments?: string) =>
    apiClient.post<{ message: string; status: string }>(`/approvals/${id}/approve`, { comments }),

  reject: (id: number, comments?: string) =>
    apiClient.post<{ message: string; status: string }>(`/approvals/${id}/reject`, { comments }),

  cancel: (id: number, reason?: string) =>
    apiClient.post<{ message: string }>(`/approvals/${id}/cancel`, { reason }),

  getHistory: (id: number) =>
    apiClient.get<ApprovalHistory[]>(`/approvals/${id}/history`),

  checkNeedsApproval: (entityType: string, data: Record<string, any>) =>
    apiClient.post<{ needs_approval: boolean; rule: { id: number; name: string; approval_type: string; sla_hours: number | null } | null }>('/approvals/check', { entity_type: entityType, data }),
};

// Approval Rules API
export const approvalRulesApi = {
  list: (params?: { entity_type?: string; active_only?: boolean }) =>
    apiClient.get<ApprovalRule[]>('/approvals/rules', { params }),

  get: (id: number) =>
    apiClient.get<ApprovalRule>(`/approvals/rules/${id}`),

  create: (data: CreateApprovalRuleData) =>
    apiClient.post<ApprovalRule>('/approvals/rules', data),

  update: (id: number, data: Partial<CreateApprovalRuleData & { is_active: boolean }>) =>
    apiClient.put<ApprovalRule>(`/approvals/rules/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/approvals/rules/${id}`),
};

// Approval Delegations API
export const approvalDelegationsApi = {
  list: () =>
    apiClient.get<ApprovalDelegation[]>('/approvals/delegations'),

  getDelegatedToMe: () =>
    apiClient.get<ApprovalDelegation[]>('/approvals/delegations/to-me'),

  create: (data: { delegate_id: number; delegation_type?: string; rule_ids?: number[]; start_date: string; end_date?: string; reason?: string }) =>
    apiClient.post<ApprovalDelegation>('/approvals/delegations', data),

  delete: (id: number) =>
    apiClient.delete(`/approvals/delegations/${id}`),
};

// Approval Quick Actions API
export const approvalQuickActionsApi = {
  list: () =>
    apiClient.get<ApprovalQuickAction[]>('/approvals/quick-actions'),

  create: (data: { name: string; action_type: string; default_comment?: string }) =>
    apiClient.post<ApprovalQuickAction>('/approvals/quick-actions', data),

  use: (actionId: number, requestId: number) =>
    apiClient.post<{ message: string; status: string }>(`/approvals/quick-actions/${actionId}/use/${requestId}`),

  delete: (id: number) =>
    apiClient.delete(`/approvals/quick-actions/${id}`),
};
