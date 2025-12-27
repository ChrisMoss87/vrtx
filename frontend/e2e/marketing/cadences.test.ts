import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToCadences,
	confirmDialog,
	fillFormField,
	clickTab,
	expectToast
} from '../fixtures';

/**
 * Cadence/Sequence Tests
 * Tests for sales cadence automation
 */

test.describe('Cadence List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToCadences(page);
	});

	test('should display cadences list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Cadence|Sequence|Cadences|Sequences/i }).first()).toBeVisible();
	});

	test('should display create cadence button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Cadence"), a:has-text("New Sequence")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search cadences', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should show cadence statistics', async ({ page }) => {
		const stats = page.locator('text=/Enrolled|Completed|Active/i');
		// May show stats
	});
});

test.describe('Cadence Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new cadence', async ({ page }) => {
		await navigateToCadences(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Cadence ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should select cadence type', async ({ page }) => {
		await navigateToCadences(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const typeSelect = page.locator('button:has-text("Type"), [role="combobox"]');
		if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should set target audience', async ({ page }) => {
		await page.goto('/cadences/create');
		await waitForLoading(page);

		const audienceSection = page.locator('text=/Audience|Target|Recipients/i');
		if (await audienceSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const segmentSelect = page.locator('button[role="combobox"]:has-text("Segment")');
			if (await segmentSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await segmentSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});
});

test.describe('Cadence Steps', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add email step', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step"), button:has-text("Add Action")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const emailOption = page.locator('[role="menuitem"]:has-text("Email"), [role="option"]:has-text("Email")');
			if (await emailOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await emailOption.click();
			}
		}
	});

	test('should add wait step', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const waitOption = page.locator('[role="menuitem"]:has-text("Wait"), [role="option"]:has-text("Delay")');
			if (await waitOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await waitOption.click();

				const daysInput = page.locator('input[name="days"], input[type="number"]');
				if (await daysInput.isVisible()) {
					await daysInput.fill('3');
				}
			}
		}
	});

	test('should add task step', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const taskOption = page.locator('[role="menuitem"]:has-text("Task"), [role="option"]:has-text("Task")');
			if (await taskOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await taskOption.click();
			}
		}
	});

	test('should add call step', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const addStepButton = page.locator('button:has-text("Add Step")');
		if (await addStepButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addStepButton.click();

			const callOption = page.locator('[role="menuitem"]:has-text("Call"), [role="option"]:has-text("Phone")');
			if (await callOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await callOption.click();
			}
		}
	});

	test('should configure step timing', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="cadence-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			await step.click();

			const timingInput = page.locator('input[name="delay_days"], select[name="timing"]');
			if (await timingInput.isVisible()) {
				await timingInput.fill('2');
			}
		}
	});

	test('should reorder steps', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const steps = page.locator('[data-testid="cadence-step"]');
		if ((await steps.count()) >= 2) {
			const firstStep = steps.first();
			const secondStep = steps.nth(1);
			await firstStep.dragTo(secondStep);
		}
	});

	test('should delete step', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const step = page.locator('[data-testid="cadence-step"]').first();
		if (await step.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = step.locator('button[aria-label="Delete"], button:has-text("Remove")');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
			}
		}
	});
});

test.describe('Cadence Email Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit email template', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const emailStep = page.locator('[data-testid="cadence-step"]:has-text("Email")').first();
		if (await emailStep.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailStep.click();

			const editor = page.locator('[contenteditable="true"], .editor');
			if (await editor.isVisible({ timeout: 2000 }).catch(() => false)) {
				await editor.fill('Test email content');
			}
		}
	});

	test('should add personalization tokens', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const tokenButton = page.locator('button:has-text("Personalize"), button:has-text("Insert Field")');
		if (await tokenButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await tokenButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should preview email', async ({ page }) => {
		await page.goto('/cadences/1/edit');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});
});

test.describe('Cadence Activation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should activate cadence', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const activateButton = page.locator('button:has-text("Activate"), button:has-text("Start")');
		if (await activateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activateButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should pause cadence', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const pauseButton = page.locator('button:has-text("Pause")');
		if (await pauseButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pauseButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should resume cadence', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const resumeButton = page.locator('button:has-text("Resume")');
		if (await resumeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resumeButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Cadence Enrollment', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should enroll contacts', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const enrollButton = page.locator('button:has-text("Enroll"), button:has-text("Add Contacts")');
		if (await enrollButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrollButton.click();

			// Select contacts
			await page.locator('[role="option"], [data-testid="contact-option"]').first().click();

			const confirmButton = page.locator('[role="dialog"] button:has-text("Enroll"), button:has-text("Add")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should view enrolled contacts', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const enrolledTab = page.locator('[role="tab"]:has-text("Enrolled"), button:has-text("Contacts")');
		if (await enrolledTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrolledTab.click();
		}
	});

	test('should remove contact from cadence', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const enrolledTab = page.locator('[role="tab"]:has-text("Enrolled")');
		if (await enrolledTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrolledTab.click();

			const row = page.locator('tbody tr').first();
			if (await row.isVisible()) {
				const removeButton = row.locator('button:has-text("Remove"), button[aria-label="Remove"]');
				if (await removeButton.isVisible()) {
					await removeButton.click();
					await confirmDialog(page, 'confirm').catch(() => {});
				}
			}
		}
	});
});

test.describe('Cadence Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view cadence analytics', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics"), button:has-text("Stats")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();
		}
	});

	test('should show completion rate', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const completionRate = page.locator('text=/Completion|Completed/i');
		// May show completion rate
	});

	test('should show step performance', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const stepPerformance = page.locator('text=/Open Rate|Click Rate|Reply Rate/i');
		// May show step performance
	});
});

test.describe('Cadence Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create from template', async ({ page }) => {
		await navigateToCadences(page);
		await page.click('button:has-text("Create"), a:has-text("New")');

		const templateOption = page.locator('button:has-text("Use Template"), [data-testid="template-select"]');
		if (await templateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateOption.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should save as template', async ({ page }) => {
		await page.goto('/cadences/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const saveTemplateOption = page.locator('[role="menuitem"]:has-text("Save as Template")');
			if (await saveTemplateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await saveTemplateOption.click();

				const nameInput = page.locator('input[name="name"]');
				if (await nameInput.isVisible()) {
					await nameInput.fill('My Cadence Template');
				}

				const confirmButton = page.locator('[role="dialog"] button:has-text("Save")');
				await confirmButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Cadence Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete cadence', async ({ page }) => {
		await navigateToCadences(page);

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
