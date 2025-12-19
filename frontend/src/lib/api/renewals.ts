import { apiClient, type PaginatedResponse } from './client';

// Types
export interface ContractLineItem {
  id: number;
  contract_id: number;
  product_id?: number;
  name: string;
  description?: string;
  quantity: number;
  unit_price: number;
  discount_percent: number;
  total: number;
  start_date?: string;
  end_date?: string;
  display_order: number;
  created_at: string;
  updated_at: string;
}

export interface Contract {
  id: number;
  name: string;
  contract_number: string;
  related_module: string;
  related_id: number;
  type: string;
  status: 'draft' | 'pending' | 'active' | 'expired' | 'cancelled';
  value: number;
  currency: string;
  billing_frequency?: string;
  start_date: string;
  end_date: string;
  renewal_date?: string;
  renewal_notice_days: number;
  auto_renew: boolean;
  renewal_status?: string;
  owner_id?: number;
  owner?: { id: number; name: string };
  terms?: string;
  notes?: string;
  custom_fields?: Record<string, unknown>;
  line_items?: ContractLineItem[];
  days_until_expiry?: number;
  is_expiring?: boolean;
  is_expired?: boolean;
  created_at: string;
  updated_at: string;
}

export interface RenewalActivity {
  id: number;
  renewal_id: number;
  type: string;
  subject?: string;
  description?: string;
  user_id?: number;
  user?: { id: number; name: string };
  metadata?: Record<string, unknown>;
  created_at: string;
}

export interface Renewal {
  id: number;
  contract_id: number;
  contract?: Contract;
  status: 'pending' | 'in_progress' | 'won' | 'lost';
  original_value: number;
  renewal_value?: number;
  upsell_value: number;
  renewal_type?: string;
  due_date: string;
  closed_date?: string;
  owner_id?: number;
  owner?: { id: number; name: string };
  new_contract_id?: number;
  new_contract?: Contract;
  loss_reason?: string;
  notes?: string;
  activities?: RenewalActivity[];
  total_value?: number;
  growth_percent?: number;
  created_at: string;
  updated_at: string;
}

export interface RenewalForecast {
  id: number;
  period_start: string;
  period_end: string;
  period_type: string;
  expected_renewals: number;
  at_risk_value: number;
  churned_value: number;
  renewed_value: number;
  expansion_value: number;
  total_contracts: number;
  at_risk_count: number;
  renewed_count: number;
  churned_count: number;
  retention_rate?: number;
  net_retention?: number;
  created_at: string;
  updated_at: string;
}

export interface RenewalPipelineSummary {
  pending: { count: number; value: number };
  in_progress: { count: number; value: number };
  total: { count: number; value: number };
}

export interface HealthScoreHistory {
  id: number;
  customer_health_score_id: number;
  overall_score: number;
  scores_snapshot?: Record<string, number>;
  recorded_at: string;
  created_at: string;
}

export interface CustomerHealthScore {
  id: number;
  related_module: string;
  related_id: number;
  overall_score: number;
  engagement_score: number;
  support_score: number;
  product_usage_score: number;
  payment_score: number;
  relationship_score: number;
  health_status: 'healthy' | 'at_risk' | 'critical';
  score_breakdown?: Record<string, { score: number; weight: number; weighted_score: number }>;
  risk_factors?: Array<{
    factor: string;
    severity: string;
    description: string;
    recommendation: string;
  }>;
  notes?: string;
  history?: HealthScoreHistory[];
  calculated_at: string;
  created_at: string;
  updated_at: string;
}

export interface HealthSummary {
  total_customers: number;
  healthy: number;
  at_risk: number;
  critical: number;
  average_score: number;
}

