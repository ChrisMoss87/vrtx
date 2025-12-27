import { APIRequestContext, request } from '@playwright/test';

/**
 * API Helpers
 * Direct API calls for faster test setup and teardown
 */

const BASE_URL = process.env.BASE_URL || 'http://techco.vrtx.local';

interface AuthTokens {
	accessToken: string;
	tokenType: string;
}

/**
 * Get authentication token via API
 */
export async function getAuthToken(
	email = 'bob@techco.com',
	password = 'password123'
): Promise<AuthTokens> {
	const context = await request.newContext({ baseURL: BASE_URL });

	const response = await context.post('/api/v1/auth/login', {
		data: { email, password }
	});

	if (!response.ok()) {
		throw new Error(`Failed to authenticate: ${response.status()}`);
	}

	const data = await response.json();
	await context.dispose();

	return {
		accessToken: data.token || data.access_token,
		tokenType: data.token_type || 'Bearer'
	};
}

/**
 * Create an authenticated API context
 */
export async function createAuthenticatedContext(): Promise<APIRequestContext> {
	const tokens = await getAuthToken();

	return request.newContext({
		baseURL: BASE_URL,
		extraHTTPHeaders: {
			Authorization: `${tokens.tokenType} ${tokens.accessToken}`,
			Accept: 'application/json',
			'Content-Type': 'application/json'
		}
	});
}

/**
 * Create a contact via API
 */
