import { Page } from '@playwright/test';
import { waitForToast, waitForLoading } from '../fixtures';

/**
 * Test Data Factory
 * Provides utilities for creating test data through the UI
 */

/**
 * Generate a unique test identifier
 */
export function uniqueId(prefix = 'e2e'): string {
	return `${prefix}-${Date.now()}-${Math.random().toString(36).substring(7)}`;
}

/**
 * Generate unique test email
 */
export function uniqueEmail(prefix = 'test'): string {
	return `${prefix}-${Date.now()}@example.com`;
}

/**
 * Create a test contact record
 */
export async function createTestContact(
	page: Page,
	overrides: Partial<{
		firstName: string;
		lastName: string;
		email: string;
		phone: string;
		company: string;
	}> = {}
): Promise<{ id: string; name: string; email: string }> {
	const firstName = overrides.firstName || `Test${Date.now()}`;
	const lastName = overrides.lastName || 'Contact';
	const email = overrides.email || uniqueEmail('contact');

	await page.goto('/records/contacts/create');
	await page.waitForLoadState('networkidle');

	// Fill contact form
	await page.fill('input[name="first_name"], input[placeholder*="First"]', firstName);
	await page.fill('input[name="last_name"], input[placeholder*="Last"]', lastName);
	await page.fill('input[name="email"], input[type="email"]', email);

	if (overrides.phone) {
		await page.fill('input[name="phone"], input[type="tel"]', overrides.phone);
	}

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/records\/contacts\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, name: `${firstName} ${lastName}`, email };
}

/**
 * Create a test lead record
 */
export async function createTestLead(
	page: Page,
	overrides: Partial<{
		company: string;
		firstName: string;
		lastName: string;
		email: string;
		status: string;
	}> = {}
): Promise<{ id: string; company: string }> {
	const company = overrides.company || `Test Company ${Date.now()}`;
	const firstName = overrides.firstName || 'Test';
	const lastName = overrides.lastName || 'Lead';
	const email = overrides.email || uniqueEmail('lead');

	await page.goto('/records/leads/create');
	await page.waitForLoadState('networkidle');

	// Fill lead form
	await page.fill('input[name="company"], input[placeholder*="Company"]', company);
	await page.fill('input[name="first_name"], input[placeholder*="First"]', firstName);
	await page.fill('input[name="last_name"], input[placeholder*="Last"]', lastName);
	await page.fill('input[name="email"], input[type="email"]', email);

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/records\/leads\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, company };
}

/**
 * Create a test deal record
 */
export async function createTestDeal(
	page: Page,
	overrides: Partial<{
		name: string;
		amount: number;
		stage: string;
		closeDate: string;
	}> = {}
): Promise<{ id: string; name: string; amount: number }> {
	const name = overrides.name || `Test Deal ${Date.now()}`;
	const amount = overrides.amount || 10000;

	await page.goto('/records/deals/create');
	await page.waitForLoadState('networkidle');

	// Fill deal form
	await page.fill('input[name="name"], input[placeholder*="Deal Name"]', name);
	await page.fill('input[name="amount"], input[type="number"]', amount.toString());

	if (overrides.closeDate) {
		await page.fill('input[name="close_date"], input[type="date"]', overrides.closeDate);
	}

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/records\/deals\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, name, amount };
}

/**
 * Create a test quote
 */
export async function createTestQuote(
	page: Page,
	overrides: Partial<{
		title: string;
		validUntil: string;
		lineItems: Array<{ description: string; quantity: number; unitPrice: number }>;
	}> = {}
): Promise<{ id: string; title: string }> {
	const title = overrides.title || `Quote ${Date.now()}`;

	await page.goto('/quotes/new');
	await page.waitForLoadState('networkidle');

	// Fill quote form
	await page.fill('input[name="title"], input[placeholder*="Title"]', title);

	if (overrides.validUntil) {
		await page.fill('input[name="valid_until"], input[type="date"]', overrides.validUntil);
	}

	// Add line items if provided
	if (overrides.lineItems) {
		for (const item of overrides.lineItems) {
			await page.click('button:has-text("Add Line Item"), button:has-text("Add Item")');
			await page.fill('input[name="description"]:last-of-type', item.description);
			await page.fill('input[name="quantity"]:last-of-type', item.quantity.toString());
			await page.fill('input[name="unit_price"]:last-of-type', item.unitPrice.toString());
		}
	}

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/quotes\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, title };
}

