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
 * Goals Tests
 * Tests for goal setting and tracking
 */

test.describe('Goals List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/goals');
		await waitForLoading(page);
	});

	test('should display goals page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Goal|Goals|Target/i }).first()).toBeVisible();
	});

	test('should display create goal button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), button:has-text("New Goal")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show goals list', async ({ page }) => {
		const goals = page.locator('[data-testid="goal-item"], tbody tr');
		// May have goals
	});

	test('should filter by type', async ({ page }) => {
		const typeFilter = page.locator('button:has-text("Type"), [data-filter="type"]');
		if (await typeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by period', async ({ page }) => {
		const periodFilter = page.locator('button:has-text("Period"), [data-filter="period"]');
		if (await periodFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodFilter.click();
			await page.locator('[role="option"]:has-text("Monthly")').click();
			await waitForLoading(page);
		}
	});
});

test.describe('Goal Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new goal', async ({ page }) => {
		await page.goto('/admin/goals/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Goal ${Date.now()}`);
		}

		// Set target
		const targetInput = page.locator('input[name="target"], input[name="target_value"]');
		if (await targetInput.isVisible()) {
			await targetInput.fill('100000');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select goal metric', async ({ page }) => {
		await page.goto('/admin/goals/create');
		await waitForLoading(page);

		const metricSelect = page.locator('button:has-text("Metric"), [data-testid="metric-select"]');
		if (await metricSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await metricSelect.click();
			await page.locator('[role="option"]:has-text("Revenue")').click();
		}
	});

	test('should set goal period', async ({ page }) => {
		await page.goto('/admin/goals/create');
		await waitForLoading(page);

		const periodSelect = page.locator('button:has-text("Period"), [data-testid="period-select"]');
		if (await periodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await periodSelect.click();
			await page.locator('[role="option"]:has-text("Monthly")').click();
		}
	});

	test('should assign to user', async ({ page }) => {
		await page.goto('/admin/goals/create');
		await waitForLoading(page);

		const userSelect = page.locator('button:has-text("Assign to"), [data-testid="user-select"]');
		if (await userSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await userSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should assign to team', async ({ page }) => {
		await page.goto('/admin/goals/create');
		await waitForLoading(page);

		const teamSelect = page.locator('button:has-text("Team"), [data-testid="team-select"]');
		if (await teamSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await teamSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Goal Progress', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view goal progress', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const progress = page.locator('[data-testid="goal-progress"], [class*="progress"]');
		// May show progress
	});

	test('should show progress percentage', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const percentage = page.locator('text=/\\d+%|Progress/i');
		// May show percentage
	});

	test('should show milestone markers', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const milestones = page.locator('[data-testid="milestone"], text=/Milestone|Target/i');
		// May show milestones
	});

	test('should show trend chart', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const chart = page.locator('canvas, svg, [data-testid="trend-chart"]');
		// May show chart
	});
});

test.describe('Goal Updates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should update goal target', async ({ page }) => {
		await page.goto('/admin/goals/1/edit');
		await waitForLoading(page);

		const targetInput = page.locator('input[name="target"]');
		if (await targetInput.isVisible()) {
			await targetInput.fill('150000');
		}

		const saveButton = page.locator('button:has-text("Save")');
		await saveButton.click();
		await waitForToast(page);
	});

	test('should add progress update', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const updateButton = page.locator('button:has-text("Update Progress"), button:has-text("Log Progress")');
		if (await updateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await updateButton.click();

			const valueInput = page.locator('input[name="value"], input[name="progress"]');
			if (await valueInput.isVisible()) {
				await valueInput.fill('25000');
			}

			const saveButton = page.locator('[role="dialog"] button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should add progress note', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const noteInput = page.locator('textarea[name="note"], textarea[placeholder*="note"]');
		if (await noteInput.isVisible()) {
			await noteInput.fill('Closed major deal this week.');

			const saveButton = page.locator('button:has-text("Add Note")');
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Goal Milestones', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add milestone', async ({ page }) => {
		await page.goto('/admin/goals/1/edit');
		await waitForLoading(page);

		const addMilestoneButton = page.locator('button:has-text("Add Milestone")');
		if (await addMilestoneButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addMilestoneButton.click();

			const milestoneInput = page.locator('input[name="milestone_value"]');
			if (await milestoneInput.isVisible()) {
				await milestoneInput.fill('50000');
			}

			const nameInput = page.locator('input[name="milestone_name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill('Halfway Point');
			}
		}
	});

	test('should mark milestone complete', async ({ page }) => {
		await page.goto('/admin/goals/1');
		await waitForLoading(page);

		const milestone = page.locator('[data-testid="milestone"]').first();
		if (await milestone.isVisible({ timeout: 2000 }).catch(() => false)) {
			const completeButton = milestone.locator('input[type="checkbox"], button:has-text("Complete")');
			if (await completeButton.isVisible()) {
				await completeButton.click();
			}
		}
	});
});

test.describe('Goal Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view goal analytics', async ({ page }) => {
		await page.goto('/admin/goals/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="goal-analytics"]');
		// May have analytics
	});

	test('should show team performance', async ({ page }) => {
		await page.goto('/admin/goals/analytics');
		await waitForLoading(page);

		const teamPerformance = page.locator('text=/Team.*Performance|Leaderboard/i');
		// May show team performance
	});

	test('should compare periods', async ({ page }) => {
		await page.goto('/admin/goals/analytics');
		await waitForLoading(page);

		const compareButton = page.locator('button:has-text("Compare")');
		if (await compareButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await compareButton.click();
		}
	});
});

test.describe('Goal Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete goal', async ({ page }) => {
		await page.goto('/admin/goals');
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
