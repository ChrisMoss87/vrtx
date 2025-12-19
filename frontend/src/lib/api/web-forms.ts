import { apiClient } from './client';

// Types
export interface WebFormStyling {
	background_color: string;
	text_color: string;
	label_color: string;
	primary_color: string;
	border_color: string;
	border_radius: string;
	font_family: string;
	font_size: string;
	padding: string;
	max_width: string;
	custom_css: string;
}

export interface WebFormThankYouConfig {
	type: 'message' | 'redirect';
	message: string;
	redirect_url: string | null;
}

export interface WebFormSpamProtection {
	recaptcha_enabled?: boolean;
	recaptcha_site_key?: string;
	recaptcha_secret_key?: string;
	min_score?: number;
	honeypot_enabled?: boolean;
	honeypot_field?: string;
}

export interface WebFormSettings {
	submit_button_text?: string;
	success_message?: string;
	redirect_url?: string;
	assign_to_user_id?: number;
	auto_responder_enabled?: boolean;
	auto_responder_template_id?: number;
	notification_email?: string;
	webhook_url?: string;
}

export interface WebFormFieldOption {
	value: string;
	label: string;
}

export interface WebFormFieldValidation {
	min_length?: number;
	max_length?: number;
	pattern?: string;
	max_size?: number; // for file uploads
	allowed_types?: string[]; // for file uploads
}

export interface WebFormField {
	id?: number;
	field_type: string;
	label: string;
	name?: string;
	placeholder?: string;
	is_required: boolean;
	module_field_id?: number | null;
	module_field?: {
		id: number;
		label: string;
		api_name: string;
	} | null;
	options?: WebFormFieldOption[] | null;
	validation_rules?: WebFormFieldValidation | null;
	display_order: number;
	settings?: Record<string, unknown>;
}

export interface WebForm {
	id: number;
	name: string;
	slug: string;
	description: string | null;
	module: {
		id: number;
		name: string;
		api_name: string;
	} | null;
	is_active: boolean;
	public_url: string;
	submission_count: number;
	created_by: {
		id: number;
		name: string;
	} | null;
	created_at: string;
	updated_at: string;
	// Detailed fields (only in show response)
	settings?: WebFormSettings;
	styling?: WebFormStyling;
	thank_you_config?: WebFormThankYouConfig;
	spam_protection?: WebFormSpamProtection;
	assign_to_user?: {
		id: number;
		name: string;
	} | null;
	fields?: WebFormField[];
	embed_code?: {
		iframe: string;
		javascript: string;
	};
}

export interface WebFormSubmission {
	id: number;
	submission_data: Record<string, unknown>;
	record_id: number | null;
	record: {
		id: number;
		data: Record<string, unknown>;
	} | null;
	status: 'processed' | 'failed' | 'spam' | 'pending';
	error_message: string | null;
	ip_address: string | null;
	referrer: string | null;
	utm_params: Record<string, string> | null;
	submitted_at: string;
}

export interface WebFormAnalytics {
	total_views: number;
	total_submissions: number;
	successful_submissions: number;
	spam_blocked: number;
	conversion_rate: number;
	daily: {
		date: string;
		views: number;
		submissions: number;
		successful: number;
		spam: number;
		conversion_rate: number;
	}[];
}

export interface ModuleForForm {
	id: number;
	name: string;
	singular_name: string;
	api_name: string;
	fields: {
		id: number;
		label: string;
		api_name: string;
		field_type: string;
		is_required: boolean;
	}[];
}

/**
 * Data structure for creating/updating web forms
 */
export interface WebFormData {
	name: string;
	slug?: string;
	description?: string;
	module_id: number;
	is_active?: boolean;
	settings?: WebFormSettings;
	styling?: WebFormStyling;
	thank_you_config?: WebFormThankYouConfig;
	spam_protection?: WebFormSpamProtection;
	assign_to_user_id?: number | null;
	fields?: Omit<WebFormField, 'id' | 'module_field'>[];
}