// API Functions - Contracts
export const contractsApi = {
  list: (params?: {
    status?: string;
    related_module?: string;
    related_id?: number;
    expiring_within?: number;
    expired?: boolean;
    search?: string;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_order?: string;
  }) => apiClient.get<PaginatedResponse<Contract>>('/contracts', { params }),

  get: (id: number) =>
    apiClient.get<{ contract: Contract }>(`/contracts/${id}`),

  create: (data: {
    name: string;
    contract_number?: string;
    related_module: string;
    related_id: number;
    type?: string;
    status?: string;
    value?: number;
    currency?: string;
    billing_frequency?: string;
    start_date: string;
    end_date: string;
    renewal_date?: string;
    renewal_notice_days?: number;
    auto_renew?: boolean;
    owner_id?: number;
    terms?: string;
    notes?: string;
    line_items?: Array<{
      name: string;
      product_id?: number;
      description?: string;
      quantity?: number;
      unit_price?: number;
      discount_percent?: number;
    }>;
  }) => apiClient.post<{ contract: Contract; message: string }>('/contracts', data),

  update: (id: number, data: Partial<Contract> & { line_items?: Array<unknown> }) =>
    apiClient.put<{ contract: Contract; message: string }>(`/contracts/${id}`, data),

  delete: (id: number) =>
    apiClient.delete<{ message: string }>(`/contracts/${id}`),

  forRecord: (module: string, recordId: number) =>
    apiClient.get<{ contracts: Contract[] }>('/contracts/for-record', {
      params: { module, record_id: recordId },
    }),

  getExpiring: (days?: number) =>
    apiClient.get<{ contracts: Contract[] }>('/contracts/expiring', {
      params: { days },
    }),
};

// API Functions - Renewals
export const renewalsApi = {
  list: (params?: {
    status?: string;
    owner_id?: number;
    upcoming_days?: number;
    overdue?: boolean;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_order?: string;
  }) => apiClient.get<PaginatedResponse<Renewal>>('/renewals', { params }),

  get: (id: number) =>
    apiClient.get<{ renewal: Renewal }>(`/renewals/${id}`),

  create: (data: { contract_id: number; owner_id?: number }) =>
    apiClient.post<{ renewal: Renewal; message: string }>('/renewals', data),

  start: (id: number) =>
    apiClient.post<{ renewal: Renewal; message: string }>(`/renewals/${id}/start`),

  win: (id: number, data: {
    renewal_value?: number;
    upsell_value?: number;
    notes?: string;
    create_new_contract?: boolean;
    new_end_date?: string;
    new_terms?: string;
    line_items?: Array<unknown>;
  }) => apiClient.post<{ renewal: Renewal; message: string }>(`/renewals/${id}/win`, data),

  lose: (id: number, data: { loss_reason: string; notes?: string }) =>
    apiClient.post<{ renewal: Renewal; message: string }>(`/renewals/${id}/lose`, data),

  addActivity: (id: number, data: {
    type: string;
    subject?: string;
    description?: string;
    metadata?: Record<string, unknown>;
  }) => apiClient.post<{ activity: RenewalActivity; message: string }>(`/renewals/${id}/activities`, data),

  getPipeline: () =>
    apiClient.get<RenewalPipelineSummary>('/renewals/pipeline'),

  getForecast: (period?: 'month' | 'quarter' | 'year') =>
    apiClient.get<{ forecast: RenewalForecast }>('/renewals/forecast', {
      params: { period },
    }),

  generate: () =>
    apiClient.post<{ count: number; message: string }>('/renewals/generate'),
};

// API Functions - Health Scores
export const healthScoresApi = {
  list: (params?: {
    status?: string;
    min_score?: number;
    max_score?: number;
    related_module?: string;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_order?: string;
  }) => apiClient.get<PaginatedResponse<CustomerHealthScore>>('/health-scores', { params }),

  get: (module: string, recordId: number) =>
    apiClient.get<{ health_score: CustomerHealthScore }>(`/health-scores/${module}/${recordId}`),

  calculate: (module: string, recordId: number) =>
    apiClient.post<{ health_score: CustomerHealthScore; message: string }>('/health-scores/calculate', {
      module,
      record_id: recordId,
    }),

  getSummary: () =>
    apiClient.get<HealthSummary>('/health-scores/summary'),

  getAtRisk: () =>
    apiClient.get<{ customers: CustomerHealthScore[] }>('/health-scores/at-risk'),

  recalculateAll: () =>
    apiClient.post<{ count: number; message: string }>('/health-scores/recalculate-all'),

  updateNotes: (id: number, notes: string) =>
    apiClient.put<{ health_score: CustomerHealthScore; message: string }>(`/health-scores/${id}/notes`, { notes }),
};
