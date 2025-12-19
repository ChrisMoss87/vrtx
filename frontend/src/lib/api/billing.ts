import { apiClient } from './client';

// Product types
export interface ProductCategory {
	id: number;
	name: string;
	parent_id: number | null;
	display_order: number;
	children?: ProductCategory[];
	created_at: string;
	updated_at: string;
}

export interface Product {
	id: number;
	name: string;
	sku: string | null;
	description: string | null;
	unit_price: number;
	currency: string;
	tax_rate: number;
	is_active: boolean;
	category_id: number | null;
	category?: ProductCategory;
	unit: string | null;
	settings: Record<string, unknown> | null;
	created_at: string;
	updated_at: string;
}

export interface ProductInput {
	name: string;
	sku?: string;
	description?: string;
	unit_price: number;
	currency?: string;
	tax_rate?: number;
	is_active?: boolean;
	category_id?: number | null;
	unit?: string;
	settings?: Record<string, unknown>;
}

export interface ProductCategoryInput {
	name: string;
	parent_id?: number | null;
	display_order?: number;
}

// Quote types
export type QuoteStatus = 'draft' | 'sent' | 'viewed' | 'accepted' | 'rejected' | 'expired';

export interface QuoteLineItem {
	id: number;
	quote_id: number;
	product_id: number | null;
	sku: string | null;
	description: string;
	detailed_description: string | null;
	item_type: 'product' | 'service' | 'text';
	quantity: number;
	unit: string | null;
	unit_price: number;
	discount_type: 'none' | 'percent' | 'fixed';
	discount_value: number;
	discount_percent: number; // Calculated for display
	tax_rate_id: number | null;
	tax_rate: number;
	line_total: number;
	sort_order: number;
	product?: Product;
	created_at: string;
	updated_at: string;
}

export interface QuoteLineItemInput {
	product_id?: number | null;
	sku?: string | null;
	description: string;
	detailed_description?: string | null;
	item_type?: 'product' | 'service' | 'text';
	quantity?: number;
	unit?: string | null;
	unit_price: number;
	discount_type?: 'none' | 'percent' | 'fixed';
	discount_value?: number;
	discount_percent?: number; // Keep for backwards compatibility
	tax_rate_id?: number | null;
	tax_rate?: number;
}

export interface QuoteTemplate {
	id: number;
	name: string;
	is_default: boolean;
	header_html: string | null;
	footer_html: string | null;
	styling: Record<string, unknown> | null;
	company_info: Record<string, unknown> | null;
	created_at: string;
	updated_at: string;
}

export interface QuoteTemplateInput {
	name: string;
	is_default?: boolean;
	header_html?: string;
	footer_html?: string;
	styling?: Record<string, unknown>;
	company_info?: Record<string, unknown>;
}

export interface QuoteVersion {
	id: number;
	quote_id: number;
	version_number: number;
	snapshot: Record<string, unknown>;
	change_notes: string | null;
	created_by: number | null;
	created_at: string;
}

export interface Quote {
	id: number;
	quote_number: string;
	deal_id: number | null;
	contact_id: number | null;
	company_id: number | null;
	title: string | null;
	status: QuoteStatus;
	version: number;
	currency: string;
	subtotal: number;
	discount_type: 'fixed' | 'percent' | null;
	discount_amount: number;
	discount_percent: number;
	tax_amount: number;
	total: number;
	valid_until: string | null;
	terms: string | null;
	notes: string | null;
	internal_notes: string | null;
	template_id: number | null;
	view_token: string;
	sent_at: string | null;
	sent_to: string | null;
	viewed_at: string | null;
	view_count: number;
	accepted_at: string | null;
	accepted_by: string | null;
	accepted_signature: string | null;
	accepted_ip: string | null;
	rejected_at: string | null;
	rejected_by: string | null;
	rejection_reason: string | null;
	created_by: number | null;
	assigned_to: number | null;
	created_at: string;
	updated_at: string;
	lineItems?: QuoteLineItem[];
	template?: QuoteTemplate;
	versions?: QuoteVersion[];
	createdBy?: { id: number; name: string; email: string };
	assignedTo?: { id: number; name: string; email: string };
	invoice?: Invoice;
}

