import { apiClient } from './client';

export interface SignatureRequest {
  id: number;
  uuid: string;
  title: string;
  description: string | null;
  message: string | null;
  document_id: number | null;
  source_type: string | null;
  source_id: number | null;
  file_path: string | null;
  file_url: string | null;
  signed_file_path: string | null;
  signed_file_url: string | null;
  status: 'draft' | 'pending' | 'in_progress' | 'completed' | 'declined' | 'expired' | 'voided';
  sent_at: string | null;
  completed_at: string | null;
  expires_at: string | null;
  voided_at: string | null;
  void_reason: string | null;
  settings: Record<string, any> | null;
  external_provider: string | null;
  external_id: string | null;
  created_by: number | null;
  created_at: string;
  updated_at: string;
  signers?: SignatureSigner[];
  fields?: SignatureField[];
}

export interface SignatureSigner {
  id: number;
  request_id: number;
  email: string;
  name: string;
  role: 'signer' | 'viewer' | 'approver' | 'cc';
  sign_order: number;
  order: number;
  status: 'pending' | 'viewed' | 'signed' | 'declined';
  access_token: string;
  sent_at: string | null;
  viewed_at: string | null;
  signed_at: string | null;
  declined_at: string | null;
  decline_reason: string | null;
  signed_ip: string | null;
  signature_data: Record<string, any> | null;
  contact_id: number | null;
  fields?: SignatureField[];
}

export interface SignatureField {
  id: number;
  request_id: number;
  signer_id: number;
  signer_index?: number;
  field_type: 'signature' | 'initials' | 'date' | 'text' | 'checkbox';
  type: 'signature' | 'initials' | 'date' | 'text' | 'checkbox';
  page_number: number;
  page: number;
  x_position: number;
  x: number;
  y_position: number;
  y: number;
  width: number;
  height: number;
  required: boolean;
  label: string | null;
  value: string | null;
  filled_at: string | null;
}

export interface SignatureAuditLog {
  id: number;
  request_id: number;
  signer_id: number | null;
  event_type: string;
  action: string;
  event_description: string | null;
  actor_name: string | null;
  actor_email: string | null;
  ip_address: string | null;
  user_agent: string | null;
  metadata: Record<string, any> | null;
  created_at: string;
}

export interface SignatureTemplate {
  id: number;
  name: string;
  description: string | null;
  signers: SignerTemplate[] | null;
  fields: FieldTemplate[] | null;
  is_active: boolean;
  created_by: number | null;
  created_at: string;
}

export interface SignerTemplate {
  email?: string;
  name?: string;
  role?: string;
}

export interface FieldTemplate {
  field_type: string;
  page_number: number;
  x_position: number;
  y_position: number;
  width?: number;
  height?: number;
  required?: boolean;
  label?: string;
}

export interface CreateSignatureRequestData {
  title: string;
  description?: string;
  document_id?: number;
  file_path?: string;
  source_type?: string;
  source_id?: number;
  expires_at?: string;
  settings?: Record<string, any>;
  signers: {
    email: string;
    name: string;
    role?: string;
    sign_order?: number;
    contact_id?: number;
  }[];
  fields?: {
    field_type: string;
    signer_order: number;
    page_number?: number;
    x_position: number;
    y_position: number;
    width?: number;
    height?: number;
    required?: boolean;
    label?: string;
  }[];
}

// Signature Requests API
export const signaturesApi = {
  list: (params?: { status?: string; source_type?: string; source_id?: number; search?: string }) =>
    apiClient.get<{ data: SignatureRequest[] }>('/signatures', { params }),

  get: (id: number) =>
    apiClient.get<SignatureRequest>(`/signatures/${id}`),

  create: (data: CreateSignatureRequestData) =>
    apiClient.post<SignatureRequest>('/signatures', data),

  createFromDocument: (documentId: number, data: Omit<CreateSignatureRequestData, 'document_id'>) =>
    apiClient.post<SignatureRequest>(`/signatures/from-document/${documentId}`, data),

  update: (id: number, data: Partial<CreateSignatureRequestData>) =>
    apiClient.put<SignatureRequest>(`/signatures/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/signatures/${id}`),

  send: (id: number) =>
    apiClient.post<{ message: string }>(`/signatures/${id}/send`),

  void: (id: number, reason: string) =>
    apiClient.post<{ message: string }>(`/signatures/${id}/void`, { reason }),

  remind: (id: number) =>
    apiClient.post<{ message: string }>(`/signatures/${id}/remind`),

  getAuditLog: (id: number) =>
    apiClient.get<SignatureAuditLog[]>(`/signatures/${id}/audit-log`),
};

// Signature Templates API
export const signatureTemplatesApi = {
  list: () =>
    apiClient.get<SignatureTemplate[]>('/signatures/templates'),

  get: (id: number) =>
    apiClient.get<SignatureTemplate>(`/signatures/templates/${id}`),

  create: (data: { name: string; description?: string; signers?: SignerTemplate[]; fields?: FieldTemplate[] }) =>
    apiClient.post<SignatureTemplate>('/signatures/templates', data),

  update: (id: number, data: Partial<{ name: string; description?: string; signers?: SignerTemplate[]; fields?: FieldTemplate[]; is_active?: boolean }>) =>
    apiClient.put<SignatureTemplate>(`/signatures/templates/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/signatures/templates/${id}`),
};

// Public Signing API (for signers accessing via token)
export const publicSigningApi = {
  getRequest: (uuid: string, token: string) =>
    apiClient.get<{ request: SignatureRequest; signer: SignatureSigner; can_sign: boolean }>(`/sign/${uuid}?token=${token}`),

  sign: (uuid: string, token: string, fields: Record<number, string>) =>
    apiClient.post<{ message: string; status: string }>(`/sign/${uuid}/sign`, { token, fields }),

  decline: (uuid: string, token: string, reason: string) =>
    apiClient.post<{ message: string }>(`/sign/${uuid}/decline`, { token, reason }),

  downloadDocument: (uuid: string, token: string) =>
    apiClient.get<{ url: string }>(`/sign/${uuid}/download?token=${token}`),
};