export const FIELD_TYPES: Record<string, string> = {
	text: 'Text Input',
	email: 'Email',
	phone: 'Phone',
	textarea: 'Text Area',
	select: 'Select Dropdown',
	multi_select: 'Multi-Select',
	checkbox: 'Checkbox',
	radio: 'Radio Buttons',
	date: 'Date Picker',
	datetime: 'Date & Time',
	number: 'Number',
	currency: 'Currency',
	file: 'File Upload',
	hidden: 'Hidden Field',
	url: 'URL'
};

// API response types
interface ListResponse {
	data: WebForm[];
}

interface DetailResponse {
	data: WebForm;
	message?: string;
}

interface SubmissionsResponse {
	data: WebFormSubmission[];
	meta: {
		current_page: number;
		last_page: number;
		per_page: number;
		total: number;
	};
}

interface AnalyticsResponse {
	data: WebFormAnalytics;
}

interface ModulesResponse {
	data: ModuleForForm[];
}

interface FieldTypesResponse {
	data: Record<string, string>;
}

interface EmbedCodeResponse {
	data: {
		iframe: string;
		javascript: string;
		public_url: string;
	};
}

/**
 * Get all web forms
 */
export async function getWebForms(params?: {
	is_active?: boolean;
	module_id?: number;
	search?: string;
}): Promise<WebForm[]> {
	const queryParams: Record<string, string> = {};
	if (params?.is_active !== undefined) queryParams.is_active = String(params.is_active);
	if (params?.module_id) queryParams.module_id = String(params.module_id);
	if (params?.search) queryParams.search = params.search;

	const response = await apiClient.get<ListResponse>('/web-forms', queryParams);
	return response.data;
}

/**
 * Get a single web form by ID
 */
export async function getWebForm(id: number): Promise<WebForm> {
	const response = await apiClient.get<DetailResponse>(`/web-forms/${id}`);
	return response.data;
}

/**
 * Create a new web form
 */
export async function createWebForm(data: WebFormData): Promise<WebForm> {
	const response = await apiClient.post<DetailResponse>('/web-forms', data);
	return response.data;
}

/**
 * Update an existing web form
 */
export async function updateWebForm(id: number, data: Partial<WebFormData>): Promise<WebForm> {
	const response = await apiClient.put<DetailResponse>(`/web-forms/${id}`, data);
	return response.data;
}

/**
 * Delete a web form
 */
export async function deleteWebForm(id: number): Promise<void> {
	await apiClient.delete(`/web-forms/${id}`);
}

/**
 * Duplicate a web form
 */
export async function duplicateWebForm(id: number, name?: string): Promise<WebForm> {
	const response = await apiClient.post<DetailResponse>(`/web-forms/${id}/duplicate`, { name });
	return response.data;
}

/**
 * Toggle form active status
 */
export async function toggleWebFormActive(id: number): Promise<WebForm> {
	const response = await apiClient.post<DetailResponse>(`/web-forms/${id}/toggle-active`, {});
	return response.data;
}

/**
 * Get form submissions
 */
export async function getWebFormSubmissions(
	id: number,
	params?: {
		status?: 'processed' | 'failed' | 'spam' | 'pending';
		start_date?: string;
		end_date?: string;
		per_page?: number;
		page?: number;
	}
): Promise<{ data: WebFormSubmission[]; meta: SubmissionsResponse['meta'] }> {
	const queryParams: Record<string, string> = {};
	if (params?.status) queryParams.status = params.status;
	if (params?.start_date) queryParams.start_date = params.start_date;
	if (params?.end_date) queryParams.end_date = params.end_date;
	if (params?.per_page) queryParams.per_page = String(params.per_page);
	if (params?.page) queryParams.page = String(params.page);

	const response = await apiClient.get<SubmissionsResponse>(
		`/web-forms/${id}/submissions`,
		queryParams
	);
	return { data: response.data, meta: response.meta };
}

/**
 * Get form analytics
 */
export async function getWebFormAnalytics(
	id: number,
	params?: {
		start_date?: string;
		end_date?: string;
	}
): Promise<WebFormAnalytics> {
	const queryParams: Record<string, string> = {};
	if (params?.start_date) queryParams.start_date = params.start_date;
	if (params?.end_date) queryParams.end_date = params.end_date;

	const response = await apiClient.get<AnalyticsResponse>(`/web-forms/${id}/analytics`, queryParams);
	return response.data;
}