export interface QuoteInput {
	deal_id?: number | null;
	contact_id?: number | null;
	company_id?: number | null;
	title?: string;
	currency?: string;
	valid_until?: string;
	terms?: string;
	notes?: string;
	internal_notes?: string;
	template_id?: number | null;
	discount_type?: 'fixed' | 'percent';
	discount_amount?: number;
	discount_percent?: number;
	assigned_to?: number | null;
	line_items?: QuoteLineItemInput[];
}

export interface QuoteStats {
	total: number;
	draft: number;
	sent: number;
	accepted: number;
	rejected: number;
	expired: number;
	total_value: number;
	pending_value: number;
}

// Invoice types
export type InvoiceStatus = 'draft' | 'sent' | 'viewed' | 'paid' | 'partial' | 'overdue' | 'cancelled';

export interface InvoiceLineItem {
	id: number;
	invoice_id: number;
	product_id: number | null;
	description: string;
	quantity: number;
	unit_price: number;
	discount_percent: number;
	tax_rate: number;
	line_total: number;
	sort_order: number;
	product?: Product;
	created_at: string;
	updated_at: string;
}

export interface InvoiceLineItemInput {
	product_id?: number | null;
	description: string;
	quantity?: number;
	unit_price: number;
	discount_percent?: number;
	tax_rate?: number;
}

export interface InvoicePayment {
	id: number;
	invoice_id: number;
	amount: number;
	payment_date: string;
	payment_method: string | null;
	reference: string | null;
	notes: string | null;
	created_by: number | null;
	created_at: string;
	updated_at: string;
	createdBy?: { id: number; name: string };
}

export interface InvoicePaymentInput {
	amount: number;
	payment_date?: string;
	payment_method?: string;
	reference?: string;
	notes?: string;
}

export interface Invoice {
	id: number;
	invoice_number: string;
	quote_id: number | null;
	deal_id: number | null;
	contact_id: number | null;
	company_id: number | null;
	title: string | null;
	status: InvoiceStatus;
	currency: string;
	subtotal: number;
	discount_amount: number;
	tax_amount: number;
	total: number;
	amount_paid: number;
	balance_due: number;
	issue_date: string;
	due_date: string;
	payment_terms: string | null;
	notes: string | null;
	internal_notes: string | null;
	template_id: number | null;
	sent_at: string | null;
	sent_to: string | null;
	viewed_at: string | null;
	view_count: number;
	created_by: number | null;
	created_at: string;
	updated_at: string;
	lineItems?: InvoiceLineItem[];
	payments?: InvoicePayment[];
	template?: QuoteTemplate;
	quote?: Quote;
	createdBy?: { id: number; name: string; email: string };
}

export interface InvoiceInput {
	deal_id?: number | null;
	contact_id?: number | null;
	company_id?: number | null;
	title?: string;
	currency?: string;
	issue_date?: string;
	due_date?: string;
	payment_terms?: string;
	discount_amount?: number;
	notes?: string;
	internal_notes?: string;
	template_id?: number | null;
	line_items?: InvoiceLineItemInput[];
}

export interface InvoiceStats {
	total: number;
	draft: number;
	sent: number;
	paid: number;
	partial: number;
	overdue: number;
	cancelled: number;
	total_revenue: number;
	total_outstanding: number;
	overdue_amount: number;
}

// PDF data types
export interface PdfData {
	type: 'quote' | 'invoice';
	document_number: string;
	title: string;
	status: string;
	date?: string;
	issue_date?: string;
	due_date?: string;
	valid_until?: string;
	company_info: Record<string, unknown>;
	customer: { contact_id: number | null; company_id: number | null };
	line_items: Array<{
		description: string;
		quantity: string;
		unit_price: string;
		discount_percent: string | null;
		tax_rate: string | null;
		line_total: string;
	}>;
	subtotal: string;
	discount?: { type: string; amount: string; percent: string | null } | null;
	discount_amount?: string | null;
	tax_amount: string;
	total: string;
	amount_paid?: string;
	balance_due?: string;
	currency: string;
	terms?: string;
	notes?: string;
	template?: {
		header_html: string | null;
		footer_html: string | null;
		styling: Record<string, unknown> | null;
	};
	created_by?: { name: string; email: string } | null;
}

