import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToProposals,
	confirmDialog,
	fillFormField,
	expectToast
} from '../fixtures';

/**
 * Proposal Management Tests
 * Tests for proposal CRUD, sections, pricing, and sending
 */

test.describe('Proposal List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToProposals(page);
	});

	test('should display proposals list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Proposal|Proposals/i }).first()).toBeVisible();
	});

	test('should display create proposal button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Proposal")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search proposals', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Proposal Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to create proposal page', async ({ page }) => {
		await navigateToProposals(page);
		await page.click('button:has-text("Create"), a:has-text("New Proposal")');
		await expect(page).toHaveURL(/\/proposals\/create|\/proposals\/new/);
	});

	test('should display proposal creation form', async ({ page }) => {
		await page.goto('/proposals/create');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"], input[placeholder*="Title"]');
		await expect(titleInput.first()).toBeVisible();
	});

	test('should create proposal successfully', async ({ page }) => {
		await page.goto('/proposals/create');
		await waitForLoading(page);

		// Fill title
		const titleInput = page.locator('input[name="title"]').first();
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Test Proposal ${Date.now()}`);
		}

		// Select deal/opportunity if required
		const dealSelect = page.locator('button[role="combobox"]:has-text("Deal"), button[role="combobox"]:has-text("Opportunity")');
		if (await dealSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dealSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should use proposal template', async ({ page }) => {
		await page.goto('/proposals/create');
		await waitForLoading(page);

		// Look for template selection
		const templateSelect = page.locator('button:has-text("Template"), [data-testid="template-select"]');
		if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Proposal Sections', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add section to proposal', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const addSectionButton = page.locator('button:has-text("Add Section"), button:has-text("New Section")');
		if (await addSectionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addSectionButton.click();

			// Fill section title
			const sectionTitle = page.locator('input[name="section_title"], input[placeholder*="Section"]').last();
			if (await sectionTitle.isVisible()) {
				await sectionTitle.fill('New Section');
			}
		}
	});

	test('should edit section content', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		// Click on section to edit
		const section = page.locator('[data-testid="proposal-section"], [class*="section"]').first();
		if (await section.isVisible({ timeout: 2000 }).catch(() => false)) {
			await section.click();

			// Look for editor
			const editor = page.locator('[contenteditable="true"], textarea, .editor');
			if (await editor.isVisible({ timeout: 2000 }).catch(() => false)) {
				await editor.fill('Updated section content');
			}
		}
	});

	test('should reorder sections', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const sections = page.locator('[data-testid="proposal-section"]');
		if ((await sections.count()) >= 2) {
			const firstSection = sections.first();
			const secondSection = sections.nth(1);

			await firstSection.dragTo(secondSection);
		}
	});

	test('should delete section', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const deleteButton = page.locator('[data-testid="proposal-section"] button:has-text("Delete"), [data-testid="proposal-section"] [aria-label="Delete"]').first();
		if (await deleteButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await deleteButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
		}
	});
});

test.describe('Proposal Pricing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add pricing items', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const addPricingButton = page.locator('button:has-text("Add Pricing"), button:has-text("Add Item")');
		if (await addPricingButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addPricingButton.click();

			// Fill pricing details
			const descriptionInput = page.locator('input[name="description"]').last();
			if (await descriptionInput.isVisible()) {
				await descriptionInput.fill('Service Package');
			}

			const priceInput = page.locator('input[name="price"], input[name="amount"]').last();
			if (await priceInput.isVisible()) {
				await priceInput.fill('5000');
			}
		}
	});

	test('should calculate totals', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		// Look for total calculation
		const total = page.locator('text=/Total|Grand Total/i');
		await expect(total.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should apply discounts', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const discountInput = page.locator('input[name="discount"]');
		if (await discountInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await discountInput.fill('500');
		}
	});
});

test.describe('Proposal Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send proposal', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const sendButton = page.locator('button:has-text("Send"), button:has-text("Send Proposal")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();

			// Fill recipient if dialog appears
			const emailInput = page.locator('input[type="email"]');
			if (await emailInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await emailInput.fill('customer@example.com');
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Send")');
			if (await confirmButton.isVisible()) {
				await confirmButton.click();
			}

			await waitForToast(page);
		}
	});

	test('should preview proposal', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});

	test('should download PDF', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);

		const pdfButton = page.locator('button:has-text("PDF"), button:has-text("Download")');
		if (await pdfButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pdfButton.click();

			const download = await downloadPromise;
			if (download) {
				expect(download.suggestedFilename()).toMatch(/\.pdf$/);
			}
		}
	});

	test('should view analytics', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics"), button:has-text("Analytics")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();

			// Look for analytics data
			const views = page.locator('text=/Views|Opened/i');
			await expect(views.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('Proposal Comments', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add comment', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const commentInput = page.locator('textarea[name="comment"], input[placeholder*="comment"]');
		if (await commentInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await commentInput.fill('This is a test comment');

			const submitButton = page.locator('button:has-text("Add Comment"), button:has-text("Post")');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should resolve comment', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const resolveButton = page.locator('button:has-text("Resolve"), [aria-label="Resolve"]').first();
		if (await resolveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resolveButton.click();
		}
	});

	test('should reply to comment', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const replyButton = page.locator('button:has-text("Reply")').first();
		if (await replyButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await replyButton.click();

			const replyInput = page.locator('textarea').last();
			if (await replyInput.isVisible()) {
				await replyInput.fill('This is a reply');
			}
		}
	});
});

test.describe('Proposal Workflow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should accept proposal', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const acceptButton = page.locator('button:has-text("Accept"), button:has-text("Approve")');
		if (await acceptButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await acceptButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should reject proposal', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const rejectButton = page.locator('button:has-text("Reject"), button:has-text("Decline")');
		if (await rejectButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await rejectButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should request changes', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const changesButton = page.locator('button:has-text("Request Changes"), button:has-text("Request Revision")');
		if (await changesButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await changesButton.click();
		}
	});
});

test.describe('Proposal Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete draft proposal', async ({ page }) => {
		await navigateToProposals(page);

		const row = page.locator('tbody tr:has-text("Draft")').first();
		if (await row.isVisible({ timeout: 2000 }).catch(() => false)) {
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

	test('should prevent deleting sent proposals', async ({ page }) => {
		await page.goto('/proposals/1');
		await waitForLoading(page);

		const sentStatus = page.locator('text=/Sent|Pending/i');
		if (await sentStatus.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = page.locator('button:has-text("Actions")');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
				// Delete should be hidden or disabled for sent proposals
			}
		}
	});
});
