import { apiClient } from './client';

export interface Proposal {
  id: number;
  uuid: string;
  name: string;
  proposal_number: string | null;
  template_id: number | null;
  deal_id: number | null;
  contact_id: number | null;
  company_id: number | null;
  status: 'draft' | 'sent' | 'viewed' | 'accepted' | 'rejected' | 'expired';
  cover_page: Record<string, any> | null;
  styling: ProposalStyling | null;
  total_value: number | null;
  currency: string;
  valid_until: string | null;
  sent_at: string | null;
  sent_to_email: string | null;
  first_viewed_at: string | null;
  last_viewed_at: string | null;
  view_count: number;
  total_time_spent: number;
  accepted_at: string | null;
  accepted_by: string | null;
  accepted_signature: string | null;
  accepted_ip: string | null;
  rejected_at: string | null;
  rejected_by: string | null;
  rejection_reason: string | null;
  created_by: number | null;
  assigned_to: number | null;
  version: number;
  created_at: string;
  updated_at: string;
  sections?: ProposalSection[];
  pricing_items?: ProposalPricingItem[];
  template?: ProposalTemplate;
}

export interface ProposalStyling {
  primary_color?: string;
  secondary_color?: string;
  font_family?: string;
  logo_url?: string;
}

export interface ProposalSection {
  id: number;
  proposal_id: number;
  section_type: 'cover' | 'executive_summary' | 'scope' | 'pricing' | 'timeline' | 'terms' | 'team' | 'case_study' | 'custom';
  title: string;
  content: string | null;
  settings: Record<string, any> | null;
  display_order: number;
  is_visible: boolean;
  is_locked: boolean;
  created_at: string;
  updated_at: string;
}

export interface ProposalPricingItem {
  id: number;
  proposal_id: number;
  section_id: number | null;
  name: string;
  description: string | null;
  quantity: number;
  unit: string | null;
  unit_price: number;
  discount_percent: number;
  line_total: number;
  is_optional: boolean;
  is_selected: boolean;
  pricing_type: 'fixed' | 'recurring' | 'usage';
  billing_frequency: string | null;
  display_order: number;
  product_id: number | null;
}

export interface ProposalTemplate {
  id: number;
  name: string;
  description: string | null;
  category: string | null;
  default_sections: DefaultSection[] | null;
  styling: ProposalStyling | null;
  cover_image_url: string | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
}

export interface DefaultSection {
  type: string;
  title: string;
  content?: string;
  settings?: Record<string, any>;
}

export interface ProposalContentBlock {
  id: number;
  name: string;
  category: string | null;
  block_type: 'text' | 'image' | 'pricing' | 'team' | 'testimonial';
  content: string;
  settings: Record<string, any> | null;
  thumbnail_url: string | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
}

export interface ProposalComment {
  id: number;
  proposal_id: number;
  section_id: number | null;
  comment: string;
  author_email: string;
  author_name: string | null;
  author_type: 'client' | 'internal';
  reply_to_id: number | null;
  is_resolved: boolean;
  resolved_by: number | null;
  resolved_at: string | null;
  created_at: string;
  replies?: ProposalComment[];
}

export interface ProposalView {
  id: number;
  proposal_id: number;
  viewer_email: string | null;
  viewer_name: string | null;
  session_id: string;
  started_at: string;
  ended_at: string | null;
  time_spent: number;
  sections_viewed: Record<number, number> | null;
  ip_address: string | null;
  user_agent: string | null;
  device_type: string | null;
}

export interface ProposalAnalytics {
  total_views: number;
  unique_viewers: number;
  total_time_spent: number;
  average_time_per_view: number;
  first_viewed_at: string | null;
  last_viewed_at: string | null;
  section_engagement: Record<number, number>;
  device_breakdown: Record<string, number>;
  view_history: {
    viewer_email: string | null;
    viewer_name: string | null;
    started_at: string;
    time_spent: number;
    device_type: string | null;
  }[];
}

export interface CreateProposalData {
  name: string;
  template_id?: number;
  deal_id?: number;
  contact_id?: number;
  company_id?: number;
  cover_page?: Record<string, any>;
  styling?: ProposalStyling;
  currency?: string;
  valid_until?: string;
  assigned_to?: number;
  sections?: {
    section_type: string;
    title: string;
    content?: string;
    settings?: Record<string, any>;
    display_order?: number;
  }[];
  pricing_items?: {
    name: string;
    description?: string;
    quantity: number;
    unit?: string;
    unit_price: number;
    discount_percent?: number;
    is_optional?: boolean;
    pricing_type?: string;
    billing_frequency?: string;
    product_id?: number;
  }[];
}

