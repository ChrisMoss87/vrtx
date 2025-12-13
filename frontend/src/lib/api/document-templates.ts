import { apiClient } from './client';

export interface DocumentTemplate {
  id: number;
  name: string;
  category: string | null;
  description: string | null;
  content: string;
  merge_fields: string[];
  conditional_blocks: ConditionalBlock[] | null;
  output_format: 'pdf' | 'docx' | 'html';
  page_settings: PageSettings | null;
  header_settings: HeaderFooterSettings | null;
  footer_settings: HeaderFooterSettings | null;
  thumbnail_url: string | null;
  is_active: boolean;
  is_shared: boolean;
  version: number;
  created_by: number | null;
  created_at: string;
  updated_at: string;
}

export interface PageSettings {
  margin_top?: string;
  margin_right?: string;
  margin_bottom?: string;
  margin_left?: string;
  orientation?: 'portrait' | 'landscape';
  paper_size?: string;
}

export interface HeaderFooterSettings {
  content?: string;
  height?: string;
}

export interface ConditionalBlock {
  placeholder: string;
  condition: {
    field: string;
    operator: string;
    value: any;
  };
  if_content: string;
  else_content: string;
}

export interface GeneratedDocument {
  id: number;
  template_id: number;
  record_type: string;
  record_id: number;
  name: string;
  output_format: string;
  file_path: string | null;
  file_url: string | null;
  file_size: number | null;
  merged_data: Record<string, any> | null;
  status: 'generated' | 'sent' | 'viewed' | 'signed';
  created_by: number | null;
  created_at: string;
  template?: DocumentTemplate;
}

export interface MergeFieldVariable {
  id: number;
  name: string;
  api_name: string;
  category: string;
  field_path: string;
  default_value: string | null;
  format: string | null;
  is_system: boolean;
}

export interface CreateTemplateData {
  name: string;
  category?: string;
  description?: string;
  content: string;
  output_format?: string;
  page_settings?: PageSettings;
  header_settings?: HeaderFooterSettings;
  footer_settings?: HeaderFooterSettings;
  conditional_blocks?: ConditionalBlock[];
  is_shared?: boolean;
}

export interface UpdateTemplateData extends Partial<CreateTemplateData> {
  is_active?: boolean;
}

// Document Templates API
export const documentTemplatesApi = {
  list: (params?: { category?: string; active_only?: boolean; search?: string }) =>
    apiClient.get<{ data: DocumentTemplate[] }>('/document-templates', { params }),

  get: (id: number) =>
    apiClient.get<DocumentTemplate>(`/document-templates/${id}`),

  create: (data: CreateTemplateData) =>
    apiClient.post<DocumentTemplate>('/document-templates', data),

  update: (id: number, data: UpdateTemplateData) =>
    apiClient.put<DocumentTemplate>(`/document-templates/${id}`, data),

  delete: (id: number) =>
    apiClient.delete(`/document-templates/${id}`),

  duplicate: (id: number) =>
    apiClient.post<DocumentTemplate>(`/document-templates/${id}/duplicate`),

  generate: (id: number, recordType: string, recordId: number) =>
    apiClient.post<GeneratedDocument>(`/document-templates/${id}/generate`, {
      record_type: recordType,
      record_id: recordId,
    }),

  preview: (id: number, recordType?: string, recordId?: number) =>
    apiClient.post<{ html: string }>(`/document-templates/${id}/preview`, {
      record_type: recordType,
      record_id: recordId,
    }),

  getVariables: () =>
    apiClient.get<Record<string, MergeFieldVariable[]>>('/document-templates/variables'),
};

// Generated Documents API
export const generatedDocumentsApi = {
  list: (params?: { record_type?: string; record_id?: number; template_id?: number }) =>
    apiClient.get<{ data: GeneratedDocument[] }>('/document-templates/generated', { params }),

  get: (id: number) =>
    apiClient.get<GeneratedDocument>(`/document-templates/generated/${id}`),

  delete: (id: number) =>
    apiClient.delete(`/document-templates/generated/${id}`),
};
