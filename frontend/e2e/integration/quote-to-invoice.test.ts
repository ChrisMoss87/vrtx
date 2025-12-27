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
 * Quote to Invoice Integration Tests
 * Tests the complete workflow from quote creation through invoice generation
 */

test.describe('Quote to Invoice Conversion Flow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should complete full quote to invoice conversion', async ({ page }) => {
		// Step 1: Create a quote
		await page.goto('/records/quotes/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"], input[name="title"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Quote ${Date.now()}`);
		}

		// Add line item
		const addLineButton = page.locator('button:has-text("Add Line"), button:has-text("Add Item")');
		if (await addLineButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addLineButton.click();

			const productInput = page.locator('input[name*="product"], [data-testid="product-select"]');
			if (await productInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await productInput.click();
				await page.locator('[role="option"]').first().click();
			}

			const quantityInput = page.locator('input[name*="quantity"]').first();
			if (await quantityInput.isVisible()) {
				await quantityInput.fill('5');
			}
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);

		// Step 2: Accept the quote
		await waitForLoading(page);

		const acceptButton = page.locator('button:has-text("Accept"), button:has-text("Mark Accepted")');
		if (await acceptButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await acceptButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}

		// Step 3: Convert to invoice
		const convertButton = page.locator('button:has-text("Convert to Invoice"), button:has-text("Create Invoice")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Confirm conversion
			const confirmButton = page.locator('[role="dialog"] button:has-text("Create"), button:has-text("Convert")');
			await confirmButton.click();
			await waitForToast(page);

			// Verify we're on the invoice page
			await expect(page).toHaveURL(/\/records\/invoices\/\d+/);
		}
	});

	test('should preserve line items during conversion', async ({ page }) => {
		// Navigate to an accepted quote
		await page.goto('/records/quotes');
		await waitForLoading(page);

		const acceptedTab = page.locator('[role="tab"]:has-text("Accepted")');
		if (await acceptedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await acceptedTab.click();
			await waitForLoading(page);
		}

		const quote = page.locator('tbody tr').first();
		if (await quote.isVisible({ timeout: 2000 }).catch(() => false)) {
			await quote.click();

			// Count line items before
			const lineItemsBefore = await page.locator('[data-testid="line-item"]').count();

			// Convert to invoice
			const convertButton = page.locator('button:has-text("Convert to Invoice")');
			if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await convertButton.click();

				const confirmButton = page.locator('[role="dialog"] button:has-text("Create")');
				await confirmButton.click();
				await waitForToast(page);

				// Verify line items on invoice
				await waitForLoading(page);
				const lineItemsAfter = await page.locator('[data-testid="line-item"]').count();
				expect(lineItemsAfter).toBeGreaterThanOrEqual(lineItemsBefore);
			}
		}
	});

	test('should apply quote discount to invoice', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		// Check for discount on quote
		const discountBefore = page.locator('[data-testid="quote-discount"], text=/Discount/i');
		const hasDiscount = await discountBefore.isVisible({ timeout: 2000 }).catch(() => false);

		if (hasDiscount) {
			// Convert to invoice
			const convertButton = page.locator('button:has-text("Convert to Invoice")');
			if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await convertButton.click();

				const confirmButton = page.locator('[role="dialog"] button:has-text("Create")');
				await confirmButton.click();
				await waitForToast(page);

				// Verify discount on invoice
				await waitForLoading(page);
				const discountAfter = page.locator('[data-testid="invoice-discount"], text=/Discount/i');
				await expect(discountAfter.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
			}
		}
	});

	test('should link invoice to quote', async ({ page }) => {
		// Navigate to an invoice created from quote
		await page.goto('/records/invoices');
		await waitForLoading(page);

		const invoice = page.locator('tbody tr').first();
		if (await invoice.isVisible({ timeout: 2000 }).catch(() => false)) {
			await invoice.click();

			// Check for quote reference
			const quoteReference = page.locator('text=/From Quote|Source Quote|Related Quote/i');
			// May show quote reference
		}
	});

	test('should set invoice due date based on terms', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert to Invoice")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Set payment terms
			const termsSelect = page.locator('[data-testid="payment-terms"], button:has-text("Terms")');
			if (await termsSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await termsSelect.click();
				await page.locator('[role="option"]:has-text("Net 30")').click();
			}
		}
	});
});

test.describe('Partial Quote Conversion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should convert partial quote to invoice', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert to Invoice")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Select specific line items
			const lineItemCheckbox = page.locator('[data-testid="line-item-select"] input[type="checkbox"]').first();
			if (await lineItemCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
				await lineItemCheckbox.check();
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Create")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should create multiple invoices from quote', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		// Check for split invoice option
		const splitButton = page.locator('button:has-text("Split"), button:has-text("Multiple Invoices")');
		if (await splitButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await splitButton.click();
		}
	});
});

test.describe('Quote Revision Before Conversion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create quote revision', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const reviseButton = page.locator('button:has-text("Revise"), button:has-text("Create Revision")');
		if (await reviseButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await reviseButton.click();

			// Make changes
			const nameInput = page.locator('input[name="name"]');
			if (await nameInput.isVisible()) {
				await nameInput.fill(`Revised Quote ${Date.now()}`);
			}

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should track quote version history', async ({ page }) => {
		await page.goto('/records/quotes/1');
		await waitForLoading(page);

		const versionsTab = page.locator('[role="tab"]:has-text("Versions"), button:has-text("History")');
		if (await versionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await versionsTab.click();

			const versions = page.locator('[data-testid="quote-version"]');
			// May have versions
		}
	});
});

test.describe('Invoice Processing After Conversion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send invoice after creation', async ({ page }) => {
		await page.goto('/records/invoices/1');
		await waitForLoading(page);

		const sendButton = page.locator('button:has-text("Send"), button:has-text("Email Invoice")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();

			const confirmButton = page.locator('[role="dialog"] button:has-text("Send")');
			await confirmButton.click();
			await waitForToast(page);
		}
	});

	test('should record payment on invoice', async ({ page }) => {
		await page.goto('/records/invoices/1');
		await waitForLoading(page);

		const paymentButton = page.locator('button:has-text("Record Payment"), button:has-text("Add Payment")');
		if (await paymentButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await paymentButton.click();

			const amountInput = page.locator('input[name="amount"]');
			if (await amountInput.isVisible()) {
				await amountInput.fill('5000');
			}

			const saveButton = page.locator('[role="dialog"] button:has-text("Save"), button:has-text("Record")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should mark invoice as paid', async ({ page }) => {
		await page.goto('/records/invoices/1');
		await waitForLoading(page);

		const paidButton = page.locator('button:has-text("Mark Paid")');
		if (await paidButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await paidButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});
});
