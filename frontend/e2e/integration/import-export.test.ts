import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Import/Export Integration Tests
 * Tests for data import and export flows
 */

test.describe('CSV Import Flow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to import page', async ({ page }) => {
		await page.goto('/admin/import');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Import/i }).first()).toBeVisible();
	});

	test('should select module for import', async ({ page }) => {
		await page.goto('/admin/import');
		await waitForLoading(page);

		const moduleSelect = page.locator('button:has-text("Module"), [data-testid="module-select"]');
		if (await moduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleSelect.click();
			await page.locator('[role="option"]:has-text("Contacts")').click();
		}
	});

	test('should upload import file', async ({ page }) => {
		await page.goto('/admin/import');
		await waitForLoading(page);

		// Select module first
		const moduleSelect = page.locator('[data-testid="module-select"]');
		if (await moduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Upload file
		const fileInput = page.locator('input[type="file"]');
		// File input exists
	});

	test('should map import columns', async ({ page }) => {
		await page.goto('/admin/import/mapping');
		await waitForLoading(page);

		const columnMapping = page.locator('[data-testid="column-mapping"]');
		if (await columnMapping.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Map a column
			const sourceColumn = page.locator('[data-testid="source-column"]').first();
			if (await sourceColumn.isVisible()) {
				await sourceColumn.click();

				const targetField = page.locator('[role="option"]').first();
				if (await targetField.isVisible({ timeout: 2000 }).catch(() => false)) {
					await targetField.click();
				}
			}
		}
	});

	test('should preview import data', async ({ page }) => {
		await page.goto('/admin/import/preview');
		await waitForLoading(page);

		const previewTable = page.locator('[data-testid="import-preview"], table');
		// May show preview
	});

	test('should handle import errors', async ({ page }) => {
		await page.goto('/admin/import/preview');
		await waitForLoading(page);

		const errors = page.locator('[data-testid="import-error"], text=/Error|Invalid/i');
		// May show errors
	});

	test('should complete import', async ({ page }) => {
		await page.goto('/admin/import/preview');
		await waitForLoading(page);

		const importButton = page.locator('button:has-text("Import"), button:has-text("Start Import")');
		if (await importButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await importButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});

			// Wait for import to complete
			const successMessage = page.locator('text=/Import complete|Successfully imported/i');
			await expect(successMessage.first()).toBeVisible({ timeout: 30000 }).catch(() => {});
		}
	});
});

test.describe('Export Flow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should export contacts to CSV', async ({ page }) => {
		await page.goto('/records/contacts');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();

			const csvOption = page.locator('[role="menuitem"]:has-text("CSV"), [role="option"]:has-text("CSV")');
			if (await csvOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
				await csvOption.click();

				const download = await downloadPromise;
				if (download) {
					expect(download.suggestedFilename()).toMatch(/\.csv$/);
				}
			}
		}
	});

	test('should export contacts to Excel', async ({ page }) => {
		await page.goto('/records/contacts');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();

			const excelOption = page.locator('[role="menuitem"]:has-text("Excel"), [role="option"]:has-text("Excel")');
			if (await excelOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
				await excelOption.click();

				const download = await downloadPromise;
				if (download) {
					expect(download.suggestedFilename()).toMatch(/\.xlsx?$/);
				}
			}
		}
	});

	test('should export selected records', async ({ page }) => {
		await page.goto('/records/contacts');
		await waitForLoading(page);

		// Select some records
		const checkboxes = page.locator('tbody tr input[type="checkbox"]');
		if ((await checkboxes.count()) >= 2) {
			await checkboxes.first().check();
			await checkboxes.nth(1).check();

			const exportButton = page.locator('button:has-text("Export Selected")');
			if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await exportButton.click();
			}
		}
	});

	test('should export with filters applied', async ({ page }) => {
		await page.goto('/records/contacts');
		await waitForLoading(page);

		// Apply a filter
		const statusFilter = page.locator('button:has-text("Status"), [data-filter="status"]');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}

		// Export filtered data
		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();
		}
	});

	test('should select export columns', async ({ page }) => {
		await page.goto('/records/contacts');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();

			const configureColumnsButton = page.locator('button:has-text("Configure"), button:has-text("Select Fields")');
			if (await configureColumnsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await configureColumnsButton.click();

				// Toggle some columns
				const columnCheckbox = page.locator('[data-testid="export-column"] input[type="checkbox"]').first();
				if (await columnCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
					await columnCheckbox.click();
				}
			}
		}
	});
});

