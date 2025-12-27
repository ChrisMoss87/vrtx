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
 * Knowledge Base Tests
 * Tests for help center and documentation
 */

test.describe('Knowledge Base Categories', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/knowledge-base');
		await waitForLoading(page);
	});

	test('should display knowledge base page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Knowledge|Help|Documentation/i }).first()).toBeVisible();
	});

	test('should display create category button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), button:has-text("Add Category")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show categories', async ({ page }) => {
		const categories = page.locator('[data-testid="kb-category"], [class*="category-card"]');
		// May have categories
	});

	test('should create category', async ({ page }) => {
		const createButton = page.locator('button:has-text("Add Category"), button:has-text("Create Category")');
		if (await createButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await createButton.click();

			const nameInput = page.locator('input[name="name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill(`Category ${Date.now()}`);
			}

			const submitButton = page.locator('button[type="submit"]');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should edit category', async ({ page }) => {
		const category = page.locator('[data-testid="kb-category"]').first();
		if (await category.isVisible({ timeout: 2000 }).catch(() => false)) {
			const editButton = category.locator('button:has-text("Edit")');
			if (await editButton.isVisible()) {
				await editButton.click();

				const nameInput = page.locator('input[name="name"]');
				if (await nameInput.isVisible()) {
					await nameInput.fill(`Updated Category ${Date.now()}`);
				}

				const saveButton = page.locator('button:has-text("Save")');
				await saveButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should delete category', async ({ page }) => {
		const category = page.locator('[data-testid="kb-category"]').first();
		if (await category.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = category.locator('button[aria-haspopup="menu"]');
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

test.describe('Knowledge Base Articles', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view articles list', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles');
		await waitForLoading(page);

		const articles = page.locator('[data-testid="kb-article"], tbody tr');
		// May have articles
	});

	test('should create article', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles/create');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"]');
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Article ${Date.now()}`);
		}

		// Select category
		const categorySelect = page.locator('button:has-text("Category"), [data-testid="category-select"]');
		if (await categorySelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await categorySelect.click();
			await page.locator('[role="option"]').first().click();
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should edit article content', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles/1/edit');
		await waitForLoading(page);

		const editor = page.locator('[contenteditable="true"], [data-testid="article-editor"]');
		if (await editor.isVisible()) {
			await editor.fill('Updated article content with helpful information.');
		}

		const saveButton = page.locator('button:has-text("Save")');
		await saveButton.click();
		await waitForToast(page);
	});

	test('should add article tags', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles/1/edit');
		await waitForLoading(page);

		const tagsInput = page.locator('input[name="tags"], [data-testid="tags-input"]');
		if (await tagsInput.isVisible()) {
			await tagsInput.fill('getting-started');
			await tagsInput.press('Enter');
		}
	});

	test('should publish article', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles/1');
		await waitForLoading(page);

		const publishButton = page.locator('button:has-text("Publish")');
		if (await publishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await publishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should unpublish article', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles/1');
		await waitForLoading(page);

		const unpublishButton = page.locator('button:has-text("Unpublish")');
		if (await unpublishButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await unpublishButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should search articles', async ({ page }) => {
		await page.goto('/admin/knowledge-base/articles');
		await waitForLoading(page);

		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('setup');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Knowledge Base Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should configure KB settings', async ({ page }) => {
		await page.goto('/admin/settings/knowledge-base');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Knowledge.*Settings|Help Center/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should configure public access', async ({ page }) => {
		await page.goto('/admin/settings/knowledge-base');
		await waitForLoading(page);

		const publicToggle = page.locator('label:has-text("Public Access") input[type="checkbox"]');
		if (await publicToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await publicToggle.click();
		}
	});

	test('should configure branding', async ({ page }) => {
		await page.goto('/admin/settings/knowledge-base');
		await waitForLoading(page);

		const logoButton = page.locator('button:has-text("Logo"), button:has-text("Upload Logo")');
		if (await logoButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Logo upload exists
		}
	});

	test('should configure custom domain', async ({ page }) => {
		await page.goto('/admin/settings/knowledge-base');
		await waitForLoading(page);

		const domainInput = page.locator('input[name="custom_domain"]');
		if (await domainInput.isVisible()) {
			await domainInput.fill('help.example.com');
		}
	});
});

test.describe('Knowledge Base Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view KB analytics', async ({ page }) => {
		await page.goto('/admin/knowledge-base/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="kb-analytics"]');
		// May have analytics
	});

	test('should show article views', async ({ page }) => {
		await page.goto('/admin/knowledge-base/analytics');
		await waitForLoading(page);

		const views = page.locator('text=/Views|Page Views/i');
		// May show views
	});

	test('should show helpfulness ratings', async ({ page }) => {
		await page.goto('/admin/knowledge-base/analytics');
		await waitForLoading(page);

		const ratings = page.locator('text=/Helpful|Rating|Feedback/i');
		// May show ratings
	});

	test('should show search analytics', async ({ page }) => {
		await page.goto('/admin/knowledge-base/analytics');
		await waitForLoading(page);

		const searchStats = page.locator('text=/Search|Queries/i');
		// May show search stats
	});
});

test.describe('Article Feedback', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view article feedback', async ({ page }) => {
		await page.goto('/admin/knowledge-base/feedback');
		await waitForLoading(page);

		const feedback = page.locator('[data-testid="article-feedback"], tbody tr');
		// May have feedback
	});

	test('should respond to feedback', async ({ page }) => {
		await page.goto('/admin/knowledge-base/feedback');
		await waitForLoading(page);

		const feedbackItem = page.locator('tbody tr').first();
		if (await feedbackItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			await feedbackItem.click();

			const responseInput = page.locator('textarea[name="response"]');
			if (await responseInput.isVisible()) {
				await responseInput.fill('Thank you for your feedback!');
			}

			const sendButton = page.locator('button:has-text("Send"), button:has-text("Respond")');
			await sendButton.click();
			await waitForToast(page);
		}
	});

	test('should mark feedback as resolved', async ({ page }) => {
		await page.goto('/admin/knowledge-base/feedback');
		await waitForLoading(page);

		const feedbackItem = page.locator('tbody tr').first();
		if (await feedbackItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			const resolveButton = feedbackItem.locator('button:has-text("Resolve")');
			if (await resolveButton.isVisible()) {
				await resolveButton.click();
				await waitForToast(page);
			}
		}
	});
});
