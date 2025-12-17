import { apiClient } from './client';

// Types
export interface CmsPage {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  content: ContentBlock[] | null;
  type: PageType;
  status: PageStatus;
  template_id: number | null;
  parent_id: number | null;
  meta_title: string | null;
  meta_description: string | null;
  meta_keywords: string | null;
  canonical_url: string | null;
  og_image: string | null;
  noindex: boolean;
  nofollow: boolean;
  featured_image_id: number | null;
  featured_image?: CmsMedia;
  published_at: string | null;
  scheduled_at: string | null;
  author_id: number | null;
  author?: { id: number; name: string };
  created_by: number | null;
  updated_by: number | null;
  settings: Record<string, unknown> | null;
  view_count: number;
  sort_order: number;
  template?: CmsTemplate;
  categories?: CmsCategory[];
  tags?: CmsTag[];
  comments_count?: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
}

export interface CmsPageVersion {
  id: number;
  page_id: number;
  version_number: number;
  title: string;
  content: ContentBlock[];
  seo_data: {
    meta_title?: string;
    meta_description?: string;
    meta_keywords?: string;
  } | null;
  change_summary: string | null;
  created_by: number | null;
  creator?: { id: number; name: string };
  created_at: string;
  updated_at: string;
}

export interface CmsTemplate {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  type: TemplateType;
  content: ContentBlock[] | null;
  settings: Record<string, unknown> | null;
  thumbnail: string | null;
  is_system: boolean;
  is_active: boolean;
  created_by: number | null;
  creator?: { id: number; name: string };
  pages_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CmsMedia {
  id: number;
  name: string;
  filename: string;
  path: string;
  disk: string;
  mime_type: string;
  size: number;
  type: MediaType;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  caption: string | null;
  description: string | null;
  metadata: Record<string, unknown> | null;
  folder_id: number | null;
  folder?: CmsMediaFolder;
  tags: string[] | null;
  uploaded_by: number | null;
  uploader?: { id: number; name: string };
  url: string;
  thumbnail_url: string | null;
  formatted_size?: string;
  created_at: string;
  updated_at: string;
}

export interface CmsMediaFolder {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  sort_order: number;
  created_by: number | null;
  creator?: { id: number; name: string };
  children?: CmsMediaFolder[];
  media_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CmsForm {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  fields: FormField[];
  settings: Record<string, unknown> | null;
  submit_action: FormSubmitAction;
  target_module_id: number | null;
  target_module?: { id: number; name: string; api_name: string };
  field_mapping: Record<string, string> | null;
  submit_button_text: string;
  success_message: string | null;
  redirect_url: string | null;
  notification_emails: string[] | null;
  notification_template_id: number | null;
  notification_template?: { id: number; name: string };
  submission_count: number;
  view_count: number;
  is_active: boolean;
  conversion_rate?: number;
  created_by: number | null;
  creator?: { id: number; name: string };
  submissions_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CmsFormSubmission {
  id: number;
  form_id: number;
  data: Record<string, unknown>;
  metadata: Record<string, unknown> | null;
  contact_id: number | null;
  lead_id: number | null;
  source_url: string | null;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  updated_at: string;
}

export interface CmsCategory {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  parent_id: number | null;
  parent?: CmsCategory;
  children?: CmsCategory[];
  image: string | null;
  sort_order: number;
  is_active: boolean;
  pages_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CmsMenu {
  id: number;
  name: string;
  slug: string;
  location: string | null;
  items: MenuItem[];
  is_active: boolean;
  created_by: number | null;
  creator?: { id: number; name: string };
  created_at: string;
  updated_at: string;
}

export interface CmsTag {
  id: number;
  name: string;
  slug: string;
  pages_count?: number;
  created_at: string;
  updated_at: string;
}

export interface CmsComment {
  id: number;
  page_id: number;
  parent_id: number | null;
  user_id: number | null;
  author_name: string | null;
  author_email: string | null;
  author_url: string | null;
  content: string;
  status: CommentStatus;
  ip_address: string | null;
  user_agent: string | null;
  replies?: CmsComment[];
  user?: { id: number; name: string };
  created_at: string;
  updated_at: string;
}

export interface ContentBlock {
  id: string;
  type: BlockType;
  props: Record<string, unknown>;
  children?: ContentBlock[];
}

export interface FormField {
  name: string;
  type: FormFieldType;
  label: string;
  required?: boolean;
  placeholder?: string;
  options?: { label: string; value: string }[];
  validation?: string[];
}

export interface MenuItem {
  id: string;
  label: string;
  url?: string;
  page_id?: number;
  target?: '_self' | '_blank';
  children?: MenuItem[];
}

// Enums
export type PageType = 'page' | 'landing' | 'blog' | 'article';
export type PageStatus = 'draft' | 'pending_review' | 'scheduled' | 'published' | 'archived';
export type TemplateType = 'page' | 'email' | 'form' | 'landing' | 'blog' | 'partial';
export type MediaType = 'image' | 'document' | 'video' | 'audio' | 'other';
export type FormSubmitAction = 'create_lead' | 'create_contact' | 'update_contact' | 'webhook' | 'email' | 'custom';
export type FormFieldType = 'text' | 'email' | 'phone' | 'textarea' | 'select' | 'checkbox' | 'radio' | 'date' | 'number' | 'file';
export type CommentStatus = 'pending' | 'approved' | 'spam' | 'trash';
export type BlockType =
  | 'text' | 'heading' | 'paragraph' | 'image' | 'video' | 'button' | 'divider' | 'spacer'
  | 'columns' | 'section' | 'container' | 'hero' | 'cta' | 'testimonials' | 'features'
  | 'pricing' | 'faq' | 'form' | 'gallery' | 'carousel' | 'embed' | 'html' | 'table';

// Response types
interface ApiResponse<T> {
  data: T;
  message?: string;
}

interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// CMS Page API
export const cmsPageApi = {
  list: async (params?: {
    type?: PageType;
    status?: PageStatus;
    author_id?: number;
    category_id?: number;
    search?: string;
    per_page?: number;
    page?: number;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
  }): Promise<PaginatedResponse<CmsPage>> => {
    return await apiClient.get<PaginatedResponse<CmsPage>>('/cms/pages', { params });
  },

  get: async (id: number): Promise<CmsPage> => {
    const response = await apiClient.get<ApiResponse<CmsPage>>(`/cms/pages/${id}`);
    return response.data;
  },

  create: async (data: {
    title: string;
    slug?: string;
    type: PageType;
    excerpt?: string;
    content?: ContentBlock[];
    template_id?: number;
    parent_id?: number;
    meta_title?: string;
    meta_description?: string;
    meta_keywords?: string;
    canonical_url?: string;
    og_image?: string;
    noindex?: boolean;
    nofollow?: boolean;
    featured_image_id?: number;
    settings?: Record<string, unknown>;
    category_ids?: number[];
    tag_names?: string[];
  }): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>('/cms/pages', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    title: string;
    slug: string;
    type: PageType;
    excerpt: string;
    content: ContentBlock[];
    template_id: number;
    parent_id: number;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    canonical_url: string;
    og_image: string;
    noindex: boolean;
    nofollow: boolean;
    featured_image_id: number;
    settings: Record<string, unknown>;
    category_ids: number[];
    tag_names: string[];
    create_version: boolean;
    version_summary: string;
  }>): Promise<CmsPage> => {
    const response = await apiClient.put<ApiResponse<CmsPage>>(`/cms/pages/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/pages/${id}`);
  },

  publish: async (id: number): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>(`/cms/pages/${id}/publish`);
    return response.data;
  },

  unpublish: async (id: number): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>(`/cms/pages/${id}/unpublish`);
    return response.data;
  },

