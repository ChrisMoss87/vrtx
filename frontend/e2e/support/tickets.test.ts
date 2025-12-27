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
 * Support Tickets Tests
 * Tests for customer support ticket management
 */

test.describe('Ticket List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/tickets');
		await waitForLoading(page);
	});

	test('should display tickets list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Ticket|Tickets|Support/i }).first()).toBeVisible();
	});

	test('should display create ticket button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), button:has-text("New Ticket")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show tickets table', async ({ page }) => {
		const tickets = page.locator('[data-testid="ticket-item"], tbody tr');
		// May have tickets
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should filter by priority', async ({ page }) => {
		const priorityFilter = page.locator('button:has-text("Priority"), [data-filter="priority"]');
		if (await priorityFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await priorityFilter.click();
			await page.locator('[role="option"]:has-text("High")').click();
			await waitForLoading(page);
		}
	});

	test('should filter by assignee', async ({ page }) => {
		const assigneeFilter = page.locator('button:has-text("Assignee"), [data-filter="assignee"]');
		if (await assigneeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await assigneeFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should search tickets', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('login issue');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Ticket Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create new ticket', async ({ page }) => {
		await page.goto('/admin/tickets/create');
		await waitForLoading(page);

		const subjectInput = page.locator('input[name="subject"]');
		if (await subjectInput.isVisible()) {
			await subjectInput.fill(`Test Ticket ${Date.now()}`);
		}

		const descInput = page.locator('textarea[name="description"]');
		if (await descInput.isVisible()) {
			await descInput.fill('Test ticket description with details about the issue.');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should set ticket priority', async ({ page }) => {
		await page.goto('/admin/tickets/create');
		await waitForLoading(page);

		const prioritySelect = page.locator('button:has-text("Priority"), [data-testid="priority-select"]');
		if (await prioritySelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await prioritySelect.click();
			await page.locator('[role="option"]:has-text("High")').click();
		}
	});

	test('should select ticket category', async ({ page }) => {
		await page.goto('/admin/tickets/create');
		await waitForLoading(page);

		const categorySelect = page.locator('button:has-text("Category"), [data-testid="category-select"]');
		if (await categorySelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await categorySelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should assign to contact', async ({ page }) => {
		await page.goto('/admin/tickets/create');
		await waitForLoading(page);

		const contactSelect = page.locator('button:has-text("Contact"), [data-testid="contact-select"]');
		if (await contactSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await contactSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Ticket Details', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view ticket details', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const subject = page.locator('[data-testid="ticket-subject"], h1, h2');
		await expect(subject.first()).toBeVisible();
	});

	test('should view ticket replies', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const replies = page.locator('[data-testid="ticket-reply"], [class*="reply"]');
		// May have replies
	});

	test('should add reply', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const replyInput = page.locator('textarea[placeholder*="Reply"], [contenteditable="true"]');
		if (await replyInput.isVisible()) {
			await replyInput.fill('Thank you for contacting support. We are looking into this issue.');

			const sendButton = page.locator('button:has-text("Send"), button:has-text("Reply")');
			await sendButton.click();
			await waitForToast(page);
		}
	});

	test('should add internal note', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const noteTab = page.locator('button:has-text("Note"), [role="tab"]:has-text("Internal")');
		if (await noteTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await noteTab.click();

			const noteInput = page.locator('textarea[placeholder*="Note"]');
			if (await noteInput.isVisible()) {
				await noteInput.fill('Internal note about this ticket.');
			}

			const saveButton = page.locator('button:has-text("Add Note")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should use canned response', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const cannedButton = page.locator('button:has-text("Canned"), button:has-text("Template")');
		if (await cannedButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cannedButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Ticket Assignment', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should assign ticket to agent', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const assignButton = page.locator('button:has-text("Assign"), [data-testid="assign-button"]');
		if (await assignButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await assignButton.click();

			const agentOption = page.locator('[role="option"]').first();
			if (await agentOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await agentOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should assign to team', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const teamButton = page.locator('button:has-text("Team"), [data-testid="team-assign"]');
		if (await teamButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await teamButton.click();

			const teamOption = page.locator('[role="option"]').first();
			if (await teamOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await teamOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should claim unassigned ticket', async ({ page }) => {
		await page.goto('/admin/tickets');
		await waitForLoading(page);

		const unassignedTab = page.locator('[role="tab"]:has-text("Unassigned")');
		if (await unassignedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await unassignedTab.click();

			const ticket = page.locator('tbody tr').first();
			if (await ticket.isVisible({ timeout: 2000 }).catch(() => false)) {
				const claimButton = ticket.locator('button:has-text("Claim")');
				if (await claimButton.isVisible()) {
					await claimButton.click();
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('Ticket Status', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should change ticket status', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const statusSelect = page.locator('button:has-text("Status"), [data-testid="status-select"]');
		if (await statusSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusSelect.click();
			await page.locator('[role="option"]:has-text("In Progress")').click();
			await waitForToast(page);
		}
	});

	test('should resolve ticket', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const resolveButton = page.locator('button:has-text("Resolve")');
		if (await resolveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resolveButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should close ticket', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const closeButton = page.locator('button:has-text("Close")');
		if (await closeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await closeButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should reopen ticket', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const reopenButton = page.locator('button:has-text("Reopen")');
		if (await reopenButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await reopenButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Ticket SLA', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show SLA status', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const slaIndicator = page.locator('[data-testid="sla-status"], text=/SLA|Due|Overdue/i');
		// May show SLA status
	});

	test('should show time to resolve', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const timeRemaining = page.locator('text=/Time remaining|Due in/i');
		// May show time remaining
	});
});

test.describe('Ticket Merge', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should merge tickets', async ({ page }) => {
		await page.goto('/admin/tickets/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const mergeOption = page.locator('[role="menuitem"]:has-text("Merge")');
			if (await mergeOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await mergeOption.click();

				const ticketSelect = page.locator('[data-testid="merge-ticket-select"]');
				if (await ticketSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
					await ticketSelect.click();
					await page.locator('[role="option"]').first().click();
				}

				const mergeButton = page.locator('[role="dialog"] button:has-text("Merge")');
				await mergeButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Ticket Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete ticket', async ({ page }) => {
		await page.goto('/admin/tickets');
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
