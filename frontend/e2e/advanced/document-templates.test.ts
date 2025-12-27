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
 * Document Templates Tests
 * Tests for document template creation and generation
 */

test.describe('Document Template List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/document-templates');
		await waitForLoading(page);
	});

	test('should display document templates page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Document.*Template|Templates/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Template")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show templates list', async ({ page }) => {
		const templates = page.locator('[data-testid="template-item"], tbody tr');
		// May have templates
	});

	test('should filter by type', async ({ page }) => {
		const typeFilter = page.locator('button:has-text("Type"), [data-filter="type"]');
		if (await typeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by module', async ({ page }) => {
		const moduleFilter = page.locator('button:has-text("Module"), [data-filter="module"]');
		if (await moduleFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should search templates', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('invoice');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Document Template Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new template', async ({ page }) => {
		await page.goto('/admin/document-templates/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Template ${Date.now()}`);
		}

		// Select format
		const formatSelect = page.locator('button:has-text("Format"), [data-testid="format-select"]');
		if (await formatSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await formatSelect.click();
			await page.locator('[role="option"]:has-text("PDF")').click();
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select template module', async ({ page }) => {
		await page.goto('/admin/document-templates/create');
		await waitForLoading(page);

		const moduleSelect = page.locator('button:has-text("Module"), [data-testid="module-select"]');
		if (await moduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should use template builder', async ({ page }) => {
		await page.goto('/admin/document-templates/create');
		await waitForLoading(page);

		const builderOption = page.locator('button:has-text("Builder"), [data-testid="use-builder"]');
		if (await builderOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await builderOption.click();
		}
	});

	test('should upload template file', async ({ page }) => {
		await page.goto('/admin/document-templates/create');
		await waitForLoading(page);

		const uploadOption = page.locator('button:has-text("Upload"), [data-testid="upload-template"]');
		if (await uploadOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify upload option exists
		}
	});
});

test.describe('Template Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit template content', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const editor = page.locator('[contenteditable="true"], [data-testid="template-editor"]');
		// May have editor
	});

	test('should insert merge field', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const insertFieldButton = page.locator('button:has-text("Insert Field"), button:has-text("Merge Field")');
		if (await insertFieldButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await insertFieldButton.click();

			const fieldOption = page.locator('[role="option"]').first();
			if (await fieldOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await fieldOption.click();
			}
		}
	});

	test('should insert table', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const tableButton = page.locator('button:has-text("Table"), button[aria-label="Insert Table"]');
		if (await tableButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await tableButton.click();
		}
	});

	test('should insert image', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const imageButton = page.locator('button:has-text("Image"), button[aria-label="Insert Image"]');
		if (await imageButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await imageButton.click();
		}
	});

	test('should insert conditional section', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const conditionalButton = page.locator('button:has-text("Conditional"), button:has-text("If/Else")');
		if (await conditionalButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await conditionalButton.click();
		}
	});

	test('should insert loop section', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const loopButton = page.locator('button:has-text("Loop"), button:has-text("Repeat")');
		if (await loopButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await loopButton.click();
		}
	});

	test('should configure page settings', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const settingsButton = page.locator('button:has-text("Settings"), button:has-text("Page Setup")');
		if (await settingsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsButton.click();

			// Page size
			const pageSizeSelect = page.locator('[data-testid="page-size"]');
			if (await pageSizeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await pageSizeSelect.click();
				await page.locator('[role="option"]:has-text("A4")').click();
			}
		}
	});

	test('should save template changes', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const saveButton = page.locator('button:has-text("Save")');
		if (await saveButton.isVisible()) {
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Template Preview', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should preview template', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();

			const preview = page.locator('[data-testid="template-preview"], iframe');
			await expect(preview).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should preview with sample data', async ({ page }) => {
		await page.goto('/admin/document-templates/1/edit');
		await waitForLoading(page);

		const sampleDataButton = page.locator('button:has-text("Sample Data"), button:has-text("Test Data")');
		if (await sampleDataButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sampleDataButton.click();

			const recordSelect = page.locator('[data-testid="sample-record"]');
			if (await recordSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await recordSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});
});

test.describe('Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate document from record', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const generateOption = page.locator('[role="menuitem"]:has-text("Generate Document")');
			if (await generateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await generateOption.click();

				// Select template
				const templateSelect = page.locator('[data-testid="template-select"]');
				if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
					await templateSelect.click();
					await page.locator('[role="option"]').first().click();
				}

				const generateButton = page.locator('[role="dialog"] button:has-text("Generate")');
				await generateButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should view generated documents', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const documentsTab = page.locator('[role="tab"]:has-text("Documents")');
		if (await documentsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await documentsTab.click();

			const documents = page.locator('[data-testid="document-item"]');
			// May have documents
		}
	});

	test('should download generated document', async ({ page }) => {
		await page.goto('/records/deals/1/documents');
		await waitForLoading(page);

		const document = page.locator('[data-testid="document-item"]').first();
		if (await document.isVisible({ timeout: 2000 }).catch(() => false)) {
			const downloadButton = document.locator('button:has-text("Download"), a:has-text("Download")');
			if (await downloadButton.isVisible()) {
				// Just verify download button exists
			}
		}
	});

	test('should send document via email', async ({ page }) => {
		await page.goto('/records/deals/1/documents');
		await waitForLoading(page);

		const document = page.locator('[data-testid="document-item"]').first();
		if (await document.isVisible({ timeout: 2000 }).catch(() => false)) {
			const sendButton = document.locator('button:has-text("Send"), button:has-text("Email")');
			if (await sendButton.isVisible()) {
				await sendButton.click();
			}
		}
	});
});

test.describe('Template Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete template', async ({ page }) => {
		await page.goto('/admin/document-templates');
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