  schedule: async (id: number, scheduledAt: string): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>(`/cms/pages/${id}/schedule`, {
      scheduled_at: scheduledAt,
    });
    return response.data;
  },

  duplicate: async (id: number): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>(`/cms/pages/${id}/duplicate`);
    return response.data;
  },

  getVersions: async (id: number): Promise<CmsPageVersion[]> => {
    const response = await apiClient.get<ApiResponse<CmsPageVersion[]>>(`/cms/pages/${id}/versions`);
    return response.data;
  },

  restoreVersion: async (id: number, versionNumber: number): Promise<CmsPage> => {
    const response = await apiClient.post<ApiResponse<CmsPage>>(
      `/cms/pages/${id}/versions/${versionNumber}/restore`
    );
    return response.data;
  },
};

// CMS Media API
export const cmsMediaApi = {
  list: async (params?: {
    folder_id?: number | null;
    type?: MediaType;
    search?: string;
    per_page?: number;
    page?: number;
    sort_by?: string;
    sort_order?: 'asc' | 'desc';
  }): Promise<PaginatedResponse<CmsMedia>> => {
    const cleanParams = params ? {
      ...params,
      folder_id: params.folder_id ?? undefined
    } : undefined;
    return await apiClient.get<PaginatedResponse<CmsMedia>>('/cms/media', { params: cleanParams });
  },

  get: async (id: number): Promise<CmsMedia> => {
    const response = await apiClient.get<ApiResponse<CmsMedia>>(`/cms/media/${id}`);
    return response.data;
  },

  upload: async (file: File, data?: {
    folder_id?: number;
    alt_text?: string;
    caption?: string;
    description?: string;
    tags?: string[];
  }): Promise<CmsMedia> => {
    const formData = new FormData();
    formData.append('file', file);
    if (data?.folder_id) formData.append('folder_id', data.folder_id.toString());
    if (data?.alt_text) formData.append('alt_text', data.alt_text);
    if (data?.caption) formData.append('caption', data.caption);
    if (data?.description) formData.append('description', data.description);
    if (data?.tags) formData.append('tags', JSON.stringify(data.tags));

    const response = await apiClient.post<ApiResponse<CmsMedia>>('/cms/media', formData);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    alt_text: string;
    caption: string;
    description: string;
    folder_id: number;
    tags: string[];
  }>): Promise<CmsMedia> => {
    const response = await apiClient.put<ApiResponse<CmsMedia>>(`/cms/media/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/media/${id}`);
  },

  move: async (id: number, folderId: number | null): Promise<CmsMedia> => {
    const response = await apiClient.post<ApiResponse<CmsMedia>>(`/cms/media/${id}/move`, {
      folder_id: folderId,
    });
    return response.data;
  },

  bulkDelete: async (ids: number[]): Promise<void> => {
    await apiClient.post('/cms/media/bulk-delete', { ids });
  },

  bulkMove: async (ids: number[], folderId: number | null): Promise<void> => {
    await apiClient.post('/cms/media/bulk-move', { ids, folder_id: folderId });
  },

  getStats: async (): Promise<{
    total_size: number;
    total_size_formatted: string;
    counts: Record<MediaType, number>;
    total_count: number;
  }> => {
    const response = await apiClient.get<ApiResponse<{
      total_size: number;
      total_size_formatted: string;
      counts: Record<MediaType, number>;
      total_count: number;
    }>>('/cms/media/stats');
    return response.data;
  },
};

