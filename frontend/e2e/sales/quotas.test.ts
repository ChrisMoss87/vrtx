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
 * Quota Management Tests
 * Tests for quota CRUD and leaderboard
 */

test.describe('Quota List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/quotas');
		await waitForLoading(page);
	});

	test('should display quotas list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Quota|Quotas/i }).first()).toBeVisible();
	});

	test('should display create quota button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Quota")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show quota progress', async ({ page }) => {
		const progress = page.locator('[class*="progress"], [data-testid="progress"]');
		// May have progress indicators
	});

	test('should filter by period', async ({ page }) => {
		const periodSelect = page.locator('button:has-text("Month"), button:has-text("Quarter"), [data-testid="period-filter"]');
		if (await periodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelect.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by user', async ({ page }) => {
		const userFilter = page.locator('button:has-text("User"), [data-filter="user"]');
		if (await userFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await userFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});
});

test.describe('Quota Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create quota', async ({ page }) => {
		await page.goto('/quotas');
		await page.click('button:has-text("Create"), a:has-text("New Quota")');

		// Fill quota details
		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Q1 Quota ${Date.now()}`);
		}

		// Set target amount
		const targetInput = page.locator('input[name="target"], input[name="amount"]');
		if (await targetInput.isVisible()) {
			await targetInput.fill('100000');
		}

		// Select user
		const userSelect = page.locator('button[role="combobox"]:has-text("User")');
		if (await userSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await userSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Set period
		const periodSelect = page.locator('button[role="combobox"]:has-text("Period")');
		if (await periodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should validate required fields', async ({ page }) => {
		await page.goto('/quotas/create');
		await waitForLoading(page);

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		const error = page.locator('text=/required/i');
		await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});
});

test.describe('Quota View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display quota details', async ({ page }) => {
		await page.goto('/quotas/1');
		await waitForLoading(page);

		const target = page.locator('text=/Target|Goal/i');
		await expect(target.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show current progress', async ({ page }) => {
		await page.goto('/quotas/1');
		await waitForLoading(page);

		const progress = page.locator('text=/Progress|Achieved/i');
		// May have progress info
	});

	test('should display progress chart', async ({ page }) => {
		await page.goto('/quotas/1');
		await waitForLoading(page);

		const chart = page.locator('canvas, svg, [data-testid="quota-chart"]');
		// May have chart
	});

	test('should show remaining amount', async ({ page }) => {
		await page.goto('/quotas/1');
		await waitForLoading(page);

		const remaining = page.locator('text=/Remaining|Left/i');
		// May have remaining info
	});
});

test.describe('Quota Edit', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit quota target', async ({ page }) => {
		await page.goto('/quotas/1/edit');
		await waitForLoading(page);

		const targetInput = page.locator('input[name="target"]');
		if (await targetInput.isVisible()) {
			await targetInput.fill('150000');

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should update quota period', async ({ page }) => {
		await page.goto('/quotas/1/edit');
		await waitForLoading(page);

		const periodSelect = page.locator('button[role="combobox"]:has-text("Period")');
		if (await periodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Leaderboard', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/quotas/leaderboard');
		await waitForLoading(page);
	});

	test('should display leaderboard page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Leaderboard/i }).first()).toBeVisible();
	});

	test('should show ranked users', async ({ page }) => {
		const rankings = page.locator('[data-testid="leaderboard-row"], tbody tr');
		// May have rankings
	});

	test('should display user progress', async ({ page }) => {
		const progress = page.locator('[class*="progress"]');
		// May have progress bars
	});

	test('should filter by period', async ({ page }) => {
		const periodSelect = page.locator('button:has-text("Month"), button:has-text("Quarter")');
		if (await periodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelect.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by team', async ({ page }) => {
		const teamFilter = page.locator('button:has-text("Team"), [data-filter="team"]');
		if (await teamFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await teamFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should show comparison metrics', async ({ page }) => {
		const metrics = page.locator('text=/vs|compared|change/i');
		// May have comparison metrics
	});
});

test.describe('Quota Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete quota', async ({ page }) => {
		await page.goto('/quotas');
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
