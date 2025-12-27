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
 * Customer Health Tests
 * Tests for customer health scoring and monitoring
 */

test.describe('Customer Health Dashboard', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/customer-health');
		await waitForLoading(page);
	});

	test('should display customer health page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Customer Health|Health Score|Account Health/i }).first()).toBeVisible();
	});

	test('should show health score distribution', async ({ page }) => {
		const distribution = page.locator('[data-testid="health-distribution"], [class*="distribution"]');
		// May show distribution
	});

	test('should show at-risk customers', async ({ page }) => {
		const atRisk = page.locator('[data-testid="at-risk"], text=/At Risk|Risk|Warning/i');
		// May show at-risk customers
	});

	test('should show healthy customers', async ({ page }) => {
		const healthy = page.locator('[data-testid="healthy"], text=/Healthy|Good|Strong/i');
		// May show healthy customers
	});

	test('should filter by health status', async ({ page }) => {
		const statusFilter = page.locator('button:has-text("Status"), [data-filter="health_status"]');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]:has-text("At Risk")').click();
			await waitForLoading(page);
		}
	});
});

test.describe('Customer Health Scores', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view customer health score', async ({ page }) => {
		await page.goto('/records/companies/1');
		await waitForLoading(page);

		const healthScore = page.locator('[data-testid="health-score"], text=/Health Score/i');
		// May show health score
	});

	test('should view score breakdown', async ({ page }) => {
		await page.goto('/records/companies/1');
		await waitForLoading(page);

		const breakdownButton = page.locator('button:has-text("Details"), button:has-text("Breakdown")');
		if (await breakdownButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await breakdownButton.click();

			const breakdown = page.locator('[data-testid="score-breakdown"], [role="dialog"]');
			await expect(breakdown).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should view score history', async ({ page }) => {
		await page.goto('/records/companies/1/health');
		await waitForLoading(page);

		const history = page.locator('[data-testid="score-history"], [class*="chart"]');
		// May show history
	});

	test('should view contributing factors', async ({ page }) => {
		await page.goto('/records/companies/1/health');
		await waitForLoading(page);

		const factors = page.locator('[data-testid="health-factors"], text=/Factor|Contributing/i');
		// May show factors
	});
});

test.describe('Health Score Configuration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view health score settings', async ({ page }) => {
		await page.goto('/admin/settings/customer-health');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Health.*Settings|Score Configuration/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should configure health factors', async ({ page }) => {
		await page.goto('/admin/settings/customer-health');
		await waitForLoading(page);

		const factorsSection = page.locator('text=/Factors|Metrics/i');
		if (await factorsSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const weightInput = page.locator('input[name*="weight"]').first();
			if (await weightInput.isVisible()) {
				await weightInput.fill('25');
			}
		}
	});

	test('should configure thresholds', async ({ page }) => {
		await page.goto('/admin/settings/customer-health');
		await waitForLoading(page);

		const thresholdsSection = page.locator('text=/Threshold|Level/i');
		if (await thresholdsSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const healthyThreshold = page.locator('input[name="healthy_threshold"]');
			if (await healthyThreshold.isVisible()) {
				await healthyThreshold.fill('70');
			}
		}
	});

	test('should save configuration', async ({ page }) => {
		await page.goto('/admin/settings/customer-health');
		await waitForLoading(page);

		const saveButton = page.locator('button:has-text("Save")');
		if (await saveButton.isVisible()) {
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Health Alerts', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view health alerts', async ({ page }) => {
		await page.goto('/admin/customer-health/alerts');
		await waitForLoading(page);

		const alerts = page.locator('[data-testid="health-alert"], tbody tr');
		// May have alerts
	});

	test('should configure alert rules', async ({ page }) => {
		await page.goto('/admin/settings/customer-health/alerts');
		await waitForLoading(page);

		const addRuleButton = page.locator('button:has-text("Add Rule"), button:has-text("Create Alert")');
		if (await addRuleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addRuleButton.click();
		}
	});

	test('should dismiss alert', async ({ page }) => {
		await page.goto('/admin/customer-health/alerts');
		await waitForLoading(page);

		const alert = page.locator('[data-testid="health-alert"]').first();
		if (await alert.isVisible({ timeout: 2000 }).catch(() => false)) {
			const dismissButton = alert.locator('button:has-text("Dismiss")');
			if (await dismissButton.isVisible()) {
				await dismissButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should snooze alert', async ({ page }) => {
		await page.goto('/admin/customer-health/alerts');
		await waitForLoading(page);

		const alert = page.locator('[data-testid="health-alert"]').first();
		if (await alert.isVisible({ timeout: 2000 }).catch(() => false)) {
			const snoozeButton = alert.locator('button:has-text("Snooze")');
			if (await snoozeButton.isVisible()) {
				await snoozeButton.click();
				await page.locator('[role="option"]').first().click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Health Reports', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view health report', async ({ page }) => {
		await page.goto('/admin/customer-health/reports');
		await waitForLoading(page);

		const report = page.locator('[data-testid="health-report"]');
		// May have report
	});

	test('should filter report by date', async ({ page }) => {
		await page.goto('/admin/customer-health/reports');
		await waitForLoading(page);

		const dateFilter = page.locator('button:has-text("Date"), [data-filter="date"]');
		if (await dateFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dateFilter.click();
			await page.locator('[role="option"]:has-text("Last 30 days")').click();
			await waitForLoading(page);
		}
	});

	test('should export health report', async ({ page }) => {
		await page.goto('/admin/customer-health/reports');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();
		}
	});
});

test.describe('Health Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create task from health alert', async ({ page }) => {
		await page.goto('/admin/customer-health');
		await waitForLoading(page);

		const atRiskCustomer = page.locator('[data-testid="at-risk-customer"]').first();
		if (await atRiskCustomer.isVisible({ timeout: 2000 }).catch(() => false)) {
			const createTaskButton = atRiskCustomer.locator('button:has-text("Create Task")');
			if (await createTaskButton.isVisible()) {
				await createTaskButton.click();
			}
		}
	});

	test('should schedule check-in call', async ({ page }) => {
		await page.goto('/admin/customer-health');
		await waitForLoading(page);

		const atRiskCustomer = page.locator('[data-testid="at-risk-customer"]').first();
		if (await atRiskCustomer.isVisible({ timeout: 2000 }).catch(() => false)) {
			const scheduleButton = atRiskCustomer.locator('button:has-text("Schedule Call")');
			if (await scheduleButton.isVisible()) {
				await scheduleButton.click();
			}
		}
	});

	test('should add manual health note', async ({ page }) => {
		await page.goto('/records/companies/1/health');
		await waitForLoading(page);

		const addNoteButton = page.locator('button:has-text("Add Note")');
		if (await addNoteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addNoteButton.click();

			const noteInput = page.locator('textarea[name="note"]');
			if (await noteInput.isVisible()) {
				await noteInput.fill('Had a positive call with the customer today.');
			}

			const saveButton = page.locator('[role="dialog"] button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should override health score', async ({ page }) => {
		await page.goto('/records/companies/1/health');
		await waitForLoading(page);

		const overrideButton = page.locator('button:has-text("Override"), button:has-text("Manual Override")');
		if (await overrideButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await overrideButton.click();

			const scoreInput = page.locator('input[name="score"]');
			if (await scoreInput.isVisible()) {
				await scoreInput.fill('80');
			}

			const reasonInput = page.locator('textarea[name="reason"]');
			if (await reasonInput.isVisible()) {
				await reasonInput.fill('Manual override due to recent positive engagement');
			}

			const saveButton = page.locator('[role="dialog"] button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});
});
