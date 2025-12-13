import { apiClient, type PaginatedResponse } from './client';

// Types
export interface TicketCategory {
  id: number;
  name: string;
  slug: string;
  description?: string;
  color: string;
  default_assignee_id?: number;
  default_priority: number;
  sla_response_hours?: number;
  sla_resolution_hours?: number;
  is_active: boolean;
  display_order: number;
  tickets_count?: number;
  created_at: string;
  updated_at: string;
}

export interface SupportTicket {
  id: number;
  ticket_number: string;
  subject: string;
  description: string;
  status: 'open' | 'pending' | 'in_progress' | 'resolved' | 'closed';
  priority: 1 | 2 | 3 | 4;
  category_id?: number;
  category?: TicketCategory;
  submitter_id?: number;
  submitter?: { id: number; name: string };
  portal_user_id?: number;
  portal_user?: { id: number; name: string; email: string };
  contact_id?: number;
  account_id?: number;
  assigned_to?: number;
  assignee?: { id: number; name: string };
  team_id?: number;
  team?: { id: number; name: string };
  channel: string;
  tags: string[];
  first_response_at?: string;
  resolved_at?: string;
  closed_at?: string;
  sla_response_due_at?: string;
  sla_resolution_due_at?: string;
  sla_response_breached: boolean;
  sla_resolution_breached: boolean;
  satisfaction_rating?: number;
  satisfaction_feedback?: string;
  custom_fields: Record<string, unknown>;
  replies?: TicketReply[];
  activities?: TicketActivity[];
  created_at: string;
  updated_at: string;
}

export interface TicketReply {
  id: number;
  ticket_id: number;
  content: string;
  user_id?: number;
  user?: { id: number; name: string };
  portal_user_id?: number;
  portal_user?: { id: number; name: string };
  is_internal: boolean;
  is_system: boolean;
  attachments: string[];
  created_at: string;
}

export interface TicketActivity {
  id: number;
  ticket_id: number;
  action: string;
  changes?: Record<string, { old: unknown; new: unknown }>;
  user_id?: number;
  user?: { id: number; name: string };
  portal_user_id?: number;
  note?: string;
  created_at: string;
}

export interface TicketEscalation {
  id: number;
  ticket_id: number;
  type: string;
  level: string;
  escalated_to?: number;
  reason?: string;
  escalated_by?: number;
  acknowledged_at?: string;
  acknowledged_by?: number;
  created_at: string;
}

export interface TicketStats {
  open: number;
  unassigned: number;
  overdue_sla: number;
  resolved_today: number;
  avg_response_time?: number;
  avg_resolution_time?: number;
}

export interface KbCategory {
  id: number;
  name: string;
  slug: string;
  description?: string;
  icon?: string;
  parent_id?: number;
  parent?: KbCategory;
  children?: KbCategory[];
  display_order: number;
  is_public: boolean;
  articles_count?: number;
  published_articles_count?: number;
  created_at: string;
  updated_at: string;
}

export interface KbArticle {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt?: string;
  category_id?: number;
  category?: KbCategory;
  status: 'draft' | 'published' | 'archived';
  author_id: number;
  author?: { id: number; name: string };
  tags: string[];
  view_count: number;
  helpful_count: number;
  not_helpful_count: number;
  is_public: boolean;
  published_at?: string;
  created_at: string;
  updated_at: string;
}

// API Functions - Categories
export const ticketCategoriesApi = {
  list: (params?: { active_only?: boolean }) =>
    apiClient.get<{ categories: TicketCategory[] }>('/support/categories', { params }),

  get: (id: number) =>
    apiClient.get<{ category: TicketCategory }>(`/support/categories/${id}`),

  create: (data: Partial<TicketCategory>) =>
    apiClient.post<{ category: TicketCategory; message: string }>('/support/categories', data),

  update: (id: number, data: Partial<TicketCategory>) =>
    apiClient.put<{ category: TicketCategory }>(`/support/categories/${id}`, data),

  delete: (id: number) =>
    apiClient.delete<{ message: string }>(`/support/categories/${id}`),

  reorder: (categories: Array<{ id: number; display_order: number }>) =>
    apiClient.post<{ message: string }>('/support/categories/reorder', { categories }),
};

