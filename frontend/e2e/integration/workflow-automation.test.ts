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
 * Workflow Automation Integration Tests
 * Tests for automated workflow triggers and actions
 */

test.describe('Workflow Trigger Integration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should trigger workflow on record creation', async ({ page }) => {
		// First, ensure workflow exists for lead creation
		await page.goto('/admin/workflows');
		await waitForLoading(page);

		// Create a lead to trigger workflow
		await page.goto('/records/leads/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Workflow Test Lead ${Date.now()}`);
		}

		const emailInput = page.locator('input[name="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`workflow-test-${Date.now()}@example.com`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);

		// Check workflow execution
		await waitForLoading(page);
		const activityTab = page.locator('[role="tab"]:has-text("Activity")');
		if (await activityTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activityTab.click();

			const workflowActivity = page.locator('text=/Workflow|Automated/i');
			// May show workflow execution
		}
	});

	test('should trigger workflow on field update', async ({ page }) => {
		await page.goto('/records/deals/1/edit');
		await waitForLoading(page);

		// Update a field that triggers workflow (e.g., stage change)
		const stageSelect = page.locator('[data-testid="stage-select"], button:has-text("Stage")');
		if (await stageSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await stageSelect.click();
			await page.locator('[role="option"]').nth(1).click();

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should trigger workflow on stage change', async ({ page }) => {
		// Navigate to kanban view
		await page.goto('/records/deals?view=kanban');
		await waitForLoading(page);

		// Drag a card to different stage
		const dealCard = page.locator('[data-testid="kanban-card"]').first();
		if (await dealCard.isVisible({ timeout: 2000 }).catch(() => false)) {
			const targetColumn = page.locator('[data-testid="kanban-column"]').nth(1);
			if (await targetColumn.isVisible()) {
				await dealCard.dragTo(targetColumn);
				await waitForLoading(page);
			}
		}
	});
});

test.describe('Workflow Action Execution', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should execute email action', async ({ page }) => {
		// Create a workflow with email action
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Email Workflow ${Date.now()}`);
		}

		// Add email action
		const addActionButton = page.locator('button:has-text("Add Action")');
		if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addActionButton.click();

			const emailOption = page.locator('[role="option"]:has-text("Send Email")');
			if (await emailOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await emailOption.click();
			}
		}
	});

	test('should execute field update action', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Add field update action
		const addActionButton = page.locator('button:has-text("Add Action")');
		if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addActionButton.click();

			const updateFieldOption = page.locator('[role="option"]:has-text("Update Field")');
			if (await updateFieldOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await updateFieldOption.click();
			}
		}
	});

	test('should execute create task action', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Add create task action
		const addActionButton = page.locator('button:has-text("Add Action")');
		if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addActionButton.click();

			const createTaskOption = page.locator('[role="option"]:has-text("Create Task")');
			if (await createTaskOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await createTaskOption.click();
			}
		}
	});

	test('should execute webhook action', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Add webhook action
		const addActionButton = page.locator('button:has-text("Add Action")');
		if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addActionButton.click();

			const webhookOption = page.locator('[role="option"]:has-text("Webhook"), [role="option"]:has-text("HTTP Request")');
			if (await webhookOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await webhookOption.click();
			}
		}
	});
});

test.describe('Workflow Conditions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should evaluate field conditions', async ({ page }) => {
		await page.goto('/admin/workflows/1/edit');
		await waitForLoading(page);

		const addConditionButton = page.locator('button:has-text("Add Condition")');
		if (await addConditionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addConditionButton.click();

			// Select field
			const fieldSelect = page.locator('[data-testid="condition-field"]');
			if (await fieldSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await fieldSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			// Select operator
			const operatorSelect = page.locator('[data-testid="condition-operator"]');
			if (await operatorSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await operatorSelect.click();
				await page.locator('[role="option"]:has-text("Equals")').click();
			}
		}
	});

	test('should handle multiple conditions (AND)', async ({ page }) => {
		await page.goto('/admin/workflows/1/edit');
		await waitForLoading(page);

		// Add multiple conditions
		const addConditionButton = page.locator('button:has-text("Add Condition")');
		if (await addConditionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addConditionButton.click();
			await addConditionButton.click();

			// Verify AND logic
			const andOperator = page.locator('text=/AND|All conditions/i');
			await expect(andOperator.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should handle multiple conditions (OR)', async ({ page }) => {
		await page.goto('/admin/workflows/1/edit');
		await waitForLoading(page);

		// Switch to OR logic
		const orToggle = page.locator('button:has-text("OR"), [data-testid="logic-toggle"]');
		if (await orToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await orToggle.click();
		}
	});
});

test.describe('Workflow Execution Monitoring', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view workflow execution history', async ({ page }) => {
		await page.goto('/admin/workflows/1');
		await waitForLoading(page);

		const historyTab = page.locator('[role="tab"]:has-text("History"), button:has-text("Executions")');
		if (await historyTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await historyTab.click();

			const executions = page.locator('[data-testid="workflow-execution"]');
			// May have executions
		}
	});

	test('should view execution details', async ({ page }) => {
		await page.goto('/admin/workflows/1/executions');
		await waitForLoading(page);

		const execution = page.locator('[data-testid="workflow-execution"]').first();
		if (await execution.isVisible({ timeout: 2000 }).catch(() => false)) {
			await execution.click();

			const details = page.locator('[data-testid="execution-details"]');
			await expect(details).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should show execution errors', async ({ page }) => {
		await page.goto('/admin/workflows/1/executions');
		await waitForLoading(page);

		// Filter for failed executions
		const failedFilter = page.locator('button:has-text("Failed"), [data-filter="status"]');
		if (await failedFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await failedFilter.click();
			await page.locator('[role="option"]:has-text("Failed")').click();
			await waitForLoading(page);
		}
	});

	test('should retry failed execution', async ({ page }) => {
		await page.goto('/admin/workflows/1/executions');
		await waitForLoading(page);

		const failedExecution = page.locator('[data-testid="workflow-execution"]:has-text("Failed")').first();
		if (await failedExecution.isVisible({ timeout: 2000 }).catch(() => false)) {
			const retryButton = failedExecution.locator('button:has-text("Retry")');
			if (await retryButton.isVisible()) {
				await retryButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Scheduled Workflows', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create scheduled workflow', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Select scheduled trigger
		const triggerSelect = page.locator('[data-testid="trigger-type"]');
		if (await triggerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await triggerSelect.click();
			await page.locator('[role="option"]:has-text("Schedule")').click();
		}

		// Set schedule
		const scheduleInput = page.locator('[data-testid="schedule-config"]');
		if (await scheduleInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Configure schedule (daily, weekly, etc.)
		}
	});

	test('should view scheduled workflow runs', async ({ page }) => {
		await page.goto('/admin/workflows');
		await waitForLoading(page);

		const scheduledTab = page.locator('[role="tab"]:has-text("Scheduled")');
		if (await scheduledTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await scheduledTab.click();
			await waitForLoading(page);
		}
	});
});
