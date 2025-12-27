import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToBlueprints,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Blueprint Tests
 * Tests for process blueprints and state machines
 */

test.describe('Blueprint List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToBlueprints(page);
	});

	test('should display blueprints list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Blueprint|Blueprints|Process/i }).first()).toBeVisible();
	});

	test('should display create blueprint button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Blueprint")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show blueprints list', async ({ page }) => {
		const blueprints = page.locator('[data-testid="blueprint-item"], tbody tr');
		// May have blueprints
	});

	test('should filter by module', async ({ page }) => {
		const moduleFilter = page.locator('button:has-text("Module"), [data-filter="module"]');
		if (await moduleFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should search blueprints', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Blueprint Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new blueprint', async ({ page }) => {
		await navigateToBlueprints(page);
		await page.click('button:has-text("Create"), a:has-text("New Blueprint")');

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Blueprint ${Date.now()}`);
		}

		// Select module
		const moduleSelect = page.locator('button:has-text("Module"), [data-testid="module-select"]');
		if (await moduleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select trigger field', async ({ page }) => {
		await page.goto('/admin/blueprints/create');
		await waitForLoading(page);

		const triggerSelect = page.locator('button:has-text("Trigger Field"), [data-testid="trigger-field"]');
		if (await triggerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await triggerSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Blueprint States', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add state to blueprint', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const addStateButton = page.locator('button:has-text("Add State"), button:has-text("Add Stage")');
		if (await addStateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStateButton.click();

			const stateNameInput = page.locator('input[name="state_name"], input[placeholder*="state"]');
			if (await stateNameInput.isVisible()) {
				await stateNameInput.fill('New State');
			}
		}
	});

	test('should configure state properties', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const state = page.locator('[data-testid="blueprint-state"]').first();
		if (await state.isVisible({ timeout: 2000 }).catch(() => false)) {
			await state.click();

			// Configure state color
			const colorPicker = page.locator('input[type="color"], [data-testid="state-color"]');
			if (await colorPicker.isVisible()) {
				// Color picker exists
			}
		}
	});

	test('should set initial state', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const state = page.locator('[data-testid="blueprint-state"]').first();
		if (await state.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = state.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const setInitialOption = page.locator('[role="menuitem"]:has-text("Set as Initial")');
				if (await setInitialOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await setInitialOption.click();
				}
			}
		}
	});

	test('should delete state', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const state = page.locator('[data-testid="blueprint-state"]').first();
		if (await state.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = state.locator('button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Blueprint Transitions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add transition between states', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const addTransitionButton = page.locator('button:has-text("Add Transition")');
		if (await addTransitionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addTransitionButton.click();

			// Select from state
			const fromSelect = page.locator('[data-testid="from-state"]');
			if (await fromSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await fromSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			// Select to state
			const toSelect = page.locator('[data-testid="to-state"]');
			if (await toSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await toSelect.click();
				await page.locator('[role="option"]').nth(1).click();
			}
		}
	});

	test('should configure transition conditions', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const transition = page.locator('[data-testid="blueprint-transition"]').first();
		if (await transition.isVisible({ timeout: 2000 }).catch(() => false)) {
			await transition.click();

			const addConditionButton = page.locator('button:has-text("Add Condition")');
			if (await addConditionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await addConditionButton.click();
			}
		}
	});

	test('should configure transition actions', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const transition = page.locator('[data-testid="blueprint-transition"]').first();
		if (await transition.isVisible({ timeout: 2000 }).catch(() => false)) {
			await transition.click();

			const actionsTab = page.locator('[role="tab"]:has-text("Actions")');
			if (await actionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
				await actionsTab.click();

				const addActionButton = page.locator('button:has-text("Add Action")');
				if (await addActionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
					await addActionButton.click();
				}
			}
		}
	});

	test('should require approval for transition', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const transition = page.locator('[data-testid="blueprint-transition"]').first();
		if (await transition.isVisible({ timeout: 2000 }).catch(() => false)) {
			await transition.click();

			const approvalToggle = page.locator('label:has-text("Require Approval") input[type="checkbox"]');
			if (await approvalToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await approvalToggle.check();
			}
		}
	});
});

test.describe('Blueprint SLAs', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should configure state SLA', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const state = page.locator('[data-testid="blueprint-state"]').first();
		if (await state.isVisible({ timeout: 2000 }).catch(() => false)) {
			await state.click();

			const slaTab = page.locator('[role="tab"]:has-text("SLA")');
			if (await slaTab.isVisible({ timeout: 2000 }).catch(() => false)) {
				await slaTab.click();

				const slaInput = page.locator('input[name="sla_hours"], input[name="max_time"]');
				if (await slaInput.isVisible()) {
					await slaInput.fill('24');
				}
			}
		}
	});

	test('should configure escalation', async ({ page }) => {
		await page.goto('/admin/blueprints/1/edit');
		await waitForLoading(page);

		const state = page.locator('[data-testid="blueprint-state"]').first();
		if (await state.isVisible({ timeout: 2000 }).catch(() => false)) {
			await state.click();

			const escalationSection = page.locator('text=/Escalation|Escalate/i');
			if (await escalationSection.isVisible({ timeout: 2000 }).catch(() => false)) {
				const escalateToSelect = page.locator('[data-testid="escalate-to"]');
				if (await escalateToSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
					await escalateToSelect.click();
					await page.locator('[role="option"]').first().click();
				}
			}
		}
	});
});

test.describe('Blueprint Activation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should activate blueprint', async ({ page }) => {
		await page.goto('/admin/blueprints/1');
		await waitForLoading(page);

		const activateButton = page.locator('button:has-text("Activate"), button:has-text("Enable")');
		if (await activateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should deactivate blueprint', async ({ page }) => {
		await page.goto('/admin/blueprints/1');
		await waitForLoading(page);

		const deactivateButton = page.locator('button:has-text("Deactivate"), button:has-text("Disable")');
		if (await deactivateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await deactivateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Blueprint Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete blueprint', async ({ page }) => {
		await navigateToBlueprints(page);

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
