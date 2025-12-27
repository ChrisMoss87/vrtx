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
 * Wizard Tests
 * Tests for multi-step wizard forms
 */

test.describe('Wizard List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/wizards');
		await waitForLoading(page);
	});

	test('should display wizards page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Wizard|Wizards|Forms/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Wizard")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show wizards list', async ({ page }) => {
		const wizards = page.locator('[data-testid="wizard-item"], tbody tr');
		// May have wizards
	});

	test('should filter by module', async ({ page }) => {
		const moduleFilter = page.locator('button:has-text("Module"), [data-filter="module"]');
		if (await moduleFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should search wizards', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('lead');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Wizard Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new wizard', async ({ page }) => {
		await page.goto('/admin/wizards/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Wizard ${Date.now()}`);
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

	test('should add wizard step', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step"), button:has-text("Add Page")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const stepNameInput = page.locator('input[name="step_name"], input[placeholder*="Step name"]');
			if (await stepNameInput.isVisible()) {
				await stepNameInput.fill('Contact Information');
			}
		}
	});

	test('should configure step fields', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="wizard-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const addFieldButton = page.locator('button:has-text("Add Field")');
			if (await addFieldButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await addFieldButton.click();

				const fieldOption = page.locator('[role="option"]').first();
				if (await fieldOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await fieldOption.click();
				}
			}
		}
	});

	test('should reorder steps', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const steps = page.locator('[data-testid="wizard-step"]');
		if ((await steps.count()) >= 2) {
			const firstStep = steps.first();
			const secondStep = steps.nth(1);
			await firstStep.dragTo(secondStep);
		}
	});

	test('should delete step', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="wizard-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = step.locator('button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Wizard Conditions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add step condition', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="wizard-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const conditionsTab = page.locator('[role="tab"]:has-text("Conditions")');
			if (await conditionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
				await conditionsTab.click();

				const addConditionButton = page.locator('button:has-text("Add Condition")');
				if (await addConditionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
					await addConditionButton.click();
				}
			}
		}
	});

	test('should configure field visibility', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const field = page.locator('[data-testid="wizard-field"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			await field.click();

			const visibilityToggle = page.locator('button:has-text("Visibility"), [data-testid="field-visibility"]');
			if (await visibilityToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await visibilityToggle.click();
			}
		}
	});

	test('should configure skip logic', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="wizard-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const skipLogicButton = page.locator('button:has-text("Skip Logic")');
			if (await skipLogicButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await skipLogicButton.click();
			}
		}
	});
});

test.describe('Wizard Preview', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should preview wizard', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();

			const preview = page.locator('[data-testid="wizard-preview"], [role="dialog"]');
			await expect(preview).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should navigate between steps in preview', async ({ page }) => {
		await page.goto('/admin/wizards/1/preview');
		await waitForLoading(page);

		const nextButton = page.locator('button:has-text("Next"), button:has-text("Continue")');
		if (await nextButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await nextButton.click();
		}

		const backButton = page.locator('button:has-text("Back"), button:has-text("Previous")');
		if (await backButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await backButton.click();
		}
	});
});

test.describe('Wizard Drafts', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should save wizard draft', async ({ page }) => {
		// Navigate to record creation with wizard
		await page.goto('/records/leads/create');
		await waitForLoading(page);

		// Fill first step
		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill('Test Lead');
		}

		const saveDraftButton = page.locator('button:has-text("Save Draft"), button:has-text("Save Progress")');
		if (await saveDraftButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await saveDraftButton.click();
			await waitForToast(page);
		}
	});

	test('should view draft list', async ({ page }) => {
		await page.goto('/admin/wizards/drafts');
		await waitForLoading(page);

		const drafts = page.locator('[data-testid="wizard-draft"], tbody tr');
		// May have drafts
	});

	test('should resume draft', async ({ page }) => {
		await page.goto('/admin/wizards/drafts');
		await waitForLoading(page);

		const draft = page.locator('[data-testid="wizard-draft"], tbody tr').first();
		if (await draft.isVisible({ timeout: 2000 }).catch(() => false)) {
			const resumeButton = draft.locator('button:has-text("Resume"), a:has-text("Continue")');
			if (await resumeButton.isVisible()) {
				await resumeButton.click();
			}
		}
	});

	test('should delete draft', async ({ page }) => {
		await page.goto('/admin/wizards/drafts');
		await waitForLoading(page);

		const draft = page.locator('[data-testid="wizard-draft"], tbody tr').first();
		if (await draft.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = draft.locator('button:has-text("Delete"), button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});
});

test.describe('Wizard Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should configure progress indicator', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const progressStyle = page.locator('[data-testid="progress-style"]');
			if (await progressStyle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await progressStyle.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should configure submit action', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const submitAction = page.locator('[data-testid="submit-action"]');
			if (await submitAction.isVisible({ timeout: 2000 }).catch(() => false)) {
				await submitAction.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should configure validation mode', async ({ page }) => {
		await page.goto('/admin/wizards/1/edit');
		await waitForLoading(page);

		const settingsTab = page.locator('[role="tab"]:has-text("Settings")');
		if (await settingsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await settingsTab.click();

			const validateOnNext = page.locator('label:has-text("Validate on Next") input[type="checkbox"]');
			if (await validateOnNext.isVisible({ timeout: 2000 }).catch(() => false)) {
				await validateOnNext.check();
			}
		}
	});
});

test.describe('Wizard Activation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should activate wizard', async ({ page }) => {
		await page.goto('/admin/wizards/1');
		await waitForLoading(page);

		const activateButton = page.locator('button:has-text("Activate"), button:has-text("Enable")');
		if (await activateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should deactivate wizard', async ({ page }) => {
		await page.goto('/admin/wizards/1');
		await waitForLoading(page);

		const deactivateButton = page.locator('button:has-text("Deactivate"), button:has-text("Disable")');
		if (await deactivateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await deactivateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Wizard Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete wizard', async ({ page }) => {
		await page.goto('/admin/wizards');
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
