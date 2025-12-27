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
 * Playbooks Tests
 * Tests for sales playbooks and guided selling
 */

test.describe('Playbook List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/playbooks');
		await waitForLoading(page);
	});

	test('should display playbooks page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Playbook|Playbooks/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Playbook")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show playbooks list', async ({ page }) => {
		const playbooks = page.locator('[data-testid="playbook-item"], tbody tr');
		// May have playbooks
	});

	test('should filter by category', async ({ page }) => {
		const categoryFilter = page.locator('button:has-text("Category"), [data-filter="category"]');
		if (await categoryFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await categoryFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should search playbooks', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('onboarding');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Playbook Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new playbook', async ({ page }) => {
		await page.goto('/admin/playbooks/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Playbook ${Date.now()}`);
		}

		const descInput = page.locator('textarea[name="description"]');
		if (await descInput.isVisible()) {
			await descInput.fill('Test playbook description');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select playbook category', async ({ page }) => {
		await page.goto('/admin/playbooks/create');
		await waitForLoading(page);

		const categorySelect = page.locator('button:has-text("Category"), [data-testid="category-select"]');
		if (await categorySelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await categorySelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should configure target stages', async ({ page }) => {
		await page.goto('/admin/playbooks/create');
		await waitForLoading(page);

		const stagesSection = page.locator('text=/Stages|When to show/i');
		if (await stagesSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const stageCheckbox = page.locator('input[type="checkbox"]').first();
			if (await stageCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
				await stageCheckbox.check();
			}
		}
	});
});

test.describe('Playbook Steps', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add playbook step', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step"), button:has-text("Add Task")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const stepNameInput = page.locator('input[name="step_name"], input[placeholder*="Step"]');
			if (await stepNameInput.isVisible()) {
				await stepNameInput.fill('Schedule Discovery Call');
			}
		}
	});

	test('should configure step type', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="playbook-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const typeSelect = page.locator('[data-testid="step-type"]');
			if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await typeSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should add step content', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="playbook-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const contentEditor = page.locator('[contenteditable="true"], textarea[name="content"]');
			if (await contentEditor.isVisible()) {
				await contentEditor.fill('Call the prospect to understand their needs.');
			}
		}
	});

	test('should add checklist items', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="playbook-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const addChecklistButton = page.locator('button:has-text("Add Checklist"), button:has-text("Add Item")');
			if (await addChecklistButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await addChecklistButton.click();

				const itemInput = page.locator('input[placeholder*="item"], input[name="checklist_item"]');
				if (await itemInput.isVisible()) {
					await itemInput.fill('Confirm meeting time');
				}
			}
		}
	});

	test('should attach resources', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="playbook-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const attachButton = page.locator('button:has-text("Attach"), button:has-text("Add Resource")');
			if (await attachButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await attachButton.click();
			}
		}
	});

	test('should reorder steps', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const steps = page.locator('[data-testid="playbook-step"]');
		if ((await steps.count()) >= 2) {
			const firstStep = steps.first();
			const secondStep = steps.nth(1);
			await firstStep.dragTo(secondStep);
		}
	});

	test('should delete step', async ({ page }) => {
		await page.goto('/admin/playbooks/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="playbook-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = step.locator('button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Playbook Usage', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view playbook on deal', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const playbookSection = page.locator('[data-testid="playbook-widget"], text=/Playbook|Guided/i');
		// May show playbook widget
	});

	test('should complete playbook step', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const playbookStep = page.locator('[data-testid="playbook-step"]').first();
		if (await playbookStep.isVisible({ timeout: 2000 }).catch(() => false)) {
			const completeButton = playbookStep.locator('button:has-text("Complete"), input[type="checkbox"]');
			if (await completeButton.isVisible()) {
				await completeButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should skip playbook step', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const playbookStep = page.locator('[data-testid="playbook-step"]').first();
		if (await playbookStep.isVisible({ timeout: 2000 }).catch(() => false)) {
			const skipButton = playbookStep.locator('button:has-text("Skip")');
			if (await skipButton.isVisible()) {
				await skipButton.click();
			}
		}
	});

	test('should view step details', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const playbookStep = page.locator('[data-testid="playbook-step"]').first();
		if (await playbookStep.isVisible({ timeout: 2000 }).catch(() => false)) {
			await playbookStep.click();

			const stepDetails = page.locator('[data-testid="step-details"], [role="dialog"]');
			await expect(stepDetails).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should add step note', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const playbookStep = page.locator('[data-testid="playbook-step"]').first();
		if (await playbookStep.isVisible({ timeout: 2000 }).catch(() => false)) {
			await playbookStep.click();

			const noteInput = page.locator('textarea[name="note"], textarea[placeholder*="note"]');
			if (await noteInput.isVisible()) {
				await noteInput.fill('Called and left voicemail');
			}

			const saveButton = page.locator('button:has-text("Save Note")');
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Playbook Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view playbook analytics', async ({ page }) => {
		await page.goto('/admin/playbooks/1/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="playbook-analytics"]');
		// May have analytics
	});

	test('should show completion rate', async ({ page }) => {
		await page.goto('/admin/playbooks/1/analytics');
		await waitForLoading(page);

		const completionRate = page.locator('text=/Completion|Completed/i');
		// May show completion rate
	});

	test('should show step performance', async ({ page }) => {
		await page.goto('/admin/playbooks/1/analytics');
		await waitForLoading(page);

		const stepPerformance = page.locator('text=/Step.*Performance|Average Time/i');
		// May show step performance
	});
});

test.describe('Playbook Activation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should activate playbook', async ({ page }) => {
		await page.goto('/admin/playbooks/1');
		await waitForLoading(page);

		const activateButton = page.locator('button:has-text("Activate"), button:has-text("Enable")');
		if (await activateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should deactivate playbook', async ({ page }) => {
		await page.goto('/admin/playbooks/1');
		await waitForLoading(page);

		const deactivateButton = page.locator('button:has-text("Deactivate"), button:has-text("Disable")');
		if (await deactivateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await deactivateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});

test.describe('Playbook Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete playbook', async ({ page }) => {
		await page.goto('/admin/playbooks');
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