/**
 * Get embed code for a form
 */
export async function getWebFormEmbedCode(id: number): Promise<EmbedCodeResponse['data']> {
	const response = await apiClient.get<EmbedCodeResponse>(`/web-forms/${id}/embed`);
	return response.data;
}

/**
 * Get available modules for form creation
 */
export async function getModulesForForms(): Promise<ModuleForForm[]> {
	const response = await apiClient.get<ModulesResponse>('/web-forms/modules');
	return response.data;
}

/**
 * Get available field types
 */
export async function getFieldTypes(): Promise<Record<string, string>> {
	const response = await apiClient.get<FieldTypesResponse>('/web-forms/field-types');
	return response.data;
}

/**
 * Get default styling for new forms
 */
export function getDefaultStyling(): WebFormStyling {
	return {
		background_color: '#ffffff',
		text_color: '#1f2937',
		label_color: '#374151',
		primary_color: '#2563eb',
		border_color: '#d1d5db',
		border_radius: '8px',
		font_family: 'Inter, system-ui, sans-serif',
		font_size: '14px',
		padding: '24px',
		max_width: '600px',
		custom_css: ''
	};
}

/**
 * Get default thank you config
 */
export function getDefaultThankYouConfig(): WebFormThankYouConfig {
	return {
		type: 'message',
		message: 'Thank you for your submission!',
		redirect_url: null
	};
}

/**
 * Get default settings
 */
export function getDefaultSettings(): WebFormSettings {
	return {
		submit_button_text: 'Submit'
	};
}

/**
 * Create a new form field with defaults
 */
export function createFormField(fieldType: string = 'text', order: number = 0): WebFormField {
	return {
		field_type: fieldType,
		label: '',
		name: undefined,
		placeholder: undefined,
		is_required: false,
		module_field_id: null,
		options: fieldType === 'select' || fieldType === 'radio' || fieldType === 'multi_select' ? [] : null,
		validation_rules: null,
		display_order: order,
		settings: {}
	};
}

/**
 * Check if field type supports options
 */
export function fieldTypeHasOptions(fieldType: string): boolean {
	return ['select', 'multi_select', 'radio', 'checkbox'].includes(fieldType);
}

/**
 * Get field type icon
 */
export function getFieldTypeIcon(fieldType: string): string {
	const icons: Record<string, string> = {
		text: 'type',
		email: 'mail',
		phone: 'phone',
		textarea: 'align-left',
		select: 'chevron-down',
		multi_select: 'list',
		checkbox: 'check-square',
		radio: 'circle',
		date: 'calendar',
		datetime: 'clock',
		number: 'hash',
		currency: 'dollar-sign',
		file: 'upload',
		hidden: 'eye-off',
		url: 'link'
	};
	return icons[fieldType] || 'type';
}

/**
 * Format submission status for display
 */
