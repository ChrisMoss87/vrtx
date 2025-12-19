import { apiClient } from './client';

// Types
export interface LandingPageTemplate {
  id: number;
  name: string;
  category: string;
  description: string | null;
  thumbnail_url: string | null;
  content: PageElement[];
  styles: PageStyles;
  is_system: boolean;
  is_active: boolean;
  usage_count: number;
  created_at: string;
  updated_at: string;
}

export interface LandingPage {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  status: 'draft' | 'published' | 'archived';
  template_id: number | null;
  content: PageElement[];
  settings: PageSettings;
  seo_settings: SeoSettings;
  styles: PageStyles;
  custom_domain: string | null;
  custom_domain_verified: boolean;
  favicon_url: string | null;
  og_image_url: string | null;
  web_form_id: number | null;
  thank_you_page_type: 'message' | 'redirect' | 'page';
  thank_you_message: string | null;
  thank_you_redirect_url: string | null;
  thank_you_page_id: number | null;
  is_ab_testing_enabled: boolean;
  campaign_id: number | null;
  published_at: string | null;
  template?: LandingPageTemplate;
  webForm?: { id: number; name: string };
  campaign?: { id: number; name: string };
  creator?: { id: number; name: string };
  variants?: LandingPageVariant[];
  created_at: string;
  updated_at: string;
}

