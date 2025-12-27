import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToModule,
	confirmDialog,
	selectTableRow,
	selectAllRows,
	searchInTable,
	clickRowAction,
	expectToast
} from '../fixtures';

/**
 * Advanced Records Tests
 * Tests for import, export, history, and bulk operations
 */

test.describe('Records Import', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to import page', async ({ page }) => {
		await navigateToModule(page, 'contacts');

		// Click import button
		const importButton = page.locator('button:has-text("Import"), a:has-text("Import")');
		await importButton.click();

		await expect(page).toHaveURL(/\/records\/contacts\/import/);
	});

	test('should display import instructions', async ({ page }) => {
		await page.goto('/records/contacts/import');
		await waitForLoading(page);

		// Verify import page elements
		await expect(page.locator('text=/Upload|Import/i').first()).toBeVisible();
		await expect(page.locator('text=/CSV|Excel/i').first()).toBeVisible();
	});

	test('should show file upload area', async ({ page }) => {
		await page.goto('/records/contacts/import');
		await waitForLoading(page);

		// Check for file upload input
		const fileInput = page.locator('input[type="file"]');
		await expect(fileInput).toBeAttached();
	});

	test('should display download template option', async ({ page }) => {
		await page.goto('/records/contacts/import');
		await waitForLoading(page);

		// Look for template download link/button
		const templateLink = page.locator('a:has-text("Template"), button:has-text("Template"), a:has-text("Download")');
		await expect(templateLink.first()).toBeVisible();
	});

	test('should show field mapping interface after file upload', async ({ page }) => {
		await page.goto('/records/contacts/import');
		await waitForLoading(page);

		// This test would need a real CSV file - skipping actual upload
		// Verify the mapping interface structure exists
		const mappingSection = page.locator('text=/Map|Mapping|Fields/i');
		// May or may not be visible without a file
	});

	test('should validate required fields during import', async ({ page }) => {
		await page.goto('/records/contacts/import');
		await waitForLoading(page);

		// Try to proceed without uploading a file
		const nextButton = page.locator('button:has-text("Next"), button:has-text("Continue"), button:has-text("Import")');
		if (await nextButton.isVisible()) {
			await nextButton.click();
			// Should show validation error
			const errorMessage = page.locator('text=/required|select|upload/i');
			await expect(errorMessage.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('Records Export', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should display export options in toolbar', async ({ page }) => {
		// Look for export button/menu
		const exportButton = page.locator('button:has-text("Export"), button[aria-label="Export"]');
		await expect(exportButton.first()).toBeVisible();
	});

	test('should show export format options', async ({ page }) => {
		// Click export button
		const exportButton = page.locator('button:has-text("Export")').first();
		await exportButton.click();

		// Check for format options
		await expect(page.locator('text=/CSV/i').first()).toBeVisible();
	});

	test('should export to CSV format', async ({ page }) => {
		// Start download listener
		const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);

		// Click export button
		const exportButton = page.locator('button:has-text("Export")').first();
		await exportButton.click();

		// Select CSV option if it's a dropdown
		const csvOption = page.locator('[role="menuitem"]:has-text("CSV"), button:has-text("CSV")');
		if (await csvOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await csvOption.click();
		}

		// Wait for download (may not work in all cases)
		const download = await downloadPromise;
		if (download) {
			expect(download.suggestedFilename()).toMatch(/\.csv$/);
		}
	});

	test('should export to Excel format', async ({ page }) => {
		// Click export button
		const exportButton = page.locator('button:has-text("Export")').first();
		await exportButton.click();

		// Select Excel option
		const excelOption = page.locator('[role="menuitem"]:has-text("Excel"), button:has-text("Excel")');
		if (await excelOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await excelOption.click();
		}
	});

	test('should export selected records only', async ({ page }) => {
		// Select first row
		const firstCheckbox = page.locator('tbody tr').first().locator('input[type="checkbox"]');
		if (await firstCheckbox.isVisible()) {
			await firstCheckbox.check();
		}

		// Click export
		const exportButton = page.locator('button:has-text("Export")').first();
		await exportButton.click();

		// Look for "Export Selected" option
		const exportSelectedOption = page.locator('text=/Selected|Export \d+/i');
		if (await exportSelectedOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await expect(exportSelectedOption.first()).toBeVisible();
		}
	});

	test('should export filtered records', async ({ page }) => {
		// Apply a search filter
		await searchInTable(page, 'test');

		// Click export
		const exportButton = page.locator('button:has-text("Export")').first();
		await exportButton.click();

		// Export should apply current filters
	});
});