// Tax Rate types
export interface TaxRate {
	id: number;
	name: string;
	display_name: string | null;
	rate: number;
	is_compound: boolean;
	is_default: boolean;
	is_active: boolean;
	effective_from: string | null;
	region: string | null;
	created_at: string;
	updated_at: string;
}

export interface TaxRateInput {
	name: string;
	display_name?: string;
	rate: number;
	is_compound?: boolean;
	is_default?: boolean;
	is_active?: boolean;
	effective_from?: string;
	region?: string;
}

// Public quote types
export interface PublicQuoteData {
	quote_number: string;
	title: string | null;
	status: QuoteStatus;
	valid_until: string | null;
	is_expired: boolean;
	can_accept: boolean;
	currency: string;
	line_items: Array<{
		description: string;
		quantity: number;
		unit_price: number;
		discount_percent: number;
		tax_rate: number;
		line_total: number;
	}>;
	subtotal: number;
	discount_amount: number;
	tax_amount: number;
	total: number;
	terms: string | null;
	notes: string | null;
	created_by: { name: string; email: string } | null;
	created_at: string;
	accepted_at: string | null;
	accepted_by: string | null;
}

// Response types
interface ProductListResponse {
	data: Product[];
}

interface ProductResponse {
	data: Product;
	message?: string;
}

interface CategoryListResponse {
	data: ProductCategory[];
}

interface CategoryResponse {
	data: ProductCategory;
	message?: string;
}

interface QuoteListResponse {
	data: Quote[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
}

interface QuoteResponse {
	data: Quote;
	message?: string;
}

interface QuoteStatsResponse {
	stats: QuoteStats;
}

interface TemplateListResponse {
	data: QuoteTemplate[];
}

interface TemplateResponse {
	data: QuoteTemplate;
	message?: string;
}

interface InvoiceListResponse {
	data: Invoice[];
	current_page: number;
	last_page: number;
	per_page: number;
	total: number;
}

interface InvoiceResponse {
	data: Invoice;
	message?: string;
}

interface InvoiceStatsResponse {
	stats: InvoiceStats;
}

interface PaymentResponse {
	data: InvoicePayment;
	invoice: Invoice;
	message?: string;
}

interface PdfResponse {
	data: PdfData;
}

interface PublicQuoteResponse {
	data: PublicQuoteData;
}

interface PublicQuoteAcceptResponse {
	message: string;
	data: {
		status: QuoteStatus;
		accepted_at: string;
		accepted_by: string;
	};
}

interface PublicQuoteRejectResponse {
	message: string;
	data: {
		status: QuoteStatus;
		rejected_at: string;
	};
}

interface TaxRateListResponse {
	data: TaxRate[];
}

interface TaxRateResponse {
	data: TaxRate;
	message?: string;
}

// ============= Product API =============

export async function getProducts(params?: {
	search?: string;
	category_id?: number;
	active_only?: boolean;
}): Promise<Product[]> {
	const queryParams: Record<string, string> = {};
	if (params?.search) queryParams.search = params.search;
	if (params?.category_id) queryParams.category_id = String(params.category_id);
	if (params?.active_only !== undefined) queryParams.active_only = String(params.active_only);

	const response = await apiClient.get<ProductListResponse>('/products', queryParams);
	return response.data;
}

export async function getProduct(id: number): Promise<Product> {
	const response = await apiClient.get<ProductResponse>(`/products/${id}`);
	return response.data;
}

export async function createProduct(data: ProductInput): Promise<Product> {
	const response = await apiClient.post<ProductResponse>('/products', data);
	return response.data;
}

export async function updateProduct(id: number, data: Partial<ProductInput>): Promise<Product> {
	const response = await apiClient.put<ProductResponse>(`/products/${id}`, data);
	return response.data;
}

export async function deleteProduct(id: number): Promise<void> {
	await apiClient.delete(`/products/${id}`);
}

// Product Categories
export async function getProductCategories(): Promise<ProductCategory[]> {
	const response = await apiClient.get<CategoryListResponse>('/products/categories');
	return response.data;
}

export async function createProductCategory(data: ProductCategoryInput): Promise<ProductCategory> {
	const response = await apiClient.post<CategoryResponse>('/products/categories', data);
	return response.data;
}

export async function updateProductCategory(
	id: number,
	data: Partial<ProductCategoryInput>
): Promise<ProductCategory> {
	const response = await apiClient.put<CategoryResponse>(`/products/categories/${id}`, data);
	return response.data;
}

export async function deleteProductCategory(id: number): Promise<void> {
	await apiClient.delete(`/products/categories/${id}`);
}

// ============= Quote API =============

export async function getQuotes(params?: {
	status?: QuoteStatus;
	deal_id?: number;
	contact_id?: number;
	company_id?: number;
	search?: string;
	page?: number;
	per_page?: number;
}): Promise<QuoteListResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.status) queryParams.status = params.status;
	if (params?.deal_id) queryParams.deal_id = String(params.deal_id);
	if (params?.contact_id) queryParams.contact_id = String(params.contact_id);
	if (params?.company_id) queryParams.company_id = String(params.company_id);
	if (params?.search) queryParams.search = params.search;
	if (params?.page) queryParams.page = String(params.page);
	if (params?.per_page) queryParams.per_page = String(params.per_page);

	return await apiClient.get<QuoteListResponse>('/quotes', queryParams);
}

