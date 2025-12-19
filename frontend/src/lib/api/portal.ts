import { apiClient, type PaginatedResponse } from './client';

// Generic API response type
type ApiResponse<T> = T;

// Types
export interface PortalUser {
  id: number;
  email: string;
  name: string;
  phone?: string;
  avatar_url?: string;
  role: 'admin' | 'member' | 'viewer';
  contact_id?: number;
  account_id?: number;
  preferences: Record<string, unknown>;
  email_verified_at?: string;
  last_login_at?: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface PortalInvitation {
  id: number;
  email: string;
  token: string;
  contact_id?: number;
  account_id?: number;
  role: string;
  invited_by: number;
  expires_at: string;
  accepted_at?: string;
  created_at: string;
  inviter?: {
    id: number;
    name: string;
  };
}

export interface PortalAnnouncement {
  id: number;
  title: string;
  content: string;
  type: 'info' | 'warning' | 'success' | 'error';
  is_active: boolean;
  is_dismissible: boolean;
  starts_at?: string;
  ends_at?: string;
  target_accounts: number[];
  created_by: number;
  created_at: string;
  creator?: {
    id: number;
    name: string;
  };
}

export interface PortalDocumentShare {
  id: number;
  portal_user_id?: number;
  account_id?: number;
  document_type: string;
  document_id: number;
  can_download: boolean;
  requires_signature: boolean;
  signed_at?: string;
  signature_ip?: string;
  view_count: number;
  first_viewed_at?: string;
  last_viewed_at?: string;
  expires_at?: string;
  shared_by: number;
  created_at: string;
}

export interface PortalActivityLog {
  id: number;
  portal_user_id: number;
  action: string;
  resource_type?: string;
  resource_id?: number;
  metadata: Record<string, unknown>;
  ip_address?: string;
  user_agent?: string;
  created_at: string;
}

export interface PortalNotification {
  id: number;
  portal_user_id: number;
  type: string;
  title: string;
  message: string;
  action_url?: string;
  data: Record<string, unknown>;
  read_at?: string;
  created_at: string;
}

export interface PortalDashboardStats {
  deals: {
    total: number;
    open: number;
    total_value: number;
  };
  invoices: {
    total: number;
    pending: number;
    overdue: number;
    pending_amount: number;
    overdue_amount: number;
  };
  quotes: {
    total: number;
    pending: number;
  };
  documents: {
    total: number;
    requiring_signature: number;
  };
}

export interface PortalDeal {
  id: number;
  name: string;
  stage?: string;
  amount: number;
  expected_close_date?: string;
  description?: string;
  owner?: string;
  created_at: string;
  updated_at: string;
}

export interface PortalInvoice {
  id: number;
  invoice_number: string;
  status: string;
  subtotal: number;
  discount?: number;
  tax: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  issue_date: string;
  due_date: string;
  notes?: string;
  terms?: string;
  line_items?: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
  }>;
  created_at: string;
}

export interface PortalQuote {
  id: number;
  quote_number: string;
  status: string;
  subtotal: number;
  discount?: number;
  tax: number;
  total: number;
  valid_until: string;
  notes?: string;
  terms?: string;
  line_items?: Array<{
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
  }>;
  created_at: string;
}

export interface PortalActivityAnalytics {
  logins_by_day: Array<{ date: string; count: number }>;
  top_actions: Array<{ action: string; count: number }>;
  active_users: number;
  total_users: number;
  engagement_rate: number;
}

// Admin API functions
export const portalAdminApi = {
  // Users
  getUsers: (params?: {
    search?: string;
    account_id?: number;
    is_active?: boolean;
    page?: number;
  }): Promise<ApiResponse<PaginatedResponse<PortalUser>>> =>
    apiClient.get('/portal-admin/users', { params }),

  getUser: (id: number): Promise<ApiResponse<{ user: PortalUser }>> =>
    apiClient.get(`/portal-admin/users/${id}`),

  updateUser: (
    id: number,
    data: { name?: string; role?: string; is_active?: boolean }
  ): Promise<ApiResponse<{ user: PortalUser }>> =>
    apiClient.put(`/portal-admin/users/${id}`, data),

  deactivateUser: (id: number): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post(`/portal-admin/users/${id}/deactivate`),

  activateUser: (id: number): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post(`/portal-admin/users/${id}/activate`),

  // Invitations
  getInvitations: (params?: {
    status?: 'pending' | 'accepted' | 'expired';
    page?: number;
  }): Promise<ApiResponse<PaginatedResponse<PortalInvitation>>> =>
    apiClient.get('/portal-admin/invitations', { params }),

  createInvitation: (data: {
    email: string;
    contact_id?: number;
    account_id?: number;
    role: string;
  }): Promise<ApiResponse<{ invitation: PortalInvitation; message: string }>> =>
    apiClient.post('/portal-admin/invitations', data),

  resendInvitation: (
    id: number
  ): Promise<ApiResponse<{ invitation: PortalInvitation; message: string }>> =>
    apiClient.post(`/portal-admin/invitations/${id}/resend`),

  cancelInvitation: (id: number): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post(`/portal-admin/invitations/${id}/cancel`),

  // Announcements
  getAnnouncements: (params?: {
    active_only?: boolean;
    page?: number;
  }): Promise<ApiResponse<PaginatedResponse<PortalAnnouncement>>> =>
    apiClient.get('/portal-admin/announcements', { params }),

  createAnnouncement: (data: {
    title: string;
    content: string;
    type: string;
    is_active?: boolean;
    is_dismissible?: boolean;
    starts_at?: string;
    ends_at?: string;
    target_accounts?: number[];
  }): Promise<ApiResponse<{ announcement: PortalAnnouncement }>> =>
    apiClient.post('/portal-admin/announcements', data),

  updateAnnouncement: (
    id: number,
    data: Partial<{
      title: string;
      content: string;
      type: string;
      is_active: boolean;
      is_dismissible: boolean;
      starts_at: string;
      ends_at: string;
      target_accounts: number[];
    }>
  ): Promise<ApiResponse<{ announcement: PortalAnnouncement }>> =>
    apiClient.put(`/portal-admin/announcements/${id}`, data),

  deleteAnnouncement: (id: number): Promise<ApiResponse<{ message: string }>> =>
    apiClient.delete(`/portal-admin/announcements/${id}`),

  // Document Sharing
  shareDocument: (data: {
    document_type: string;
    document_id: number;
    portal_user_id?: number;
    account_id?: number;
    can_download?: boolean;
    requires_signature?: boolean;
    expires_at?: string;
  }): Promise<ApiResponse<{ share: PortalDocumentShare; message: string }>> =>
    apiClient.post('/portal-admin/share-document', data),

  // Analytics
  getAnalytics: (params?: {
    days?: number;
  }): Promise<ApiResponse<PortalActivityAnalytics>> =>
    apiClient.get('/portal-admin/analytics', { params }),
};