// Proposals API
export const proposalsApi = {
  list: (params?: { status?: string; deal_id?: number; search?: string }) =>
    apiClient.get<{ data: Proposal[] }>('/proposals', { params }),

  get: (id: number) =>
    apiClient.get<Proposal>(`/proposals/${id}`),

  create: (data: CreateProposalData) =>
    apiClient.post<Proposal>('/proposals', data),

  update: (id: number, data: Partial<CreateProposalData>) =>
    apiClient.put<Proposal>(`/proposals/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/proposals/${id}`),

  duplicate: (id: number) =>
    apiClient.post<Proposal>(`/proposals/${id}/duplicate`),

  send: (id: number, email: string, message?: string) =>
    apiClient.post<{ message: string; public_url: string }>(`/proposals/${id}/send`, { email, message }),

  getAnalytics: (id: number) =>
    apiClient.get<ProposalAnalytics>(`/proposals/${id}/analytics`),

  // Sections
  addSection: (proposalId: number, data: { section_type: string; title: string; content?: string; settings?: Record<string, any>; display_order?: number }) =>
    apiClient.post<ProposalSection>(`/proposals/${proposalId}/sections`, data),

  updateSection: (sectionId: number, data: Partial<{ title: string; content: string; settings: Record<string, any>; is_visible: boolean; is_locked: boolean }>) =>
    apiClient.put<ProposalSection>(`/proposals/sections/${sectionId}`, data),

  deleteSection: (sectionId: number) =>
    apiClient.delete(`/proposals/sections/${sectionId}`),

  reorderSections: (proposalId: number, order: number[]) =>
    apiClient.post<{ message: string }>(`/proposals/${proposalId}/sections/reorder`, { order }),

  // Pricing Items
  addPricingItem: (proposalId: number, data: { name: string; description?: string; quantity: number; unit?: string; unit_price: number; discount_percent?: number; is_optional?: boolean; pricing_type?: string; billing_frequency?: string; product_id?: number; section_id?: number }) =>
    apiClient.post<ProposalPricingItem>(`/proposals/${proposalId}/pricing-items`, data),

  updatePricingItem: (itemId: number, data: Partial<{ name: string; description: string; quantity: number; unit: string; unit_price: number; discount_percent: number; is_optional: boolean; is_selected: boolean }>) =>
    apiClient.put<ProposalPricingItem>(`/proposals/pricing-items/${itemId}`, data),

  deletePricingItem: (itemId: number) =>
    apiClient.delete(`/proposals/pricing-items/${itemId}`),

  // Comments
  getComments: (proposalId: number) =>
    apiClient.get<ProposalComment[]>(`/proposals/${proposalId}/comments`),

  addComment: (proposalId: number, data: { section_id?: number; comment: string; author_email: string; author_name?: string; author_type?: string; reply_to_id?: number }) =>
    apiClient.post<ProposalComment>(`/proposals/${proposalId}/comments`, data),

  resolveComment: (commentId: number) =>
    apiClient.post<{ message: string }>(`/proposals/comments/${commentId}/resolve`),
};

// Proposal Templates API
export const proposalTemplatesApi = {
  list: (params?: { category?: string }) =>
    apiClient.get<ProposalTemplate[]>('/proposals/templates', { params }),

  get: (id: number) =>
    apiClient.get<ProposalTemplate>(`/proposals/templates/${id}`),

  create: (data: { name: string; description?: string; category?: string; default_sections?: DefaultSection[]; styling?: ProposalStyling; cover_image_url?: string }) =>
    apiClient.post<ProposalTemplate>('/proposals/templates', data),

  update: (id: number, data: Partial<{ name: string; description: string; category: string; default_sections: DefaultSection[]; styling: ProposalStyling; cover_image_url: string; is_active: boolean }>) =>
    apiClient.put<ProposalTemplate>(`/proposals/templates/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/proposals/templates/${id}`),
};

// Content Blocks API
export const contentBlocksApi = {
  list: (category?: string) =>
    apiClient.get<ProposalContentBlock[]>('/proposals/content-blocks', { params: { category } }),

  create: (data: { name: string; category?: string; block_type: string; content: string; settings?: Record<string, any>; thumbnail_url?: string }) =>
    apiClient.post<ProposalContentBlock>('/proposals/content-blocks', data),

  update: (id: number, data: Partial<{ name: string; category: string; block_type: string; content: string; settings: Record<string, any>; thumbnail_url: string; is_active: boolean }>) =>
    apiClient.put<ProposalContentBlock>(`/proposals/content-blocks/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/proposals/content-blocks/${id}`),
};

// Public Proposal API (for recipients viewing proposals)
export const publicProposalApi = {
  get: (uuid: string) =>
    apiClient.get<{ proposal: Proposal; can_accept: boolean } | { message: string; expired?: boolean; status?: string; valid_until?: string; accepted_at?: string }>(`/proposal/${uuid}`),

  trackView: (uuid: string, email?: string, name?: string) =>
    apiClient.post<{ session_id: string }>(`/proposal/${uuid}/track-view`, { email, name }),

  updateViewSession: (uuid: string, sessionId: string, sectionsViewed?: Record<number, number>, ended?: boolean) =>
    apiClient.post<{ message: string }>(`/proposal/${uuid}/update-session`, { session_id: sessionId, sections_viewed: sectionsViewed, ended }),

  toggleItem: (uuid: string, itemId: number) =>
    apiClient.post<{ is_selected: boolean; total_value: number }>(`/proposal/${uuid}/items/${itemId}/toggle`),

  accept: (uuid: string, acceptedBy: string, signature?: string) =>
    apiClient.post<{ message: string; accepted_at: string }>(`/proposal/${uuid}/accept`, { accepted_by: acceptedBy, signature }),

  reject: (uuid: string, rejectedBy: string, reason?: string) =>
    apiClient.post<{ message: string }>(`/proposal/${uuid}/reject`, { rejected_by: rejectedBy, reason }),

  getComments: (uuid: string) =>
    apiClient.get<ProposalComment[]>(`/proposal/${uuid}/comments`),

  addComment: (uuid: string, data: { section_id?: number; comment: string; author_email: string; author_name?: string }) =>
    apiClient.post<ProposalComment>(`/proposal/${uuid}/comments`, data),
};