// CMS Media Folder API
export const cmsMediaFolderApi = {
  list: async (params?: {
    parent_id?: number | null;
    include_children?: boolean;
  }): Promise<CmsMediaFolder[]> => {
    const cleanParams = params ? {
      ...params,
      parent_id: params.parent_id ?? undefined
    } : undefined;
    const response = await apiClient.get<ApiResponse<CmsMediaFolder[]>>('/cms/media-folders', { params: cleanParams });
    return response.data;
  },

  getTree: async (): Promise<CmsMediaFolder[]> => {
    const response = await apiClient.get<ApiResponse<CmsMediaFolder[]>>('/cms/media-folders/tree');
    return response.data;
  },

  get: async (id: number): Promise<{ data: CmsMediaFolder; breadcrumbs: { id: number; name: string; slug: string }[] }> => {
    return await apiClient.get(`/cms/media-folders/${id}`);
  },

  create: async (data: {
    name: string;
    slug?: string;
    parent_id?: number;
  }): Promise<CmsMediaFolder> => {
    const response = await apiClient.post<ApiResponse<CmsMediaFolder>>('/cms/media-folders', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    parent_id: number;
  }>): Promise<CmsMediaFolder> => {
    const response = await apiClient.put<ApiResponse<CmsMediaFolder>>(`/cms/media-folders/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/media-folders/${id}`);
  },
};

