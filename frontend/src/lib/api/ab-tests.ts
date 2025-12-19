import { apiClient } from './client';

// Types
export interface AbTest {
  id: number;
  name: string;
  description: string | null;
  type: AbTestType;
  entity_type: AbTestEntityType;
  entity_id: number;
  status: AbTestStatus;
  goal: AbTestGoal;
  min_sample_size: number;
  confidence_level: number;
  auto_select_winner: boolean;
  winner_variant_id: number | null;
  started_at: string | null;
  ended_at: string | null;
  scheduled_end_at: string | null;
  variants?: AbTestVariant[];
  winner_variant?: AbTestVariant;
  creator?: { id: number; name: string };
  created_at: string;
  updated_at: string;
}

export interface AbTestVariant {
  id: number;
  test_id: number;
  name: string;
  variant_code: string;
  content: Record<string, unknown>;
  traffic_percentage: number;
  is_control: boolean;
  is_active: boolean;
  is_winner: boolean;
  created_at: string;
  updated_at: string;
}

export interface VariantStatistics {
  id: number;
  name: string;
  variant_code: string;
  is_control: boolean;
  is_winner: boolean;
  traffic_percentage: number;
  impressions: number;
  conversions: number;
  clicks: number;
  opens: number;
  conversion_rate: number;
  click_rate: number;
  open_rate: number;
}

export interface TestStatistics {
  variants: VariantStatistics[];
  significance: {
    is_significant: boolean;
    recommended_winner: number | null;
    improvement: number;
  };
  has_winner: boolean;
  recommended_winner: number | null;
}

export type AbTestType = 'email_subject' | 'email_content' | 'cta_button' | 'send_time' | 'form_layout';
export type AbTestEntityType = 'email_template' | 'campaign' | 'web_form';
export type AbTestStatus = 'draft' | 'running' | 'paused' | 'completed';
export type AbTestGoal = 'conversion' | 'click_rate' | 'open_rate';

interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

interface PaginatedResponse<T> {
  success: boolean;
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// A/B Test API
export const abTestApi = {
  list: async (params?: {
    status?: AbTestStatus;
    type?: AbTestType;
    entity_type?: AbTestEntityType;
    search?: string;
    sort_field?: string;
    sort_order?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<AbTest>> => {
    const response = await apiClient.get<PaginatedResponse<AbTest>>('/ab-tests', { params });
    return response;
  },

  get: async (id: number): Promise<AbTest> => {
    const response = await apiClient.get<ApiResponse<AbTest>>(`/ab-tests/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    description?: string;
    type: AbTestType;
    entity_type: AbTestEntityType;
    entity_id: number;
    goal?: AbTestGoal;
    min_sample_size?: number;
    confidence_level?: number;
    auto_select_winner?: boolean;
    scheduled_end_at?: string;
    control_content?: Record<string, unknown>;
  }): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>('/ab-tests', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    description: string;
    goal: AbTestGoal;
    min_sample_size: number;
    confidence_level: number;
    auto_select_winner: boolean;
    scheduled_end_at: string;
  }>): Promise<AbTest> => {
    const response = await apiClient.put<ApiResponse<AbTest>>(`/ab-tests/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/ab-tests/${id}`);
  },

  start: async (id: number): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>(`/ab-tests/${id}/start`);
    return response.data;
  },

  pause: async (id: number): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>(`/ab-tests/${id}/pause`);
    return response.data;
  },

  resume: async (id: number): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>(`/ab-tests/${id}/resume`);
    return response.data;
  },

  complete: async (id: number): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>(`/ab-tests/${id}/complete`);
    return response.data;
  },

  statistics: async (id: number): Promise<TestStatistics> => {
    const response = await apiClient.get<ApiResponse<TestStatistics>>(`/ab-tests/${id}/statistics`);
    return response.data;
  },

  getTypes: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/ab-tests/types');
    return response.data;
  },

  getEntityTypes: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/ab-tests/entity-types');
    return response.data;
  },

  getStatuses: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/ab-tests/statuses');
    return response.data;
  },

  getGoals: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/ab-tests/goals');
    return response.data;
  },
};

// Variant API
export const abTestVariantApi = {
  list: async (testId: number): Promise<VariantStatistics[]> => {
    const response = await apiClient.get<ApiResponse<VariantStatistics[]>>(`/ab-tests/${testId}/variants`);
    return response.data;
  },

  create: async (testId: number, data: {
    name?: string;
    content?: Record<string, unknown>;
    traffic_percentage?: number;
  }): Promise<AbTestVariant> => {
    const response = await apiClient.post<ApiResponse<AbTestVariant>>(`/ab-tests/${testId}/variants`, data);
    return response.data;
  },

  update: async (testId: number, variantId: number, data: Partial<{
    name: string;
    content: Record<string, unknown>;
    traffic_percentage: number;
    is_active: boolean;
  }>): Promise<AbTestVariant> => {
    const response = await apiClient.put<ApiResponse<AbTestVariant>>(`/ab-tests/${testId}/variants/${variantId}`, data);
    return response.data;
  },

  delete: async (testId: number, variantId: number): Promise<void> => {
    await apiClient.delete(`/ab-tests/${testId}/variants/${variantId}`);
  },

  declareWinner: async (testId: number, variantId: number): Promise<AbTest> => {
    const response = await apiClient.post<ApiResponse<AbTest>>(`/ab-tests/${testId}/variants/${variantId}/declare-winner`);
    return response.data;
  },
};

// Helper functions
export function getStatusColor(status: AbTestStatus): string {
  switch (status) {
    case 'draft':
      return 'text-gray-600 bg-gray-100';
    case 'running':
      return 'text-green-600 bg-green-100';
    case 'paused':
      return 'text-yellow-600 bg-yellow-100';
    case 'completed':
      return 'text-blue-600 bg-blue-100';
    default:
      return 'text-gray-600 bg-gray-100';
  }
}

export function getTestTypeLabel(type: AbTestType): string {
  const labels: Record<AbTestType, string> = {
    'email_subject': 'Email Subject',
    'email_content': 'Email Content',
    'cta_button': 'CTA Button',
    'send_time': 'Send Time',
    'form_layout': 'Form Layout',
  };
  return labels[type] || type;
}

export function getEntityTypeLabel(entityType: AbTestEntityType): string {
  const labels: Record<AbTestEntityType, string> = {
    'email_template': 'Email Template',
    'campaign': 'Campaign',
    'web_form': 'Web Form',
  };
  return labels[entityType] || entityType;
}

export function getGoalLabel(goal: AbTestGoal): string {
  const labels: Record<AbTestGoal, string> = {
    'conversion': 'Conversion Rate',
    'click_rate': 'Click Rate',
    'open_rate': 'Open Rate',
  };
  return labels[goal] || goal;
}
