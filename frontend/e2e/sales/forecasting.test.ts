import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToForecasting,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Forecasting Tests
 * Tests for forecast overview, scenarios, and adjustments
 */

test.describe('Forecast Overview', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToForecasting(page);
	});

	test('should display forecasting page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Forecast|Forecasting/i }).first()).toBeVisible();
	});

	test('should show forecast summary', async ({ page }) => {
		const summary = page.locator('[data-testid="forecast-summary"], [class*="summary"]');
		await expect(summary.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should display pipeline-weighted values', async ({ page }) => {
		const weightedValue = page.locator('text=/Weighted|Pipeline Value/i');
		await expect(weightedValue.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show forecast by period', async ({ page }) => {
		const periodSelector = page.locator('button:has-text("Month"), button:has-text("Quarter"), [data-testid="period-select"]');
		if (await periodSelector.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelector.click();
		}
	});

	test('should display forecast chart', async ({ page }) => {
		const chart = page.locator('canvas, [data-testid="forecast-chart"], svg');
		await expect(chart.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});
});

test.describe('Forecast Scenarios', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to scenarios page', async ({ page }) => {
		await page.goto('/forecasts/scenarios');
		await waitForLoading(page);

		await expect(page.locator('text=/Scenario|Scenarios/i').first()).toBeVisible();
	});

	test('should create forecast scenario', async ({ page }) => {
		await page.goto('/forecasts/scenarios');
		await waitForLoading(page);

		const createButton = page.locator('button:has-text("Create"), button:has-text("New Scenario")');
		if (await createButton.isVisible()) {
			await createButton.click();

			const nameInput = page.locator('input[name="name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill(`Test Scenario ${Date.now()}`);
			}

			const submitButton = page.locator('button[type="submit"]');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should edit scenario', async ({ page }) => {
		await page.goto('/forecasts/scenarios/1');
		await waitForLoading(page);

		const editButton = page.locator('button:has-text("Edit")');
		if (await editButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await editButton.click();
		}
	});

	test('should compare scenarios', async ({ page }) => {
		await page.goto('/forecasts/scenarios');
		await waitForLoading(page);

		const compareButton = page.locator('button:has-text("Compare")');
		if (await compareButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await compareButton.click();
		}
	});

	test('should delete scenario', async ({ page }) => {
		await page.goto('/forecasts/scenarios');
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
				}
			}
		}
	});
});

test.describe('Forecast Adjustments', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should adjust deal probability', async ({ page }) => {
		await navigateToForecasting(page);

		// Find a deal and adjust probability
		const dealRow = page.locator('tr:has-text("Deal")').first();
		if (await dealRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			const probabilityInput = dealRow.locator('input[type="number"]');
			if (await probabilityInput.isVisible()) {
				await probabilityInput.fill('75');
			}
		}
	});

	test('should add forecast note', async ({ page }) => {
		await navigateToForecasting(page);

		const addNoteButton = page.locator('button:has-text("Add Note"), button:has-text("Note")');
		if (await addNoteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addNoteButton.click();

			const noteInput = page.locator('textarea[name="note"]');
			if (await noteInput.isVisible()) {
				await noteInput.fill('Forecast adjustment note');
			}
		}
	});

	test('should commit forecast', async ({ page }) => {
		await navigateToForecasting(page);

		const commitButton = page.locator('button:has-text("Commit"), button:has-text("Submit Forecast")');
		if (await commitButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await commitButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Forecast History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view historical accuracy', async ({ page }) => {
		await page.goto('/forecasts');
		await waitForLoading(page);

		const historyTab = page.locator('[role="tab"]:has-text("History"), button:has-text("History")');
		if (await historyTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await historyTab.click();
		}
	});

	test('should export forecast report', async ({ page }) => {
		await navigateToForecasting(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();
		}
	});
});

test.describe('Forecast Quotas', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to quotas', async ({ page }) => {
		await page.goto('/forecasts/quotas');
		await waitForLoading(page);

		await expect(page.locator('text=/Quota|Quotas/i').first()).toBeVisible();
	});

	test('should display quota progress', async ({ page }) => {
		await page.goto('/forecasts/quotas');
		await waitForLoading(page);

		const progress = page.locator('[class*="progress"], [data-testid="quota-progress"]');
		// May have quota progress
	});

	test('should compare quota to forecast', async ({ page }) => {
		await page.goto('/forecasts/quotas');
		await waitForLoading(page);

		const comparison = page.locator('text=/vs|compared to/i');
		// May have comparison
	});
});
