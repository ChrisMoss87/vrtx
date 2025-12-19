import { apiClient } from './client';

// Types
export type CampaignType = 'email' | 'drip' | 'event' | 'product_launch' | 'newsletter' | 're_engagement';
export type CampaignStatus = 'draft' | 'scheduled' | 'active' | 'paused' | 'completed' | 'cancelled';
export type AssetType = 'email' | 'image' | 'document' | 'landing_page';

export interface Campaign {
	id: number;
	name: string;
	description: string | null;
	type: CampaignType;
	status: CampaignStatus;
	module_id: number | null;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	start_date: string | null;
	end_date: string | null;
	budget: number | null;
	spent: number;
	settings: Record<string, unknown>;
	goals: CampaignGoal[];
	created_by: number | null;
	creator?: {
		id: number;
		name: string;
	};
	owner_id: number | null;
	owner?: {
		id: number;
		name: string;
	};
	audiences?: CampaignAudience[];
	assets?: CampaignAsset[];
	audiences_count?: number;
	sends_count?: number;
	created_at: string;
	updated_at: string;
}

export interface CampaignGoal {
	name: string;
	target: number;
	metric: string;
}

export interface CampaignAudience {
	id: number;
	campaign_id: number;
	name: string;
	description: string | null;
	module_id: number;
	module?: {
		id: number;
		name: string;
		api_name: string;
	};
	segment_rules: SegmentRule[];
	contact_count: number;
	is_dynamic: boolean;
	last_refreshed_at: string | null;
	created_at: string;
	updated_at: string;
}

export interface SegmentRule {
	field: string;
	operator: string;
	value: unknown;
}

export interface CampaignAsset {
	id: number;
	campaign_id: number;
	type: AssetType;
	name: string;
	description: string | null;
	subject: string | null;
	content: string | null;
	metadata: Record<string, unknown>;
	version: number;
	is_active: boolean;
	created_at: string;
	updated_at: string;
}

export interface CampaignAnalytics {
	total_sends: number;
	delivered: number;
	opened: number;
	clicked: number;
	bounced: number;
	conversions: number;
	revenue: number;
	open_rate: number;
	click_rate: number;
	bounce_rate: number;
	conversion_rate: number;
}

export interface CampaignMetric {
	id: number;
	campaign_id: number;
	date: string;
	sends: number;
	delivered: number;
	opens: number;
	unique_opens: number;
	clicks: number;
	unique_clicks: number;
	bounces: number;
	unsubscribes: number;
	conversions: number;
	revenue: number;
}

export interface TopLink {
	url: string;
	link_name: string | null;
	click_count: number;
}

export interface EmailTemplate {
	id: number;
	name: string;
	description: string | null;
	category: string | null;
	subject: string | null;
	html_content: string;
	text_content: string | null;
	thumbnail_url: string | null;
	variables: string[];
	is_system: boolean;
	is_active: boolean;
	created_by: number | null;
	created_at: string;
	updated_at: string;
}

export interface CreateCampaignRequest {
	name: string;
	description?: string;
	type: CampaignType;
	module_id?: number;
	start_date?: string;
	end_date?: string;
	budget?: number;
	settings?: Record<string, unknown>;
	goals?: CampaignGoal[];
	owner_id?: number;
}

export interface UpdateCampaignRequest extends Partial<CreateCampaignRequest> {}

export interface CreateAudienceRequest {
	name: string;
	description?: string;
	module_id: number;
	segment_rules?: SegmentRule[];
	is_dynamic?: boolean;
}

export interface UpdateAudienceRequest extends Partial<Omit<CreateAudienceRequest, 'module_id'>> {}

export interface CreateAssetRequest {
	type: AssetType;
	name: string;
	description?: string;
	subject?: string;
	content?: string;
	metadata?: Record<string, unknown>;
}

export interface UpdateAssetRequest extends Partial<CreateAssetRequest> {}

export interface CreateTemplateRequest {
	name: string;
	description?: string;
	category?: string;
	subject?: string;
	html_content: string;
	text_content?: string;
	variables?: string[];
}

