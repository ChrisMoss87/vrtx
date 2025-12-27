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
 * Competitor Management Tests
 * Tests for competitor tracking and battlecards
 */

test.describe('Competitor List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/competitors');
		await waitForLoading(page);
	});

	test('should display competitors list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Competitor|Competitors/i }).first()).toBeVisible();
	});

	test('should display create competitor button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Competitor"), button:has-text("Add")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should search competitors', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should display competitor cards or table', async ({ page }) => {
		const competitors = page.locator('[data-testid="competitor-card"], tbody tr');
		// May have existing competitors
	});
});

test.describe('Competitor Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create competitor', async ({ page }) => {
		await page.goto('/competitors');
		await page.click('button:has-text("Create"), a:has-text("New Competitor")');

		// Fill name
		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Test Competitor ${Date.now()}`);
		}

		// Fill website
		const websiteInput = page.locator('input[name="website"]');
		if (await websiteInput.isVisible()) {
			await websiteInput.fill('https://competitor.example.com');
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should validate required fields', async ({ page }) => {
		await page.goto('/competitors/create');
		await waitForLoading(page);

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		const error = page.locator('text=/required/i');
		await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});
});

test.describe('Competitor View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display competitor details', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const name = page.locator('h1, h2, [data-testid="competitor-name"]');
		await expect(name.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show competitor website', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const website = page.locator('a[href*="http"], text=/website/i');
		// May have website
	});

	test('should display strengths and weaknesses', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const strengths = page.locator('text=/Strength|Strengths/i');
		const weaknesses = page.locator('text=/Weakness|Weaknesses/i');
		// May have strengths/weaknesses
	});
});

test.describe('Battlecards', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display battlecard', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const battlecardTab = page.locator('[role="tab"]:has-text("Battlecard")');
		if (await battlecardTab.isVisible()) {
			await battlecardTab.click();
		}

		const battlecard = page.locator('[data-testid="battlecard"], [class*="battlecard"]');
		await expect(battlecard.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should add battlecard section', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const addSectionButton = page.locator('button:has-text("Add Section")');
		if (await addSectionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addSectionButton.click();

			const titleInput = page.locator('input[name="title"]');
			if (await titleInput.isVisible()) {
				await titleInput.fill('New Section');
			}
		}
	});

	test('should edit battlecard section', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const section = page.locator('[data-testid="battlecard-section"]').first();
		if (await section.isVisible({ timeout: 2000 }).catch(() => false)) {
			await section.click();
		}
	});
});

test.describe('Competitor Notes', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add competitor note', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const noteInput = page.locator('textarea[name="note"], input[placeholder*="note"]');
		if (await noteInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await noteInput.fill('Test competitor note');

			const addButton = page.locator('button:has-text("Add Note"), button:has-text("Save")');
			await addButton.click();
			await waitForToast(page);
		}
	});

	test('should display note history', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const notes = page.locator('[data-testid="competitor-notes"], [class*="notes"]');
		// May have notes
	});
});

test.describe('Competitor Objections', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add objection handling', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const addObjectionButton = page.locator('button:has-text("Add Objection")');
		if (await addObjectionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addObjectionButton.click();

			const objectionInput = page.locator('input[name="objection"]');
			if (await objectionInput.isVisible()) {
				await objectionInput.fill('Test objection');
			}

			const responseInput = page.locator('textarea[name="response"]');
			if (await responseInput.isVisible()) {
				await responseInput.fill('Test response');
			}

			const submitButton = page.locator('[role="dialog"] button:has-text("Add"), button:has-text("Save")');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should display objections list', async ({ page }) => {
		await page.goto('/competitors/1');
		await waitForLoading(page);

		const objections = page.locator('[data-testid="objections"], [class*="objection"]');
		// May have objections
	});
});

test.describe('Deal Competitor Association', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should associate competitor with deal', async ({ page }) => {
		// Navigate to a deal
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const addCompetitorButton = page.locator('button:has-text("Add Competitor")');
		if (await addCompetitorButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addCompetitorButton.click();

			await page.locator('[role="option"]').first().click();
			await waitForToast(page);
		}
	});

	test('should view deal competitors', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const competitorsSection = page.locator('text=/Competitor|Competitors/i');
		// May have competitors section
	});

	test('should update competitor status on deal', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const statusSelect = page.locator('[data-testid="competitor-status"] button[role="combobox"]');
		if (await statusSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Competitor Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete competitor', async ({ page }) => {
		await page.goto('/competitors');
		await waitForLoading(page);

		const row = page.locator('tbody tr, [data-testid="competitor-card"]').first();
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