export interface LandingPageVariant {
  id: number;
  page_id: number;
  name: string;
  variant_code: string;
  content: PageElement[];
  styles: PageStyles;
  traffic_percentage: number;
  is_active: boolean;
  is_winner: boolean;
  declared_winner_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface VariantComparison {
  id: number;
  name: string;
  variant_code: string;
  traffic_percentage: number;
  views: number;
  conversions: number;
  conversion_rate: number;
  is_winner: boolean;
  is_active: boolean;
}

export interface PageElement {
  id: string;
  type: PageElementType;
  props: Record<string, unknown>;
  children?: PageElement[];
}

export type PageElementType =
  | 'section'
  | 'container'
  | 'hero'
  | 'heading'
  | 'text'
  | 'image'
  | 'video'
  | 'button'
  | 'form'
  | 'cta'
  | 'testimonials'
  | 'features'
  | 'pricing'
  | 'faq'
  | 'footer'
  | 'divider'
  | 'spacer';

export interface PageSettings {
  background_color?: string;
  background_image?: string;
  max_width?: string;
  padding?: string;
}

export interface PageStyles {
  primary_color?: string;
  secondary_color?: string;
  accent_color?: string;
  text_color?: string;
  heading_font?: string;
  body_font?: string;
  custom_css?: string;
}

export interface SeoSettings {
  title?: string;
  description?: string;
  keywords?: string[];
  canonical_url?: string;
  no_index?: boolean;
  no_follow?: boolean;
}

export interface PageAnalytics {
  totals: {
    views: number;
    unique_visitors: number;
    form_submissions: number;
    bounces: number;
    conversion_rate: number;
    bounce_rate: number;
  };
  daily: Array<{
    date: string;
    views: number;
    unique_visitors: number;
    form_submissions: number;
    bounces: number;
  }>;
  referrer_breakdown: Record<string, number>;
  device_breakdown: Record<string, number>;
  location_breakdown: Record<string, number>;
}

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

// Landing Page API
export const landingPageApi = {
  list: async (params?: {
    status?: LandingPage['status'];
    campaign_id?: number;
    search?: string;
    sort_field?: string;
    sort_order?: string;
    page?: number;
    per_page?: number;
  }): Promise<PaginatedResponse<LandingPage>> => {
    const response = await apiClient.get<PaginatedResponse<LandingPage>>('/landing-pages', { params });
    return response;
  },

  get: async (id: number): Promise<LandingPage> => {
    const response = await apiClient.get<ApiResponse<LandingPage>>(`/landing-pages/${id}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    slug?: string;
    description?: string;
    template_id?: number;
    content?: PageElement[];
    settings?: PageSettings;
    seo_settings?: SeoSettings;
    styles?: PageStyles;
    web_form_id?: number;
    thank_you_page_type?: LandingPage['thank_you_page_type'];
    thank_you_message?: string;
    thank_you_redirect_url?: string;
    campaign_id?: number;
  }): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>('/landing-pages', data);
    return response.data;
  },

  update: async (id: number, data: Partial<{
    name: string;
    slug: string;
    description: string;
    content: PageElement[];
    settings: PageSettings;
    seo_settings: SeoSettings;
    styles: PageStyles;
    web_form_id: number;
    thank_you_page_type: LandingPage['thank_you_page_type'];
    thank_you_message: string;
    thank_you_redirect_url: string;
    thank_you_page_id: number;
    campaign_id: number;
    favicon_url: string;
    og_image_url: string;
    custom_domain: string;
  }>): Promise<LandingPage> => {
    const response = await apiClient.put<ApiResponse<LandingPage>>(`/landing-pages/${id}`, data);
    return response.data;
  },

  delete: async (id: number): Promise<void> => {
    await apiClient.delete(`/landing-pages/${id}`);
  },

  duplicate: async (id: number): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>(`/landing-pages/${id}/duplicate`);
    return response.data;
  },

  publish: async (id: number): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>(`/landing-pages/${id}/publish`);
    return response.data;
  },

  unpublish: async (id: number): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>(`/landing-pages/${id}/unpublish`);
    return response.data;
  },

  archive: async (id: number): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>(`/landing-pages/${id}/archive`);
    return response.data;
  },

  saveAsTemplate: async (id: number, data: {
    name: string;
    category?: string;
    description?: string;
  }): Promise<LandingPageTemplate> => {
    const response = await apiClient.post<ApiResponse<LandingPageTemplate>>(
      `/landing-pages/${id}/save-as-template`,
      data
    );
    return response.data;
  },

  analytics: async (id: number, params?: {
    start_date?: string;
    end_date?: string;
  }): Promise<PageAnalytics> => {
    const response = await apiClient.get<ApiResponse<PageAnalytics>>(`/landing-pages/${id}/analytics`, { params });
    return response.data;
  },

  getStatuses: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/landing-pages/statuses');
    return response.data;
  },

  getThankYouTypes: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/landing-pages/thank-you-types');
    return response.data;
  },
};

// Variant API
export const landingPageVariantApi = {
  list: async (pageId: number): Promise<VariantComparison[]> => {
    const response = await apiClient.get<ApiResponse<VariantComparison[]>>(`/landing-pages/${pageId}/variants`);
    return response.data;
  },

  create: async (pageId: number, data: {
    name?: string;
    content?: PageElement[];
    styles?: PageStyles;
    traffic_percentage?: number;
  }): Promise<LandingPageVariant> => {
    const response = await apiClient.post<ApiResponse<LandingPageVariant>>(`/landing-pages/${pageId}/variants`, data);
    return response.data;
  },

  update: async (pageId: number, variantId: number, data: Partial<{
    name: string;
    content: PageElement[];
    styles: PageStyles;
    traffic_percentage: number;
    is_active: boolean;
  }>): Promise<LandingPageVariant> => {
    const response = await apiClient.put<ApiResponse<LandingPageVariant>>(
      `/landing-pages/${pageId}/variants/${variantId}`,
      data
    );
    return response.data;
  },

  delete: async (pageId: number, variantId: number): Promise<void> => {
    await apiClient.delete(`/landing-pages/${pageId}/variants/${variantId}`);
  },

  declareWinner: async (pageId: number, variantId: number): Promise<LandingPage> => {
    const response = await apiClient.post<ApiResponse<LandingPage>>(
      `/landing-pages/${pageId}/variants/${variantId}/declare-winner`
    );
    return response.data;
  },
};

// Template API
export const landingPageTemplateApi = {
  list: async (params?: { category?: string }): Promise<LandingPageTemplate[]> => {
    const response = await apiClient.get<ApiResponse<LandingPageTemplate[]>>('/landing-pages/templates', { params });
    return response.data;
  },

  get: async (templateId: number): Promise<LandingPageTemplate> => {
    const response = await apiClient.get<ApiResponse<LandingPageTemplate>>(`/landing-pages/templates/${templateId}`);
    return response.data;
  },

  create: async (data: {
    name: string;
    category?: string;
    description?: string;
    thumbnail_url?: string;
    content: PageElement[];
    styles?: PageStyles;
  }): Promise<LandingPageTemplate> => {
    const response = await apiClient.post<ApiResponse<LandingPageTemplate>>('/landing-pages/templates', data);
    return response.data;
  },

  update: async (templateId: number, data: Partial<{
    name: string;
    category: string;
    description: string;
    thumbnail_url: string;
    content: PageElement[];
    styles: PageStyles;
    is_active: boolean;
  }>): Promise<LandingPageTemplate> => {
    const response = await apiClient.put<ApiResponse<LandingPageTemplate>>(
      `/landing-pages/templates/${templateId}`,
      data
    );
    return response.data;
  },

  delete: async (templateId: number): Promise<void> => {
    await apiClient.delete(`/landing-pages/templates/${templateId}`);
  },

  getCategories: async (): Promise<Record<string, string>> => {
    const response = await apiClient.get<ApiResponse<Record<string, string>>>('/landing-pages/templates/categories');
    return response.data;
  },
};

// Helper functions
export function getStatusColor(status: LandingPage['status']): string {
  switch (status) {
    case 'draft':
      return 'text-yellow-600 bg-yellow-100';
    case 'published':
      return 'text-green-600 bg-green-100';
    case 'archived':
      return 'text-gray-600 bg-gray-100';
    default:
      return 'text-gray-600 bg-gray-100';
  }
}

export function generateElementId(): string {
  return `el_${Math.random().toString(36).substring(2, 11)}`;
}

export function createDefaultElement(type: PageElementType): PageElement {
  const id = generateElementId();

  const defaults: Record<PageElementType, Partial<PageElement>> = {
    section: { props: { backgroundColor: '#ffffff', padding: '60px 20px' } },
    container: { props: { maxWidth: '1200px' } },
    hero: { props: { title: 'Your Headline Here', subtitle: 'Your compelling subheadline', ctaText: 'Get Started' } },
    heading: { props: { text: 'Heading', level: 'h2' } },
    text: { props: { text: 'Your paragraph text goes here.' } },
    image: { props: { src: '', alt: 'Image description' } },
    video: { props: { src: '', autoplay: false } },
    button: { props: { text: 'Click Me', variant: 'primary' } },
    form: { props: { formId: null } },
    cta: { props: { title: 'Ready to Get Started?', buttonText: 'Sign Up Now' } },
    testimonials: { props: { items: [] } },
    features: { props: { items: [] } },
    pricing: { props: { items: [] } },
    faq: { props: { items: [] } },
    footer: { props: { copyright: '© 2025 Your Company' } },
    divider: { props: { style: 'solid' } },
    spacer: { props: { height: '40px' } },
  };

  return {
    id,
    type,
    props: defaults[type]?.props || {},
    children: [],
  };
}
