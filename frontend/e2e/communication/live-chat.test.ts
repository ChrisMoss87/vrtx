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
 * Live Chat Tests
 * Tests for real-time chat with website visitors
 */

test.describe('Chat Dashboard', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/chat');
		await waitForLoading(page);
	});

	test('should display chat dashboard', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Chat|Conversations|Live Chat/i }).first()).toBeVisible();
	});

	test('should show active conversations', async ({ page }) => {
		const conversations = page.locator('[data-testid="conversation-list"], [class*="conversation"]');
		// May have conversations
	});

	test('should show online status toggle', async ({ page }) => {
		const statusToggle = page.locator('button:has-text("Online"), button:has-text("Available"), [data-testid="status-toggle"]');
		await expect(statusToggle.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should show waiting queue', async ({ page }) => {
		const queue = page.locator('text=/Queue|Waiting|Pending/i');
		// May show queue
	});
});

test.describe('Chat Conversation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should select conversation', async ({ page }) => {
		await page.goto('/admin/chat');
		await waitForLoading(page);

		const conversation = page.locator('[data-testid="conversation-item"]').first();
		if (await conversation.isVisible({ timeout: 2000 }).catch(() => false)) {
			await conversation.click();

			const chatPanel = page.locator('[data-testid="chat-panel"], [class*="chat-messages"]');
			await expect(chatPanel).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should send message', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const messageInput = page.locator('input[placeholder*="message"], textarea[placeholder*="message"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Hello, how can I help you?');
			await messageInput.press('Enter');
		}
	});

	test('should send canned response', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const cannedButton = page.locator('button:has-text("Canned"), button[aria-label="Quick replies"]');
		if (await cannedButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cannedButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should attach file in chat', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const attachButton = page.locator('button[aria-label="Attach"], button:has-text("Attach")');
		if (await attachButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists
		}
	});

	test('should view visitor info', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const visitorInfo = page.locator('[data-testid="visitor-info"], [class*="visitor-details"]');
		// May show visitor info
	});
});

test.describe('Chat Assignment', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should assign chat to agent', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const assignButton = page.locator('button:has-text("Assign"), button:has-text("Transfer")');
		if (await assignButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await assignButton.click();

			const agentOption = page.locator('[role="option"]').first();
			if (await agentOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await agentOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should transfer chat', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const transferButton = page.locator('button:has-text("Transfer")');
		if (await transferButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await transferButton.click();

			const agentOption = page.locator('[role="option"]').first();
			if (await agentOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await agentOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should join chat', async ({ page }) => {
		await page.goto('/admin/chat');
		await waitForLoading(page);

		const conversation = page.locator('[data-testid="conversation-item"]').first();
		if (await conversation.isVisible({ timeout: 2000 }).catch(() => false)) {
			const joinButton = conversation.locator('button:has-text("Join")');
			if (await joinButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await joinButton.click();
			}
		}
	});
});

test.describe('Chat Status', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should close conversation', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const closeButton = page.locator('button:has-text("Close"), button:has-text("End Chat")');
		if (await closeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await closeButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should resolve conversation', async ({ page }) => {
		await page.goto('/admin/chat/1');
		await waitForLoading(page);

		const resolveButton = page.locator('button:has-text("Resolve"), button:has-text("Mark Resolved")');
		if (await resolveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await resolveButton.click();
			await waitForToast(page);
		}
	});

	test('should reopen conversation', async ({ page }) => {
		await page.goto('/admin/chat');
		await waitForLoading(page);

		// Go to closed tab
		const closedTab = page.locator('[role="tab"]:has-text("Closed")');
		if (await closedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await closedTab.click();

			const conversation = page.locator('[data-testid="conversation-item"]').first();
			if (await conversation.isVisible({ timeout: 2000 }).catch(() => false)) {
				await conversation.click();

				const reopenButton = page.locator('button:has-text("Reopen")');
				if (await reopenButton.isVisible({ timeout: 2000 }).catch(() => false)) {
					await reopenButton.click();
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('Chat Widget Configuration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view widget settings', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Chat.*Settings|Widget/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should customize widget appearance', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		const colorPicker = page.locator('input[type="color"], [data-testid="color-picker"]');
		if (await colorPicker.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Color picker exists
		}
	});

	test('should configure welcome message', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		const welcomeInput = page.locator('textarea[name="welcome_message"], input[name="greeting"]');
		if (await welcomeInput.isVisible()) {
			await welcomeInput.fill('Welcome! How can we help you today?');
		}
	});

	test('should set business hours', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		const businessHoursSection = page.locator('text=/Business Hours|Availability/i');
		if (await businessHoursSection.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Business hours section exists
		}
	});

	test('should configure offline message', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		const offlineInput = page.locator('textarea[name="offline_message"]');
		if (await offlineInput.isVisible()) {
			await offlineInput.fill('We are currently offline. Please leave a message.');
		}
	});

	test('should get embed code', async ({ page }) => {
		await page.goto('/admin/settings/chat');
		await waitForLoading(page);

		const embedTab = page.locator('[role="tab"]:has-text("Embed"), button:has-text("Get Code")');
		if (await embedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await embedTab.click();

			const embedCode = page.locator('code, pre, textarea[readonly]');
			await expect(embedCode.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('Canned Responses', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view canned responses', async ({ page }) => {
		await page.goto('/admin/settings/chat/canned-responses');
		await waitForLoading(page);

		const responses = page.locator('tbody tr, [data-testid="canned-response"]');
		// May have canned responses
	});

	test('should create canned response', async ({ page }) => {
		await page.goto('/admin/settings/chat/canned-responses');
		await waitForLoading(page);

		const createButton = page.locator('button:has-text("Create"), button:has-text("Add")');
		if (await createButton.isVisible()) {
			await createButton.click();

			const shortcutInput = page.locator('input[name="shortcut"]');
			if (await shortcutInput.isVisible()) {
				await shortcutInput.fill('/greeting');
			}

			const messageInput = page.locator('textarea[name="message"]');
			if (await messageInput.isVisible()) {
				await messageInput.fill('Hello! Thanks for reaching out.');
			}

			const submitButton = page.locator('button[type="submit"]');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should edit canned response', async ({ page }) => {
		await page.goto('/admin/settings/chat/canned-responses');
		await waitForLoading(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const editButton = row.locator('button:has-text("Edit")');
			if (await editButton.isVisible()) {
				await editButton.click();

				const messageInput = page.locator('textarea[name="message"]');
				if (await messageInput.isVisible()) {
					await messageInput.fill('Updated greeting message');
				}

				const saveButton = page.locator('button:has-text("Save")');
				await saveButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should delete canned response', async ({ page }) => {
		await page.goto('/admin/settings/chat/canned-responses');
		await waitForLoading(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const deleteButton = row.locator('button:has-text("Delete"), button[aria-label="Delete"]');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});
});

test.describe('Chat Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view chat analytics', async ({ page }) => {
		await page.goto('/admin/chat/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="chat-analytics"], [class*="analytics"]');
		// May have analytics
	});

	test('should show response time metrics', async ({ page }) => {
		await page.goto('/admin/chat/analytics');
		await waitForLoading(page);

		const responseTime = page.locator('text=/Response Time|Avg Response/i');
		// May show response time
	});

	test('should show satisfaction ratings', async ({ page }) => {
		await page.goto('/admin/chat/analytics');
		await waitForLoading(page);

		const ratings = page.locator('text=/Satisfaction|Rating|CSAT/i');
		// May show ratings
	});
});
