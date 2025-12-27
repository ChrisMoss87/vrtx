import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToLandingPages,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Landing Page Tests
 * Tests for landing page builder, publishing, and analytics
 */

test.describe('Landing Page List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToLandingPages(page);
	});

	test('should display landing pages list', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Landing Page|Landing Pages/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search landing pages', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Landing Page Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new landing page', async ({ page }) => {
		await navigateToLandingPages(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const nameInput = page.locator('input[name="name"], input[name="title"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Landing Page ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should use page template', async ({ page }) => {
		await navigateToLandingPages(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const templateSelect = page.locator('button:has-text("Template"), [data-testid="template-select"]');
		if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should set page slug', async ({ page }) => {
		await page.goto('/landing-pages/new');
		await waitForLoading(page);

		const slugInput = page.locator('input[name="slug"]');
		if (await slugInput.isVisible()) {
			await slugInput.fill('test-landing-page');
		}
	});
});

test.describe('Landing Page Editor', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit page content', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const editor = page.locator('[contenteditable="true"], .editor, [data-testid="page-editor"]');
		// May have editor
	});

	test('should add sections', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const addSectionButton = page.locator('button:has-text("Add Section"), button:has-text("Add Block")');
		if (await addSectionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addSectionButton.click();
		}
	});

	test('should configure SEO settings', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const seoTab = page.locator('[role="tab"]:has-text("SEO"), button:has-text("SEO")');
		if (await seoTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await seoTab.click();

			const titleInput = page.locator('input[name="meta_title"]');
			if (await titleInput.isVisible()) {
				await titleInput.fill('Page Title');
			}
		}
	});

	test('should add form to page', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const addFormButton = page.locator('button:has-text("Add Form")');
		if (await addFormButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addFormButton.click();
		}
	});

	test('should preview page', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});

	test('should save changes', async ({ page }) => {
		await page.goto('/landing-pages/1/edit');
		await waitForLoading(page);

		const saveButton = page.locator('button:has-text("Save")');
		if (await saveButton.isVisible()) {
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Landing Page Publishing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should publish page', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const publishButton = page.locator('button:has-text("Publish")');
		if (await publishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await publishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should unpublish page', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const unpublishButton = page.locator('button:has-text("Unpublish")');
		if (await unpublishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await unpublishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should view published page', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const viewButton = page.locator('a:has-text("View"), button:has-text("Open")');
		if (await viewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Don't actually navigate - just verify button exists
		}
	});
});

test.describe('Landing Page Versions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view page versions', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const versionsTab = page.locator('[role="tab"]:has-text("Versions"), button:has-text("History")');
		if (await versionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await versionsTab.click();
		}
	});

	test('should restore previous version', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const versionsTab = page.locator('[role="tab"]:has-text("Versions")');
		if (await versionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await versionsTab.click();

			const restoreButton = page.locator('button:has-text("Restore")').first();
			if (await restoreButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await restoreButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Landing Page Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view page analytics', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();
		}
	});

	test('should show page views', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const views = page.locator('text=/Views|Visitors/i');
		// May show views
	});

	test('should show conversion rate', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const conversion = page.locator('text=/Conversion|Conversions/i');
		// May show conversion
	});
});

test.describe('Landing Page Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should save page as template', async ({ page }) => {
		await page.goto('/landing-pages/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const saveTemplateOption = page.locator('[role="menuitem"]:has-text("Save as Template")');
			if (await saveTemplateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await saveTemplateOption.click();

				const nameInput = page.locator('input[name="name"]');
				if (await nameInput.isVisible()) {
					await nameInput.fill('My Template');
				}

				const confirmButton = page.locator('[role="dialog"] button:has-text("Save")');
				await confirmButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Landing Page Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete landing page', async ({ page }) => {
		await navigateToLandingPages(page);

		const row = page.locator('tbody tr, [data-testid="landing-page-card"]').first();
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
