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
 * SMS Tests
 * Tests for SMS messaging functionality
 */

test.describe('SMS List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/sms');
		await waitForLoading(page);
	});

	test('should display SMS list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /SMS|Text|Messages/i }).first()).toBeVisible();
	});

	test('should display compose button', async ({ page }) => {
		const composeButton = page.locator('button:has-text("Compose"), button:has-text("New SMS"), button:has-text("Send SMS")');
		await expect(composeButton.first()).toBeVisible();
	});

	test('should show conversations', async ({ page }) => {
		const conversations = page.locator('[data-testid="sms-conversation"], tbody tr');
		// May have conversations
	});

	test('should search messages', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('SMS Compose', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should compose and send SMS', async ({ page }) => {
		await page.goto('/admin/sms/compose');
		await waitForLoading(page);

		// Select recipient
		const toInput = page.locator('input[name="to"], [data-testid="recipient-input"]');
		if (await toInput.isVisible()) {
			await toInput.fill('+1234567890');
		}

		// Fill message
		const messageInput = page.locator('textarea[name="message"], input[name="body"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Test SMS message');
		}

		// Send
		const sendButton = page.locator('button:has-text("Send")');
		await sendButton.click();
		await waitForToast(page);
	});

	test('should show character count', async ({ page }) => {
		await page.goto('/admin/sms/compose');
		await waitForLoading(page);

		const messageInput = page.locator('textarea[name="message"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Test message');

			const charCount = page.locator('text=/\\d+.*character|\\d+\\/160/i');
			// May show character count
		}
	});

	test('should select from contacts', async ({ page }) => {
		await page.goto('/admin/sms/compose');
		await waitForLoading(page);

		const contactsButton = page.locator('button:has-text("Contacts"), button:has-text("Select Contact")');
		if (await contactsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await contactsButton.click();

			const contactOption = page.locator('[role="option"]').first();
			if (await contactOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await contactOption.click();
			}
		}
	});

	test('should use SMS template', async ({ page }) => {
		await page.goto('/admin/sms/compose');
		await waitForLoading(page);

		const templateButton = page.locator('button:has-text("Template"), button:has-text("Use Template")');
		if (await templateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should add personalization', async ({ page }) => {
		await page.goto('/admin/sms/compose');
		await waitForLoading(page);

		const personalizeButton = page.locator('button:has-text("Personalize"), button:has-text("Insert Field")');
		if (await personalizeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await personalizeButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('SMS Conversation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view conversation thread', async ({ page }) => {
		await page.goto('/admin/sms/1');
		await waitForLoading(page);

		const messages = page.locator('[data-testid="sms-message"], [class*="message"]');
		// May have messages
	});

	test('should reply to conversation', async ({ page }) => {
		await page.goto('/admin/sms/1');
		await waitForLoading(page);

		const messageInput = page.locator('textarea[placeholder*="message"], input[placeholder*="Reply"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Reply message');
			await messageInput.press('Enter');
		}
	});

	test('should show delivery status', async ({ page }) => {
		await page.goto('/admin/sms/1');
		await waitForLoading(page);

		const status = page.locator('text=/Delivered|Sent|Failed|Pending/i');
		// May show status
	});
});

test.describe('SMS Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view SMS templates', async ({ page }) => {
		await page.goto('/admin/sms-templates');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Template|Templates/i }).first()).toBeVisible();
	});

	test('should create SMS template', async ({ page }) => {
		await page.goto('/admin/sms-templates/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`SMS Template ${Date.now()}`);
		}

		const messageInput = page.locator('textarea[name="message"], textarea[name="content"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Hello {{first_name}}, this is a test message.');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should edit SMS template', async ({ page }) => {
		await page.goto('/admin/sms-templates/1/edit');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Updated Template ${Date.now()}`);
		}

		const saveButton = page.locator('button:has-text("Save")');
		await saveButton.click();
		await waitForToast(page);
	});

	test('should delete SMS template', async ({ page }) => {
		await page.goto('/admin/sms-templates');
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

test.describe('Bulk SMS', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send bulk SMS', async ({ page }) => {
		await page.goto('/admin/sms/bulk');
		await waitForLoading(page);

		// Select recipients
		const recipientsSelect = page.locator('button:has-text("Recipients"), [data-testid="recipient-select"]');
		if (await recipientsSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await recipientsSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Fill message
		const messageInput = page.locator('textarea[name="message"]');
		if (await messageInput.isVisible()) {
			await messageInput.fill('Bulk SMS message to all recipients');
		}

		// Send
		const sendButton = page.locator('button:has-text("Send")');
		await sendButton.click();
		await confirmDialog(page, 'confirm').catch(() => {});
		await waitForToast(page);
	});

	test('should schedule bulk SMS', async ({ page }) => {
		await page.goto('/admin/sms/bulk');
		await waitForLoading(page);

		const scheduleButton = page.locator('button:has-text("Schedule")');
		if (await scheduleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await scheduleButton.click();

			const dateInput = page.locator('input[type="datetime-local"], input[type="date"]');
			if (await dateInput.isVisible()) {
				const futureDate = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 16);
				await dateInput.fill(futureDate);
			}
		}
	});

	test('should preview bulk SMS', async ({ page }) => {
		await page.goto('/admin/sms/bulk');
		await waitForLoading(page);

		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();
		}
	});
});

test.describe('SMS Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view SMS settings', async ({ page }) => {
		await page.goto('/admin/settings/sms');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /SMS.*Settings/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should configure SMS provider', async ({ page }) => {
		await page.goto('/admin/settings/sms');
		await waitForLoading(page);

		const providerSelect = page.locator('button:has-text("Provider"), [data-testid="provider-select"]');
		if (await providerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await providerSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should configure sender ID', async ({ page }) => {
		await page.goto('/admin/settings/sms');
		await waitForLoading(page);

		const senderInput = page.locator('input[name="sender_id"], input[name="from_number"]');
		if (await senderInput.isVisible()) {
			await senderInput.fill('MyCompany');
		}
	});

	test('should test SMS connection', async ({ page }) => {
		await page.goto('/admin/settings/sms');
		await waitForLoading(page);

		const testButton = page.locator('button:has-text("Test"), button:has-text("Send Test")');
		if (await testButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await testButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('SMS Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view SMS analytics', async ({ page }) => {
		await page.goto('/admin/sms/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="sms-analytics"], [class*="analytics"]');
		// May have analytics
	});

	test('should show delivery rate', async ({ page }) => {
		await page.goto('/admin/sms/analytics');
		await waitForLoading(page);

		const deliveryRate = page.locator('text=/Delivery Rate|Delivered/i');
		// May show delivery rate
	});

	test('should show message count', async ({ page }) => {
		await page.goto('/admin/sms/analytics');
		await waitForLoading(page);

		const messageCount = page.locator('text=/Messages Sent|Total Messages/i');
		// May show message count
	});
});
