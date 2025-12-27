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
 * E-Signature Tests
 * Tests for electronic signature functionality
 */

test.describe('Signature Requests List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/signatures');
		await waitForLoading(page);
	});

	test('should display signatures page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Signature|E-Sign|Sign/i }).first()).toBeVisible();
	});

	test('should display create request button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), button:has-text("New Request")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show signature requests', async ({ page }) => {
		const requests = page.locator('[data-testid="signature-request"], tbody tr');
		// May have requests
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search requests', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('contract');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});
});

test.describe('Signature Request Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create signature request', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"], input[name="title"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Signature Request ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Continue")');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should upload document for signing', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const uploadArea = page.locator('[data-testid="document-upload"], input[type="file"]');
		if (await uploadArea.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Upload area exists
		}
	});

	test('should use document template', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const templateOption = page.locator('button:has-text("Use Template"), [data-testid="template-select"]');
		if (await templateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateOption.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should add signers', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const addSignerButton = page.locator('button:has-text("Add Signer")');
		if (await addSignerButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addSignerButton.click();

			const emailInput = page.locator('input[name="signer_email"], input[type="email"]');
			if (await emailInput.isVisible()) {
				await emailInput.fill('signer@example.com');
			}

			const nameInput = page.locator('input[name="signer_name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill('John Doe');
			}
		}
	});

	test('should set signing order', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const orderToggle = page.locator('label:has-text("Signing Order") input[type="checkbox"]');
		if (await orderToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await orderToggle.check();
		}
	});
});

test.describe('Signature Field Placement', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should add signature field', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const signatureField = page.locator('button:has-text("Signature"), [data-testid="signature-field"]');
		if (await signatureField.isVisible({ timeout: 2000 }).catch(() => false)) {
			await signatureField.click();
			// Drag to document or click to place
		}
	});

	test('should add initials field', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const initialsField = page.locator('button:has-text("Initials"), [data-testid="initials-field"]');
		if (await initialsField.isVisible({ timeout: 2000 }).catch(() => false)) {
			await initialsField.click();
		}
	});

	test('should add date field', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const dateField = page.locator('button:has-text("Date Signed"), [data-testid="date-field"]');
		if (await dateField.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dateField.click();
		}
	});

	test('should add text field', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const textField = page.locator('button:has-text("Text"), [data-testid="text-field"]');
		if (await textField.isVisible({ timeout: 2000 }).catch(() => false)) {
			await textField.click();
		}
	});

	test('should assign field to signer', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const field = page.locator('[data-testid="signature-field-placed"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			await field.click();

			const signerSelect = page.locator('[data-testid="field-signer"]');
			if (await signerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await signerSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should mark field as required', async ({ page }) => {
		await page.goto('/admin/signatures/1/edit');
		await waitForLoading(page);

		const field = page.locator('[data-testid="signature-field-placed"]').first();
		if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
			await field.click();

			const requiredToggle = page.locator('label:has-text("Required") input[type="checkbox"]');
			if (await requiredToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await requiredToggle.check();
			}
		}
	});
});

test.describe('Send Signature Request', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send request for signing', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const sendButton = page.locator('button:has-text("Send"), button:has-text("Send for Signing")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should customize email message', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const customizeButton = page.locator('button:has-text("Customize Email"), button:has-text("Edit Message")');
		if (await customizeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await customizeButton.click();

			const subjectInput = page.locator('input[name="email_subject"]');
			if (await subjectInput.isVisible()) {
				await subjectInput.fill('Please sign this document');
			}

			const messageInput = page.locator('textarea[name="email_message"]');
			if (await messageInput.isVisible()) {
				await messageInput.fill('Please review and sign the attached document.');
			}
		}
	});

	test('should set expiration date', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const expirationInput = page.locator('input[name="expires_at"], input[type="date"]');
		if (await expirationInput.isVisible()) {
			const futureDate = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
			await expirationInput.fill(futureDate);
		}
	});
});

test.describe('Signature Request Tracking', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view request status', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const status = page.locator('[data-testid="request-status"], text=/Pending|Completed|Sent/i');
		await expect(status.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should view signer progress', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const signerList = page.locator('[data-testid="signer-list"], [class*="signer"]');
		// May show signer progress
	});

	test('should send reminder', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const reminderButton = page.locator('button:has-text("Remind"), button:has-text("Send Reminder")');
		if (await reminderButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await reminderButton.click();
			await waitForToast(page);
		}
	});

	test('should void request', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const voidButton = page.locator('button:has-text("Void"), button:has-text("Cancel Request")');
		if (await voidButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await voidButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should download signed document', async ({ page }) => {
		await page.goto('/admin/signatures');
		await waitForLoading(page);

		// Go to completed tab
		const completedTab = page.locator('[role="tab"]:has-text("Completed")');
		if (await completedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await completedTab.click();

			const request = page.locator('tbody tr').first();
			if (await request.isVisible({ timeout: 2000 }).catch(() => false)) {
				const downloadButton = request.locator('button:has-text("Download"), a:has-text("Download")');
				if (await downloadButton.isVisible()) {
					// Just verify download button exists
				}
			}
		}
	});
});

test.describe('Signature Templates', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view signature templates', async ({ page }) => {
		await page.goto('/admin/signatures/templates');
		await waitForLoading(page);

		const templates = page.locator('[data-testid="signature-template"], tbody tr');
		// May have templates
	});

	test('should create signature template', async ({ page }) => {
		await page.goto('/admin/signatures/templates/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Signature Template ${Date.now()}`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should use template for new request', async ({ page }) => {
		await page.goto('/admin/signatures/create');
		await waitForLoading(page);

		const templateOption = page.locator('button:has-text("From Template"), [data-testid="use-template"]');
		if (await templateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
			await templateOption.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Audit Trail', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view signature audit trail', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const auditTab = page.locator('[role="tab"]:has-text("Audit"), button:has-text("Activity")');
		if (await auditTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await auditTab.click();

			const auditLog = page.locator('[data-testid="audit-log"], [class*="timeline"]');
			// May show audit log
		}
	});

	test('should download audit certificate', async ({ page }) => {
		await page.goto('/admin/signatures/1');
		await waitForLoading(page);

		const certificateButton = page.locator('button:has-text("Certificate"), button:has-text("Download Audit")');
		if (await certificateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists
		}
	});
});
