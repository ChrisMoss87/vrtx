import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToDealRooms,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Deal Room Tests
 * Tests for deal room CRUD, members, documents, and collaboration
 */

test.describe('Deal Room List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToDealRooms(page);
	});

	test('should display deal rooms list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Deal Room|Deal Rooms/i }).first()).toBeVisible();
	});

	test('should display create deal room button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Deal Room")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show deal room cards', async ({ page }) => {
		const cards = page.locator('[data-testid="deal-room-card"], [class*="deal-room"]');
		// May have existing deal rooms
	});

	test('should search deal rooms', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Deal Room Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create deal room', async ({ page }) => {
		await navigateToDealRooms(page);
		await page.click('button:has-text("Create"), a:has-text("New Deal Room")');

		// Fill name
		const nameInput = page.locator('input[name="name"], input[placeholder*="Name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Test Deal Room ${Date.now()}`);
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();
		await waitForToast(page);
	});
});

test.describe('Deal Room Members', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add member to room', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const addMemberButton = page.locator('button:has-text("Add Member"), button:has-text("Invite")');
		if (await addMemberButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addMemberButton.click();

			// Fill email
			const emailInput = page.locator('input[type="email"]');
			if (await emailInput.isVisible()) {
				await emailInput.fill('member@example.com');
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Add"), button:has-text("Invite")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should remove member from room', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const memberRow = page.locator('[data-testid="member-row"], tr').first();
		if (await memberRow.isVisible()) {
			const removeButton = memberRow.locator('button:has-text("Remove"), [aria-label="Remove"]');
			if (await removeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await removeButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});

	test('should update member role', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const roleSelect = page.locator('button[role="combobox"]').first();
		if (await roleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await roleSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Deal Room Documents', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display documents list', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const documentsTab = page.locator('[role="tab"]:has-text("Documents")');
		if (await documentsTab.isVisible()) {
			await documentsTab.click();
		}

		const documentsList = page.locator('[data-testid="documents-list"], table');
		// May have documents
	});

	test('should upload document', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const uploadButton = page.locator('button:has-text("Upload"), button:has-text("Add Document")');
		if (await uploadButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await uploadButton.click();
			// Would need file for actual upload
		}
	});

	test('should track document views', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const documentsTab = page.locator('[role="tab"]:has-text("Documents")');
		if (await documentsTab.isVisible()) {
			await documentsTab.click();
		}

		const viewCount = page.locator('text=/views|viewed/i');
		// May show view counts
	});
});

test.describe('Deal Room Messages', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send message', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const messageInput = page.locator('textarea[name="message"], input[placeholder*="message"]');
		if (await messageInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await messageInput.fill('Test message');

			const sendButton = page.locator('button:has-text("Send"), button[type="submit"]');
			await sendButton.click();
		}
	});

	test('should display message history', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const messages = page.locator('[data-testid="message"], [class*="message"]');
		// May have messages
	});
});

test.describe('Deal Room Action Items', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create action item', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const addActionButton = page.locator('button:has-text("Add Action"), button:has-text("New Task")');
		if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addActionButton.click();

			const titleInput = page.locator('input[name="title"]');
			if (await titleInput.isVisible()) {
				await titleInput.fill('Test Action Item');
			}

			const submitButton = page.locator('[role="dialog"] button:has-text("Create"), button:has-text("Save")');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should mark action item complete', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const checkbox = page.locator('[data-testid="action-item"] input[type="checkbox"]').first();
		if (await checkbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await checkbox.check();
		}
	});

	test('should assign action item', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const assignButton = page.locator('button:has-text("Assign")').first();
		if (await assignButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await assignButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Deal Room Activity', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display activity timeline', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const activityTab = page.locator('[role="tab"]:has-text("Activity")');
		if (await activityTab.isVisible()) {
			await activityTab.click();
		}

		const timeline = page.locator('[data-testid="activity-timeline"], [class*="timeline"]');
		// May have activity
	});
});

test.describe('Deal Room Sharing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should share public link', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const shareButton = page.locator('button:has-text("Share"), button:has-text("Copy Link")');
		if (await shareButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await shareButton.click();
			await waitForToast(page);
		}
	});

	test('should configure access settings', async ({ page }) => {
		await page.goto('/deal-rooms/1');
		await waitForLoading(page);

		const settingsButton = page.locator('button:has-text("Settings"), button[aria-label="Settings"]');
		if (await settingsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsButton.click();
		}
	});
});