/**
 * Create a test invoice
 */
export async function createTestInvoice(
	page: Page,
	overrides: Partial<{
		title: string;
		dueDate: string;
		lineItems: Array<{ description: string; quantity: number; unitPrice: number }>;
	}> = {}
): Promise<{ id: string; title: string }> {
	const title = overrides.title || `Invoice ${Date.now()}`;

	await page.goto('/invoices/new');
	await page.waitForLoadState('networkidle');

	// Fill invoice form
	await page.fill('input[name="title"], input[placeholder*="Title"]', title);

	if (overrides.dueDate) {
		await page.fill('input[name="due_date"], input[type="date"]', overrides.dueDate);
	}

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/invoices\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, title };
}

/**
 * Create a test campaign
 */
export async function createTestCampaign(
	page: Page,
	overrides: Partial<{
		name: string;
		type: string;
		status: string;
	}> = {}
): Promise<{ id: string; name: string }> {
	const name = overrides.name || `Campaign ${Date.now()}`;

	await page.goto('/marketing/campaigns/create');
	await page.waitForLoadState('networkidle');

	// Fill campaign form
	await page.fill('input[name="name"], input[placeholder*="Name"]', name);

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	// Extract record ID from URL
	await page.waitForURL(/\/marketing\/campaigns\/\d+/);
	const id = page.url().split('/').pop() || '';

	return { id, name };
}

/**
 * Create a test user (admin only)
 */
export async function createTestUser(
	page: Page,
	overrides: Partial<{
		name: string;
		email: string;
		role: string;
	}> = {}
): Promise<{ id: string; name: string; email: string }> {
	const name = overrides.name || `Test User ${Date.now()}`;
	const email = overrides.email || uniqueEmail('user');

	await page.goto('/settings/users');
	await page.waitForLoadState('networkidle');

	// Click create user button
	await page.click('button:has-text("Create User"), button:has-text("Add User")');
	await waitForLoading(page);

	// Fill user form
	await page.fill('input[name="name"]', name);
	await page.fill('input[name="email"]', email);

	if (overrides.role) {
		// Select role
		await page.click('button[role="combobox"]:has-text("Role")');
		await page.click(`[role="option"]:has-text("${overrides.role}")`);
	}

	// Submit form
	await page.click('button[type="submit"]:has-text("Save"), button:has-text("Create")');
	await waitForToast(page);

	const id = ''; // ID extraction depends on UI response

	return { id, name, email };
}

/**
 * Delete a record by navigating to it and clicking delete
 */
export async function deleteRecord(
	page: Page,
	moduleApiName: string,
	recordId: string
): Promise<void> {
	await page.goto(`/records/${moduleApiName}/${recordId}`);
	await page.waitForLoadState('networkidle');

	// Click actions menu
	await page.click('button:has-text("Actions"), button[aria-haspopup="menu"]');

	// Click delete option
	await page.click('[role="menuitem"]:has-text("Delete")');

	// Confirm deletion
	await page.click('button:has-text("Confirm"), button:has-text("Delete")');
	await waitForToast(page);
}

/**
 * Bulk delete records from list view
 */
export async function bulkDeleteRecords(page: Page, moduleApiName: string): Promise<void> {
	await page.goto(`/records/${moduleApiName}`);
	await page.waitForLoadState('networkidle');

	// Select all visible records
	const selectAllCheckbox = page.locator('thead input[type="checkbox"]').first();
	if (await selectAllCheckbox.isVisible()) {
		await selectAllCheckbox.check();
	}

	// Click bulk delete action
	await page.click('button:has-text("Delete Selected"), button:has-text("Bulk Delete")');

	// Confirm deletion
	await page.click('button:has-text("Confirm"), button:has-text("Delete")');
	await waitForToast(page);
}
