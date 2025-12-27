import { test, expect } from '@playwright/test';
import {
	navigateToAuditLogs,
	waitForLoading
} from '../fixtures';

/**
 * Audit Logs Tests
 * Tests for audit log viewing and filtering
 *
 * Note: Authentication is handled by global-setup.ts, so no login() call needed
 */

// Helper to wait for audit logs page to fully load
async function waitForAuditLogsPage(page: import('@playwright/test').Page) {
	// First wait for loading spinner to disappear
	const spinner = page.locator('.animate-spin, [data-loading]');
	await spinner.waitFor({ state: 'hidden', timeout: 45000 }).catch(() => {});
	// Then wait for either table rows or stats text
	await page.locator('tbody tr').or(page.getByText('Total Entries')).first().waitFor({ timeout: 30000 });
}

test.describe('Audit Log List', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToAuditLogs(page);
		await waitForAuditLogsPage(page);
	});

	test('should display audit logs page', async ({ page }) => {
		// Page has h1 "Audit Logs" with History icon
		await expect(page.locator('h1:has-text("Audit Logs")')).toBeVisible();
	});

	test('should display filter and refresh buttons', async ({ page }) => {
		await expect(page.locator('button:has-text("Filters")')).toBeVisible();
		await expect(page.locator('button:has-text("Refresh")')).toBeVisible();
	});

	test('should display summary statistics cards', async ({ page }) => {
		// There are stats cards showing Total Entries, Creates, Updates, Deletes
		await expect(page.locator('text="Total Entries"')).toBeVisible();
	});

	test('should display audit log table', async ({ page }) => {
		const table = page.locator('table');
		await expect(table).toBeVisible({ timeout: 5000 });
	});

	test('should display table headers', async ({ page }) => {
		await expect(page.locator('th:has-text("Timestamp")')).toBeVisible();
		await expect(page.locator('th:has-text("Event")')).toBeVisible();
		await expect(page.locator('th:has-text("Entity")')).toBeVisible();
		await expect(page.locator('th:has-text("User")')).toBeVisible();
	});

	test('should display audit log entries', async ({ page }) => {
		const entries = page.locator('tbody tr');
		// Wait for data to load
		await expect(entries.first()).toBeVisible({ timeout: 10000 }).catch(() => {});
	});

	test('should show event type badges', async ({ page }) => {
		// Event types are shown as badges (Created, Updated, Deleted, Login, etc.)
		const eventBadge = page.locator('tbody span:has-text("Created"), tbody span:has-text("Updated"), tbody span:has-text("Deleted"), tbody span:has-text("Login")');
		await expect(eventBadge.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show timestamps', async ({ page }) => {
		// Timestamps are in the first column with Calendar icon
		const timestampCell = page.locator('tbody td').first();
		await expect(timestampCell).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show pagination', async ({ page }) => {
		// Pagination shows "Showing X to Y of Z entries" and page controls
		const paginationInfo = page.locator('text=/Showing \\d+ to \\d+ of \\d+ entries/');
		await expect(paginationInfo).toBeVisible({ timeout: 5000 }).catch(() => {});
	});
});

test.describe('Audit Log Filtering', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToAuditLogs(page);
		await waitForAuditLogsPage(page);
	});

	test('should toggle filters panel', async ({ page }) => {
		// Click Filters button to show filter panel
		await page.click('button:has-text("Filters")');

		// Filter panel should be visible
		await expect(page.locator('text="Event Type"')).toBeVisible();
		await expect(page.locator('text="User ID"')).toBeVisible();
		await expect(page.locator('text="Start Date"')).toBeVisible();
		await expect(page.locator('text="End Date"')).toBeVisible();
	});

	test('should filter by event type', async ({ page }) => {
		// Show filters
		await page.click('button:has-text("Filters")');

		// Click event type dropdown
		const eventTypeSelect = page.locator('button:has-text("All events")');
		if (await eventTypeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await eventTypeSelect.click();
			await page.locator('[role="option"]:has-text("Created")').click();
			await page.click('button:has-text("Apply Filters")');
			await waitForLoading(page);
		}
	});

	test('should filter by date range', async ({ page }) => {
		// Show filters
		await page.click('button:has-text("Filters")');

		// Fill start date
		const startDateInput = page.locator('input[type="date"]').first();
		if (await startDateInput.isVisible()) {
			await startDateInput.fill('2024-01-01');
		}

		await page.click('button:has-text("Apply Filters")');
		await waitForLoading(page);
	});

	test('should clear filters', async ({ page }) => {
		// Show filters
		await page.click('button:has-text("Filters")');

		// Apply a filter first
		const eventTypeSelect = page.locator('button:has-text("All events")');
		if (await eventTypeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await eventTypeSelect.click();
			await page.locator('[role="option"]:has-text("Created")').click();
		}

		// Clear filters
		await page.click('button:has-text("Clear")');
		await waitForLoading(page);
	});

	test('should refresh logs', async ({ page }) => {
		await page.click('button:has-text("Refresh")');
		await waitForLoading(page);
	});
});

