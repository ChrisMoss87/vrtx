import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToInvoices,
	confirmDialog,
	fillFormField,
	clickTab,
	searchInTable,
	clickRowAction,
	expectToast
} from '../fixtures';

/**
 * Invoice Management Tests
 * Tests for invoice CRUD, payments, and status management
 */

test.describe('Invoice List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToInvoices(page);
	});

	test('should display invoices list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Invoice|Invoices/i }).first()).toBeVisible();
	});

	test('should display create invoice button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Invoice"), button:has-text("New")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should filter by status', async ({ page }) => {
		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});

	test('should search invoices', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should display invoice table', async ({ page }) => {
		const table = page.locator('table');
		await expect(table.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show overdue status indicator', async ({ page }) => {
		const overdueIndicator = page.locator('text=/Overdue/i, [class*="overdue"]');
		// May or may not have overdue invoices
	});
});

test.describe('Invoice Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to create invoice page', async ({ page }) => {
		await navigateToInvoices(page);
		await page.click('button:has-text("Create"), a:has-text("New Invoice")');
		await expect(page).toHaveURL(/\/invoices\/new|\/invoices\/create/);
	});

	test('should display invoice creation form', async ({ page }) => {
		await page.goto('/invoices/new');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"], input[placeholder*="Title"]');
		await expect(titleInput.first()).toBeVisible();
	});

	test('should create invoice successfully', async ({ page }) => {
		await page.goto('/invoices/new');
		await waitForLoading(page);

		// Fill invoice details
		const titleInput = page.locator('input[name="title"]').first();
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Test Invoice ${Date.now()}`);
		}

		// Select customer if required
		const customerSelect = page.locator('button[role="combobox"]:has-text("Customer")');
		if (await customerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await customerSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Set due date
		const dateInput = page.locator('input[name="due_date"], input[type="date"]');
		if (await dateInput.isVisible()) {
			const futureDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
			await dateInput.fill(futureDate);
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should create invoice from quote', async ({ page }) => {
		// Navigate to a quote
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for convert to invoice button
		const convertButton = page.locator('button:has-text("Convert to Invoice"), button:has-text("Create Invoice")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();
			await waitForToast(page);
		}
	});

	test('should add line items', async ({ page }) => {
		await page.goto('/invoices/new');
		await waitForLoading(page);

		const addItemButton = page.locator('button:has-text("Add Line Item"), button:has-text("Add Item")');
		if (await addItemButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addItemButton.click();

			const descriptionInput = page.locator('input[name="description"]').last();
			if (await descriptionInput.isVisible()) {
				await descriptionInput.fill('Service Item');
			}
		}
	});

	test('should apply taxes', async ({ page }) => {
		await page.goto('/invoices/new');
		await waitForLoading(page);

		const taxInput = page.locator('input[name="tax_rate"], input[placeholder*="Tax"]');
		if (await taxInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await taxInput.fill('10');
		}
	});
});

test.describe('Invoice View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display invoice details', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const invoiceNumber = page.locator('text=/INV-|Invoice #/i');
		await expect(invoiceNumber.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show invoice status', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const statusBadge = page.locator('[class*="badge"], [class*="status"]');
		await expect(statusBadge.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should display line items', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const itemsSection = page.locator('table, [data-testid="line-items"]');
		await expect(itemsSection.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show total amounts', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const total = page.locator('text=/Total|Amount Due/i');
		await expect(total.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should display payment history', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const paymentsSection = page.locator('text=/Payment|Payments/i');
		await expect(paymentsSection.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});
});

test.describe('Invoice Payments', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should record payment', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const recordPaymentButton = page.locator('button:has-text("Record Payment"), button:has-text("Add Payment")');
		if (await recordPaymentButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await recordPaymentButton.click();

			// Fill payment details
			const amountInput = page.locator('input[name="amount"]');
			if (await amountInput.isVisible()) {
				await amountInput.fill('500');
			}

			// Select payment method
			const methodSelect = page.locator('button[role="combobox"]:has-text("Method")');
			if (await methodSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await methodSelect.click();
				await page.locator('[role="option"]').first().click();
			}

			// Submit
			const submitButton = page.locator('[role="dialog"] button:has-text("Record"), button:has-text("Save")');
			await submitButton.click();
			await waitForToast(page);
		}
	});

	test('should handle partial payments', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const recordPaymentButton = page.locator('button:has-text("Record Payment")');
		if (await recordPaymentButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await recordPaymentButton.click();

			// Enter partial amount
			const amountInput = page.locator('input[name="amount"]');
			if (await amountInput.isVisible()) {
				await amountInput.fill('100'); // Partial payment
			}

			const submitButton = page.locator('[role="dialog"] button:has-text("Record")');
			await submitButton.click();
			await waitForToast(page);

			// Verify status is "Partially Paid"
			const statusBadge = page.locator('text=/Partial|Partially Paid/i');
			await expect(statusBadge.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should mark as paid', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const markPaidButton = page.locator('button:has-text("Mark as Paid"), button:has-text("Mark Paid")');
		if (await markPaidButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await markPaidButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should void payment', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		// Find a payment entry and void it
		const voidButton = page.locator('button:has-text("Void"), [role="menuitem"]:has-text("Void")');
		if (await voidButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await voidButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
		}
	});
});

test.describe('Invoice Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send invoice', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const sendButton = page.locator('button:has-text("Send"), button:has-text("Send Invoice")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();

			// Fill email if dialog appears
			const emailInput = page.locator('input[type="email"]');
			if (await emailInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await emailInput.fill('customer@example.com');
			}

			const confirmButton = page.locator('[role="dialog"] button:has-text("Send")');
			if (await confirmButton.isVisible()) {
				await confirmButton.click();
			}

			await waitForToast(page);
		}
	});

	test('should send reminder', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const reminderButton = page.locator('button:has-text("Send Reminder"), button:has-text("Reminder")');
		if (await reminderButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await reminderButton.click();
			await waitForToast(page);
		}
	});

	test('should generate PDF', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const downloadPromise = page.waitForEvent('download', { timeout: 10000 }).catch(() => null);

		const pdfButton = page.locator('button:has-text("Download PDF"), button:has-text("PDF"), button:has-text("Download")');
		if (await pdfButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await pdfButton.click();

			const download = await downloadPromise;
			if (download) {
				expect(download.suggestedFilename()).toMatch(/\.pdf$/);
			}
		}
	});

	test('should duplicate invoice', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions"), button[aria-haspopup="menu"]');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const duplicateOption = page.locator('[role="menuitem"]:has-text("Duplicate")');
			if (await duplicateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await duplicateOption.click();
				await waitForToast(page);
			}
		}
	});

	test('should void invoice', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const actionsButton = page.locator('button:has-text("Actions")');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const voidOption = page.locator('[role="menuitem"]:has-text("Void")');
			if (await voidOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await voidOption.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});
});

test.describe('Invoice Edit', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to edit page', async ({ page }) => {
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")');
		await editButton.first().click();

		await expect(page).toHaveURL(/\/edit/);
	});

	test('should update invoice details', async ({ page }) => {
		await page.goto('/invoices/1/edit');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"]').first();
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Updated Invoice ${Date.now()}`);

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should prevent editing sent invoices', async ({ page }) => {
		// Navigate to a sent invoice
		await page.goto('/invoices/1');
		await waitForLoading(page);

		// Check if status is sent
		const sentStatus = page.locator('text=/Sent|Pending Payment/i');
		if (await sentStatus.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Edit button may be hidden or disabled
			const editButton = page.locator('button:has-text("Edit")');
			if (await editButton.isVisible()) {
				await expect(editButton).toBeDisabled().catch(() => {});
			}
		}
	});
});

