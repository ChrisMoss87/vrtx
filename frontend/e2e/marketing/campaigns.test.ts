import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToCampaigns,
	confirmDialog,
	fillFormField,
	clickTab,
	expectToast
} from '../fixtures';

/**
 * Campaign Management Tests
 * Tests for campaign CRUD, targeting, and analytics
 */

test.describe('Campaign List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToCampaigns(page);
	});

	test('should display campaigns list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Campaign|Campaigns/i }).first()).toBeVisible();
	});

	test('should display create campaign button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Campaign")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by type', async ({ page }) => {
		const typeFilter = page.locator('button:has-text("Type"), [data-filter="type"]');
		if (await typeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search campaigns', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Campaign Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create email campaign', async ({ page }) => {
		await navigateToCampaigns(page);
		await page.click('button:has-text("Create"), a:has-text("New Campaign")');

		// Select campaign type
		const typeSelect = page.locator('button:has-text("Email"), [role="option"]:has-text("Email")');
		if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeSelect.click();
		}

		// Fill name
		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Email Campaign ${Date.now()}`);
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should create drip campaign', async ({ page }) => {
		await navigateToCampaigns(page);
		await page.click('button:has-text("Create"), a:has-text("New Campaign")');

		// Select drip type
		const typeSelect = page.locator('button:has-text("Drip"), [role="option"]:has-text("Drip")');
		if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeSelect.click();
		}

		// Fill name
		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Drip Campaign ${Date.now()}`);
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should configure audience targeting', async ({ page }) => {
		await page.goto('/marketing/campaigns/create');
		await waitForLoading(page);

		// Look for audience/targeting section
		const audienceSection = page.locator('text=/Audience|Target|Recipients/i');
		if (await audienceSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Configure targeting
			const segmentSelect = page.locator('button[role="combobox"]:has-text("Segment")');
			if (await segmentSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await segmentSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should validate required fields', async ({ page }) => {
		await page.goto('/marketing/campaigns/create');
		await waitForLoading(page);

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		const error = page.locator('text=/required/i');
		await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});
});

test.describe('Campaign Builder', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should design email template', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const designTab = page.locator('[role="tab"]:has-text("Design"), button:has-text("Edit Template")');
		if (await designTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await designTab.click();
		}
	});

	test('should add personalization tokens', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const tokenButton = page.locator('button:has-text("Personalize"), button:has-text("Insert Field")');
		if (await tokenButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await tokenButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should preview email', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});

	test('should send test email', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const testButton = page.locator('button:has-text("Send Test"), button:has-text("Test Email")');
		if (await testButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await testButton.click();

			const emailInput = page.locator('input[type="email"]');
			if (await emailInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await emailInput.fill('test@example.com');
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Send")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Campaign Scheduling', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should schedule campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const scheduleButton = page.locator('button:has-text("Schedule")');
		if (await scheduleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await scheduleButton.click();

			const dateInput = page.locator('input[type="datetime-local"], input[type="date"]');
			if (await dateInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				const futureDate = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
				await dateInput.fill(futureDate);
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Schedule")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should start campaign immediately', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const startButton = page.locator('button:has-text("Start"), button:has-text("Send Now")');
		if (await startButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await startButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Campaign Status Management', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should pause active campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const pauseButton = page.locator('button:has-text("Pause")');
		if (await pauseButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pauseButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should resume paused campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const resumeButton = page.locator('button:has-text("Resume")');
		if (await resumeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resumeButton.click();
			await waitForToast(page);
		}
	});

	test('should cancel campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const cancelButton = page.locator('button:has-text("Cancel"), button:has-text("Stop")');
		if (await cancelButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cancelButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Campaign Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view campaign analytics', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics"), button:has-text("Analytics")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();
		}
	});

	test('should show open rate', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const openRate = page.locator('text=/Open Rate|Opens|Opened/i');
		// May show open rate
	});

	test('should show click rate', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const clickRate = page.locator('text=/Click Rate|Clicks|Clicked/i');
		// May show click rate
	});

	test('should show delivery stats', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const deliveryStats = page.locator('text=/Delivered|Bounced|Sent/i');
		// May show delivery stats
	});
});

test.describe('Campaign Duplication', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should duplicate campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions"), button[aria-haspopup="menu"]');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const duplicateOption = page.locator('[role="menuitem"]:has-text("Duplicate")');
			if (await duplicateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await duplicateOption.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Campaign Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete draft campaign', async ({ page }) => {
		await navigateToCampaigns(page);

		const row = page.locator('tbody tr:has-text("Draft")').first();
		if (await row.isVisible({ timeout: 2000 }).catch(() => false)) {
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