// CMS Template API
export const cmsTemplateApi = {
  list: async (params?: {
    type?: TemplateType;
    is_active?: boolean;
    search?: string;
    per_page?: number;
    page?: number;
  }): Promise<PaginatedResponse<CmsTemplate>> => {
    return await apiClient.get<PaginatedResponse<CmsTemplate>>('/cms/templates', { params });
  },

  get: async (id: number): Promise<CmsTemplate> => {
    const response = await apiClient.get<ApiResponse<CmsTemplate>>(`/cms/templates/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    slug?: string;
    type: TemplateType;
    description?: string;
    content?: ContentBlock[];
    settings?: Record<string, unknown>;
    thumbnail?: string;
  }): Promise<CmsTemplate> => {
    const response = await apiClient.post<ApiResponse<CmsTemplate>>('/cms/templates', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    type: TemplateType;
    description: string;
    content: ContentBlock[];
    settings: Record<string, unknown>;
    thumbnail: string;
    is_active: boolean;
  }>): Promise<CmsTemplate> => {
    const response = await apiClient.put<ApiResponse<CmsTemplate>>(`/cms/templates/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/templates/${id}`);
  },

  duplicate: async (id: number): Promise<CmsTemplate> => {
    const response = await apiClient.post<ApiResponse<CmsTemplate>>(`/cms/templates/${id}/duplicate`);
    return response.data;
  },

  preview: async (id: number, data?: Record<string, unknown>): Promise<{
    content: ContentBlock[] | null;
    settings: Record<string, unknown> | null;
    preview_data: Record<string, unknown>;
  }> => {
    const response = await apiClient.post<ApiResponse<{
      content: ContentBlock[] | null;
      settings: Record<string, unknown> | null;
      preview_data: Record<string, unknown>;
    }>>(`/cms/templates/${id}/preview`, { data });
    return response.data;
  },
};

// CMS Form API
export const cmsFormApi = {
  list: async (params?: {
    is_active?: boolean;
    search?: string;
    per_page?: number;
    page?: number;
  }): Promise<PaginatedResponse<CmsForm>> => {
    return await apiClient.get<PaginatedResponse<CmsForm>>('/cms/forms', { params });
  },

  get: async (id: number): Promise<CmsForm> => {
    const response = await apiClient.get<ApiResponse<CmsForm>>(`/cms/forms/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    slug?: string;
    description?: string;
    fields: FormField[];
    settings?: Record<string, unknown>;
    submit_action?: FormSubmitAction;
    target_module_id?: number;
    field_mapping?: Record<string, string>;
    submit_button_text?: string;
    success_message?: string;
    redirect_url?: string;
    notification_emails?: string[];
    notification_template_id?: number;
  }): Promise<CmsForm> => {
    const response = await apiClient.post<ApiResponse<CmsForm>>('/cms/forms', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    description: string;
    fields: FormField[];
    settings: Record<string, unknown>;
    submit_action: FormSubmitAction;
    target_module_id: number;
    field_mapping: Record<string, string>;
    submit_button_text: string;
    success_message: string;
    redirect_url: string;
    notification_emails: string[];
    notification_template_id: number;
    is_active: boolean;
  }>): Promise<CmsForm> => {
    const response = await apiClient.put<ApiResponse<CmsForm>>(`/cms/forms/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/forms/${id}`);
  },

  duplicate: async (id: number): Promise<CmsForm> => {
    const response = await apiClient.post<ApiResponse<CmsForm>>(`/cms/forms/${id}/duplicate`);
    return response.data;
  },

  getSubmissions: async (id: number, params?: {
    per_page?: number;
    page?: number;
  }): Promise<PaginatedResponse<CmsFormSubmission>> => {
    return await apiClient.get<PaginatedResponse<CmsFormSubmission>>(`/cms/forms/${id}/submissions`, { params });
  },

  getSubmission: async (formId: number, submissionId: number): Promise<CmsFormSubmission> => {
    const response = await apiClient.get<ApiResponse<CmsFormSubmission>>(
      `/cms/forms/${formId}/submissions/${submissionId}`
    );
    return response.data;
  },

  deleteSubmission: async (formId: number, submissionId: number): Promise<void> => {
    await apiClient.delete(`/cms/forms/${formId}/submissions/${submissionId}`);
  },

  getEmbedCode: async (id: number): Promise<{ embed_code: string; api_endpoint: string }> => {
    const response = await apiClient.get<ApiResponse<{ embed_code: string; api_endpoint: string }>>(
      `/cms/forms/${id}/embed-code`
    );
    return response.data;
  },

  getAnalytics: async (id: number): Promise<{
    total_submissions: number;
    total_views: number;
    conversion_rate: number;
    daily_submissions: { date: string; count: number }[];
  }> => {
    const response = await apiClient.get<ApiResponse<{
      total_submissions: number;
      total_views: number;
      conversion_rate: number;
      daily_submissions: { date: string; count: number }[];
    }>>(`/cms/forms/${id}/analytics`);
    return response.data;
  },
};