export function formatSubmissionStatus(
	status: string
): { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' } {
	const statusMap: Record<
		string,
		{ label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
	> = {
		processed: { label: 'Processed', variant: 'default' },
		pending: { label: 'Pending', variant: 'secondary' },
		failed: { label: 'Failed', variant: 'destructive' },
		spam: { label: 'Spam', variant: 'outline' }
	};
	return statusMap[status] || { label: status, variant: 'secondary' };
}

/**
 * Form template definition - module-aware templates
 */
export interface WebFormTemplate {
	id: string;
	name: string;
	description: string;
	icon: string;
	// Which module types this template is designed for (by api_name)
	targetModules: string[];
	// Field mappings by module field api_name - these map to module fields
	fieldMappings: {
		api_name: string; // Module field api_name to map to
		label?: string; // Override label (optional)
		placeholder?: string; // Override placeholder (optional)
		is_required?: boolean; // Override required (optional)
	}[];
	settings: Partial<WebFormSettings>;
	styling: Partial<WebFormStyling>;
}

/**
 * Pre-built form templates that map to module fields
 */
export const FORM_TEMPLATES: WebFormTemplate[] = [
	// Contact forms
	{
		id: 'contact_basic',
		name: 'Basic Contact Form',
		description: 'Capture name, email and phone from new contacts',
		icon: 'user-plus',
		targetModules: ['contacts'],
		fieldMappings: [
			{ api_name: 'first_name', placeholder: 'Enter first name', is_required: true },
			{ api_name: 'last_name', placeholder: 'Enter last name', is_required: true },
			{ api_name: 'email', placeholder: 'your@email.com', is_required: true },
			{ api_name: 'phone', placeholder: '+1 (555) 000-0000' }
		],
		settings: { submit_button_text: 'Submit' },
		styling: {}
	},
	{
		id: 'contact_full',
		name: 'Full Contact Form',
		description: 'Comprehensive contact capture with company details',
		icon: 'users',
		targetModules: ['contacts'],
		fieldMappings: [
			{ api_name: 'first_name', is_required: true },
			{ api_name: 'last_name', is_required: true },
			{ api_name: 'email', is_required: true },
			{ api_name: 'phone' },
			{ api_name: 'mobile' },
			{ api_name: 'organization_name', label: 'Company' },
			{ api_name: 'job_title' },
			{ api_name: 'lead_source' }
		],
		settings: { submit_button_text: 'Get Started' },
		styling: {}
	},
	// Lead/Contact inquiry forms
	{
		id: 'lead_inquiry',
		name: 'Lead Inquiry Form',
		description: 'Capture leads with notes for follow-up',
		icon: 'mail',
		targetModules: ['contacts'],
		fieldMappings: [
			{ api_name: 'first_name', is_required: true },
			{ api_name: 'last_name', is_required: true },
			{ api_name: 'email', is_required: true },
			{ api_name: 'phone' },
			{ api_name: 'organization_name', label: 'Company' },
			{ api_name: 'notes', label: 'Message', placeholder: 'How can we help you?' }
		],
		settings: { submit_button_text: 'Send Message' },
		styling: {}
	},
	// Organization forms
	{
		id: 'organization_basic',
		name: 'Company Registration',
		description: 'Register new organizations/companies',
		icon: 'building',
		targetModules: ['organizations'],
		fieldMappings: [
			{ api_name: 'name', label: 'Company Name', is_required: true },
			{ api_name: 'industry' },
			{ api_name: 'website' },
			{ api_name: 'phone' },
			{ api_name: 'email' }
		],
		settings: { submit_button_text: 'Register Company' },
		styling: {}
	},
	{
		id: 'organization_full',
		name: 'Full Company Profile',
		description: 'Comprehensive company information capture',
		icon: 'building-2',
		targetModules: ['organizations'],
		fieldMappings: [
			{ api_name: 'name', label: 'Company Name', is_required: true },
			{ api_name: 'industry' },
			{ api_name: 'company_size' },
			{ api_name: 'website' },
			{ api_name: 'phone' },
			{ api_name: 'email' },
			{ api_name: 'street', label: 'Address' },
			{ api_name: 'city' },
			{ api_name: 'state' },
			{ api_name: 'country' },
			{ api_name: 'description' }
		],
		settings: { submit_button_text: 'Submit' },
		styling: {}
	},
	// Deal forms
	{
		id: 'deal_inquiry',
		name: 'Sales Inquiry',
		description: 'Capture new sales opportunities',
		icon: 'dollar-sign',
		targetModules: ['deals'],
		fieldMappings: [
			{ api_name: 'name', label: 'Project/Deal Name', is_required: true },
			{ api_name: 'expected_revenue', label: 'Budget' },
			{ api_name: 'source', label: 'How did you find us?' },
			{ api_name: 'description', label: 'Project Details', placeholder: 'Tell us about your project...' }
		],
		settings: { submit_button_text: 'Request Quote' },
		styling: {}
	},
	// Support case forms
	{
		id: 'case_support',
		name: 'Support Request',
		description: 'Allow customers to submit support tickets',
		icon: 'life-buoy',
		targetModules: ['cases'],
		fieldMappings: [
			{ api_name: 'subject', is_required: true },
			{ api_name: 'type', label: 'Issue Type' },
			{ api_name: 'priority' },
			{ api_name: 'description', is_required: true, placeholder: 'Please describe your issue in detail...' },
			{ api_name: 'email', label: 'Your Email', is_required: true },
			{ api_name: 'phone', label: 'Phone (optional)' }
		],
		settings: { submit_button_text: 'Submit Ticket' },
		styling: {}
	},
	{
		id: 'case_feedback',
		name: 'Customer Feedback',
		description: 'Collect customer feedback and suggestions',
		icon: 'message-square',
		targetModules: ['cases'],
		fieldMappings: [
			{ api_name: 'subject', label: 'Feedback Topic', is_required: true },
			{ api_name: 'type', label: 'Feedback Type' },
			{ api_name: 'description', label: 'Your Feedback', is_required: true },
			{ api_name: 'email', label: 'Your Email (optional)' }
		],
		settings: { submit_button_text: 'Submit Feedback' },
		styling: {}
	},
	// Task forms
	{
		id: 'task_request',
		name: 'Task Request',
		description: 'Allow users to submit task requests',
		icon: 'check-square',
		targetModules: ['tasks'],
		fieldMappings: [
			{ api_name: 'subject', is_required: true },
			{ api_name: 'priority' },
			{ api_name: 'due_date' },
			{ api_name: 'description', placeholder: 'Describe the task...' }
		],
		settings: { submit_button_text: 'Submit Request' },
		styling: {}
	},
	// Event registration
	{
		id: 'event_registration',
		name: 'Event Registration',
		description: 'Register attendees for events',
		icon: 'calendar',
		targetModules: ['events'],
		fieldMappings: [
			{ api_name: 'title', label: 'Your Name', is_required: true },
			{ api_name: 'description', label: 'Additional Notes' }
		],
		settings: { submit_button_text: 'Register' },
		styling: {}
	},
	// Quote request
	{
		id: 'quote_request',
		name: 'Quote Request',
		description: 'Let customers request price quotes',
		icon: 'file-text',
		targetModules: ['quotes'],
		fieldMappings: [
			{ api_name: 'subject', label: 'Project Name', is_required: true },
			{ api_name: 'notes', label: 'Project Requirements', placeholder: 'Describe what you need...' }
		],
		settings: { submit_button_text: 'Request Quote' },
		styling: {}
	}
];

/**
 * Get templates available for a specific module
 */
export function getTemplatesForModule(moduleApiName: string): WebFormTemplate[] {
	return FORM_TEMPLATES.filter((t) => t.targetModules.includes(moduleApiName));
}

/**
 * Get a template by ID
 */
export function getTemplateById(id: string): WebFormTemplate | undefined {
	return FORM_TEMPLATES.find((t) => t.id === id);
}

/**
 * Generate form fields from a template for a specific module
 * Maps template field definitions to actual module fields
 */
export function generateFieldsFromTemplate(
	template: WebFormTemplate,
	module: ModuleForForm
): Omit<WebFormField, 'id' | 'module_field'>[] {
	const fields: Omit<WebFormField, 'id' | 'module_field'>[] = [];

	template.fieldMappings.forEach((mapping, index) => {
		// Find the matching module field
		const moduleField = module.fields.find((f) => f.api_name === mapping.api_name);

		if (moduleField) {
			fields.push({
				field_type: mapModuleFieldType(moduleField.field_type),
				label: mapping.label ?? moduleField.label,
				placeholder: mapping.placeholder,
				is_required: mapping.is_required ?? moduleField.is_required,
				module_field_id: moduleField.id,
				display_order: index
			});
		}
	});

	return fields;
}

/**
 * Map CRM module field types to web form field types
 */
function mapModuleFieldType(moduleFieldType: string): string {
	const typeMap: Record<string, string> = {
		text: 'text',
		email: 'email',
		phone: 'phone',
		url: 'url',
		textarea: 'textarea',
		number: 'number',
		currency: 'currency',
		date: 'date',
		datetime: 'datetime',
		select: 'select',
		multi_select: 'multi_select',
		checkbox: 'checkbox',
		boolean: 'checkbox',
		lookup: 'select',
		picklist: 'select'
	};
	return typeMap[moduleFieldType] || 'text';
}
