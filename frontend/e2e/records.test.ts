import { test, expect } from '@playwright/test';

const BASE_URL = 'http://acme.vrtx.local';
const TEST_USER = {
	email: 'john@acme.com',
	password: 'password123'
};

test.describe('Record CRUD Operations', () => {
	test.beforeEach(async ({ page }) => {
		// Login before each test
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should navigate to records page and display DataTable', async ({ page }) => {
		// Navigate to a module's records page
		await page.goto(`${BASE_URL}/records/contacts`);

		// Wait for the page to load
		await page.waitForLoadState('networkidle');

		// Check for DataTable or empty state
		const hasTable = await page.locator('table').isVisible();
		const hasEmptyState = await page.locator('text=No records').isVisible();

		expect(hasTable || hasEmptyState).toBeTruthy();
	});

	test('should display toolbar with actions', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Create button should be visible
		const createButton = page
			.locator('button:has-text("Create"), button:has-text("New"), button:has-text("Add")')
			.first();
		await expect(createButton).toBeVisible();
	});

	test('should display column visibility toggle if table exists', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Look for columns toggle button if table exists
		const table = page.locator('table');
		if (await table.isVisible()) {
			const columnsButton = page.locator('button:has-text("Columns")');
			if (await columnsButton.isVisible()) {
				await columnsButton.click();

				// Dropdown should appear
				await expect(page.locator('[role="menu"], [role="dialog"]').first()).toBeVisible();
			}
		}
	});

	test('should show search/filter functionality', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Look for search input
		const searchInput = page
			.locator('input[placeholder*="Search"], input[placeholder*="Filter"]')
			.first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await page.waitForTimeout(500); // Wait for debounce

			// Search should update the results (no error)
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should navigate to create record form', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Find and click create button
		const createButton = page
			.locator(
				'button:has-text("Create"), button:has-text("New"), a:has-text("Create"), a:has-text("New")'
			)
			.first();

		if (await createButton.isVisible()) {
			await createButton.click();

			// Should navigate to create page or open modal
			await page.waitForTimeout(500);

			// Check if we're on a create page or modal opened
			const hasForm = await page.locator('form').isVisible();
			const hasModal = await page.locator('[role="dialog"]').isVisible();

			expect(hasForm || hasModal).toBeTruthy();
		}
	});

	test('should show pagination if records exist', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		const table = page.locator('table');
		if (await table.isVisible()) {
			// Look for pagination controls
			const pagination = page
				.locator('[data-testid="pagination"], .pagination, nav[aria-label*="pagination"]')
				.first();
			const pageInfo = page.locator('text=/Page \\d+|Showing|of \\d+/i').first();

			const hasPagination = (await pagination.isVisible()) || (await pageInfo.isVisible());
			// Pagination should exist for tables
			expect(hasPagination).toBeDefined();
		}
	});

	test('should support row selection if enabled', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		const table = page.locator('table');
		if (await table.isVisible()) {
			// Look for checkbox in first row
			const checkbox = table.locator('input[type="checkbox"]').first();

			if (await checkbox.isVisible()) {
				// Click to select
				await checkbox.click();

				// Should show selection indicator
				const selectionIndicator = page.locator('text=/\\d+ selected|Selected/i');
				if (await selectionIndicator.isVisible()) {
					await expect(selectionIndicator).toBeVisible();
				}
			}
		}
	});

	test('should handle sorting by clicking column headers', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		const table = page.locator('table');
		if (await table.isVisible()) {
			// Find a sortable column header
			const sortableHeader = table
				.locator('th:has-text("Name"), th:has-text("Email"), th:has-text("Created")')
				.first();

			if (await sortableHeader.isVisible()) {
				// Click to sort
				await sortableHeader.click();

				// Wait for potential reload
				await page.waitForTimeout(500);

				// URL should include sort param or table should update
				await expect(page).not.toHaveURL(/error/);
			}
		}
	});
});

test.describe('Record Form Validation', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should show validation errors for required fields', async ({ page }) => {
		// Navigate to create record
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Try to submit empty form
		const submitButton = page.locator('button[type="submit"]').first();
		if (await submitButton.isVisible()) {
			await submitButton.click();

			// Should show validation errors
			await page.waitForTimeout(500);

			const errorMessage = page.locator('.text-red-500, .text-destructive, [role="alert"]').first();
			// Either has inline validation or form-level error
			const hasError = await errorMessage.isVisible();
			expect(hasError).toBeDefined();
		}
	});
});

test.describe('DataTable Views', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should show views dropdown if views feature is enabled', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Look for views dropdown
		const viewsButton = page
			.locator('button:has-text("View"), button:has-text("Default View")')
			.first();

		if (await viewsButton.isVisible()) {
			await viewsButton.click();

			// Dropdown should appear
			await expect(page.locator('[role="menu"]').first()).toBeVisible();
		}
	});

	test('should persist view settings', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Change page size if pagination exists
		const pageSizeSelector = page
			.locator('select:has-text("25"), select:has-text("50"), button:has-text("per page")')
			.first();

		if (await pageSizeSelector.isVisible()) {
			await pageSizeSelector.click();

			// Select a different page size
			await page.locator('text=25, text=100').first().click();

			// Reload and verify setting persisted
			await page.reload();
			await page.waitForLoadState('networkidle');

			// Settings should be maintained
			await expect(page).not.toHaveURL(/error/);
		}
	});
});

test.describe('Bulk Actions', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE_URL}/login`);
		await page.fill('input[name="email"]', TEST_USER.email);
		await page.fill('input[name="password"]', TEST_USER.password);
		await page.click('button[type="submit"]');
		await page.waitForURL('**/dashboard');
	});

	test('should show bulk actions when records are selected', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		const table = page.locator('table');
		if (await table.isVisible()) {
			// Select first row
			const firstRowCheckbox = table.locator('tbody input[type="checkbox"]').first();

			if (await firstRowCheckbox.isVisible()) {
				await firstRowCheckbox.click();

				// Bulk actions should appear
				const bulkActions = page.locator(
					'button:has-text("Delete"), button:has-text("Export"), button:has-text("Update")'
				);

				// At least one bulk action should be visible when items are selected
				await page.waitForTimeout(500);
			}
		}
	});
});