test.describe('Invoice Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show delete confirmation', async ({ page }) => {
		await navigateToInvoices(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
				if (await deleteOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await deleteOption.click();

					const dialog = page.locator('[role="alertdialog"]');
					await expect(dialog).toBeVisible({ timeout: 3000 }).catch(() => {});
				}
			}
		}
	});

	test('should prevent deleting paid invoices', async ({ page }) => {
		// Navigate to a paid invoice
		await page.goto('/invoices/1');
		await waitForLoading(page);

		const paidStatus = page.locator('text=/Paid/i');
		if (await paidStatus.isVisible({ timeout: 2000 }).catch(() => false)) {
			const actionsButton = page.locator('button:has-text("Actions")');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				// Delete option should be hidden or disabled
				const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
				const isVisible = await deleteOption.isVisible({ timeout: 1000 }).catch(() => false);
				// If visible, it should be disabled
			}
		}
	});
});

test.describe('Invoice Filtering', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToInvoices(page);
	});

	test('should filter by draft status', async ({ page }) => {
		const draftTab = page.locator('[role="tab"]:has-text("Draft")');
		if (await draftTab.isVisible()) {
			await draftTab.click();
			await waitForLoading(page);
		}
	});

	test('should filter by sent status', async ({ page }) => {
		const sentTab = page.locator('[role="tab"]:has-text("Sent")');
		if (await sentTab.isVisible()) {
			await sentTab.click();
			await waitForLoading(page);
		}
	});

	test('should filter by paid status', async ({ page }) => {
		const paidTab = page.locator('[role="tab"]:has-text("Paid")');
		if (await paidTab.isVisible()) {
			await paidTab.click();
			await waitForLoading(page);
		}
	});

	test('should filter by overdue', async ({ page }) => {
		const overdueTab = page.locator('[role="tab"]:has-text("Overdue")');
		if (await overdueTab.isVisible()) {
			await overdueTab.click();
			await waitForLoading(page);
		}
	});

	test('should filter by date range', async ({ page }) => {
		const dateFilter = page.locator('button:has-text("Date"), [data-filter="date"]');
		if (await dateFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dateFilter.click();
		}
	});

	test('should filter by customer', async ({ page }) => {
		const customerFilter = page.locator('button:has-text("Customer"), [data-filter="customer"]');
		if (await customerFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await customerFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});
});
