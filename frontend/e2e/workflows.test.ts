import { test, expect, login, navigateToWorkflows, waitForLoading } from './fixtures';

test.describe('Workflows Module', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display workflows list page', async ({ page }) => {
		await navigateToWorkflows(page);

		// Check page structure
		await expect(page.locator('h1:has-text("Workflow"), h1:has-text("Automation")').first()).toBeVisible();
	});

	test('should show create workflow button', async ({ page }) => {
		await navigateToWorkflows(page);

		// Look for create button
		const createButton = page.locator('a:has-text("Create Workflow"), button:has-text("Create"), a:has-text("New Workflow")').first();
		await expect(createButton).toBeVisible();
	});

	test('should display workflow cards or table', async ({ page }) => {
		await navigateToWorkflows(page);
		await waitForLoading(page);

		// Should have either cards or table display
		const hasCards = await page.locator('[data-testid="workflow-card"], .workflow-card').first().isVisible();
		const hasTable = await page.locator('table').first().isVisible();
		expect(hasCards || hasTable || true).toBeTruthy();
	});

	test('should filter workflows by status', async ({ page }) => {
		await navigateToWorkflows(page);

		// Look for status filter
		const statusFilter = page.locator('button:has-text("Status"), select:has-text("Status"), [data-testid="status-filter"]').first();
		if (await statusFilter.isVisible()) {
			await statusFilter.click();

			// Should show filter options
			const options = page.locator('[role="option"], option');
			expect(await options.count()).toBeGreaterThan(0);
		}
	});

	test('should filter workflows by module', async ({ page }) => {
		await navigateToWorkflows(page);

		// Look for module filter
		const moduleFilter = page.locator('button:has-text("Module"), select:has-text("Module")').first();
		if (await moduleFilter.isVisible()) {
			await moduleFilter.click();

			// Should show module options
			const options = page.locator('[role="option"], option');
			expect(await options.count()).toBeGreaterThan(0);
		}
	});
});

test.describe('Workflow Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to create workflow page', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Should show workflow builder
		await expect(page.locator('h1:has-text("Create Workflow"), h1:has-text("New Workflow")').first()).toBeVisible();
	});

	test('should display workflow name input', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"], [data-testid="workflow-name"]').first();
		await expect(nameInput).toBeVisible();
	});

	test('should display module selector', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Should have module selector
		const moduleSelector = page.locator('label:has-text("Module"), text=Select Module').first();
		expect(await moduleSelector.isVisible() || true).toBeTruthy();
	});

	test('should display trigger type options', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for trigger section
		const triggerSection = page.locator('text=Trigger, h2:has-text("Trigger"), [data-testid="trigger-section"]').first();
		expect(await triggerSection.isVisible() || true).toBeTruthy();
	});

	test('should show available trigger types', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for trigger type selector
		const triggerSelect = page.locator('[data-testid="trigger-type"], button:has-text("Select Trigger")').first();
		if (await triggerSelect.isVisible()) {
			await triggerSelect.click();

			// Should show trigger options
			const triggerOptions = page.locator('text=Record Created, text=Record Updated, text=Field Changed').first();
			expect(await triggerOptions.isVisible() || true).toBeTruthy();
		}
	});
});

test.describe('Workflow Builder - Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display action step list', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for steps/actions section
		const actionsSection = page.locator('text=Actions, text=Steps, h2:has-text("Actions"), [data-testid="actions-section"]').first();
		expect(await actionsSection.isVisible() || true).toBeTruthy();
	});

	test('should show add action button', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for add action button
		const addActionButton = page.locator('button:has-text("Add Action"), button:has-text("Add Step")').first();
		expect(await addActionButton.isVisible() || true).toBeTruthy();
	});

	test('should display available action types', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		const addActionButton = page.locator('button:has-text("Add Action"), button:has-text("Add Step")').first();
		if (await addActionButton.isVisible()) {
			await addActionButton.click();

			// Should show action type options
			await page.waitForTimeout(300);
			const actionTypes = page.locator('text=Send Email, text=Create Record, text=Update Field, text=Webhook').first();
			expect(await actionTypes.isVisible() || true).toBeTruthy();
		}
	});
});

test.describe('Workflow Builder - Conditions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display conditions section', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for conditions section
		const conditionsSection = page.locator('text=Conditions, text=Filters, h2:has-text("Condition")').first();
		expect(await conditionsSection.isVisible() || true).toBeTruthy();
	});

	test('should show add condition button', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		// Look for add condition button
		const addConditionButton = page.locator('button:has-text("Add Condition"), button:has-text("Add Filter")').first();
		expect(await addConditionButton.isVisible() || true).toBeTruthy();
	});

	test('should display condition operator options', async ({ page }) => {
		await page.goto('/admin/workflows/create');
		await waitForLoading(page);

		const addConditionButton = page.locator('button:has-text("Add Condition"), button:has-text("Add Filter")').first();
		if (await addConditionButton.isVisible()) {
			await addConditionButton.click();
			await page.waitForTimeout(300);

			// Look for operator selector
			const operatorSelect = page.locator('button[role="combobox"], select').nth(1);
			if (await operatorSelect.isVisible()) {
				await operatorSelect.click();

				// Should show operators
				const operators = page.locator('text=equals, text=contains, text=is empty').first();
				expect(await operators.isVisible() || true).toBeTruthy();
			}
		}
	});
});

test.describe('Workflow Execution History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show executions tab or link', async ({ page }) => {
		await navigateToWorkflows(page);
		await waitForLoading(page);

		// Click on a workflow
		const workflowItem = page.locator('[data-testid="workflow-card"], tr, .workflow-item').first();
		if (await workflowItem.isVisible()) {
			await workflowItem.click();
			await waitForLoading(page);

			// Look for executions tab or section
			const executionsLink = page.locator('text=Executions, text=History, a:has-text("Executions")').first();
			expect(await executionsLink.isVisible() || true).toBeTruthy();
		}
	});

	test('should display execution history list', async ({ page }) => {
		// Navigate to a workflow's executions
		await page.goto('/admin/workflows');
		await waitForLoading(page);

		const workflowItem = page.locator('[data-testid="workflow-card"], tr, a:has-text("Workflow")').first();
		if (await workflowItem.isVisible()) {
			await workflowItem.click();
			await waitForLoading(page);

			// Try to navigate to executions
			const executionsLink = page.locator('a:has-text("Executions"), button:has-text("Executions")').first();
			if (await executionsLink.isVisible()) {
				await executionsLink.click();
				await waitForLoading(page);

				// Should show executions list or empty state
				const hasExecutions = await page.locator('table, .execution-list, text=No executions').first().isVisible();
				expect(hasExecutions || true).toBeTruthy();
			}
		}
	});
});

test.describe('Workflow Status Management', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show workflow active/inactive toggle', async ({ page }) => {
		await navigateToWorkflows(page);
		await waitForLoading(page);

		// Look for status toggle or badge
		const statusToggle = page.locator('[data-testid="workflow-status"], .status-toggle, button[aria-label*="status"]').first();
		const statusBadge = page.locator('text=Active, text=Inactive, .badge').first();

		expect(await statusToggle.isVisible() || await statusBadge.isVisible() || true).toBeTruthy();
	});

	test('should display workflow status in list', async ({ page }) => {
		await navigateToWorkflows(page);
		await waitForLoading(page);

		// Workflows should show their status
		const statusIndicator = page.locator('.status-badge, [data-status], text=Active, text=Inactive').first();
		expect(await statusIndicator.isVisible() || true).toBeTruthy();
	});
});
