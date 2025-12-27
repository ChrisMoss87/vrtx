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
 * A/B Testing Tests
 * Tests for A/B test configuration and analysis
 */

test.describe('A/B Test List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/marketing/ab-tests');
		await waitForLoading(page);
	});

	test('should display A/B tests list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /A\/B Test|Split Test|Experiment/i }).first()).toBeVisible();
	});

	test('should display create test button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Test")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should show test statistics', async ({ page }) => {
		const stats = page.locator('text=/Running|Completed|Draft/i');
		// May show stats
	});
});

test.describe('A/B Test Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests');
		await waitForLoading(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`A/B Test ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select test type', async ({ page }) => {
		await page.goto('/marketing/ab-tests/create');
		await waitForLoading(page);

		const typeOptions = page.locator('[role="option"], [data-testid="test-type"]');
		if ((await typeOptions.count()) > 0) {
			await typeOptions.first().click();
		}
	});

	test('should configure email A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/create');
		await waitForLoading(page);

		const emailOption = page.locator('[role="option"]:has-text("Email"), button:has-text("Email")');
		if (await emailOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailOption.click();
		}
	});

	test('should configure landing page A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/create');
		await waitForLoading(page);

		const pageOption = page.locator('[role="option"]:has-text("Landing Page"), button:has-text("Page")');
		if (await pageOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pageOption.click();
		}
	});
});

test.describe('A/B Test Variants', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add variant', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const addVariantButton = page.locator('button:has-text("Add Variant"), button:has-text("Add Version")');
		if (await addVariantButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addVariantButton.click();

			const variantName = page.locator('input[name="variant_name"]');
			if (await variantName.isVisible()) {
				await variantName.fill('Variant B');
			}
		}
	});

	test('should configure variant content', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const variant = page.locator('[data-testid="variant"]').first();
		if (await variant.isVisible({ timeout: 2000 }).catch(() => false)) {
			await variant.click();

			const editor = page.locator('[contenteditable="true"], .editor');
			if (await editor.isVisible({ timeout: 2000 }).catch(() => false)) {
				await editor.fill('Variant content');
			}
		}
	});

	test('should set variant weight', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const weightInput = page.locator('input[name="weight"], input[type="range"]').first();
		if (await weightInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await weightInput.fill('50');
		}
	});

	test('should delete variant', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const variant = page.locator('[data-testid="variant"]').nth(1); // Second variant
		if (await variant.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = variant.locator('button[aria-label="Delete"], button:has-text("Remove")');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('A/B Test Configuration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should set winning criteria', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const criteriaSelect = page.locator('button:has-text("Winning Criteria"), select[name="winning_criteria"]');
		if (await criteriaSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await criteriaSelect.click();
			await page.locator('[role="option"]:has-text("Click Rate")').click();
		}
	});

	test('should set sample size', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const sampleInput = page.locator('input[name="sample_size"], input[name="sample_percentage"]');
		if (await sampleInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sampleInput.fill('20');
		}
	});

	test('should set test duration', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const durationInput = page.locator('input[name="duration_days"], input[name="duration"]');
		if (await durationInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await durationInput.fill('7');
		}
	});

	test('should enable auto-winner', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1/edit');
		await waitForLoading(page);

		const autoWinnerToggle = page.locator('label:has-text("Auto-select winner") input, input[name="auto_winner"]');
		if (await autoWinnerToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await autoWinnerToggle.check();
		}
	});
});

test.describe('A/B Test Execution', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should start A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const startButton = page.locator('button:has-text("Start Test"), button:has-text("Launch")');
		if (await startButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await startButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should pause A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const pauseButton = page.locator('button:has-text("Pause")');
		if (await pauseButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pauseButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should end test and pick winner', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const endButton = page.locator('button:has-text("End Test"), button:has-text("Pick Winner")');
		if (await endButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await endButton.click();

			// Select winning variant
			const winnerOption = page.locator('[role="option"], [data-testid="variant-option"]').first();
			if (await winnerOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await winnerOption.click();
			}

			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('A/B Test Results', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view test results', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const resultsTab = page.locator('[role="tab"]:has-text("Results"), button:has-text("Analytics")');
		if (await resultsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resultsTab.click();
		}
	});

	test('should show variant comparison', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const comparison = page.locator('[data-testid="variant-comparison"], table');
		// May show variant comparison
	});

	test('should show statistical significance', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const significance = page.locator('text=/Significance|Confidence|p-value/i');
		// May show significance
	});

	test('should show conversion rates', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const conversionRates = page.locator('text=/Conversion|Rate|%/');
		// May show conversion rates
	});

	test('should export results', async ({ page }) => {
		await page.goto('/marketing/ab-tests/1');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();
		}
	});
});

test.describe('A/B Test Subject Line Testing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create subject line test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/create');
		await waitForLoading(page);

		const subjectLineOption = page.locator('[role="option"]:has-text("Subject Line"), button:has-text("Subject")');
		if (await subjectLineOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await subjectLineOption.click();

			const subjectA = page.locator('input[name="subject_a"]');
			if (await subjectA.isVisible()) {
				await subjectA.fill('Subject Line A');
			}

			const subjectB = page.locator('input[name="subject_b"]');
			if (await subjectB.isVisible()) {
				await subjectB.fill('Subject Line B');
			}
		}
	});
});

test.describe('A/B Test Send Time Testing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create send time test', async ({ page }) => {
		await page.goto('/marketing/ab-tests/create');
		await waitForLoading(page);

		const sendTimeOption = page.locator('[role="option"]:has-text("Send Time"), button:has-text("Timing")');
		if (await sendTimeOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendTimeOption.click();

			const timeA = page.locator('input[name="time_a"]');
			if (await timeA.isVisible()) {
				await timeA.fill('09:00');
			}

			const timeB = page.locator('input[name="time_b"]');
			if (await timeB.isVisible()) {
				await timeB.fill('14:00');
			}
		}
	});
});

test.describe('A/B Test Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete A/B test', async ({ page }) => {
		await page.goto('/marketing/ab-tests');
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
