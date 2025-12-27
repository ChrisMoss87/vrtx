import { test as base, expect, Page } from '@playwright/test';

/**
 * Test user credentials for TechCo tenant (primary test tenant)
 */
export const TEST_USER = {
	email: 'bob@techco.com',
	password: 'password123',
	name: 'Bob TechCo'
};

/**
 * Test user credentials for TechCo tenant
 */
export const TEST_USER_TECHCO = {
	email: 'bob@techco.com',
	password: 'password123',
	name: 'Bob TechCo'
};

/**
 * Alternative test user for multi-user scenarios
 */
export const TEST_USER_ALT = {
	email: 'alice@techco.com',
	password: 'password123',
	name: 'Alice TechCo'
};

/**
 * Admin test user for admin-only operations
 */
export const TEST_USER_ADMIN = {
	email: 'admin@techco.com',
	password: 'password123',
	name: 'Admin TechCo'
};

/**
 * Login helper function with rate limit handling
 */
export async function login(page: Page, credentials = TEST_USER, retries = 3) {
	for (let attempt = 1; attempt <= retries; attempt++) {
		await page.goto('/login');

		// Wait for the page to be fully hydrated (JavaScript loaded)
		await page.waitForLoadState('networkidle');

		// Check if we're already logged in (redirected to dashboard)
		if (page.url().includes('/dashboard')) {
			return;
		}

		// Check for rate limiting message and wait if present
		const rateLimitError = page.locator('text=/Too Many Attempts|rate limit/i');
		if (await rateLimitError.isVisible({ timeout: 1000 }).catch(() => false)) {
			if (attempt < retries) {
				// Wait before retrying (exponential backoff)
				await page.waitForTimeout(5000 * attempt);
				continue;
			}
			throw new Error('Rate limited after multiple attempts');
		}

		// Fill the email field
		const emailInput = page.locator('input[name="email"], input[type="email"]').first();
		await emailInput.waitFor({ state: 'visible' });
		await emailInput.fill(credentials.email);

		// Fill the password field
		const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
		await passwordInput.fill(credentials.password);

		// Click the submit button
		await page.locator('button[type="submit"]').click();

		// Wait for either success navigation or error
		try {
			await page.waitForURL('**/dashboard', { timeout: 10000 });
			return; // Success
		} catch {
			// Check for rate limiting or credential errors
			const errorMessage = page.locator('text=/Too Many Attempts|credentials are incorrect/i');
			if (await errorMessage.isVisible({ timeout: 1000 }).catch(() => false)) {
				const errorText = await errorMessage.textContent();
				if (errorText?.includes('Too Many')) {
					if (attempt < retries) {
						await page.waitForTimeout(5000 * attempt);
						continue;
					}
				}
			}
			throw new Error(`Login failed on attempt ${attempt}`);
		}
	}
}

/**
 * Logout helper function
 */
export async function logout(page: Page) {
	// Open user menu and click logout
	const userMenu = page.locator('[data-testid="user-menu"], button:has-text("John")').first();
	if (await userMenu.isVisible()) {
		await userMenu.click();
		await page.click('text=Logout');
		await page.waitForURL('**/login');
	}
}

/**
 * Navigate to a module's records page
 */