// API Functions - Tickets
export const ticketsApi = {
  list: (params?: {
    status?: string;
    priority?: number;
    category_id?: number;
    assigned_to?: string | number;
    team_id?: number;
    search?: string;
    overdue_only?: boolean;
    sort?: string;
    direction?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
  }) => apiClient.get<PaginatedResponse<SupportTicket>>('/support/tickets', { params }),

  get: (id: number) =>
    apiClient.get<{ ticket: SupportTicket }>(`/support/tickets/${id}`),

  create: (data: {
    subject: string;
    description: string;
    priority?: number;
    category_id?: number;
    assigned_to?: number;
    team_id?: number;
    contact_id?: number;
    account_id?: number;
    channel?: string;
    tags?: string[];
    custom_fields?: Record<string, unknown>;
  }) => apiClient.post<{ ticket: SupportTicket; message: string }>('/support/tickets', data),

  update: (id: number, data: Partial<SupportTicket>) =>
    apiClient.put<{ ticket: SupportTicket }>(`/support/tickets/${id}`, data),

  delete: (id: number) =>
    apiClient.delete<{ message: string }>(`/support/tickets/${id}`),

  reply: (id: number, data: { content: string; is_internal?: boolean; attachments?: string[] }) =>
    apiClient.post<{ reply: TicketReply; message: string }>(`/support/tickets/${id}/reply`, data),

  assign: (id: number, assigned_to: number) =>
    apiClient.post<{ ticket: SupportTicket; message: string }>(`/support/tickets/${id}/assign`, { assigned_to }),

  resolve: (id: number, resolution_note?: string) =>
    apiClient.post<{ ticket: SupportTicket; message: string }>(`/support/tickets/${id}/resolve`, { resolution_note }),

  close: (id: number) =>
    apiClient.post<{ ticket: SupportTicket; message: string }>(`/support/tickets/${id}/close`),

  reopen: (id: number) =>
    apiClient.post<{ ticket: SupportTicket; message: string }>(`/support/tickets/${id}/reopen`),

  escalate: (id: number, data: { type: string; level: string; escalated_to: number; reason: string }) =>
    apiClient.post<{ escalation: TicketEscalation; message: string }>(`/support/tickets/${id}/escalate`, data),

  merge: (id: number, secondary_ticket_id: number) =>
    apiClient.post<{ ticket: SupportTicket; message: string }>(`/support/tickets/${id}/merge`, { secondary_ticket_id }),

  stats: (my_stats?: boolean) =>
    apiClient.get<TicketStats>('/support/tickets/stats', { params: { my_stats } }),

  statuses: () => apiClient.get<Record<string, string>>('/support/tickets/statuses'),

  priorities: () => apiClient.get<Record<number, string>>('/support/tickets/priorities'),

  channels: () => apiClient.get<Record<string, string>>('/support/tickets/channels'),
};

// API Functions - Knowledge Base
export const knowledgeBaseApi = {
  // Categories
  categories: (params?: { public_only?: boolean; top_level_only?: boolean }) =>
    apiClient.get<{ categories: KbCategory[] }>('/support/kb/categories', { params }),

  getCategory: (slug: string) =>
    apiClient.get<{ category: KbCategory }>(`/support/kb/categories/${slug}`),

  createCategory: (data: Partial<KbCategory>) =>
    apiClient.post<{ category: KbCategory; message: string }>('/support/kb/categories', data),

  updateCategory: (id: number, data: Partial<KbCategory>) =>
    apiClient.put<{ category: KbCategory }>(`/support/kb/categories/${id}`, data),

  deleteCategory: (id: number) =>
    apiClient.delete<{ message: string }>(`/support/kb/categories/${id}`),

  // Articles
  articles: (params?: {
    status?: string;
    category_id?: number;
    search?: string;
    public_only?: boolean;
    include_drafts?: boolean;
    sort?: string;
    direction?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
  }) => apiClient.get<PaginatedResponse<KbArticle>>('/support/kb/articles', { params }),

  getArticle: (slug: string) =>
    apiClient.get<{ article: KbArticle }>(`/support/kb/articles/${slug}`),

  createArticle: (data: {
    title: string;
    content: string;
    excerpt?: string;
    category_id?: number;
    status?: string;
    tags?: string[];
    is_public?: boolean;
  }) => apiClient.post<{ article: KbArticle; message: string }>('/support/kb/articles', data),

  updateArticle: (id: number, data: Partial<KbArticle>) =>
    apiClient.put<{ article: KbArticle }>(`/support/kb/articles/${id}`, data),

  deleteArticle: (id: number) =>
    apiClient.delete<{ message: string }>(`/support/kb/articles/${id}`),

  publishArticle: (id: number) =>
    apiClient.post<{ article: KbArticle; message: string }>(`/support/kb/articles/${id}/publish`),

  unpublishArticle: (id: number) =>
    apiClient.post<{ article: KbArticle; message: string }>(`/support/kb/articles/${id}/unpublish`),

  articleFeedback: (id: number, data: { is_helpful: boolean; comment?: string }) =>
    apiClient.post<{ message: string }>(`/support/kb/articles/${id}/feedback`, data),

  search: (q: string) =>
    apiClient.get<{ results: KbArticle[] }>('/support/kb/search', { params: { q } }),
};
