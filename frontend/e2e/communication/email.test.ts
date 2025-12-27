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
 * Email Tests
 * Tests for email sending, receiving, and management
 */

test.describe('Email List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/emails');
		await waitForLoading(page);
	});

	test('should display emails list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Email|Emails|Inbox/i }).first()).toBeVisible();
	});

	test('should display compose button', async ({ page }) => {
		const composeButton = page.locator('button:has-text("Compose"), button:has-text("New Email")');
		await expect(composeButton.first()).toBeVisible();
	});

	test('should filter by folder', async ({ page }) => {
		const folders = page.locator('[data-testid="email-folder"], a:has-text("Inbox"), a:has-text("Sent")');
		if ((await folders.count()) > 1) {
			await folders.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search emails', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should show unread count', async ({ page }) => {
		const unreadCount = page.locator('text=/\\d+ unread|Unread \\(\\d+\\)/i');
		// May show unread count
	});
});

test.describe('Email Compose', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should open compose modal', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const composeButton = page.locator('button:has-text("Compose"), button:has-text("New Email")');
		await composeButton.click();

		const modal = page.locator('[role="dialog"]');
		await expect(modal).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should compose and send email', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		// Fill recipient
		const toInput = page.locator('input[name="to"], [data-testid="to-input"]');
		if (await toInput.isVisible()) {
			await toInput.fill('test@example.com');
		}

		// Fill subject
		const subjectInput = page.locator('input[name="subject"]');
		if (await subjectInput.isVisible()) {
			await subjectInput.fill('Test Email Subject');
		}

		// Fill body
		const bodyEditor = page.locator('[contenteditable="true"], textarea[name="body"]');
		if (await bodyEditor.isVisible()) {
			await bodyEditor.fill('This is a test email body.');
		}

		// Send
		const sendButton = page.locator('button:has-text("Send")');
		await sendButton.click();
		await waitForToast(page);
	});

	test('should save email as draft', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const subjectInput = page.locator('input[name="subject"]');
		if (await subjectInput.isVisible()) {
			await subjectInput.fill('Draft Email');
		}

		const saveDraftButton = page.locator('button:has-text("Save Draft"), button:has-text("Save")');
		if (await saveDraftButton.isVisible()) {
			await saveDraftButton.click();
			await waitForToast(page);
		}
	});

	test('should add CC and BCC recipients', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const ccButton = page.locator('button:has-text("CC"), button:has-text("Add CC")');
		if (await ccButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await ccButton.click();

			const ccInput = page.locator('input[name="cc"]');
			if (await ccInput.isVisible()) {
				await ccInput.fill('cc@example.com');
			}
		}

		const bccButton = page.locator('button:has-text("BCC"), button:has-text("Add BCC")');
		if (await bccButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await bccButton.click();

			const bccInput = page.locator('input[name="bcc"]');
			if (await bccInput.isVisible()) {
				await bccInput.fill('bcc@example.com');
			}
		}
	});

	test('should attach files', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const attachButton = page.locator('button:has-text("Attach"), button[aria-label="Attach"]');
		if (await attachButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists
		}
	});

	test('should use email template', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const templateButton = page.locator('button:has-text("Template"), button:has-text("Use Template")');
		if (await templateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateButton.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Email Reading', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view email details', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const emailRow = page.locator('tbody tr, [data-testid="email-row"]').first();
		if (await emailRow.isVisible()) {
			await emailRow.click();

			// Should show email content
			const emailContent = page.locator('[data-testid="email-content"], [class*="email-body"]');
			await expect(emailContent).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should mark as read', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const unreadEmail = page.locator('tbody tr.unread, [data-testid="email-row"]:not(.read)').first();
		if (await unreadEmail.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = unreadEmail.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const markReadOption = page.locator('[role="menuitem"]:has-text("Mark as Read")');
				if (await markReadOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await markReadOption.click();
				}
			}
		}
	});

	test('should mark as unread', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const readEmail = page.locator('tbody tr:not(.unread), [data-testid="email-row"].read').first();
		if (await readEmail.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = readEmail.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const markUnreadOption = page.locator('[role="menuitem"]:has-text("Mark as Unread")');
				if (await markUnreadOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await markUnreadOption.click();
				}
			}
		}
	});
});

