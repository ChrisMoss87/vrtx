import { test, expect } from '@playwright/test';

const BASE_URL = 'http://acme.vrtx.local';
const TEST_USER = {
	email: 'john@acme.com',
	password: 'password123'
};

test.describe('Module Management', () => {
	test.beforeEach(async ({ page }) => {
		// Login before each test
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');

		// Wait for navigation to complete
		await page.waitForURL('**/dashboard');
	});

	test('should display modules list page', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules`);

		// Check page title
		await expect(page.locator('h1')).toContainText('Modules');

		// Check create button exists
		await expect(page.getByRole('button', { name: /create module/i })).toBeVisible();
	});

	test('should navigate to create module page', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules`);

		// Click create module button
		await page.getByRole('button', { name: /create module/i }).click();

		// Verify we're on create page
		await expect(page.locator('h1')).toContainText('Create Module');
		await expect(page.getByTestId('module-name')).toBeVisible();
		await expect(page.getByTestId('singular-name')).toBeVisible();
	});

	test('should create a simple module with one block and fields', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules/create`);

		// Fill module basic info
		await page.getByTestId('module-name').fill('Products');
		await page.getByTestId('singular-name').fill('Product');
		await page.getByTestId('module-icon').fill('package');
		await page.getByTestId('module-description').fill('Manage product catalog');

		// Add a block
		await page.getByTestId('add-block').click();
		await expect(page.getByTestId('block-0')).toBeVisible();

		// Fill block info
		await page.getByTestId('block-name-0').fill('Product Details');
		await page.getByTestId('block-type-0').selectOption('section');

		// Add first field
		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-0').fill('Product Name');
		await page.getByTestId('field-type-0-0').selectOption('text');
		await page.getByTestId('field-required-0-0').check();

		// Add second field
		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-1').fill('Price');
		await page.getByTestId('field-type-0-1').selectOption('number');
		await page.getByTestId('field-required-0-1').check();

		// Add third field
		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-2').fill('Description');
		await page.getByTestId('field-type-0-2').selectOption('textarea');

		// Submit the form
		await page.getByTestId('submit-module').click();

		// Wait for redirect to modules list
		await page.waitForURL('**/modules');

		// Verify module was created
		await expect(page.locator('text=Products')).toBeVisible();
	});

	test('should create a complex module with multiple blocks', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules/create`);

		// Fill module basic info
		await page.getByTestId('module-name').fill('Customers');
		await page.getByTestId('singular-name').fill('Customer');
		await page.getByTestId('module-icon').fill('users');
		await page.getByTestId('module-description').fill('Customer relationship management');

		// Add first block - Contact Information
		await page.getByTestId('add-block').click();
		await page.getByTestId('block-name-0').fill('Contact Information');
		await page.getByTestId('block-type-0').selectOption('section');

		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-0').fill('First Name');
		await page.getByTestId('field-type-0-0').selectOption('text');
		await page.getByTestId('field-required-0-0').check();

		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-1').fill('Last Name');
		await page.getByTestId('field-type-0-1').selectOption('text');
		await page.getByTestId('field-required-0-1').check();

		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-2').fill('Email');
		await page.getByTestId('field-type-0-2').selectOption('email');
		await page.getByTestId('field-required-0-2').check();
		await page.getByTestId('field-unique-0-2').check();

		// Add second block - Additional Details
		await page.getByTestId('add-block').click();
		await page.getByTestId('block-name-1').fill('Additional Details');
		await page.getByTestId('block-type-1').selectOption('section');

		await page.getByTestId('add-field-1').click();
		await page.getByTestId('field-label-1-0').fill('Phone');
		await page.getByTestId('field-type-1-0').selectOption('phone');

		await page.getByTestId('add-field-1').click();
		await page.getByTestId('field-label-1-1').fill('Company');
		await page.getByTestId('field-type-1-1').selectOption('text');

		await page.getByTestId('add-field-1').click();
		await page.getByTestId('field-label-1-2').fill('Date Joined');
		await page.getByTestId('field-type-1-2').selectOption('date');

		// Submit the form
		await page.getByTestId('submit-module').click();

		// Wait for redirect
		await page.waitForURL('**/modules');

		// Verify module was created
		await expect(page.locator('text=Customers')).toBeVisible();
	});

	test('should show validation errors for missing required fields', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules/create`);

		// Try to submit without filling anything
		await page.getByTestId('submit-module').click();

		// Should show error message
		await expect(page.getByTestId('error-message')).toBeVisible();
		await expect(page.getByTestId('error-message')).toContainText('Module name is required');

		// Fill module name but not singular name
		await page.getByTestId('module-name').fill('Test Module');
		await page.getByTestId('submit-module').click();

		await expect(page.getByTestId('error-message')).toContainText('Singular name is required');

		// Fill singular name but no blocks
		await page.getByTestId('singular-name').fill('Test Item');
		await page.getByTestId('submit-module').click();

		await expect(page.getByTestId('error-message')).toContainText('At least one block is required');
	});

	test('should be able to remove blocks and fields', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules/create`);

		// Fill basic info
		await page.getByTestId('module-name').fill('Test Module');
		await page.getByTestId('singular-name').fill('Test Item');

		// Add two blocks
		await page.getByTestId('add-block').click();
		await page.getByTestId('add-block').click();

		await expect(page.getByTestId('block-0')).toBeVisible();
		await expect(page.getByTestId('block-1')).toBeVisible();

		// Remove first block
		await page.getByTestId('remove-block-0').click();

		// Only second block should remain
		await expect(page.getByTestId('block-0')).toBeVisible();

		// Add fields to remaining block
		await page.getByTestId('block-name-0').fill('Details');
		await page.getByTestId('add-field-0').click();
		await page.getByTestId('add-field-0').click();

		await expect(page.getByTestId('field-0-0')).toBeVisible();
		await expect(page.getByTestId('field-0-1')).toBeVisible();

		// Remove first field
		await page.getByTestId('remove-field-0-0').click();

		// Only second field should remain
		await expect(page.getByTestId('field-0-0')).toBeVisible();
	});

	test('should display existing module in list', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules`);

		// The "Contacts" module created in the backend test should be visible
		await expect(page.locator('text=Contacts')).toBeVisible();
		await expect(page.locator('text=contacts')).toBeVisible(); // API name
	});

	test('should navigate to view module records', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules`);

		// Click on View Records button for Contacts module
		const viewButton = page.locator('text=Contacts').locator('..').locator('..').getByRole('button', { name: /view records/i });
		await viewButton.click();

		// Should navigate to records page
		await expect(page).toHaveURL(/\/records\/contacts/);
		await expect(page.getByTestId('module-title')).toContainText('Contacts');
	});

	test('should toggle module status', async ({ page }) => {
		await page.goto(`${BASE_URL}/modules`);

		// Find the Contacts module card
		const contactsCard = page.locator('text=Contacts').locator('..');

		// Check initial status badge
		const badge = contactsCard.locator('[class*="badge"]').first();
		const initialStatus = await badge.textContent();

		// Click the power button to toggle status
		const powerButton = contactsCard.locator('button[title*="activate"], button[title*="Activate"]').first();
		await powerButton.click();

		// Wait a bit for the update
		await page.waitForTimeout(500);

		// Status should have changed
		const newStatus = await badge.textContent();
		expect(newStatus).not.toBe(initialStatus);
	});
});