export async function createContactViaApi(
	ctx: APIRequestContext,
	data: {
		first_name?: string;
		last_name?: string;
		email?: string;
		phone?: string;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const contactData = {
		first_name: data.first_name || `Test${Date.now()}`,
		last_name: data.last_name || 'Contact',
		email: data.email || `test-${Date.now()}@example.com`,
		phone: data.phone || ''
	};

	const response = await ctx.post('/api/v1/records/contacts', {
		data: contactData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create contact: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a lead via API
 */
export async function createLeadViaApi(
	ctx: APIRequestContext,
	data: {
		company?: string;
		first_name?: string;
		last_name?: string;
		email?: string;
		status?: string;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const leadData = {
		company: data.company || `Test Company ${Date.now()}`,
		first_name: data.first_name || 'Test',
		last_name: data.last_name || 'Lead',
		email: data.email || `lead-${Date.now()}@example.com`,
		status: data.status || 'new'
	};

	const response = await ctx.post('/api/v1/records/leads', {
		data: leadData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create lead: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a deal via API
 */
export async function createDealViaApi(
	ctx: APIRequestContext,
	data: {
		name?: string;
		amount?: number;
		stage?: string;
		close_date?: string;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const dealData = {
		name: data.name || `Test Deal ${Date.now()}`,
		amount: data.amount || 10000,
		stage: data.stage || 'prospecting',
		close_date: data.close_date || new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
	};

	const response = await ctx.post('/api/v1/records/deals', {
		data: dealData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create deal: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a quote via API
 */
export async function createQuoteViaApi(
	ctx: APIRequestContext,
	data: {
		title?: string;
		valid_until?: string;
		line_items?: Array<{ description: string; quantity: number; unit_price: number }>;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const quoteData = {
		title: data.title || `Quote ${Date.now()}`,
		valid_until: data.valid_until || new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
		line_items: data.line_items || []
	};

	const response = await ctx.post('/api/v1/quotes', {
		data: quoteData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create quote: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create an invoice via API
 */
export async function createInvoiceViaApi(
	ctx: APIRequestContext,
	data: {
		title?: string;
		due_date?: string;
		line_items?: Array<{ description: string; quantity: number; unit_price: number }>;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const invoiceData = {
		title: data.title || `Invoice ${Date.now()}`,
		due_date: data.due_date || new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
		line_items: data.line_items || []
	};

	const response = await ctx.post('/api/v1/invoices', {
		data: invoiceData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create invoice: ${response.status()}`);
	}

	return response.json();
}

/**
 * Delete a record via API
 */
export async function deleteRecordViaApi(
	ctx: APIRequestContext,
	moduleApiName: string,
	recordId: number | string
): Promise<void> {
	const response = await ctx.delete(`/api/v1/records/${moduleApiName}/${recordId}`);

	if (!response.ok() && response.status() !== 404) {
		throw new Error(`Failed to delete record: ${response.status()}`);
	}
}

/**
 * Bulk delete records via API
 */
export async function bulkDeleteViaApi(
	ctx: APIRequestContext,
	moduleApiName: string,
	recordIds: Array<number | string>
): Promise<void> {
	const response = await ctx.post(`/api/v1/records/${moduleApiName}/bulk-delete`, {
		data: { ids: recordIds }
	});

	if (!response.ok()) {
		throw new Error(`Failed to bulk delete: ${response.status()}`);
	}
}

/**
 * Get a record via API
 */
export async function getRecordViaApi(
	ctx: APIRequestContext,
	moduleApiName: string,
	recordId: number | string
): Promise<{ id: number; [key: string]: unknown }> {
	const response = await ctx.get(`/api/v1/records/${moduleApiName}/${recordId}`);

	if (!response.ok()) {
		throw new Error(`Failed to get record: ${response.status()}`);
	}

	return response.json();
}

/**
 * Update a record via API
 */
export async function updateRecordViaApi(
	ctx: APIRequestContext,
	moduleApiName: string,
	recordId: number | string,
	data: Record<string, unknown>
): Promise<{ id: number; [key: string]: unknown }> {
	const response = await ctx.patch(`/api/v1/records/${moduleApiName}/${recordId}`, {
		data
	});

	if (!response.ok()) {
		throw new Error(`Failed to update record: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a workflow via API
 */
export async function createWorkflowViaApi(
	ctx: APIRequestContext,
	data: {
		name?: string;
		module_id?: number;
		trigger_type?: string;
		is_active?: boolean;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const workflowData = {
		name: data.name || `Test Workflow ${Date.now()}`,
		module_id: data.module_id || 1,
		trigger_type: data.trigger_type || 'record_created',
		is_active: data.is_active ?? false
	};

	const response = await ctx.post('/api/v1/workflows', {
		data: workflowData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create workflow: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a dashboard via API
 */
export async function createDashboardViaApi(
	ctx: APIRequestContext,
	data: {
		name?: string;
		description?: string;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const dashboardData = {
		name: data.name || `Test Dashboard ${Date.now()}`,
		description: data.description || 'Test dashboard created via API'
	};

	const response = await ctx.post('/api/v1/dashboards', {
		data: dashboardData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create dashboard: ${response.status()}`);
	}

	return response.json();
}

/**
 * Create a report via API
 */
export async function createReportViaApi(
	ctx: APIRequestContext,
	data: {
		name?: string;
		module_id?: number;
		type?: string;
	} = {}
): Promise<{ id: number; [key: string]: unknown }> {
	const reportData = {
		name: data.name || `Test Report ${Date.now()}`,
		module_id: data.module_id || 1,
		type: data.type || 'tabular'
	};

	const response = await ctx.post('/api/v1/reports', {
		data: reportData
	});

	if (!response.ok()) {
		throw new Error(`Failed to create report: ${response.status()}`);
	}

	return response.json();
}

/**
 * Cleanup helper - delete all test data matching a pattern
 */
export async function cleanupTestData(
	ctx: APIRequestContext,
	moduleApiName: string,
	searchPattern: string
): Promise<void> {
	// Get records matching pattern
	const response = await ctx.get(`/api/v1/records/${moduleApiName}`, {
		params: { search: searchPattern, per_page: 100 }
	});

	if (!response.ok()) {
		return;
	}

	const data = await response.json();
	const records = data.data || [];

	// Delete each matching record
	for (const record of records) {
		await deleteRecordViaApi(ctx, moduleApiName, record.id).catch(() => {});
	}
}