export async function getQuote(id: number): Promise<Quote> {
	const response = await apiClient.get<QuoteResponse>(`/quotes/${id}`);
	return response.data;
}

export async function createQuote(data: QuoteInput): Promise<Quote> {
	const response = await apiClient.post<QuoteResponse>('/quotes', data);
	return response.data;
}

export async function updateQuote(id: number, data: Partial<QuoteInput>): Promise<Quote> {
	const response = await apiClient.put<QuoteResponse>(`/quotes/${id}`, data);
	return response.data;
}

export async function deleteQuote(id: number): Promise<void> {
	await apiClient.delete(`/quotes/${id}`);
}

export async function sendQuote(
	id: number,
	data: { to_email: string; message?: string }
): Promise<Quote> {
	const response = await apiClient.post<QuoteResponse>(`/quotes/${id}/send`, data);
	return response.data;
}

export async function duplicateQuote(id: number): Promise<Quote> {
	const response = await apiClient.post<QuoteResponse>(`/quotes/${id}/duplicate`);
	return response.data;
}

export async function getQuotePdf(id: number): Promise<PdfData> {
	const response = await apiClient.get<PdfResponse>(`/quotes/${id}/pdf`);
	return response.data;
}

export async function convertQuoteToInvoice(id: number): Promise<Invoice> {
	const response = await apiClient.post<InvoiceResponse>(`/quotes/${id}/convert-to-invoice`);
	return response.data;
}

export async function getQuoteStats(): Promise<QuoteStats> {
	const response = await apiClient.get<QuoteStatsResponse>('/quotes/stats');
	return response.stats;
}

// Quote Templates
export async function getQuoteTemplates(): Promise<QuoteTemplate[]> {
	const response = await apiClient.get<TemplateListResponse>('/quotes/templates');
	return response.data;
}

export async function createQuoteTemplate(data: QuoteTemplateInput): Promise<QuoteTemplate> {
	const response = await apiClient.post<TemplateResponse>('/quotes/templates', data);
	return response.data;
}

export async function updateQuoteTemplate(
	id: number,
	data: Partial<QuoteTemplateInput>
): Promise<QuoteTemplate> {
	const response = await apiClient.put<TemplateResponse>(`/quotes/templates/${id}`, data);
	return response.data;
}

export async function deleteQuoteTemplate(id: number): Promise<void> {
	await apiClient.delete(`/quotes/templates/${id}`);
}

// ============= Invoice API =============

export async function getInvoices(params?: {
	status?: InvoiceStatus;
	deal_id?: number;
	contact_id?: number;
	company_id?: number;
	overdue?: boolean;
	search?: string;
	page?: number;
	per_page?: number;
}): Promise<InvoiceListResponse> {
	const queryParams: Record<string, string> = {};
	if (params?.status) queryParams.status = params.status;
	if (params?.deal_id) queryParams.deal_id = String(params.deal_id);
	if (params?.contact_id) queryParams.contact_id = String(params.contact_id);
	if (params?.company_id) queryParams.company_id = String(params.company_id);
	if (params?.overdue) queryParams.overdue = 'true';
	if (params?.search) queryParams.search = params.search;
	if (params?.page) queryParams.page = String(params.page);
	if (params?.per_page) queryParams.per_page = String(params.per_page);

	return await apiClient.get<InvoiceListResponse>('/invoices', queryParams);
}