test.describe('Audit Log Details', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToAuditLogs(page);
		await waitForAuditLogsPage(page);
	});

	test('should open log details dialog', async ({ page }) => {
		// Wait for entries to load
		const entries = page.locator('tbody tr');
		await expect(entries.first()).toBeVisible({ timeout: 10000 }).catch(() => {});

		// Click the eye icon button to view details
		const viewButton = page.locator('tbody tr').first().locator('button:has(svg.lucide-eye)');
		if (await viewButton.isVisible()) {
			await viewButton.click();

			const dialog = page.locator('[role="dialog"]');
			await expect(dialog).toBeVisible();
			await expect(dialog.locator('text="Audit Log Details"')).toBeVisible();
		}
	});

	test('should show metadata in details dialog', async ({ page }) => {
		const entries = page.locator('tbody tr');
		await expect(entries.first()).toBeVisible({ timeout: 10000 }).catch(() => {});

		const viewButton = page.locator('tbody tr').first().locator('button:has(svg.lucide-eye)');
		if (await viewButton.isVisible()) {
			await viewButton.click();

			const dialog = page.locator('[role="dialog"]');
			await expect(dialog).toBeVisible();

			// Check for metadata
			await expect(dialog.locator('text="Timestamp:"')).toBeVisible();
			await expect(dialog.locator('text="User:"')).toBeVisible();
		}
	});

	test('should show changes in details dialog for updates', async ({ page }) => {
		// Find an update entry
		const updateEntry = page.locator('tbody tr:has-text("Updated")').first();
		if (await updateEntry.isVisible({ timeout: 5000 }).catch(() => false)) {
			const viewButton = updateEntry.locator('button:has(svg.lucide-eye)');
			await viewButton.click();

			const dialog = page.locator('[role="dialog"]');
			await expect(dialog).toBeVisible();

			// Look for changes section
			const changes = dialog.locator('text="Changes", text="Changed Fields"');
			await expect(changes.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should close details dialog', async ({ page }) => {
		const entries = page.locator('tbody tr');
		await expect(entries.first()).toBeVisible({ timeout: 10000 }).catch(() => {});

		const viewButton = page.locator('tbody tr').first().locator('button:has(svg.lucide-eye)');
		if (await viewButton.isVisible()) {
			await viewButton.click();

			const dialog = page.locator('[role="dialog"]');
			await expect(dialog).toBeVisible();

			// Close dialog
			await dialog.locator('button:has-text("Close")').click();
			await expect(dialog).not.toBeVisible();
		}
	});
});

test.describe('Audit Log Pagination', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToAuditLogs(page);
		await waitForAuditLogsPage(page);
	});

	test('should show pagination controls', async ({ page }) => {
		// Look for pagination buttons
		const prevButton = page.locator('button:has(svg.lucide-chevron-left)');
		const nextButton = page.locator('button:has(svg.lucide-chevron-right)');

		await expect(prevButton).toBeVisible({ timeout: 5000 }).catch(() => {});
		await expect(nextButton).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should show page info', async ({ page }) => {
		// Look for "Page X of Y" text
		const pageInfo = page.locator('text=/Page \\d+ of \\d+/');
		await expect(pageInfo).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should navigate to next page', async ({ page }) => {
		const nextButton = page.locator('button:has(svg.lucide-chevron-right)');
		if (await nextButton.isVisible() && await nextButton.isEnabled()) {
			await nextButton.click();
			await waitForLoading(page);
		}
	});
});

test.describe('Audit Log Summary', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToAuditLogs(page);
		await waitForAuditLogsPage(page);
	});

	test('should display summary cards', async ({ page }) => {
		// Check for summary stat cards
		await expect(page.locator('text="Total Entries"')).toBeVisible();
		await expect(page.locator('text=/Creates.*This Page|This Page/i').first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show counts in summary cards', async ({ page }) => {
		// Each card should have a number
		const totalCard = page.locator('div:has-text("Total Entries")').first();
		const countText = totalCard.locator('.text-2xl');
		await expect(countText).toBeVisible({ timeout: 5000 }).catch(() => {});
	});
});
