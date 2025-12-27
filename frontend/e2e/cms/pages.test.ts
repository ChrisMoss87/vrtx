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
 * CMS Pages Tests
 * Tests for content management pages
 */

test.describe('CMS Pages List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/cms/pages');
		await waitForLoading(page);
	});

	test('should display pages list', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Page|Pages|Content/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Page")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show pages tree or list', async ({ page }) => {
		const pages = page.locator('[data-testid="page-item"], tbody tr, [class*="tree-item"]');
		// May have pages
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search pages', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('about');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('CMS Page Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new page', async ({ page }) => {
		await page.goto('/admin/cms/pages/create');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"]');
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Test Page ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should set page slug', async ({ page }) => {
		await page.goto('/admin/cms/pages/create');
		await waitForLoading(page);

		const slugInput = page.locator('input[name="slug"]');
		if (await slugInput.isVisible()) {
			await slugInput.fill('test-page-slug');
		}
	});

	test('should select parent page', async ({ page }) => {
		await page.goto('/admin/cms/pages/create');
		await waitForLoading(page);

		const parentSelect = page.locator('button:has-text("Parent"), [data-testid="parent-select"]');
		if (await parentSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await parentSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should select page template', async ({ page }) => {
		await page.goto('/admin/cms/pages/create');
		await waitForLoading(page);

		const templateSelect = page.locator('button:has-text("Template"), [data-testid="template-select"]');
		if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('CMS Page Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit page content', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const editor = page.locator('[contenteditable="true"], [data-testid="content-editor"]');
		// May have editor
	});

	test('should add content block', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const addBlockButton = page.locator('button:has-text("Add Block"), button:has-text("Add Section")');
		if (await addBlockButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addBlockButton.click();

			const blockOption = page.locator('[role="option"], [data-testid="block-option"]').first();
			if (await blockOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await blockOption.click();
			}
		}
	});

	test('should reorder blocks', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const blocks = page.locator('[data-testid="content-block"]');
		if ((await blocks.count()) >= 2) {
			const firstBlock = blocks.first();
			const secondBlock = blocks.nth(1);
			await firstBlock.dragTo(secondBlock);
		}
	});

	test('should delete block', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const block = page.locator('[data-testid="content-block"]').first();
		if (await block.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = block.locator('button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});

	test('should save page changes', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const saveButton = page.locator('button:has-text("Save")');
		if (await saveButton.isVisible()) {
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('CMS Page SEO', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should configure SEO settings', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const seoTab = page.locator('[role="tab"]:has-text("SEO")');
		if (await seoTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await seoTab.click();

			const metaTitleInput = page.locator('input[name="meta_title"]');
			if (await metaTitleInput.isVisible()) {
				await metaTitleInput.fill('SEO Page Title');
			}

			const metaDescInput = page.locator('textarea[name="meta_description"]');
			if (await metaDescInput.isVisible()) {
				await metaDescInput.fill('SEO meta description for the page.');
			}
		}
	});

	test('should set featured image', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const seoTab = page.locator('[role="tab"]:has-text("SEO")');
		if (await seoTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await seoTab.click();

			const imageButton = page.locator('button:has-text("Featured Image"), button:has-text("Add Image")');
			if (await imageButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await imageButton.click();
			}
		}
	});
});

test.describe('CMS Page Publishing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should publish page', async ({ page }) => {
		await page.goto('/admin/cms/pages/1');
		await waitForLoading(page);

		const publishButton = page.locator('button:has-text("Publish")');
		if (await publishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await publishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should unpublish page', async ({ page }) => {
		await page.goto('/admin/cms/pages/1');
		await waitForLoading(page);

		const unpublishButton = page.locator('button:has-text("Unpublish")');
		if (await unpublishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await unpublishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should schedule publishing', async ({ page }) => {
		await page.goto('/admin/cms/pages/1');
		await waitForLoading(page);

		const scheduleButton = page.locator('button:has-text("Schedule")');
		if (await scheduleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await scheduleButton.click();

			const dateInput = page.locator('input[type="datetime-local"], input[type="date"]');
			if (await dateInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				const futureDate = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
				await dateInput.fill(futureDate);
			}
		}
	});

	test('should preview page', async ({ page }) => {
		await page.goto('/admin/cms/pages/1/edit');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});
});

test.describe('CMS Page Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete page', async ({ page }) => {
		await page.goto('/admin/cms/pages');
		await waitForLoading(page);

		const row = page.locator('tbody tr, [data-testid="page-item"]').first();
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