export interface UpdateTemplateRequest extends Partial<CreateTemplateRequest> {}

// API Functions

/**
 * Get campaign types
 */
export async function getCampaignTypes(): Promise<Record<CampaignType, string>> {
	const response = await apiClient.get<{ success: boolean; data: Record<CampaignType, string> }>(
		'/campaigns/types'
	);
	return response.data;
}

/**
 * Get campaign statuses
 */
export async function getCampaignStatuses(): Promise<Record<CampaignStatus, string>> {
	const response = await apiClient.get<{ success: boolean; data: Record<CampaignStatus, string> }>(
		'/campaigns/statuses'
	);
	return response.data;
}

/**
 * List campaigns with filters
 */
export async function getCampaigns(params?: {
	type?: CampaignType;
	status?: CampaignStatus;
	search?: string;
	owner_id?: number;
	sort_field?: string;
	sort_order?: 'asc' | 'desc';
	per_page?: number;
	page?: number;
}): Promise<{
	data: Campaign[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}> {
	const response = await apiClient.get<{
		success: boolean;
		data: Campaign[];
		meta: {
			current_page: number;
			last_page: number;
			per_page: number;
			total: number;
		};
	}>('/campaigns', params as Record<string, string>);
	return { data: response.data, meta: response.meta };
}

/**
 * Get a single campaign
 */
export async function getCampaign(id: number): Promise<{ campaign: Campaign; analytics: CampaignAnalytics }> {
	const response = await apiClient.get<{
		success: boolean;
		data: Campaign;
		analytics: CampaignAnalytics;
	}>(`/campaigns/${id}`);
	return { campaign: response.data, analytics: response.analytics };
}

/**
 * Create a campaign
 */
export async function createCampaign(data: CreateCampaignRequest): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		'/campaigns',
		data
	);
	return response.data;
}

/**
 * Update a campaign
 */
export async function updateCampaign(id: number, data: UpdateCampaignRequest): Promise<Campaign> {
	const response = await apiClient.put<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}`,
		data
	);
	return response.data;
}

/**
 * Delete a campaign
 */
export async function deleteCampaign(id: number): Promise<void> {
	await apiClient.delete(`/campaigns/${id}`);
}

/**
 * Duplicate a campaign
 */
export async function duplicateCampaign(id: number): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}/duplicate`
	);
	return response.data;
}

/**
 * Start a campaign
 */
export async function startCampaign(id: number): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}/start`
	);
	return response.data;
}

/**
 * Pause a campaign
 */
export async function pauseCampaign(id: number): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}/pause`
	);
	return response.data;
}

/**
 * Complete a campaign
 */
