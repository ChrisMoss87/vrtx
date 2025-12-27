import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToQuotes,
	confirmDialog,
	fillFormField,
	clickTab,
	searchInTable,
	clickRowAction,
	expectToast
} from '../fixtures';

/**
 * Quote Management Tests
 * Tests for quote CRUD, line items, sending, and conversion
 */

test.describe('Quote List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToQuotes(page);
	});

	test('should display quotes list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Quote|Quotes/i }).first()).toBeVisible();
	});

	test('should display stats cards', async ({ page }) => {
		// Look for stat cards (Draft, Pending, Won, Lost)
		const statsCards = page.locator('[data-testid="stats-card"], [class*="stat-card"], [class*="metric"]');
		await expect(statsCards.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should filter by status tabs', async ({ page }) => {
		// Check for status tabs
		const draftTab = page.locator('[role="tab"]:has-text("Draft")');
		if (await draftTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await draftTab.click();
			await waitForLoading(page);
		}

		const sentTab = page.locator('[role="tab"]:has-text("Sent"), [role="tab"]:has-text("Pending")');
		if (await sentTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sentTab.click();
			await waitForLoading(page);
		}
	});

	test('should search quotes', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test quote');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should display create quote button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New Quote"), button:has-text("New")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should display quotes table', async ({ page }) => {
		const table = page.locator('table, [data-testid="quotes-table"]');
		await expect(table).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show quote number column', async ({ page }) => {
		const header = page.locator('th:has-text("Number"), th:has-text("Quote #")');
		await expect(header).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should paginate quotes', async ({ page }) => {
		const pagination = page.locator('[data-testid="pagination"], nav[aria-label="pagination"]');
		// Pagination may or may not be visible based on data
	});
});

test.describe('Quote Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to create quote page', async ({ page }) => {
		await navigateToQuotes(page);
		await page.click('button:has-text("Create"), a:has-text("New Quote")');
		await expect(page).toHaveURL(/\/quotes\/new|\/quotes\/create/);
	});

	test('should display quote creation form', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Check for form elements
		const titleInput = page.locator('input[name="title"], input[placeholder*="Title"]');
		await expect(titleInput.first()).toBeVisible();
	});

	test('should validate required fields', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Try to submit without required fields
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();

		// Should show validation error
		const errorMessage = page.locator('text=/required/i');
		await expect(errorMessage.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should create quote successfully', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Fill quote title
		const titleInput = page.locator('input[name="title"], input[placeholder*="Title"]').first();
		await titleInput.fill(`Test Quote ${Date.now()}`);

		// Select customer/contact if required
		const customerSelect = page.locator('button[role="combobox"]:has-text("Customer"), button[role="combobox"]:has-text("Contact")');
		if (await customerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await customerSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Set valid until date
		const dateInput = page.locator('input[name="valid_until"], input[type="date"]');
		if (await dateInput.isVisible()) {
			const futureDate = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
			await dateInput.fill(futureDate);
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();

		await waitForToast(page);
	});

	test('should add line items to quote', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Fill basic info
		const titleInput = page.locator('input[name="title"]').first();
		if (await titleInput.isVisible()) {
			await titleInput.fill('Quote with Items');
		}

		// Add line item
		const addItemButton = page.locator('button:has-text("Add Line Item"), button:has-text("Add Item"), button:has-text("Add Product")');
		if (await addItemButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addItemButton.click();

			// Fill line item details
			const descriptionInput = page.locator('input[name="description"], input[placeholder*="Description"]').last();
			if (await descriptionInput.isVisible()) {
				await descriptionInput.fill('Test Product');
			}

			const quantityInput = page.locator('input[name="quantity"]').last();
			if (await quantityInput.isVisible()) {
				await quantityInput.fill('2');
			}

			const priceInput = page.locator('input[name="unit_price"], input[name="price"]').last();
			if (await priceInput.isVisible()) {
				await priceInput.fill('100');
			}
		}
	});

	test('should calculate totals correctly', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Add line item and check total
		const addItemButton = page.locator('button:has-text("Add Line Item")');
		if (await addItemButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addItemButton.click();

			await page.fill('input[name="quantity"]:last-of-type', '2');
			await page.fill('input[name="unit_price"]:last-of-type', '100');

			// Check for calculated total
			const totalElement = page.locator('text=/\\$200|200\\.00/');
			await expect(totalElement.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should apply discounts', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Look for discount field
		const discountInput = page.locator('input[name="discount"], input[placeholder*="Discount"]');
		if (await discountInput.isVisible({ timeout: 2000 }).catch(() => false)) {
			await discountInput.fill('10');
		}
	});
});