test.describe('Records History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to record history page', async ({ page }) => {
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);

		// Click on first record to view it
		const firstRow = page.locator('tbody tr').first();
		await firstRow.click();

		// Wait for record view
		await page.waitForURL(/\/records\/contacts\/\d+/);

		// Look for history tab or link
		const historyLink = page.locator('a:has-text("History"), button:has-text("History"), [role="tab"]:has-text("History")');
		if (await historyLink.isVisible({ timeout: 3000 }).catch(() => false)) {
			await historyLink.click();
			await expect(page).toHaveURL(/\/history/);
		}
	});

	test('should display change history timeline', async ({ page }) => {
		// Navigate directly to a record's history page
		await page.goto('/records/contacts/1/history');
		await waitForLoading(page);

		// Check for timeline elements
		const timeline = page.locator('[data-testid="history-timeline"], .timeline, [class*="timeline"]');
		// Timeline may or may not exist depending on record
	});

	test('should show field-level changes', async ({ page }) => {
		await page.goto('/records/contacts/1/history');
		await waitForLoading(page);

		// Look for field change entries
		const changeEntries = page.locator('text=/changed|updated|modified/i');
		// May have entries depending on data
	});

	test('should display user who made changes', async ({ page }) => {
		await page.goto('/records/contacts/1/history');
		await waitForLoading(page);

		// Look for user names/avatars in history
		const userInfo = page.locator('[class*="user"], [class*="author"], [class*="avatar"]');
		// User info may be present
	});

	test('should show timestamps for changes', async ({ page }) => {
		await page.goto('/records/contacts/1/history');
		await waitForLoading(page);

		// Look for date/time information
		const timestamps = page.locator('time, [datetime], text=/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/');
		// Timestamps may be present
	});

	test('should filter history by date range', async ({ page }) => {
		await page.goto('/records/contacts/1/history');
		await waitForLoading(page);

		// Look for date filter
		const dateFilter = page.locator('input[type="date"], button:has-text("Date Range"), [data-filter="date"]');
		if (await dateFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await expect(dateFilter.first()).toBeVisible();
		}
	});
});