// Portal User API (for portal-side authentication)
// Note: These would use a separate apiClient configured for portal auth
export const portalUserApi = {
  // Auth
  login: (data: {
    email: string;
    password: string;
  }): Promise<
    ApiResponse<{ user: PortalUser; token: string; expires_at: string }>
  > => apiClient.post('/portal/login', data),

  register: (data: {
    token: string;
    name: string;
    password: string;
    password_confirmation: string;
  }): Promise<
    ApiResponse<{ user: PortalUser; token: string; expires_at: string }>
  > => apiClient.post('/portal/register', data),

  verifyInvitation: (
    token: string
  ): Promise<
    ApiResponse<{ valid: boolean; email?: string; role?: string; message?: string }>
  > => apiClient.post('/portal/verify-invitation', { token }),

  logout: (): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post('/portal/logout'),

  me: (): Promise<ApiResponse<{ user: PortalUser }>> =>
    apiClient.get('/portal/me'),

  updateProfile: (data: {
    name?: string;
    phone?: string;
    preferences?: Record<string, unknown>;
  }): Promise<ApiResponse<{ user: PortalUser }>> =>
    apiClient.put('/portal/profile', data),

  changePassword: (data: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post('/portal/change-password', data),

  // Dashboard
  getDashboard: (): Promise<
    ApiResponse<{
      stats: PortalDashboardStats;
      announcements: PortalAnnouncement[];
      recent_activity: PortalActivityLog[];
      unread_notifications_count: number;
    }>
  > => apiClient.get('/portal/dashboard'),

  // Deals
  getDeals: (): Promise<ApiResponse<{ deals: PortalDeal[] }>> =>
    apiClient.get('/portal/deals'),

  getDeal: (id: number): Promise<ApiResponse<{ deal: PortalDeal }>> =>
    apiClient.get(`/portal/deals/${id}`),

  // Invoices
  getInvoices: (): Promise<ApiResponse<{ invoices: PortalInvoice[] }>> =>
    apiClient.get('/portal/invoices'),

  getInvoice: (id: number): Promise<ApiResponse<{ invoice: PortalInvoice }>> =>
    apiClient.get(`/portal/invoices/${id}`),

  // Quotes
  getQuotes: (): Promise<ApiResponse<{ quotes: PortalQuote[] }>> =>
    apiClient.get('/portal/quotes'),

  getQuote: (id: number): Promise<ApiResponse<{ quote: PortalQuote }>> =>
    apiClient.get(`/portal/quotes/${id}`),

  acceptQuote: (
    id: number
  ): Promise<ApiResponse<{ message: string; quote: PortalQuote }>> =>
    apiClient.post(`/portal/quotes/${id}/accept`),

  // Documents
  getDocuments: (): Promise<ApiResponse<{ documents: PortalDocumentShare[] }>> =>
    apiClient.get('/portal/documents'),

  viewDocument: (
    id: number
  ): Promise<ApiResponse<{ document: PortalDocumentShare }>> =>
    apiClient.get(`/portal/documents/${id}`),

  signDocument: (
    id: number
  ): Promise<ApiResponse<{ message: string; document: PortalDocumentShare }>> =>
    apiClient.post(`/portal/documents/${id}/sign`),

  // Notifications
  getNotifications: (): Promise<ApiResponse<PaginatedResponse<PortalNotification>>> =>
    apiClient.get('/portal/notifications'),

  markNotificationRead: (
    id: number
  ): Promise<ApiResponse<{ message: string }>> =>
    apiClient.post(`/portal/notifications/${id}/read`),

  markAllNotificationsRead: (): Promise<
    ApiResponse<{ message: string; count: number }>
  > => apiClient.post('/portal/notifications/read-all'),

  // Announcements
  getAnnouncements: (): Promise<
    ApiResponse<{ announcements: PortalAnnouncement[] }>
  > => apiClient.get('/portal/announcements'),

  // Activity
  getActivity: (
    limit?: number
  ): Promise<ApiResponse<{ activity: PortalActivityLog[] }>> =>
    apiClient.get('/portal/activity', { params: { limit } }),
};