// CMS Category API
export const cmsCategoryApi = {
  list: async (params?: {
    parent_id?: number | null;
    is_active?: boolean;
    include_children?: boolean;
  }): Promise<CmsCategory[]> => {
    const cleanParams = params ? {
      ...params,
      parent_id: params.parent_id ?? undefined
    } : undefined;
    const response = await apiClient.get<ApiResponse<CmsCategory[]>>('/cms/categories', { params: cleanParams });
    return response.data;
  },

  getTree: async (): Promise<CmsCategory[]> => {
    const response = await apiClient.get<ApiResponse<CmsCategory[]>>('/cms/categories/tree');
    return response.data;
  },

  get: async (id: number): Promise<{ data: CmsCategory; breadcrumbs: { id: number; name: string; slug: string }[] }> => {
    return await apiClient.get(`/cms/categories/${id}`);
  },

  create: async (data: {
    name: string;
    slug?: string;
    description?: string;
    parent_id?: number;
    image?: string;
    sort_order?: number;
    is_active?: boolean;
  }): Promise<CmsCategory> => {
    const response = await apiClient.post<ApiResponse<CmsCategory>>('/cms/categories', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    description: string;
    parent_id: number;
    image: string;
    sort_order: number;
    is_active: boolean;
  }>): Promise<CmsCategory> => {
    const response = await apiClient.put<ApiResponse<CmsCategory>>(`/cms/categories/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/categories/${id}`);
  },

  reorder: async (items: { id: number; sort_order: number }[]): Promise<void> => {
    await apiClient.post('/cms/categories/reorder', { items });
  },
};

// CMS Menu API
export const cmsMenuApi = {
  list: async (params?: {
    location?: string;
    is_active?: boolean;
  }): Promise<CmsMenu[]> => {
    const response = await apiClient.get<ApiResponse<CmsMenu[]>>('/cms/menus', { params });
    return response.data;
  },

  get: async (id: number): Promise<CmsMenu> => {
    const response = await apiClient.get<ApiResponse<CmsMenu>>(`/cms/menus/${id}`);
    return response.data;
  },

  getByLocation: async (location: string): Promise<CmsMenu | null> => {
    const response = await apiClient.get<ApiResponse<CmsMenu | null>>(`/cms/menus/by-location/${location}`);
    return response.data;
  },

  getLocations: async (): Promise<string[]> => {
    const response = await apiClient.get<ApiResponse<string[]>>('/cms/menus/locations');
    return response.data;
  },

  create: async (data: {
    name: string;
    slug?: string;
    location?: string;
    items?: MenuItem[];
    is_active?: boolean;
  }): Promise<CmsMenu> => {
    const response = await apiClient.post<ApiResponse<CmsMenu>>('/cms/menus', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    location: string;
    items: MenuItem[];
    is_active: boolean;
  }>): Promise<CmsMenu> => {
    const response = await apiClient.put<ApiResponse<CmsMenu>>(`/cms/menus/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/menus/${id}`);
  },
};