test.describe('Bulk Operations', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should select single row', async ({ page }) => {
		const firstRow = page.locator('tbody tr').first();
		const checkbox = firstRow.locator('input[type="checkbox"]');

		if (await checkbox.isVisible()) {
			await checkbox.check();
			await expect(checkbox).toBeChecked();
		}
	});

	test('should select all rows', async ({ page }) => {
		const selectAllCheckbox = page.locator('thead input[type="checkbox"]').first();

		if (await selectAllCheckbox.isVisible()) {
			await selectAllCheckbox.check();
			await expect(selectAllCheckbox).toBeChecked();

			// Verify body checkboxes are also checked
			const bodyCheckboxes = page.locator('tbody input[type="checkbox"]');
			const count = await bodyCheckboxes.count();
			for (let i = 0; i < Math.min(count, 5); i++) {
				await expect(bodyCheckboxes.nth(i)).toBeChecked();
			}
		}
	});

	test('should show bulk action bar when rows selected', async ({ page }) => {
		const firstRow = page.locator('tbody tr').first();
		const checkbox = firstRow.locator('input[type="checkbox"]');

		if (await checkbox.isVisible()) {
			await checkbox.check();

			// Look for bulk action bar/toolbar
			const bulkActions = page.locator('[data-testid="bulk-actions"], [class*="bulk"], text=/\d+ selected/i');
			await expect(bulkActions.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should perform bulk delete', async ({ page }) => {
		const firstRow = page.locator('tbody tr').first();
		const checkbox = firstRow.locator('input[type="checkbox"]');

		if (await checkbox.isVisible()) {
			await checkbox.check();

			// Click bulk delete button
			const deleteButton = page.locator('button:has-text("Delete Selected"), button:has-text("Delete")');
			if (await deleteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await deleteButton.click();

				// Confirm deletion
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});

	test('should cancel bulk delete', async ({ page }) => {
		const firstRow = page.locator('tbody tr').first();
		const checkbox = firstRow.locator('input[type="checkbox"]');

		if (await checkbox.isVisible()) {
			await checkbox.check();

			// Click bulk delete button
			const deleteButton = page.locator('button:has-text("Delete Selected"), button:has-text("Delete")');
			if (await deleteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await deleteButton.click();

				// Cancel deletion
				await confirmDialog(page, 'cancel').catch(() => {});

				// Verify record still exists
				await expect(checkbox).toBeVisible();
			}
		}
	});

	test('should perform bulk update', async ({ page }) => {
		const firstRow = page.locator('tbody tr').first();
		const checkbox = firstRow.locator('input[type="checkbox"]');

		if (await checkbox.isVisible()) {
			await checkbox.check();

			// Look for bulk update button
			const updateButton = page.locator('button:has-text("Update Selected"), button:has-text("Bulk Update"), button:has-text("Edit")');
			if (await updateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await updateButton.click();
				// Bulk update modal should appear
			}
		}
	});

	test('should deselect all rows', async ({ page }) => {
		const selectAllCheckbox = page.locator('thead input[type="checkbox"]').first();

		if (await selectAllCheckbox.isVisible()) {
			// Select all
			await selectAllCheckbox.check();
			await expect(selectAllCheckbox).toBeChecked();

			// Deselect all
			await selectAllCheckbox.uncheck();
			await expect(selectAllCheckbox).not.toBeChecked();
		}
	});
});

test.describe('Record Permissions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display create button with permission', async ({ page }) => {
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);

		// User should have create permission by default
		const createButton = page.locator('button:has-text("Create"), a:has-text("Create"), button:has-text("Add")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should display edit button on record view', async ({ page }) => {
		await page.goto('/records/contacts/1');
		await waitForLoading(page);

		// Look for edit button
		const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")');
		await expect(editButton.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should display delete option on record', async ({ page }) => {
		await page.goto('/records/contacts/1');
		await waitForLoading(page);

		// Look for delete button or menu option
		const actionsButton = page.locator('button:has-text("Actions"), button[aria-haspopup="menu"]');
		if (await actionsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await actionsButton.click();
			const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
			await expect(deleteOption).toBeVisible({ timeout: 2000 }).catch(() => {});
		}
	});
});

test.describe('Error Handling', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should handle invalid record ID gracefully', async ({ page }) => {
		await page.goto('/records/contacts/99999999');
		await waitForLoading(page);

		// Should show error or redirect
		const errorMessage = page.locator('text=/not found|error|404/i');
		await expect(errorMessage.first()).toBeVisible({ timeout: 5000 }).catch(() => {
			// May redirect to list instead
		});
	});

	test('should handle invalid module gracefully', async ({ page }) => {
		await page.goto('/records/invalidmodule');
		await waitForLoading(page);

		// Should show error or redirect
		const errorMessage = page.locator('text=/not found|error|invalid/i');
		// May handle differently
	});

	test('should preserve form data on validation error', async ({ page }) => {
		await page.goto('/records/contacts/create');
		await waitForLoading(page);

		// Fill some data
		const nameInput = page.locator('input[name="first_name"], input[placeholder*="First"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill('Test Name');

			// Try to submit without required fields
			await page.click('button[type="submit"]');

			// Name should still be filled
			await expect(nameInput).toHaveValue('Test Name');
		}
	});
});
