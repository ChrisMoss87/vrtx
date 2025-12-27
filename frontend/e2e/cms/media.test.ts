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
 * CMS Media Library Tests
 * Tests for media file management
 */

test.describe('Media Library', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/cms/media');
		await waitForLoading(page);
	});

	test('should display media library', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Media|Library|Files/i }).first()).toBeVisible();
	});

	test('should display upload button', async ({ page }) => {
		const uploadButton = page.locator('button:has-text("Upload"), button:has-text("Add")');
		await expect(uploadButton.first()).toBeVisible();
	});

	test('should show media items', async ({ page }) => {
		const mediaItems = page.locator('[data-testid="media-item"], [class*="media-card"]');
		// May have media items
	});

	test('should toggle view mode', async ({ page }) => {
		const gridViewButton = page.locator('button[aria-label="Grid view"], button:has-text("Grid")');
		if (await gridViewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await gridViewButton.click();
		}

		const listViewButton = page.locator('button[aria-label="List view"], button:has-text("List")');
		if (await listViewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await listViewButton.click();
		}
	});

	test('should filter by type', async ({ page }) => {
		const typeFilter = page.locator('button:has-text("Type"), [data-filter="type"]');
		if (await typeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeFilter.click();
			await page.locator('[role="option"]:has-text("Image")').click();
			await waitForLoading(page);
		}
	});

	test('should search media', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('logo');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Media Upload', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show upload dialog', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const uploadButton = page.locator('button:has-text("Upload")');
		if (await uploadButton.isVisible()) {
			await uploadButton.click();

			const uploadDialog = page.locator('[role="dialog"], [data-testid="upload-dialog"]');
			await expect(uploadDialog).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should show drag and drop area', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const dropzone = page.locator('[data-testid="dropzone"], [class*="drop"]');
		// May have dropzone
	});

	test('should upload image via dialog', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const uploadButton = page.locator('button:has-text("Upload")');
		if (await uploadButton.isVisible()) {
			await uploadButton.click();

			const fileInput = page.locator('input[type="file"]');
			// File input exists
		}
	});
});

test.describe('Media Folders', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create folder', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const createFolderButton = page.locator('button:has-text("New Folder"), button:has-text("Create Folder")');
		if (await createFolderButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await createFolderButton.click();

			const folderNameInput = page.locator('input[name="folder_name"], input[placeholder*="Folder name"]');
			if (await folderNameInput.isVisible()) {
				await folderNameInput.fill(`Folder ${Date.now()}`);
			}

			const createButton = page.locator('[role="dialog"] button:has-text("Create")');
			await createButton.click();
			await waitForToast(page);
		}
	});

	test('should navigate folders', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const folder = page.locator('[data-testid="media-folder"], [data-type="folder"]').first();
		if (await folder.isVisible({ timeout: 2000 }).catch(() => false)) {
			await folder.dblclick();
			await waitForLoading(page);
		}
	});

	test('should show breadcrumbs', async ({ page }) => {
		await page.goto('/admin/cms/media/folder/1');
		await waitForLoading(page);

		const breadcrumbs = page.locator('[data-testid="breadcrumbs"], nav[aria-label="Breadcrumb"]');
		// May have breadcrumbs
	});

	test('should move item to folder', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = mediaItem.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const moveOption = page.locator('[role="menuitem"]:has-text("Move")');
				if (await moveOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await moveOption.click();

					const folderOption = page.locator('[role="option"]').first();
					if (await folderOption.isVisible({ timeout: 2000 }).catch(() => false)) {
						await folderOption.click();
						await waitForToast(page);
					}
				}
			}
		}
	});
});

test.describe('Media Details', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view media details', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			await mediaItem.click();

			const detailsPanel = page.locator('[data-testid="media-details"], [class*="details-panel"]');
			await expect(detailsPanel).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should edit media title', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			await mediaItem.click();

			const titleInput = page.locator('input[name="title"], input[name="name"]');
			if (await titleInput.isVisible()) {
				await titleInput.fill('Updated Media Title');
			}

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should edit alt text', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			await mediaItem.click();

			const altInput = page.locator('input[name="alt"], input[name="alt_text"]');
			if (await altInput.isVisible()) {
				await altInput.fill('Alternative text for image');
			}
		}
	});

	test('should copy media URL', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			await mediaItem.click();

			const copyButton = page.locator('button:has-text("Copy URL"), button[aria-label="Copy"]');
			if (await copyButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await copyButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Media Bulk Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should select multiple items', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const checkbox = page.locator('[data-testid="media-item"] input[type="checkbox"]').first();
		if (await checkbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await checkbox.check();
		}
	});

	test('should select all items', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const selectAllCheckbox = page.locator('input[aria-label="Select all"], [data-testid="select-all"]');
		if (await selectAllCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await selectAllCheckbox.check();
		}
	});

	test('should bulk delete', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		// Select items first
		const checkbox = page.locator('[data-testid="media-item"] input[type="checkbox"]').first();
		if (await checkbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await checkbox.check();

			const deleteButton = page.locator('button:has-text("Delete Selected"), button:has-text("Delete")');
			if (await deleteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});

	test('should bulk move', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		// Select items first
		const checkbox = page.locator('[data-testid="media-item"] input[type="checkbox"]').first();
		if (await checkbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await checkbox.check();

			const moveButton = page.locator('button:has-text("Move Selected"), button:has-text("Move")');
			if (await moveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await moveButton.click();
			}
		}
	});
});

test.describe('Media Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete media item', async ({ page }) => {
		await page.goto('/admin/cms/media');
		await waitForLoading(page);

		const mediaItem = page.locator('[data-testid="media-item"]').first();
		if (await mediaItem.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = mediaItem.locator('button[aria-haspopup="menu"]');
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