// CMS Tag API
export const cmsTagApi = {
  list: async (params?: {
    search?: string;
    limit?: number;
  }): Promise<CmsTag[]> => {
    const response = await apiClient.get<ApiResponse<CmsTag[]>>('/cms/tags', { params });
    return response.data;
  },

  get: async (id: number): Promise<CmsTag> => {
    const response = await apiClient.get<ApiResponse<CmsTag>>(`/cms/tags/${id}`);
    return response.data;
  },

  getPopular: async (limit?: number): Promise<CmsTag[]> => {
    const response = await apiClient.get<ApiResponse<CmsTag[]>>('/cms/tags/popular', { params: { limit } });
    return response.data;
  },

  create: async (name: string): Promise<CmsTag> => {
    const response = await apiClient.post<ApiResponse<CmsTag>>('/cms/tags', { name });
    return response.data;
  },

  update: async (id: number, name: string): Promise<CmsTag> => {
    const response = await apiClient.put<ApiResponse<CmsTag>>(`/cms/tags/${id}`, { name });
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/cms/tags/${id}`);
  },

  merge: async (sourceId: number, targetId: number): Promise<CmsTag> => {
    const response = await apiClient.post<ApiResponse<CmsTag>>('/cms/tags/merge', {
      source_id: sourceId,
      target_id: targetId,
    });
    return response.data;
  },
};

// Helper functions
export function getPageStatusColor(status: PageStatus): string {
  switch (status) {
    case 'draft':
      return 'bg-gray-100 text-gray-800';
    case 'pending_review':
      return 'bg-yellow-100 text-yellow-800';
    case 'scheduled':
      return 'bg-blue-100 text-blue-800';
    case 'published':
      return 'bg-green-100 text-green-800';
    case 'archived':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
}

export function getPageStatusLabel(status: PageStatus): string {
  switch (status) {
    case 'draft':
      return 'Draft';
    case 'pending_review':
      return 'Pending Review';
    case 'scheduled':
      return 'Scheduled';
    case 'published':
      return 'Published';
    case 'archived':
      return 'Archived';
    default:
      return status;
  }
}

export function getMediaTypeIcon(type: MediaType): string {
  switch (type) {
    case 'image':
      return 'image';
    case 'document':
      return 'file-text';
    case 'video':
      return 'video';
    case 'audio':
      return 'music';
    case 'other':
    default:
      return 'file';
  }
}

export function generateBlockId(): string {
  return `block_${Math.random().toString(36).substring(2, 11)}`;
}

export function createDefaultBlock(type: BlockType): ContentBlock {
  const id = generateBlockId();

  const defaults: Record<BlockType, Partial<ContentBlock>> = {
    text: { props: { content: '' } },
    heading: { props: { content: 'Heading', level: 'h2' } },
    paragraph: { props: { content: 'Your text goes here.' } },
    image: { props: { src: '', alt: '' } },
    video: { props: { src: '', autoplay: false } },
    button: { props: { label: 'Click Me', url: '#', variant: 'primary' } },
    divider: { props: { style: 'solid' } },
    spacer: { props: { height: 40 } },
    columns: { props: { columns: 2 }, children: [] },
    section: { props: { background: '#ffffff', padding: 60 }, children: [] },
    container: { props: { maxWidth: 1200 }, children: [] },
    hero: { props: { title: 'Your Headline', subtitle: 'Your subheadline', ctaText: 'Get Started' } },
    cta: { props: { title: 'Ready to get started?', buttonText: 'Sign Up' } },
    testimonials: { props: { items: [] } },
    features: { props: { items: [] } },
    pricing: { props: { items: [] } },
    faq: { props: { items: [] } },
    form: { props: { formId: null } },
    gallery: { props: { images: [] } },
    carousel: { props: { items: [] } },
    embed: { props: { code: '' } },
    html: { props: { code: '' } },
    table: { props: { rows: [['']], headers: [''] } },
  };

  return {
    id,
    type,
    props: defaults[type]?.props || {},
    children: defaults[type]?.children,
  };
}
