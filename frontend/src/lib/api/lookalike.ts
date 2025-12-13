import { apiClient } from './client';

// Types
export interface LookalikeAudience {
  id: number;
  name: string;
  description: string | null;
  source_type: SourceType;
  source_id: number | null;
  source_criteria: Record<string, unknown>;
  match_criteria: Record<string, boolean>;
  weights: Record<string, number>;
  min_similarity_score: number;
  size_limit: number | null;
  status: AudienceStatus;
  last_built_at: string | null;
  build_duration_seconds: number | null;
  source_count: number;
  match_count: number;
  matches_count?: number;
  auto_refresh: boolean;
  refresh_frequency: RefreshFrequency | null;
  next_refresh_at: string | null;
  export_destinations: string[];
  last_exported_at: string | null;
  creator?: { id: number; name: string };
  build_jobs?: BuildJob[];
  created_at: string;
  updated_at: string;
}

export interface LookalikeMatch {
  id: number;
  audience_id: number;
  contact_id: number;
  contact_module: string;
  similarity_score: number;
  match_factors: Record<string, number>;
  enrichment_data: Record<string, unknown>;
  enriched_at: string | null;
  exported: boolean;
  exported_at: string | null;
  export_destination: string | null;
  created_at: string;
}

export interface BuildJob {
  id: number;
  audience_id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  progress: number;
  records_processed: number;
  matches_found: number;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
}

export type SourceType = 'saved_search' | 'manual' | 'segment';
export type AudienceStatus = 'draft' | 'building' | 'ready' | 'expired';
export type RefreshFrequency = 'daily' | 'weekly' | 'monthly';
export type CriteriaType = 'industry' | 'company_size' | 'location' | 'behavior' | 'technology' | 'engagement' | 'purchase';
export type ExportDestination = 'google_ads' | 'facebook' | 'linkedin' | 'csv';

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

// Lookalike Audience API
export const lookalikeApi = {
  list: async (params?: {
    status?: AudienceStatus;
    search?: string;
    sort_field?: string;
    sort_order?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<LookalikeAudience>> => {
    const response = await apiClient.get<PaginatedResponse<LookalikeAudience>>('/lookalike-audiences', { params });
    return response;
  },

  get: async (id: number): Promise<LookalikeAudience> => {
    const response = await apiClient.get<ApiResponse<LookalikeAudience>>(`/lookalike-audiences/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    description?: string;
    source_type: SourceType;
    source_id?: number;
    source_criteria?: Record<string, unknown>;
    match_criteria?: Record<string, boolean>;
    weights?: Record<string, number>;
    min_similarity_score?: number;
    size_limit?: number;
    auto_refresh?: boolean;
    refresh_frequency?: RefreshFrequency;
    export_destinations?: string[];
  }): Promise<LookalikeAudience> => {
    const response = await apiClient.post<ApiResponse<LookalikeAudience>>('/lookalike-audiences', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    description: string;
    match_criteria: Record<string, boolean>;
    weights: Record<string, number>;
    min_similarity_score: number;
    size_limit: number;
    auto_refresh: boolean;
    refresh_frequency: RefreshFrequency;
    export_destinations: string[];
  }>): Promise<LookalikeAudience> => {
    const response = await apiClient.put<ApiResponse<LookalikeAudience>>(`/lookalike-audiences/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/lookalike-audiences/${id}`);
  },

  build: async (id: number): Promise<{ job: BuildJob; audience: LookalikeAudience }> => {
    const response = await apiClient.post<ApiResponse<{ job: BuildJob; audience: LookalikeAudience }>>(`/lookalike-audiences/${id}/build`);
    return response.data;
  },

  matches: async (id: number, params?: {
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<LookalikeMatch>> => {
    const response = await apiClient.get<PaginatedResponse<LookalikeMatch>>(`/lookalike-audiences/${id}/matches`, { params });
    return response;
  },

  export: async (id: number, destination: ExportDestination): Promise<{ records_exported: number; destination: string }> => {
    const response = await apiClient.post<ApiResponse<{ records_exported: number; destination: string }>>(`/lookalike-audiences/${id}/export`, { destination });
    return response.data;
  },

  getSourceTypes: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/lookalike-audiences/source-types');
    return response.data;
  },

  getStatuses: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/lookalike-audiences/statuses');
    return response.data;
  },

  getCriteriaTypes: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/lookalike-audiences/criteria-types');
    return response.data;
  },

  getExportDestinations: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/lookalike-audiences/export-destinations');
    return response.data;
  },
};

// Helper functions
export function getStatusColor(status: AudienceStatus): string {
  switch (status) {
    case 'draft':
      return 'text-gray-600 bg-gray-100';
    case 'building':
      return 'text-blue-600 bg-blue-100';
    case 'ready':
      return 'text-green-600 bg-green-100';
    case 'expired':
      return 'text-yellow-600 bg-yellow-100';
    default:
      return 'text-gray-600 bg-gray-100';
  }
}

export function getSourceTypeLabel(type: SourceType): string {
  const labels: Record<SourceType, string> = {
    'saved_search': 'Saved Search',
    'manual': 'Manual Selection',
    'segment': 'Segment',
  };
  return labels[type] || type;
}

export function getCriteriaLabel(criteria: CriteriaType): string {
  const labels: Record<CriteriaType, string> = {
    'industry': 'Industry',
    'company_size': 'Company Size',
    'location': 'Location',
    'behavior': 'Behavior Patterns',
    'technology': 'Technology Usage',
    'engagement': 'Engagement Level',
    'purchase': 'Purchase History',
  };
  return labels[criteria] || criteria;
}

export function getScoreLabel(score: number): { label: string; class: string } {
  if (score >= 90) return { label: 'Excellent', class: 'text-green-600 bg-green-100' };
  if (score >= 80) return { label: 'Very Good', class: 'text-emerald-600 bg-emerald-100' };
  if (score >= 70) return { label: 'Good', class: 'text-blue-600 bg-blue-100' };
  if (score >= 60) return { label: 'Fair', class: 'text-yellow-600 bg-yellow-100' };
  return { label: 'Low', class: 'text-gray-600 bg-gray-100' };
}
