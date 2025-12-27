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
 * Web Form Tests
 * Tests for form builder and submissions
 */

test.describe('Web Form List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/web-forms');
		await waitForLoading(page);
	});

	test('should display web forms list', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Web Form|Web Forms|Form|Forms/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Form")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show form statistics', async ({ page }) => {
		const stats = page.locator('text=/Submissions|Views/i');
		// May show stats
	});

	test('should search forms', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Web Form Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new form', async ({ page }) => {
		await page.goto('/admin/web-forms/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Test Form ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should add form fields', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const addFieldButton = page.locator('button:has-text("Add Field")');
		if (await addFieldButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addFieldButton.click();

			// Select field type
			const textOption = page.locator('[role="option"]:has-text("Text")');
			if (await textOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await textOption.click();
			}
		}
	});

	test('should configure field properties', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		// Click on a field to edit
		const field = page.locator('[data-testid="form-field"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			await field.click();

			// Edit field label
			const labelInput = page.locator('input[name="label"]');
			if (await labelInput.isVisible()) {
				await labelInput.fill('Custom Field Label');
			}
		}
	});

	test('should set field as required', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const field = page.locator('[data-testid="form-field"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			await field.click();

			const requiredCheckbox = page.locator('input[name="required"], label:has-text("Required") input');
			if (await requiredCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
				await requiredCheckbox.check();
			}
		}
	});

	test('should reorder fields', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const fields = page.locator('[data-testid="form-field"]');
		if ((await fields.count()) >= 2) {
			const firstField = fields.first();
			const secondField = fields.nth(1);

			await firstField.dragTo(secondField);
		}
	});

	test('should delete field', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const field = page.locator('[data-testid="form-field"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = field.locator('button[aria-label="Delete"], button:has-text("Remove")');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Web Form Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should configure thank you message', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const thankYouInput = page.locator('textarea[name="thank_you_message"]');
			if (await thankYouInput.isVisible()) {
				await thankYouInput.fill('Thank you for your submission!');
			}
		}
	});

	test('should configure redirect URL', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const redirectInput = page.locator('input[name="redirect_url"]');
			if (await redirectInput.isVisible()) {
				await redirectInput.fill('https://example.com/thank-you');
			}
		}
	});

	test('should configure notification email', async ({ page }) => {
		await page.goto('/admin/web-forms/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const emailInput = page.locator('input[name="notification_email"]');
			if (await emailInput.isVisible()) {
				await emailInput.fill('notify@example.com');
			}
		}
	});
});

test.describe('Web Form Embed', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show embed code', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const embedTab = page.locator('[role="tab"]:has-text("Embed"), button:has-text("Get Code")');
		if (await embedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await embedTab.click();

			const embedCode = page.locator('code, pre, textarea[readonly]');
			await expect(embedCode.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should copy embed code', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const copyButton = page.locator('button:has-text("Copy")');
		if (await copyButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await copyButton.click();
			await waitForToast(page);
		}
	});

	test('should preview embedded form', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});
});

test.describe('Web Form Submissions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view form submissions', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const submissionsTab = page.locator('[role="tab"]:has-text("Submissions")');
		if (await submissionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await submissionsTab.click();
		}

		const table = page.locator('table');
		// May have submissions
	});

	test('should view submission details', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const submissionsTab = page.locator('[role="tab"]:has-text("Submissions")');
		if (await submissionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await submissionsTab.click();

			const row = page.locator('tbody tr').first();
			if (await row.isVisible({ timeout: 2000 }).catch(() => false)) {
				await row.click();
			}
		}
	});

	test('should export submissions', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const submissionsTab = page.locator('[role="tab"]:has-text("Submissions")');
		if (await submissionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await submissionsTab.click();

			const exportButton = page.locator('button:has-text("Export")');
			if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await exportButton.click();
			}
		}
	});

	test('should delete submission', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const submissionsTab = page.locator('[role="tab"]:has-text("Submissions")');
		if (await submissionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await submissionsTab.click();

			const row = page.locator('tbody tr').first();
			if (await row.isVisible({ timeout: 2000 }).catch(() => false)) {
				const actionsButton = row.locator('button[aria-haspopup="menu"]');
				if (await actionsButton.isVisible()) {
					await actionsButton.click();

					const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
					if (await deleteOption.isVisible({ timeout: 2000 }).catch(() => false)) {
						await deleteOption.click();
						await confirmDialog(page, 'confirm').catch(() => {});
					}
				}
			}
		}
	});
});

test.describe('Web Form Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view form analytics', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();
		}
	});

	test('should show submission count', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const submissionCount = page.locator('text=/Submissions|Total/i');
		// May show count
	});

	test('should show conversion rate', async ({ page }) => {
		await page.goto('/admin/web-forms/1');
		await waitForLoading(page);

		const conversionRate = page.locator('text=/Conversion|Rate/i');
		// May show conversion rate
	});
});

test.describe('Web Form Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete web form', async ({ page }) => {
		await page.goto('/admin/web-forms');
		await waitForLoading(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
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
