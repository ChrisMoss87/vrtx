import { expect, test } from '@playwright/test';

const TEST_USER = {
	name: 'Test User',
	email: `test-${Date.now()}@example.com`,
	password: 'password123'
};

test.describe('Authentication Flow', () => {
	test('complete registration and login flow', async ({ page }) => {
		// Log console messages
		page.on('console', (msg) => console.log('PAGE LOG:', msg.text()));
		page.on('pageerror', (error) => console.log('PAGE ERROR:', error.message));

		// Navigate to register page
		await page.goto('/register');
		await expect(page).toHaveTitle(/Register/);

		// Fill registration form
		await page.fill('input[name="name"]', TEST_USER.name);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.fill('input[name="password_confirmation"]', TEST_USER.password);

		// Submit registration
		await page.click('button[type="submit"]');

		// Wait a bit for the request
		await page.waitForTimeout(2000);

		// Take screenshot for debugging
		await page.screenshot({ path: 'test-results/after-submit.png' });

		// Should redirect to dashboard
		await page.waitForURL('/dashboard', { timeout: 10000 });
		await expect(page).toHaveTitle(/Dashboard/);

		// Check user name is displayed
		await expect(page.getByText(`Welcome, ${TEST_USER.name}`)).toBeVisible();

		// Logout
		await page.click('button:has-text("Logout")');

		// Should redirect to login
		await page.waitForURL('/login', { timeout: 5000 });

		// Login with same credentials
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');

		// Should be back on dashboard
		await page.waitForURL('/dashboard', { timeout: 10000 });
		await expect(page).toHaveTitle(/Dashboard/);
		await expect(page.getByText(`Welcome, ${TEST_USER.name}`)).toBeVisible();
	});

	test('login with invalid credentials shows error', async ({ page }) => {
		await page.goto('/login');

		await page.fill('input[name="email"]', 'invalid@example.com');
		await page.fill('input[name="password"]', 'wrongpassword');
		await page.click('button[type="submit"]');

		// Should show error message
		await expect(page.locator('.bg-red-50')).toBeVisible();
	});

	test('registration with mismatched passwords shows error', async ({ page }) => {
		await page.goto('/register');

		await page.fill('input[name="name"]', 'Test User');
		await page.fill('input[name="email"]', 'test@example.com');
		await page.fill('input[name="password"]', 'password123');
		await page.fill('input[name="password_confirmation"]', 'different');

		await page.click('button[type="submit"]');

		// Should show error
		await expect(page.locator('.bg-red-50')).toBeVisible();
		await expect(page.getByText(/Passwords do not match/)).toBeVisible();
	});
});