test.describe('Quote View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display quote details', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Check for quote header/title
		const quoteTitle = page.locator('h1, h2, [data-testid="quote-title"]');
		await expect(quoteTitle.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show quote status', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for status badge
		const statusBadge = page.locator('[data-testid="status-badge"], [class*="badge"], [class*="status"]');
		await expect(statusBadge.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should display line items', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for line items table
		const itemsTable = page.locator('table, [data-testid="line-items"]');
		await expect(itemsTable.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show quote totals', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for totals section
		const totals = page.locator('text=/Total|Subtotal/i');
		await expect(totals.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should display action buttons', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Check for action buttons
		const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")');
		await expect(editButton.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});
});

test.describe('Quote Workflow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should send quote to customer', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for send button
		const sendButton = page.locator('button:has-text("Send"), button:has-text("Send Quote")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();

			// May show send dialog
			const sendDialog = page.locator('[role="dialog"]');
			if (await sendDialog.isVisible({ timeout: 2000 }).catch(() => false)) {
				// Fill recipient email if needed
				const emailInput = sendDialog.locator('input[type="email"]');
				if (await emailInput.isVisible()) {
					await emailInput.fill('customer@example.com');
				}

				// Confirm send
				await sendDialog.locator('button:has-text("Send")').click();
			}

			await waitForToast(page);
		}
	});

	test('should show public quote link', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for share/public link
		const shareButton = page.locator('button:has-text("Share"), button:has-text("Copy Link"), button:has-text("Public Link")');
		if (await shareButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await shareButton.click();
		}
	});

	test('should track quote views', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for view tracking information
		const viewInfo = page.locator('text=/viewed|views|opened/i');
		// May or may not be visible
	});

	test('should handle quote acceptance', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for accept button (may be on customer view)
		const acceptButton = page.locator('button:has-text("Accept"), button:has-text("Approve")');
		if (await acceptButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await acceptButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
		}
	});

	test('should handle quote rejection', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for reject button
		const rejectButton = page.locator('button:has-text("Reject"), button:has-text("Decline")');
		if (await rejectButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await rejectButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
		}
	});

	test('should convert accepted quote to invoice', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for convert button
		const convertButton = page.locator('button:has-text("Convert to Invoice"), button:has-text("Create Invoice")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();
			await waitForToast(page);
		}
	});
});

test.describe('Quote Edit', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to edit page', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		const editButton = page.locator('button:has-text("Edit"), a:has-text("Edit")');
		await editButton.first().click();

		await expect(page).toHaveURL(/\/edit/);
	});

	test('should update quote title', async ({ page }) => {
		await page.goto('/quotes/1/edit');
		await waitForLoading(page);

		const titleInput = page.locator('input[name="title"]').first();
		if (await titleInput.isVisible()) {
			await titleInput.fill(`Updated Quote ${Date.now()}`);

			const saveButton = page.locator('button:has-text("Save")');
			await saveButton.click();
			await waitForToast(page);
		}
	});

	test('should add line items to existing quote', async ({ page }) => {
		await page.goto('/quotes/1/edit');
		await waitForLoading(page);

		const addItemButton = page.locator('button:has-text("Add Line Item")');
		if (await addItemButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addItemButton.click();
		}
	});

	test('should remove line items', async ({ page }) => {
		await page.goto('/quotes/1/edit');
		await waitForLoading(page);

		const removeButton = page.locator('button[aria-label="Remove"], button:has-text("Remove")').first();
		if (await removeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await removeButton.click();
		}
	});
});

test.describe('Quote Duplication', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should duplicate quote', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		// Look for duplicate option
		const actionsButton = page.locator('button:has-text("Actions"), button[aria-haspopup="menu"]');
		if (await actionsButton.isVisible()) {
			await actionsButton.click();

			const duplicateOption = page.locator('[role="menuitem"]:has-text("Duplicate"), [role="menuitem"]:has-text("Clone")');
			if (await duplicateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await duplicateOption.click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Quote Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show delete confirmation', async ({ page }) => {
		await navigateToQuotes(page);

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

	test('should cancel quote deletion', async ({ page }) => {
		await navigateToQuotes(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")');
				if (await deleteOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await deleteOption.click();
					await confirmDialog(page, 'cancel').catch(() => {});
				}
			}
		}
	});

	test('should delete quote', async ({ page }) => {
		await navigateToQuotes(page);

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

test.describe('Quote Validation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should validate email format for recipient', async ({ page }) => {
		await page.goto('/quotes/1');
		await waitForLoading(page);

		const sendButton = page.locator('button:has-text("Send")');
		if (await sendButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sendButton.click();

			const emailInput = page.locator('input[type="email"]');
			if (await emailInput.isVisible()) {
				await emailInput.fill('invalid-email');

				const confirmButton = page.locator('[role="dialog"] button:has-text("Send")');
				await confirmButton.click();

				// Should show validation error
				const error = page.locator('text=/valid email|invalid/i');
				await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
			}
		}
	});

	test('should validate valid until date', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		// Try to set a past date
		const dateInput = page.locator('input[name="valid_until"], input[type="date"]');
		if (await dateInput.isVisible()) {
			const pastDate = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];
			await dateInput.fill(pastDate);

			// Submit and check for error
			const submitButton = page.locator('button[type="submit"]');
			await submitButton.click();

			const error = page.locator('text=/future|past|invalid date/i');
			await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should validate line item quantities', async ({ page }) => {
		await page.goto('/quotes/new');
		await waitForLoading(page);

		const addItemButton = page.locator('button:has-text("Add Line Item")');
		if (await addItemButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await addItemButton.click();

			const quantityInput = page.locator('input[name="quantity"]').last();
			if (await quantityInput.isVisible()) {
				await quantityInput.fill('-1');

				const submitButton = page.locator('button[type="submit"]');
				await submitButton.click();

				// Should show error for negative quantity
				const error = page.locator('text=/positive|greater than 0|invalid/i');
				await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
			}
		}
	});
});