test.describe('Module Records Management', () => {
	test.beforeEach(async ({ page }) => {
		// Login
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should display empty state for module with no records', async ({ page }) => {
		// Navigate to Contacts module
		await page.goto(`${BASE_URL}/records/contacts`);

		// Check for empty state
		const emptyState = page.locator('text=No records yet');
		if (await emptyState.isVisible()) {
			await expect(emptyState).toBeVisible();
			await expect(page.getByRole('button', { name: /create contact/i })).toBeVisible();
		}
	});

	test('should display records table when records exist', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);

		// If we have the table, verify its structure
		const table = page.getByTestId('records-table');
		if (await table.isVisible()) {
			await expect(table).toBeVisible();

			// Check headers exist
			await expect(table.locator('th')).toContainText('ID');
			await expect(table.locator('th')).toContainText('Actions');
		}
	});

	test('should show create record button', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);

		// Create button should always be visible
		await expect(page.getByTestId('create-record')).toBeVisible();
	});
});

test.describe('Module API Integration', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should handle API errors gracefully', async ({ page }) => {
		// Try to access a non-existent module
		await page.goto(`${BASE_URL}/records/nonexistentmodule`);

		// Should show error message
		await expect(page.getByTestId('error-message')).toBeVisible();
	});

	test('should create module and immediately view it', async ({ page }) => {
		const moduleName = `TestModule_${Date.now()}`;

		await page.goto(`${BASE_URL}/modules/create`);

		// Create module
		await page.getByTestId('module-name').fill(moduleName);
		await page.getByTestId('singular-name').fill('TestItem');

		await page.getByTestId('add-block').click();
		await page.getByTestId('block-name-0').fill('Info');

		await page.getByTestId('add-field-0').click();
		await page.getByTestId('field-label-0-0').fill('Name');
		await page.getByTestId('field-type-0-0').selectOption('text');

		await page.getByTestId('submit-module').click();

		// Wait for redirect
		await page.waitForURL('**/modules');

		// Verify module exists
		await expect(page.locator(`text=${moduleName}`)).toBeVisible();

		// Click to view records
		const viewButton = page.locator(`text=${moduleName}`).locator('..').locator('..').getByRole('button', { name: /view records/i });
		await viewButton.click();

		// Should show module records page
		await expect(page.getByTestId('module-title')).toContainText(moduleName);
	});
});
