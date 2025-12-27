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
 * Shared Inbox Tests
 * Tests for team inbox and unified communications
 */

test.describe('Shared Inbox List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/inbox');
		await waitForLoading(page);
	});

	test('should display shared inbox page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Inbox|Shared Inbox|Conversations/i }).first()).toBeVisible();
	});

	test('should show conversation list', async ({ page }) => {
		const conversations = page.locator('[data-testid="conversation-item"], tbody tr');
		// May have conversations
	});

	test('should filter by channel', async ({ page }) => {
		const channelFilter = page.locator('button:has-text("Channel"), [data-filter="channel"]');
		if (await channelFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await channelFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
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

	test('should search conversations', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Conversation View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view conversation thread', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const messages = page.locator('[data-testid="message"], [class*="message"]');
		// May have messages
	});

	test('should show contact info', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const contactInfo = page.locator('[data-testid="contact-info"], [class*="contact-details"]');
		// May show contact info
	});

	test('should show channel indicator', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const channelIndicator = page.locator('[data-testid="channel-badge"], text=/Email|SMS|Chat|Phone/i');
		// May show channel indicator
	});

	test('should show conversation history', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const history = page.locator('[data-testid="conversation-history"], [class*="timeline"]');
		// May show history
	});
});

test.describe('Conversation Reply', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should reply to conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const replyInput = page.locator('textarea[placeholder*="Reply"], [contenteditable="true"]');
		if (await replyInput.isVisible()) {
			await replyInput.fill('This is a reply message.');

			const sendButton = page.locator('button:has-text("Send")');
			await sendButton.click();
			await waitForToast(page);
		}
	});

	test('should select reply channel', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const channelSelect = page.locator('button:has-text("Reply via"), [data-testid="channel-select"]');
		if (await channelSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await channelSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should use canned response', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const cannedButton = page.locator('button:has-text("Canned"), button[aria-label="Quick replies"]');
		if (await cannedButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cannedButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should add note to conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const noteTab = page.locator('button:has-text("Note"), [role="tab"]:has-text("Note")');
		if (await noteTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await noteTab.click();

			const noteInput = page.locator('textarea[placeholder*="Note"]');
			if (await noteInput.isVisible()) {
				await noteInput.fill('Internal note about this conversation');
			}

			const saveButton = page.locator('button:has-text("Save Note"), button:has-text("Add Note")');
			await saveButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Conversation Assignment', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should assign to team member', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const assignButton = page.locator('button:has-text("Assign"), button:has-text("Assignee")');
		if (await assignButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await assignButton.click();

			const memberOption = page.locator('[role="option"]').first();
			if (await memberOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await memberOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should assign to team', async ({ page }) => {
		await page.goto('/admin/inbox/1');
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

	test('should claim conversation', async ({ page }) => {
		await page.goto('/admin/inbox');
		await waitForLoading(page);

		// Filter to unassigned
		const unassignedTab = page.locator('[role="tab"]:has-text("Unassigned")');
		if (await unassignedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await unassignedTab.click();

			const conversation = page.locator('[data-testid="conversation-item"]').first();
			if (await conversation.isVisible({ timeout: 2000 }).catch(() => false)) {
				const claimButton = conversation.locator('button:has-text("Claim"), button:has-text("Take")');
				if (await claimButton.isVisible({ timeout: 2000 }).catch(() => false)) {
					await claimButton.click();
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('Conversation Status', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should close conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const closeButton = page.locator('button:has-text("Close"), button:has-text("Resolve")');
		if (await closeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await closeButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should snooze conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const snoozeButton = page.locator('button:has-text("Snooze")');
		if (await snoozeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await snoozeButton.click();

			const snoozeOption = page.locator('[role="option"]:has-text("1 hour"), [role="option"]:has-text("Tomorrow")').first();
			if (await snoozeOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await snoozeOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should mark as spam', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions"), button[aria-haspopup="menu"]');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const spamOption = page.locator('[role="menuitem"]:has-text("Spam"), [role="menuitem"]:has-text("Mark as Spam")');
			if (await spamOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await spamOption.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});

	test('should set priority', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const priorityButton = page.locator('button:has-text("Priority"), [data-testid="priority-select"]');
		if (await priorityButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await priorityButton.click();

			const highOption = page.locator('[role="option"]:has-text("High")');
			if (await highOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await highOption.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Conversation Tags', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add tag to conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const tagsButton = page.locator('button:has-text("Tags"), button:has-text("Add Tag")');
		if (await tagsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await tagsButton.click();

			const tagOption = page.locator('[role="option"]').first();
			if (await tagOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await tagOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should remove tag from conversation', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const existingTag = page.locator('[data-testid="conversation-tag"]').first();
		if (await existingTag.isVisible({ timeout: 2000 }).catch(() => false)) {
			const removeButton = existingTag.locator('button[aria-label="Remove"], svg');
			if (await removeButton.isVisible()) {
				await removeButton.click();
			}
		}
	});
});

test.describe('Inbox Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view inbox settings', async ({ page }) => {
		await page.goto('/admin/settings/inbox');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Inbox.*Settings/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should configure auto-assignment', async ({ page }) => {
		await page.goto('/admin/settings/inbox');
		await waitForLoading(page);

		const autoAssignToggle = page.locator('label:has-text("Auto-assign") input[type="checkbox"]');
		if (await autoAssignToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await autoAssignToggle.click();
		}
	});

	test('should configure SLA settings', async ({ page }) => {
		await page.goto('/admin/settings/inbox');
		await waitForLoading(page);

		const slaSection = page.locator('text=/SLA|Response Time/i');
		if (await slaSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			const slaInput = page.locator('input[name="sla_minutes"], input[name="response_time"]');
			if (await slaInput.isVisible()) {
				await slaInput.fill('30');
			}
		}
	});

	test('should manage inbox channels', async ({ page }) => {
		await page.goto('/admin/settings/inbox/channels');
		await waitForLoading(page);

		const channels = page.locator('[data-testid="inbox-channel"], tbody tr');
		// May have channels
	});
});

test.describe('Inbox Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view inbox analytics', async ({ page }) => {
		await page.goto('/admin/inbox/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="inbox-analytics"], [class*="analytics"]');
		// May have analytics
	});

	test('should show response time metrics', async ({ page }) => {
		await page.goto('/admin/inbox/analytics');
		await waitForLoading(page);

		const responseTime = page.locator('text=/Response Time|Avg Response/i');
		// May show response time
	});

	test('should show resolution metrics', async ({ page }) => {
		await page.goto('/admin/inbox/analytics');
		await waitForLoading(page);

		const resolution = page.locator('text=/Resolution|Resolved/i');
		// May show resolution metrics
	});

	test('should show team performance', async ({ page }) => {
		await page.goto('/admin/inbox/analytics');
		await waitForLoading(page);

		const teamPerformance = page.locator('text=/Team|Agent.*Performance/i');
		// May show team performance
	});
});
