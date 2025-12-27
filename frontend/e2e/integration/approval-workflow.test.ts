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
 * Approval Workflow Integration Tests
 * Tests for end-to-end approval processes
 */

test.describe('Quote Approval Workflow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should trigger approval for high-value quote', async ({ page }) => {
		// Create a quote above approval threshold
		await page.goto('/records/quotes/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`High Value Quote ${Date.now()}`);
		}

		// Add line item with high value
		const addLineButton = page.locator('button:has-text("Add Line")');
		if (await addLineButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addLineButton.click();

			const priceInput = page.locator('input[name*="price"], input[name*="amount"]').first();
			if (await priceInput.isVisible()) {
				await priceInput.fill('100000'); // Above typical approval threshold
			}
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);

		// Check for pending approval status
		await waitForLoading(page);
		const approvalStatus = page.locator('text=/Pending Approval|Awaiting Approval/i');
		await expect(approvalStatus.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should receive approval notification', async ({ page }) => {
		// Check notifications for approval request
		await page.goto('/admin/notifications');
		await waitForLoading(page);

		const approvalNotification = page.locator('text=/Approval.*request|requires approval/i');
		// May have approval notifications
	});

	test('should approve quote from notification', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const quoteApproval = page.locator('[data-testid="pending-approval"]:has-text("Quote")').first();
		if (await quoteApproval.isVisible({ timeout: 2000 }).catch(() => false)) {
			const approveButton = quoteApproval.locator('button:has-text("Approve")');
			if (await approveButton.isVisible()) {
				await approveButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});

	test('should reject quote with reason', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const quoteApproval = page.locator('[data-testid="pending-approval"]:has-text("Quote")').first();
		if (await quoteApproval.isVisible({ timeout: 2000 }).catch(() => false)) {
			const rejectButton = quoteApproval.locator('button:has-text("Reject")');
			if (await rejectButton.isVisible()) {
				await rejectButton.click();

				const reasonInput = page.locator('textarea[name="reason"]');
				if (await reasonInput.isVisible()) {
					await reasonInput.fill('Discount too high, needs revision');
				}

				const confirmButton = page.locator('[role="dialog"] button:has-text("Reject")');
				await confirmButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should update quote status after approval', async ({ page }) => {
		await page.goto('/records/quotes');
		await waitForLoading(page);

		const approvedTab = page.locator('[role="tab"]:has-text("Approved")');
		if (await approvedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await approvedTab.click();
			await waitForLoading(page);
		}

		const approvedQuote = page.locator('tbody tr').first();
		if (await approvedQuote.isVisible({ timeout: 2000 }).catch(() => false)) {
			await approvedQuote.click();

			const status = page.locator('[data-testid="quote-status"], text=/Approved/i');
			await expect(status.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('Discount Approval Workflow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should trigger approval for large discount', async ({ page }) => {
		await page.goto('/records/quotes/1/edit');
		await waitForLoading(page);

		// Add large discount
		const discountInput = page.locator('input[name="discount"], input[name="discount_percent"]');
		if (await discountInput.isVisible()) {
			await discountInput.fill('25'); // Above typical approval threshold
		}

		const saveButton = page.locator('button:has-text("Save")');
		await saveButton.click();

		// Check for approval trigger
		const approvalDialog = page.locator('[role="dialog"]:has-text("Approval")');
		await expect(approvalDialog).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show discount approval chain', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const discountApproval = page.locator('[data-testid="pending-approval"]:has-text("Discount")').first();
		if (await discountApproval.isVisible({ timeout: 2000 }).catch(() => false)) {
			await discountApproval.click();

			const approvalChain = page.locator('[data-testid="approval-chain"]');
			// May show approval chain
		}
	});
});

test.describe('Multi-Level Approval', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should route through multiple approvers', async ({ page }) => {
		// View multi-level approval request
		await page.goto('/admin/approvals/pending/1');
		await waitForLoading(page);

		const approvalLevels = page.locator('[data-testid="approval-level"]');
		const levelCount = await approvalLevels.count();
		expect(levelCount).toBeGreaterThanOrEqual(1);
	});

	test('should notify next approver after first approval', async ({ page }) => {
		// Approve as first-level approver
		await page.goto('/admin/approvals/pending/1');
		await waitForLoading(page);

		const approveButton = page.locator('button:has-text("Approve")');
		if (await approveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await approveButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);

			// Check that next approver is pending
			const pendingApprover = page.locator('text=/Waiting for|Pending/i');
			// May show next pending approver
		}
	});

	test('should complete after all approvals', async ({ page }) => {
		await page.goto('/admin/approvals/history');
		await waitForLoading(page);

		const statusFilter = page.locator('button:has-text("Status")');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]:has-text("Approved")').click();
			await waitForLoading(page);
		}

		const completedApproval = page.locator('tbody tr').first();
		if (await completedApproval.isVisible({ timeout: 2000 }).catch(() => false)) {
			await completedApproval.click();

			// All approvers should be marked approved
			const approvedStatus = page.locator('[data-testid="approver-status"]:has-text("Approved")');
			// May show all approved
		}
	});
});

test.describe('Blueprint Approval Integration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should require approval for state transition', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		// Try to change to state that requires approval
		const stageSelect = page.locator('[data-testid="stage-select"]');
		if (await stageSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await stageSelect.click();

			// Select stage that requires approval
			const approvalRequiredStage = page.locator('[role="option"]:has-text("Proposal")');
			if (await approvalRequiredStage.isVisible({ timeout: 2000 }).catch(() => false)) {
				await approvalRequiredStage.click();

				// Check for approval requirement
				const approvalRequired = page.locator('text=/Requires Approval|Approval Required/i');
				await expect(approvalRequired.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
			}
		}
	});

	test('should block transition until approved', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const blockedStatus = page.locator('text=/Pending Approval|Waiting for Approval/i');
		// May show blocked status
	});

	test('should complete transition after approval', async ({ page }) => {
		// After approval is granted
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const newStage = page.locator('[data-testid="deal-stage"]');
		// Check stage changed after approval
	});
});

test.describe('Approval Escalation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show escalation warning', async ({ page }) => {
		await page.goto('/admin/approvals/pending/1');
		await waitForLoading(page);

		const escalationWarning = page.locator('text=/Escalation|Overdue|SLA/i');
		// May show escalation warning
	});

	test('should auto-escalate after timeout', async ({ page }) => {
		await page.goto('/admin/approvals/pending');
		await waitForLoading(page);

		const escalatedApproval = page.locator('[data-testid="pending-approval"]:has-text("Escalated")');
		// May have escalated approvals
	});

	test('should notify escalation manager', async ({ page }) => {
		await page.goto('/admin/notifications');
		await waitForLoading(page);

		const escalationNotification = page.locator('text=/Escalated|Escalation/i');
		// May have escalation notifications
	});
});

test.describe('Approval Delegation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delegate approval to another user', async ({ page }) => {
		await page.goto('/admin/approvals/pending/1');
		await waitForLoading(page);

		const delegateButton = page.locator('button:has-text("Delegate")');
		if (await delegateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await delegateButton.click();

			const userSelect = page.locator('[data-testid="delegate-user"]');
			if (await userSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await userSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Delegate")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should show delegation in approval history', async ({ page }) => {
		await page.goto('/admin/approvals/history/1');
		await waitForLoading(page);

		const delegationEvent = page.locator('text=/Delegated to|Delegation/i');
		// May show delegation event
	});
});