export async function getInvoice(id: number): Promise<Invoice> {
	const response = await apiClient.get<InvoiceResponse>(`/invoices/${id}`);
	return response.data;
}

export async function createInvoice(data: InvoiceInput): Promise<Invoice> {
	const response = await apiClient.post<InvoiceResponse>('/invoices', data);
	return response.data;
}

export async function updateInvoice(id: number, data: Partial<InvoiceInput>): Promise<Invoice> {
	const response = await apiClient.put<InvoiceResponse>(`/invoices/${id}`, data);
	return response.data;
}

export async function deleteInvoice(id: number): Promise<void> {
	await apiClient.delete(`/invoices/${id}`);
}

export async function sendInvoice(
	id: number,
	data: { to_email: string; message?: string }
): Promise<Invoice> {
	const response = await apiClient.post<InvoiceResponse>(`/invoices/${id}/send`, data);
	return response.data;
}

export async function getInvoicePdf(id: number): Promise<PdfData> {
	const response = await apiClient.get<PdfResponse>(`/invoices/${id}/pdf`);
	return response.data;
}

export async function cancelInvoice(id: number): Promise<Invoice> {
	const response = await apiClient.post<InvoiceResponse>(`/invoices/${id}/cancel`);
	return response.data;
}

export async function recordPayment(id: number, data: InvoicePaymentInput): Promise<InvoicePayment> {
	const response = await apiClient.post<PaymentResponse>(`/invoices/${id}/payments`, data);
	return response.data;
}

export async function deletePayment(invoiceId: number, paymentId: number): Promise<void> {
	await apiClient.delete(`/invoices/${invoiceId}/payments/${paymentId}`);
}

export async function getInvoiceStats(): Promise<InvoiceStats> {
	const response = await apiClient.get<InvoiceStatsResponse>('/invoices/stats');
	return response.stats;
}

// ============= Public Quote API =============

export async function getPublicQuote(token: string): Promise<PublicQuoteData> {
	const response = await apiClient.get<PublicQuoteResponse>(`/quote/${token}`);
	return response.data;
}

export async function acceptPublicQuote(
	token: string,
	data: { accepted_by: string; signature?: string }
): Promise<PublicQuoteAcceptResponse['data']> {
	const response = await apiClient.post<PublicQuoteAcceptResponse>(`/quote/${token}/accept`, data);
	return response.data;
}

export async function rejectPublicQuote(
	token: string,
	data: { rejected_by: string; reason?: string }
): Promise<PublicQuoteRejectResponse['data']> {
	const response = await apiClient.post<PublicQuoteRejectResponse>(`/quote/${token}/reject`, data);
	return response.data;
}

export async function getPublicQuotePdf(token: string): Promise<PdfData> {
	const response = await apiClient.get<PdfResponse>(`/quote/${token}/pdf`);
	return response.data;
}

// ============= Tax Rates API =============

export async function getTaxRates(params?: { active_only?: boolean }): Promise<TaxRate[]> {
	const queryParams: Record<string, string> = {};
	if (params?.active_only !== undefined) queryParams.active_only = String(params.active_only);

	const response = await apiClient.get<TaxRateListResponse>('/tax-rates', queryParams);
	return response.data;
}

export async function getTaxRate(id: number): Promise<TaxRate> {
	const response = await apiClient.get<TaxRateResponse>(`/tax-rates/${id}`);
	return response.data;
}

export async function createTaxRate(data: TaxRateInput): Promise<TaxRate> {
	const response = await apiClient.post<TaxRateResponse>('/tax-rates', data);
	return response.data;
}

export async function updateTaxRate(id: number, data: Partial<TaxRateInput>): Promise<TaxRate> {
	const response = await apiClient.put<TaxRateResponse>(`/tax-rates/${id}`, data);
	return response.data;
}

export async function deleteTaxRate(id: number): Promise<void> {
	await apiClient.delete(`/tax-rates/${id}`);
}
