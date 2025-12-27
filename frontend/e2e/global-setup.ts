import { chromium, FullConfig } from '@playwright/test';
import { TEST_USER } from './fixtures';

/**
 * Global setup that runs once before all tests.
 * Authenticates and saves the storage state for reuse.
 */
async function globalSetup(config: FullConfig) {
	const { baseURL } = config.projects[0].use;

	const browser = await chromium.launch();
	const context = await browser.newContext();
	const page = await context.newPage();

	console.log('Global Setup: Authenticating test user...');

	// Navigate to login page
	await page.goto(`${baseURL}/login`);
	await page.waitForLoadState('networkidle');

	// Check if already logged in
	if (!page.url().includes('/dashboard')) {
		// Fill credentials
		const emailInput = page.locator('input[name="email"], input[type="email"]').first();
		await emailInput.waitFor({ state: 'visible', timeout: 10000 });
		await emailInput.fill(TEST_USER.email);

		const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
		await passwordInput.fill(TEST_USER.password);

		// Submit login
		await page.locator('button[type="submit"]').click();

		// Wait for successful login
		await page.waitForURL('**/dashboard', { timeout: 30000 });
	}

	console.log('Global Setup: Authentication successful, saving storage state...');

	// Save signed-in state to file
	await context.storageState({ path: 'e2e/.auth/user.json' });

	await browser.close();
}

export default globalSetup;