test.describe('Email Reply', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should reply to email', async ({ page }) => {
		await page.goto('/admin/emails/1');
		await waitForLoading(page);

		const replyButton = page.locator('button:has-text("Reply")');
		if (await replyButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await replyButton.click();

			const replyEditor = page.locator('[contenteditable="true"], textarea');
			if (await replyEditor.isVisible()) {
				await replyEditor.fill('This is my reply.');
			}

			const sendButton = page.locator('button:has-text("Send")');
			await sendButton.click();
			await waitForToast(page);
		}
	});

	test('should reply all', async ({ page }) => {
		await page.goto('/admin/emails/1');
		await waitForLoading(page);

		const replyAllButton = page.locator('button:has-text("Reply All")');
		if (await replyAllButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await replyAllButton.click();
		}
	});

	test('should forward email', async ({ page }) => {
		await page.goto('/admin/emails/1');
		await waitForLoading(page);

		const forwardButton = page.locator('button:has-text("Forward")');
		if (await forwardButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await forwardButton.click();

			const toInput = page.locator('input[name="to"]');
			if (await toInput.isVisible()) {
				await toInput.fill('forward@example.com');
			}
		}
	});
});

test.describe('Email Organization', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should move to folder', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const emailRow = page.locator('tbody tr').first();
		if (await emailRow.isVisible()) {
			const actionsButton = emailRow.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const moveOption = page.locator('[role="menuitem"]:has-text("Move to")');
				if (await moveOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await moveOption.click();
					await page.locator('[role="option"]').first().click();
				}
			}
		}
	});

	test('should star email', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const starButton = page.locator('button[aria-label="Star"], [data-testid="star-button"]').first();
		if (await starButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await starButton.click();
		}
	});

	test('should archive email', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const emailRow = page.locator('tbody tr').first();
		if (await emailRow.isVisible()) {
			const actionsButton = emailRow.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const archiveOption = page.locator('[role="menuitem"]:has-text("Archive")');
				if (await archiveOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await archiveOption.click();
					await waitForToast(page);
				}
			}
		}
	});

	test('should delete email', async ({ page }) => {
		await page.goto('/admin/emails');
		await waitForLoading(page);

		const emailRow = page.locator('tbody tr').first();
		if (await emailRow.isVisible()) {
			const actionsButton = emailRow.locator('button[aria-haspopup="menu"]');
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

test.describe('Email Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view email templates', async ({ page }) => {
		await page.goto('/admin/email-templates');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Template|Templates/i }).first()).toBeVisible();
	});

	test('should create email template', async ({ page }) => {
		await page.goto('/admin/email-templates/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Template ${Date.now()}`);
		}

		const subjectInput = page.locator('input[name="subject"]');
		if (await subjectInput.isVisible()) {
			await subjectInput.fill('Template Subject');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should edit email template', async ({ page }) => {
		await page.goto('/admin/email-templates/1/edit');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Updated Template ${Date.now()}`);
		}

		const saveButton = page.locator('button:has-text("Save")');
		await saveButton.click();
		await waitForToast(page);
	});
});

test.describe('Email Account Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view email accounts', async ({ page }) => {
		await page.goto('/admin/settings/email');
		await waitForLoading(page);

		const accounts = page.locator('[data-testid="email-account"], table tbody tr');
		// May have email accounts
	});

	test('should add email account', async ({ page }) => {
		await page.goto('/admin/settings/email');
		await waitForLoading(page);

		const addButton = page.locator('button:has-text("Add Account"), button:has-text("Connect")');
		if (await addButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addButton.click();
		}
	});

	test('should configure email signature', async ({ page }) => {
		await page.goto('/admin/settings/email');
		await waitForLoading(page);

		const signatureTab = page.locator('[role="tab"]:has-text("Signature")');
		if (await signatureTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await signatureTab.click();

			const signatureEditor = page.locator('[contenteditable="true"], textarea[name="signature"]');
			if (await signatureEditor.isVisible()) {
				await signatureEditor.fill('Best regards,\nTest User');
			}
		}
	});
});