export async function navigateToModule(page: Page, moduleApiName: string) {
	await page.goto(`/records/${moduleApiName}`);
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to reports page
 */
export async function navigateToReports(page: Page) {
	await page.goto('/reports');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to dashboards page
 */
export async function navigateToDashboards(page: Page) {
	await page.goto('/dashboards');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to pipelines page
 */
export async function navigateToPipelines(page: Page) {
	await page.goto('/pipelines');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to workflows page
 */
export async function navigateToWorkflows(page: Page) {
	await page.goto('/admin/workflows');
	await page.waitForLoadState('networkidle');
}

/**
 * Wait for toast notification
 */
export async function waitForToast(page: Page, text?: string) {
	const toastLocator = text
		? page.locator(`[data-sonner-toast]:has-text("${text}")`)
		: page.locator('[data-sonner-toast]');
	await expect(toastLocator.first()).toBeVisible({ timeout: 5000 });
}

/**
 * Wait for loading state to finish
 */
export async function waitForLoading(page: Page) {
	// Wait for any loading spinners to disappear
	const loadingIndicators = page.locator(
		'.animate-spin, [data-loading], [aria-busy="true"], .skeleton'
	);
	await loadingIndicators.first().waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
}

/**
 * Fill a form field by label
 */
export async function fillFormField(page: Page, label: string, value: string) {
	const field = page.locator(`label:has-text("${label}")`).locator('..').locator('input, textarea');
	await field.fill(value);
}

/**
 * Select an option in a select field by label
 */
export async function selectFormOption(page: Page, label: string, optionText: string) {
	// Click the select trigger
	const selectGroup = page.locator(`label:has-text("${label}")`).locator('..');
	const trigger = selectGroup.locator('button[role="combobox"], [data-select-trigger]');
	await trigger.click();

	// Click the option
	await page.locator(`[role="option"]:has-text("${optionText}")`).click();
}

// ============================================
// Additional Navigation Helpers
// ============================================

/**
 * Navigate to quotes page
 */
export async function navigateToQuotes(page: Page) {
	await page.goto('/quotes');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to invoices page
 */
export async function navigateToInvoices(page: Page) {
	await page.goto('/invoices');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to proposals page
 */
export async function navigateToProposals(page: Page) {
	await page.goto('/proposals');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to campaigns page
 */
export async function navigateToCampaigns(page: Page) {
	await page.goto('/marketing/campaigns');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to cadences page
 */
export async function navigateToCadences(page: Page) {
	await page.goto('/marketing/cadences');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to landing pages
 */
export async function navigateToLandingPages(page: Page) {
	await page.goto('/landing-pages');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to users management page
 */
export async function navigateToUsers(page: Page) {
	await page.goto('/settings/users');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to roles management page
 */
export async function navigateToRoles(page: Page) {
	await page.goto('/settings/roles');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to blueprints page
 */
export async function navigateToBlueprints(page: Page) {
	await page.goto('/admin/blueprints');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to approvals page
 */
export async function navigateToApprovals(page: Page) {
	await page.goto('/approvals');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to audit logs page
 */
export async function navigateToAuditLogs(page: Page) {
	await page.goto('/admin/audit-logs');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to email inbox
 */
export async function navigateToEmail(page: Page) {
	await page.goto('/email');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to scheduling pages
 */
export async function navigateToScheduling(page: Page) {
	await page.goto('/settings/scheduling');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to deal rooms
 */
export async function navigateToDealRooms(page: Page) {
	await page.goto('/deal-rooms');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to forecasting
 */
export async function navigateToForecasting(page: Page) {
	await page.goto('/forecasts');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to CMS pages
 */
export async function navigateToCmsPages(page: Page) {
	await page.goto('/cms/pages');
	await page.waitForLoadState('networkidle');
}

/**
 * Navigate to support tickets
 */
export async function navigateToTickets(page: Page) {
	await page.goto('/support');
	await page.waitForLoadState('networkidle');
}

// ============================================
// Dialog and Modal Helpers
// ============================================

/**
 * Confirm or cancel a dialog
 */
export async function confirmDialog(page: Page, action: 'confirm' | 'cancel' = 'confirm') {
	const dialog = page.locator('[role="alertdialog"], [role="dialog"]').first();
	await expect(dialog).toBeVisible({ timeout: 5000 });

	if (action === 'confirm') {
		await dialog
			.locator('button:has-text("Confirm"), button:has-text("Delete"), button:has-text("Yes"), button:has-text("OK")')
			.first()
			.click();
	} else {
		await dialog
			.locator('button:has-text("Cancel"), button:has-text("No"), button:has-text("Close")')
			.first()
			.click();
	}
}

/**
 * Close any open modal
 */
export async function closeModal(page: Page) {
	const closeButton = page.locator('[role="dialog"] button[aria-label="Close"], [role="dialog"] button:has-text("Close")');
	if (await closeButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		await closeButton.click();
	}
}

// ============================================
// Table and List Helpers
// ============================================

/**
 * Click a row action in a table
 */
export async function clickRowAction(page: Page, rowText: string, actionText: string) {
	const row = page.locator(`tr:has-text("${rowText}")`).first();
	await row.locator('button[aria-haspopup="menu"], button:has([data-lucide="more-vertical"])').click();
	await page.locator(`[role="menuitem"]:has-text("${actionText}")`).click();
}

/**
 * Select a row in a table by clicking its checkbox
 */
export async function selectTableRow(page: Page, rowText: string) {
	const row = page.locator(`tr:has-text("${rowText}")`).first();
	await row.locator('input[type="checkbox"]').check();
}

/**
 * Select all rows in a table
 */
export async function selectAllRows(page: Page) {
	const selectAllCheckbox = page.locator('thead input[type="checkbox"]').first();
	await selectAllCheckbox.check();
}

/**
 * Get the count of rows in a table
 */
export async function getTableRowCount(page: Page): Promise<number> {
	return page.locator('tbody tr').count();
}

/**
 * Search in a table/list
 */
export async function searchInTable(page: Page, searchText: string) {
	const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]').first();
	await searchInput.fill(searchText);
	await searchInput.press('Enter');
	await waitForLoading(page);
}

// ============================================
// Form Helpers
// ============================================

/**
 * Upload a file to a file input
 */
export async function uploadFile(page: Page, selector: string, filePath: string) {
	const input = page.locator(selector);
	await input.setInputFiles(filePath);
	await waitForLoading(page);
}

/**
 * Select a dropdown option by clicking the trigger and then the option
 */
export async function selectDropdownOption(page: Page, triggerText: string, optionText: string) {
	await page.locator(`button:has-text("${triggerText}")`).first().click();
	await page.locator(`[role="option"]:has-text("${optionText}")`).click();
}

/**
 * Fill a date input
 */
export async function fillDateField(page: Page, label: string, date: string) {
	const field = page.locator(`label:has-text("${label}")`).locator('..').locator('input[type="date"]');
	await field.fill(date);
}

/**
 * Toggle a checkbox by label
 */
export async function toggleCheckbox(page: Page, label: string, check = true) {
	const checkbox = page.locator(`label:has-text("${label}")`).locator('input[type="checkbox"]');
	if (check) {
		await checkbox.check();
	} else {
		await checkbox.uncheck();
	}
}

// ============================================
// Tab and Filter Helpers
// ============================================

/**
 * Click a tab by its text
 */
export async function clickTab(page: Page, tabText: string) {
	await page.locator(`[role="tab"]:has-text("${tabText}")`).click();
	await waitForLoading(page);
}

/**
 * Apply a filter by clicking filter button and selecting option
 */
export async function applyFilter(page: Page, filterName: string, value: string) {
	// Click filter button
	await page.locator(`button:has-text("${filterName}"), [data-filter="${filterName}"]`).click();
	// Select option
	await page.locator(`[role="option"]:has-text("${value}"), [role="menuitem"]:has-text("${value}")`).click();
	await waitForLoading(page);
}

/**
 * Clear all filters
 */
export async function clearFilters(page: Page) {
	const clearButton = page.locator('button:has-text("Clear Filters"), button:has-text("Reset")');
	if (await clearButton.isVisible({ timeout: 1000 }).catch(() => false)) {
		await clearButton.click();
		await waitForLoading(page);
	}
}

// ============================================
// Assertion Helpers
// ============================================

/**
 * Assert that a toast message appears
 */
export async function expectToast(page: Page, text: string) {
	const toast = page.locator(`[data-sonner-toast]:has-text("${text}")`);
	await expect(toast).toBeVisible({ timeout: 5000 });
}

/**
 * Assert that the page URL matches a pattern
 */
export async function expectUrl(page: Page, pattern: RegExp | string) {
	if (typeof pattern === 'string') {
		await expect(page).toHaveURL(new RegExp(pattern));
	} else {
		await expect(page).toHaveURL(pattern);
	}
}

/**
 * Assert that an element with text is visible
 */
export async function expectVisible(page: Page, text: string) {
	await expect(page.locator(`text=${text}`).first()).toBeVisible();
}

/**
 * Assert that a table contains a row with specific text
 */
export async function expectTableRow(page: Page, text: string) {
	await expect(page.locator(`tbody tr:has-text("${text}")`).first()).toBeVisible();
}

/**
 * Custom test fixture with authenticated user
 */
export const test = base.extend<{ authenticatedPage: Page }>({
	authenticatedPage: async ({ page }, use) => {
		await login(page);
		await use(page);
	}
});

export { expect };