export async function completeCampaign(id: number): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}/complete`
	);
	return response.data;
}

/**
 * Cancel a campaign
 */
export async function cancelCampaign(id: number): Promise<Campaign> {
	const response = await apiClient.post<{ success: boolean; data: Campaign; message: string }>(
		`/campaigns/${id}/cancel`
	);
	return response.data;
}

/**
 * Get campaign analytics
 */
export async function getCampaignAnalytics(id: number): Promise<{
	analytics: CampaignAnalytics;
	top_links: TopLink[];
}> {
	const response = await apiClient.get<{
		success: boolean;
		analytics: CampaignAnalytics;
		top_links: TopLink[];
	}>(`/campaigns/${id}/analytics`);
	return { analytics: response.analytics, top_links: response.top_links };
}

/**
 * Get campaign metrics over time
 */
export async function getCampaignMetrics(
	id: number,
	startDate?: string,
	endDate?: string
): Promise<CampaignMetric[]> {
	const params: Record<string, string> = {};
	if (startDate) params.start_date = startDate;
	if (endDate) params.end_date = endDate;

	const response = await apiClient.get<{ success: boolean; data: CampaignMetric[] }>(
		`/campaigns/${id}/metrics`,
		params
	);
	return response.data;
}

// Audience functions

/**
 * Add audience to campaign
 */
export async function addAudience(campaignId: number, data: CreateAudienceRequest): Promise<CampaignAudience> {
	const response = await apiClient.post<{ success: boolean; data: CampaignAudience; message: string }>(
		`/campaigns/${campaignId}/audiences`,
		data
	);
	return response.data;
}

/**
 * Update audience
 */
export async function updateAudience(
	campaignId: number,
	audienceId: number,
	data: UpdateAudienceRequest
): Promise<CampaignAudience> {
	const response = await apiClient.put<{ success: boolean; data: CampaignAudience; message: string }>(
		`/campaigns/${campaignId}/audiences/${audienceId}`,
		data
	);
	return response.data;
}

/**
 * Delete audience
 */
export async function deleteAudience(campaignId: number, audienceId: number): Promise<void> {
	await apiClient.delete(`/campaigns/${campaignId}/audiences/${audienceId}`);
}

/**
 * Preview audience records
 */
export async function previewAudience(
	campaignId: number,
	audienceId: number
): Promise<{ records: unknown[]; total_count: number }> {
	const response = await apiClient.get<{
		success: boolean;
		data: unknown[];
		total_count: number;
	}>(`/campaigns/${campaignId}/audiences/${audienceId}/preview`);
	return { records: response.data, total_count: response.total_count };
}

/**
 * Refresh audience count
 */
export async function refreshAudience(campaignId: number, audienceId: number): Promise<number> {
	const response = await apiClient.post<{ success: boolean; contact_count: number; message: string }>(
		`/campaigns/${campaignId}/audiences/${audienceId}/refresh`
	);
	return response.contact_count;
}

// Asset functions

/**
 * Add asset to campaign
 */
export async function addAsset(campaignId: number, data: CreateAssetRequest): Promise<CampaignAsset> {
	const response = await apiClient.post<{ success: boolean; data: CampaignAsset; message: string }>(
		`/campaigns/${campaignId}/assets`,
		data
	);
	return response.data;
}

/**
 * Update asset
 */
export async function updateAsset(
	campaignId: number,
	assetId: number,
	data: UpdateAssetRequest
): Promise<CampaignAsset> {
	const response = await apiClient.put<{ success: boolean; data: CampaignAsset; message: string }>(
		`/campaigns/${campaignId}/assets/${assetId}`,
		data
	);
	return response.data;
}

/**
 * Delete asset
 */
export async function deleteAsset(campaignId: number, assetId: number): Promise<void> {
	await apiClient.delete(`/campaigns/${campaignId}/assets/${assetId}`);
}

// Template functions

/**
 * Get email templates
 */
export async function getEmailTemplates(category?: string): Promise<EmailTemplate[]> {
	const params: Record<string, string> = {};
	if (category) params.category = category;

	const response = await apiClient.get<{ success: boolean; data: EmailTemplate[] }>(
		'/campaigns/templates',
		params
	);
	return response.data;
}

/**
 * Get a single template
 */
export async function getEmailTemplate(templateId: number): Promise<EmailTemplate> {
	const response = await apiClient.get<{ success: boolean; data: EmailTemplate }>(
		`/campaigns/templates/${templateId}`
	);
	return response.data;
}

/**
 * Create a template
 */
export async function createEmailTemplate(data: CreateTemplateRequest): Promise<EmailTemplate> {
	const response = await apiClient.post<{ success: boolean; data: EmailTemplate; message: string }>(
		'/campaigns/templates',
		data
	);
	return response.data;
}

/**
 * Update a template
 */
export async function updateEmailTemplate(
	templateId: number,
	data: UpdateTemplateRequest
): Promise<EmailTemplate> {
	const response = await apiClient.put<{ success: boolean; data: EmailTemplate; message: string }>(
		`/campaigns/templates/${templateId}`,
		data
	);
	return response.data;
}

/**
 * Delete a template
 */
export async function deleteEmailTemplate(templateId: number): Promise<void> {
	await apiClient.delete(`/campaigns/templates/${templateId}`);
}
