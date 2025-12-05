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
 * Login helper function
 */
export async function login(page: Page, credentials = TEST_USER) {
	await page.goto('/login');

	// Wait for the page to be fully hydrated (JavaScript loaded)
	await page.waitForLoadState('networkidle');

	// Fill the email field
	const emailInput = page.locator('input[name="email"], input[type="email"]').first();
	await emailInput.waitFor({ state: 'visible' });
	await emailInput.fill(credentials.email);

	// Fill the password field
	const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
	await passwordInput.fill(credentials.password);

	// Click the submit button and wait for navigation
	await Promise.all([
		page.waitForURL('**/dashboard', { timeout: 15000 }),
		page.locator('button[type="submit"]').click()
	]);
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
