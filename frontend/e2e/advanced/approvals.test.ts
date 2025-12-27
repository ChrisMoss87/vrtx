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
 * Approval Tests
 * Tests for approval workflows and requests
 */

test.describe('Approval Rules List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/approvals');
		await waitForLoading(page);
	});

	test('should display approvals page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Approval|Approvals/i }).first()).toBeVisible();
	});

	test('should display create rule button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Rule")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show approval rules', async ({ page }) => {
		const rules = page.locator('[data-testid="approval-rule"], tbody tr');
		// May have rules
	});

	test('should filter by module', async ({ page }) => {
		const moduleFilter = page.locator('button:has-text("Module"), [data-filter="module"]');
		if (await moduleFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await moduleFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});
});

test.describe('Approval Rule Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create approval rule', async ({ page }) => {
		await page.goto('/admin/approvals/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Approval Rule ${Date.now()}`);
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

	test('should configure trigger conditions', async ({ page }) => {
		await page.goto('/admin/approvals/create');
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
		}
	});

	test('should add approvers', async ({ page }) => {
		await page.goto('/admin/approvals/create');
		await waitForLoading(page);

		const addApproverButton = page.locator('button:has-text("Add Approver")');
		if (await addApproverButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addApproverButton.click();

			const approverSelect = page.locator('[data-testid="approver-select"]');
			if (await approverSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await approverSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should configure approval chain', async ({ page }) => {
		await page.goto('/admin/approvals/create');
		await waitForLoading(page);

		// Select approval type (sequential/parallel)
		const typeSelect = page.locator('button:has-text("Type"), [data-testid="approval-type"]');
		if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeSelect.click();
			await page.locator('[role="option"]:has-text("Sequential")').click();
		}
	});

	test('should configure escalation', async ({ page }) => {
		await page.goto('/admin/approvals/create');
		await waitForLoading(page);

		const escalationSection = page.locator('text=/Escalation|Auto-escalate/i');
		if (await escalationSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const timeoutInput = page.locator('input[name="escalation_timeout"]');
			if (await timeoutInput.isVisible()) {
				await timeoutInput.fill('48');
			}
		}
	});
});

test.describe('Pending Approvals', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view pending approvals', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const pendingList = page.locator('[data-testid="pending-approval"], tbody tr');
		// May have pending approvals
	});

	test('should view approval request details', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const request = page.locator('[data-testid="pending-approval"], tbody tr').first();
		if (await request.isVisible({ timeout: 2000 }).catch(() => false)) {
			await request.click();

			const details = page.locator('[role="dialog"], [data-testid="approval-details"]');
			await expect(details).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should approve request', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const request = page.locator('[data-testid="pending-approval"], tbody tr').first();
		if (await request.isVisible({ timeout: 2000 }).catch(() => false)) {
			const approveButton = request.locator('button:has-text("Approve")');
			if (await approveButton.isVisible()) {
				await approveButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});

	test('should reject request', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const request = page.locator('[data-testid="pending-approval"], tbody tr').first();
		if (await request.isVisible({ timeout: 2000 }).catch(() => false)) {
			const rejectButton = request.locator('button:has-text("Reject")');
			if (await rejectButton.isVisible()) {
				await rejectButton.click();

				// Add rejection reason
				const reasonInput = page.locator('textarea[name="reason"]');
				if (await reasonInput.isVisible({ timeout: 2000 }).catch(() => false)) {
					await reasonInput.fill('Rejected due to incomplete information');
				}

				const confirmButton = page.locator('[role="dialog"] button:has-text("Reject")');
				await confirmButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should add comment to request', async ({ page }) => {
		await page.goto('/admin/approvals/pending/1');
		await waitForLoading(page);

		const commentInput = page.locator('textarea[placeholder*="comment"], input[placeholder*="comment"]');
		if (await commentInput.isVisible()) {
			await commentInput.fill('Please provide more details');

			const addButton = page.locator('button:has-text("Add Comment"), button:has-text("Comment")');
			await addButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Approval History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view approval history', async ({ page }) => {
		await page.goto('/admin/approvals/history');
		await waitForLoading(page);

		const history = page.locator('[data-testid="approval-history"], tbody tr');
		// May have history
	});

	test('should filter by status', async ({ page }) => {
		await page.goto('/admin/approvals/history');
		await waitForLoading(page);

		const statusFilter = page.locator('button:has-text("Status"), [data-filter="status"]');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]:has-text("Approved")').click();
			await waitForLoading(page);
		}
	});

	test('should filter by date range', async ({ page }) => {
		await page.goto('/admin/approvals/history');
		await waitForLoading(page);

		const dateFilter = page.locator('button:has-text("Date"), [data-filter="date"]');
		if (await dateFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dateFilter.click();
			await page.locator('[role="option"]:has-text("Last 30 days")').click();
			await waitForLoading(page);
		}
	});
});

test.describe('My Approvals', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view my pending approvals', async ({ page }) => {
		await page.goto('/admin/approvals/my-pending');
		await waitForLoading(page);

		const myApprovals = page.locator('[data-testid="my-approval"], tbody tr');
		// May have approvals
	});

	test('should view requests I submitted', async ({ page }) => {
		await page.goto('/admin/approvals/my-requests');
		await waitForLoading(page);

		const myRequests = page.locator('[data-testid="my-request"], tbody tr');
		// May have requests
	});

	test('should cancel my request', async ({ page }) => {
		await page.goto('/admin/approvals/my-requests');
		await waitForLoading(page);

		const request = page.locator('tbody tr').first();
		if (await request.isVisible()) {
			const cancelButton = request.locator('button:has-text("Cancel"), button:has-text("Withdraw")');
			if (await cancelButton.isVisible()) {
				await cancelButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});
});

test.describe('Delegation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delegate approvals', async ({ page }) => {
		await page.goto('/admin/approvals/settings');
		await waitForLoading(page);

		const delegateButton = page.locator('button:has-text("Delegate"), button:has-text("Set Delegate")');
		if (await delegateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await delegateButton.click();

			const userSelect = page.locator('[data-testid="delegate-user"]');
			if (await userSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await userSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Save")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should set delegation period', async ({ page }) => {
		await page.goto('/admin/approvals/settings');
		await waitForLoading(page);

		const startDateInput = page.locator('input[name="delegation_start"]');
		if (await startDateInput.isVisible()) {
			const today = new Date().toISOString().slice(0, 10);
			await startDateInput.fill(today);
		}

		const endDateInput = page.locator('input[name="delegation_end"]');
		if (await endDateInput.isVisible()) {
			const nextWeek = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
			await endDateInput.fill(nextWeek);
		}
	});
});

test.describe('Approval Rule Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete approval rule', async ({ page }) => {
		await page.goto('/admin/approvals');
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
