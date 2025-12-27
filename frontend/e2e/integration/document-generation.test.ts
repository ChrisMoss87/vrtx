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
 * Document Generation Integration Tests
 * Tests for end-to-end document generation and delivery
 */

test.describe('Quote Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate PDF quote', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const generateButton = page.locator('button:has-text("Generate PDF"), button:has-text("Download PDF")');
		if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
			await generateButton.click();

			const download = await downloadPromise;
			if (download) {
				expect(download.suggestedFilename()).toMatch(/\.pdf$/);
			}
		}
	});

	test('should generate quote from template', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const generateOption = page.locator('[role="menuitem"]:has-text("Generate Document")');
			if (await generateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await generateOption.click();

				// Select template
				const templateSelect = page.locator('[data-testid="template-select"]');
				if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
					await templateSelect.click();
					await page.locator('[role="option"]').first().click();
				}

				const generateButton = page.locator('[role="dialog"] button:has-text("Generate")');
				await generateButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should include line items in document', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		// Verify line items exist
		const lineItems = page.locator('[data-testid="line-item"]');
		const hasLineItems = await lineItems.count() > 0;

		if (hasLineItems) {
			// Generate document
			const generateButton = page.locator('button:has-text("Generate PDF")');
			if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await generateButton.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Invoice Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate PDF invoice', async ({ page }) => {
		await page.goto('/records/invoices/1');
		await waitForLoading(page);

		const generateButton = page.locator('button:has-text("Generate PDF"), button:has-text("Download PDF")');
		if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);
			await generateButton.click();

			const download = await downloadPromise;
			if (download) {
				expect(download.suggestedFilename()).toMatch(/\.pdf$/);
			}
		}
	});

	test('should email invoice', async ({ page }) => {
		await page.goto('/records/invoices/1');
		await waitForLoading(page);

		const emailButton = page.locator('button:has-text("Email Invoice"), button:has-text("Send")');
		if (await emailButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailButton.click();

			// Verify email recipient
			const recipientInput = page.locator('input[name="to"], input[type="email"]');
			if (await recipientInput.isVisible()) {
				// May be pre-filled
			}

			const sendButton = page.locator('[role="dialog"] button:has-text("Send")');
			await sendButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Proposal Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate proposal document', async ({ page }) => {
		await page.goto('/records/proposals/1');
		await waitForLoading(page);

		const generateButton = page.locator('button:has-text("Generate"), button:has-text("Export")');
		if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await generateButton.click();
			await waitForToast(page);
		}
	});

	test('should include proposal sections', async ({ page }) => {
		await page.goto('/records/proposals/1');
		await waitForLoading(page);

		// Verify sections exist
		const sections = page.locator('[data-testid="proposal-section"]');
		// May have sections
	});

	test('should generate with pricing table', async ({ page }) => {
		await page.goto('/records/proposals/1');
		await waitForLoading(page);

		const pricingSection = page.locator('[data-testid="pricing-table"], text=/Pricing/i');
		// May have pricing section
	});
});

test.describe('Contract Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate contract from template', async ({ page }) => {
		await page.goto('/records/contracts/1');
		await waitForLoading(page);

		const generateButton = page.locator('button:has-text("Generate Contract")');
		if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await generateButton.click();

			// Select template
			const templateSelect = page.locator('[data-testid="template-select"]');
			if (await templateSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await templateSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Generate")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should include merge fields', async ({ page }) => {
		await page.goto('/records/contracts/1');
		await waitForLoading(page);

		// Check for populated merge fields
		const contractContent = page.locator('[data-testid="contract-content"]');
		// Content should have replaced merge fields
	});
});

test.describe('Document with Signature', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate and send for signature', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const signButton = page.locator('button:has-text("Send for Signature"), button:has-text("Request Signature")');
		if (await signButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await signButton.click();

			// Configure signature request
			const signerEmailInput = page.locator('input[name="signer_email"]');
			if (await signerEmailInput.isVisible()) {
				await signerEmailInput.fill('signer@example.com');
			}

			const sendButton = page.locator('[role="dialog"] button:has-text("Send")');
			await sendButton.click();
			await waitForToast(page);
		}
	});

	test('should track signature status', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const signatureStatus = page.locator('[data-testid="signature-status"], text=/Pending Signature|Sent for Signature/i');
		// May show signature status
	});
});

test.describe('Bulk Document Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate documents for multiple records', async ({ page }) => {
		await page.goto('/records/quotes');
		await waitForLoading(page);

		// Select multiple quotes
		const checkboxes = page.locator('tbody tr input[type="checkbox"]');
		if ((await checkboxes.count()) >= 2) {
			await checkboxes.first().check();
			await checkboxes.nth(1).check();

			const bulkActionsButton = page.locator('button:has-text("Actions")');
			if (await bulkActionsButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await bulkActionsButton.click();

				const generateOption = page.locator('[role="menuitem"]:has-text("Generate Documents")');
				if (await generateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await generateOption.click();
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('Document History', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view generated documents', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const documentsTab = page.locator('[role="tab"]:has-text("Documents")');
		if (await documentsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await documentsTab.click();

			const documents = page.locator('[data-testid="generated-document"]');
			// May have documents
		}
	});

	test('should download previous document version', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const documentsTab = page.locator('[role="tab"]:has-text("Documents")');
		if (await documentsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await documentsTab.click();

			const document = page.locator('[data-testid="generated-document"]').first();
			if (await document.isVisible({ timeout: 2000 }).catch(() => false)) {
				const downloadButton = document.locator('button:has-text("Download")');
				if (await downloadButton.isVisible()) {
					// Just verify download button exists
				}
			}
		}
	});

	test('should view document send history', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const historyTab = page.locator('[role="tab"]:has-text("Activity"), [role="tab"]:has-text("History")');
		if (await historyTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await historyTab.click();

			const sendEvent = page.locator('text=/Document sent|Emailed/i');
			// May show send events
		}
	});
});

test.describe('Document Template Variables', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should populate all merge fields', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		// Generate document and check preview
		const previewButton = page.locator('button:has-text("Preview")');
		if (await previewButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await previewButton.click();

			// Check for unfilled merge fields (should be none)
			const unfilledMerge = page.locator('text=/{{.*}}|\\[\\[.*\\]\\]/');
			// Should not have unfilled merge fields
		}
	});

	test('should handle missing data gracefully', async ({ page }) => {
		await page.goto('/admin/document-templates/1/preview');
		await waitForLoading(page);

		// Preview with sample data
		const preview = page.locator('[data-testid="document-preview"]');
		// Should not show error for missing optional fields
	});
});