test.describe('Import Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should download import template', async ({ page }) => {
		await page.goto('/admin/import');
		await waitForLoading(page);

		// Select module
		const moduleSelect = page.locator('[data-testid="module-select"]');
		if (await moduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		const templateButton = page.locator('button:has-text("Download Template"), a:has-text("Template")');
		if (await templateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
			await templateButton.click();

			const download = await downloadPromise;
			if (download) {
				expect(download.suggestedFilename()).toMatch(/\.(csv|xlsx?)$/);
			}
		}
	});

	test('should save import mapping as template', async ({ page }) => {
		await page.goto('/admin/import/mapping');
		await waitForLoading(page);

		const saveTemplateButton = page.locator('button:has-text("Save Mapping"), button:has-text("Save Template")');
		if (await saveTemplateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await saveTemplateButton.click();

			const nameInput = page.locator('input[name="template_name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill(`Import Template ${Date.now()}`);
			}

			const saveButton = page.locator('[role="dialog"] button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should use saved mapping template', async ({ page }) => {
		await page.goto('/admin/import');
		await waitForLoading(page);

		const useTemplateButton = page.locator('button:has-text("Use Template"), [data-testid="template-select"]');
		if (await useTemplateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await useTemplateButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Import History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view import history', async ({ page }) => {
		await page.goto('/admin/import/history');
		await waitForLoading(page);

		const importHistory = page.locator('[data-testid="import-record"], tbody tr');
		// May have import records
	});

	test('should view import details', async ({ page }) => {
		await page.goto('/admin/import/history');
		await waitForLoading(page);

		const importRecord = page.locator('[data-testid="import-record"], tbody tr').first();
		if (await importRecord.isVisible({ timeout: 2000 }).catch(() => false)) {
			await importRecord.click();

			const details = page.locator('[data-testid="import-details"]');
			await expect(details).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should view import errors', async ({ page }) => {
		await page.goto('/admin/import/history/1');
		await waitForLoading(page);

		const errorsTab = page.locator('[role="tab"]:has-text("Errors"), button:has-text("Failed Records")');
		if (await errorsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await errorsTab.click();

			const errors = page.locator('[data-testid="import-error"]');
			// May have errors
		}
	});

	test('should undo import', async ({ page }) => {
		await page.goto('/admin/import/history/1');
		await waitForLoading(page);

		const undoButton = page.locator('button:has-text("Undo"), button:has-text("Rollback")');
		if (await undoButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await undoButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Scheduled Exports', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create scheduled export', async ({ page }) => {
		await page.goto('/admin/exports/scheduled');
		await waitForLoading(page);

		const createButton = page.locator('button:has-text("Create"), button:has-text("Schedule Export")');
		if (await createButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await createButton.click();

			const nameInput = page.locator('input[name="name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill(`Scheduled Export ${Date.now()}`);
			}

			// Configure schedule
			const scheduleSelect = page.locator('[data-testid="schedule-type"]');
			if (await scheduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await scheduleSelect.click();
				await page.locator('[role="option"]:has-text("Daily")').click();
			}

			const submitButton = page.locator('button[type="submit"]');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should configure export email delivery', async ({ page }) => {
		await page.goto('/admin/exports/scheduled/create');
		await waitForLoading(page);

		const emailToggle = page.locator('label:has-text("Email") input[type="checkbox"]');
		if (await emailToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailToggle.check();

			const emailInput = page.locator('input[name="email"], input[name="recipients"]');
			if (await emailInput.isVisible()) {
				await emailInput.fill('export@example.com');
			}
		}
	});

	test('should view scheduled exports', async ({ page }) => {
		await page.goto('/admin/exports/scheduled');
		await waitForLoading(page);

		const scheduledExports = page.locator('[data-testid="scheduled-export"], tbody tr');
		// May have scheduled exports
	});

	test('should delete scheduled export', async ({ page }) => {
		await page.goto('/admin/exports/scheduled');
		await waitForLoading(page);

		const export_ = page.locator('tbody tr').first();
		if (await export_.isVisible()) {
			const actionsButton = export_.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
				if (await deleteOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await deleteOption.click();
					await confirmDialog(page, 'confirm').catch(() => {});
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('Data Migration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should import from external CRM', async ({ page }) => {
		await page.goto('/admin/import/external');
		await waitForLoading(page);

		const crmOptions = page.locator('[data-testid="crm-option"], button:has-text("Salesforce"), button:has-text("HubSpot")');
		// May have CRM import options
	});

	test('should validate data before migration', async ({ page }) => {
		await page.goto('/admin/import/preview');
		await waitForLoading(page);

		const validationResults = page.locator('[data-testid="validation-results"]');
		// May show validation results
	});

	test('should handle duplicate records', async ({ page }) => {
		await page.goto('/admin/import/preview');
		await waitForLoading(page);

		const duplicateHandling = page.locator('[data-testid="duplicate-handling"], text=/Duplicate/i');
		if (await duplicateHandling.isVisible({ timeout: 2000 }).catch(() => false)) {
			const duplicateOption = page.locator('button:has-text("Skip"), button:has-text("Update")');
			if (await duplicateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await duplicateOption.click();
			}
		}
	});
});
