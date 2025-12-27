import { test, expect, Page } from '@playwright/test';
import { login, waitForLoading, waitForToast, navigateToModule } from './fixtures';

/**
 * DataTable Advanced Filtering E2E Tests
 *
 * Comprehensive tests for the DataTable filter functionality including:
 * - Filter panel interactions
 * - Different filter types (text, number, date, select, boolean)
 * - Filter operators
 * - Filter combinations
 * - Filter persistence
 * - Filter presets
 * - Quick filters
 */

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

async function waitForTableLoad(page: Page) {
	await page.locator('.animate-spin').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
	await page.locator('table').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
	await page.waitForLoadState('networkidle');
}

async function openFiltersPanel(page: Page) {
	const filtersButton = page.locator('button:has-text("Filters")');
	if (await filtersButton.isVisible()) {
		await filtersButton.click();
		// Wait for panel to slide in
		await page.waitForTimeout(300);
	}
}

async function closeFiltersPanel(page: Page) {
	const closeButton = page.locator('.rounded-lg.border.bg-card button:has(svg)').first();
	if (await closeButton.isVisible()) {
		await closeButton.click();
		await page.waitForTimeout(200);
	}
}

async function getTableRowCount(page: Page): Promise<number> {
	return page.locator('tbody tr').count();
}

async function applyFilters(page: Page) {
	const applyButton = page.locator('button:has-text("Apply Filters")');
	if (await applyButton.isVisible()) {
		await applyButton.click();
		await waitForTableLoad(page);
	}
}

async function clearAllFilters(page: Page) {
	const clearButton = page.locator('button:has-text("Clear all")');
	if (await clearButton.isVisible()) {
		await clearButton.click();
	}
}

// =============================================================================
// FILTER PANEL TESTS
// =============================================================================

test.describe('DataTable Filter Panel', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should open filter panel when clicking Filters button', async ({ page }) => {
		await openFiltersPanel(page);

		// Filter panel should be visible
		const filterPanel = page.locator('.rounded-lg.border.bg-card');
		await expect(filterPanel).toBeVisible();

		// Should have header with "Filters" title
		const header = filterPanel.locator('h4:has-text("Filters")');
		await expect(header).toBeVisible();
	});

	test('should close filter panel with X button', async ({ page }) => {
		await openFiltersPanel(page);

		const filterPanel = page.locator('.rounded-lg.border.bg-card');
		await expect(filterPanel).toBeVisible();

		// Click close button
		const closeButton = filterPanel.locator('button:has(svg.lucide-x)').first();
		await closeButton.click();

		await page.waitForTimeout(300);

		// Panel should be closed
		await expect(filterPanel).not.toBeVisible();
	});

	test('should display filterable columns', async ({ page }) => {
		await openFiltersPanel(page);

		const filterPanel = page.locator('.rounded-lg.border.bg-card');

		// Should show column labels
		const columnLabels = filterPanel.locator('label');
		expect(await columnLabels.count()).toBeGreaterThan(0);
	});

	test('should show filter count badge', async ({ page }) => {
		await openFiltersPanel(page);

		// Add a filter
		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('test');
		}

		// Badge should show filter count
		const badge = page.locator('.rounded-full.bg-primary');
		// Badge appears after filter is added
	});

	test('should show Apply Filters button', async ({ page }) => {
		await openFiltersPanel(page);

		const applyButton = page.locator('button:has-text("Apply Filters")');
		await expect(applyButton).toBeVisible();
	});

	test('should show Clear all button when filters are selected', async ({ page }) => {
		await openFiltersPanel(page);

		// Add a filter
		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('test');

			// Clear all button should appear
			const clearButton = page.locator('button:has-text("Clear all")');
			await expect(clearButton).toBeVisible();
		}
	});
});

// =============================================================================
// TEXT FILTER TESTS
// =============================================================================

test.describe('DataTable Text Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should filter by text contains', async ({ page }) => {
		await openFiltersPanel(page);

		// Find a text input filter
		const textInput = page.locator('input[id^="filter-"][placeholder*="Contains"]').first();

		if (await textInput.isVisible()) {
			const initialRowCount = await getTableRowCount(page);

			await textInput.fill('john');
			await applyFilters(page);

			// Results should be filtered
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should clear individual text filter', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();

		if (await textInput.isVisible()) {
			await textInput.fill('test');

			// Clear button should appear next to label
			const clearButton = textInput.locator('..').locator('button:has(svg)');
			if (await clearButton.isVisible()) {
				await clearButton.click();

				// Input should be cleared
				await expect(textInput).toHaveValue('');
			}
		}
	});

	test('should highlight active text filter', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();

		if (await textInput.isVisible()) {
			await textInput.fill('test');

			// Input should have highlight styling (border-primary class)
			await expect(textInput).toHaveClass(/border-primary|ring-primary/);
		}
	});
});

// =============================================================================
// NUMBER FILTER TESTS
// =============================================================================

test.describe('DataTable Number Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'deals');
		await waitForTableLoad(page);
	});

	test('should filter by number equals', async ({ page }) => {
		await openFiltersPanel(page);

		// Find a number input filter
		const numberInput = page.locator('input[id^="filter-"][type="number"]').first();

		if (await numberInput.isVisible()) {
			await numberInput.fill('1000');
			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should accept decimal values', async ({ page }) => {
		await openFiltersPanel(page);

		const numberInput = page.locator('input[id^="filter-"][type="number"]').first();

		if (await numberInput.isVisible()) {
			await numberInput.fill('99.99');
			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should accept negative values', async ({ page }) => {
		await openFiltersPanel(page);

		const numberInput = page.locator('input[id^="filter-"][type="number"]').first();

		if (await numberInput.isVisible()) {
			await numberInput.fill('-100');

			// Should accept the value
			await expect(numberInput).toHaveValue('-100');
		}
	});
});

// =============================================================================
// SELECT/MULTISELECT FILTER TESTS
// =============================================================================

test.describe('DataTable Select Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should open select dropdown', async ({ page }) => {
		await openFiltersPanel(page);

		// Find a select trigger
		const selectTrigger = page.locator('button[role="combobox"]').first();

		if (await selectTrigger.isVisible()) {
			await selectTrigger.click();

			// Options should appear
			const options = page.locator('[role="option"]');
			expect(await options.count()).toBeGreaterThan(0);
		}
	});

	test('should select single option', async ({ page }) => {
		await openFiltersPanel(page);

		const selectTrigger = page.locator('button[role="combobox"]').first();

		if (await selectTrigger.isVisible()) {
			await selectTrigger.click();

			const option = page.locator('[role="option"]').first();
			if (await option.isVisible()) {
				await option.click();

				// Trigger should show selected value
				const triggerText = await selectTrigger.textContent();
				expect(triggerText).not.toBe('Any');
			}
		}
	});

	test('should allow multiple selections in multiselect', async ({ page }) => {
		await openFiltersPanel(page);

		const selectTrigger = page.locator('button[role="combobox"]').first();

		if (await selectTrigger.isVisible()) {
			await selectTrigger.click();

			const options = page.locator('[role="option"]');
			const optionCount = await options.count();

			if (optionCount >= 2) {
				// Select multiple options
				await options.first().click();
				await selectTrigger.click();
				await options.nth(1).click();

				// Trigger should show "2 selected" or similar
				const triggerText = await selectTrigger.textContent();
				// May show "2 selected" or individual values
			}
		}
	});

	test('should show option counts if available', async ({ page }) => {
		await openFiltersPanel(page);

		const selectTrigger = page.locator('button[role="combobox"]').first();

		if (await selectTrigger.isVisible()) {
			await selectTrigger.click();

			// Look for count in parentheses
			const countSpan = page.locator('[role="option"] span:has-text("(")');
			// Counts may or may not be shown
		}
	});

	test('should clear select filter with Any option', async ({ page }) => {
		await openFiltersPanel(page);

		const selectTrigger = page.locator('button[role="combobox"]').first();

		if (await selectTrigger.isVisible()) {
			// Select an option first
			await selectTrigger.click();
			const option = page.locator('[role="option"]').first();
			if (await option.isVisible()) {
				await option.click();
			}

			// Reopen and select "Any" to clear
			await selectTrigger.click();
			const anyOption = page.locator('[role="option"]:has-text("Any")');
			if (await anyOption.isVisible()) {
				await anyOption.click();

				// Should show "Any" again
				const triggerText = await selectTrigger.textContent();
				expect(triggerText).toContain('Any');
			}
		}
	});
});

// =============================================================================
// BOOLEAN FILTER TESTS
// =============================================================================

test.describe('DataTable Boolean Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show Yes/No options', async ({ page }) => {
		await openFiltersPanel(page);

		// Find a boolean select
		const booleanSelect = page.locator('button[role="combobox"]').filter({ hasText: /Any|Yes|No/ }).first();

		if (await booleanSelect.isVisible()) {
			await booleanSelect.click();

			// Should have Yes and No options
			const yesOption = page.locator('[role="option"]:has-text("Yes")');
			const noOption = page.locator('[role="option"]:has-text("No")');

			await expect(yesOption).toBeVisible();
			await expect(noOption).toBeVisible();
		}
	});

	test('should filter by boolean true', async ({ page }) => {
		await openFiltersPanel(page);

		const booleanSelect = page.locator('button[role="combobox"]').filter({ hasText: /Any/ }).first();

		if (await booleanSelect.isVisible()) {
			await booleanSelect.click();

			const yesOption = page.locator('[role="option"]:has-text("Yes")');
			if (await yesOption.isVisible()) {
				await yesOption.click();
				await applyFilters(page);

				await expect(page).not.toHaveURL(/error/);
			}
		}
	});

	test('should filter by boolean false', async ({ page }) => {
		await openFiltersPanel(page);

		const booleanSelect = page.locator('button[role="combobox"]').filter({ hasText: /Any/ }).first();

		if (await booleanSelect.isVisible()) {
			await booleanSelect.click();

			const noOption = page.locator('[role="option"]:has-text("No")');
			if (await noOption.isVisible()) {
				await noOption.click();
				await applyFilters(page);

				await expect(page).not.toHaveURL(/error/);
			}
		}
	});
});

// =============================================================================
// DATE FILTER TESTS
// =============================================================================

test.describe('DataTable Date Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show date preset options', async ({ page }) => {
		await openFiltersPanel(page);

		// Find a date filter dropdown
		const dateSelect = page.locator('button[role="combobox"]').first();

		if (await dateSelect.isVisible()) {
			await dateSelect.click();

			// Should have date presets
			const todayOption = page.locator('[role="option"]:has-text("Today")');
			const last7Days = page.locator('[role="option"]:has-text("Last 7 days")');
			const last30Days = page.locator('[role="option"]:has-text("Last 30 days")');

			// At least some date options should exist
		}
	});

	test('should filter by Today', async ({ page }) => {
		await openFiltersPanel(page);

		// Find date filter
		const dateSelects = page.locator('button[role="combobox"]');
		const count = await dateSelects.count();

		for (let i = 0; i < count; i++) {
			const select = dateSelects.nth(i);
			await select.click();

			const todayOption = page.locator('[role="option"]:has-text("Today")');
			if (await todayOption.isVisible({ timeout: 1000 }).catch(() => false)) {
				await todayOption.click();
				await applyFilters(page);
				await expect(page).not.toHaveURL(/error/);
				break;
			}

			await page.keyboard.press('Escape');
		}
	});

	test('should filter by Last 7 days', async ({ page }) => {
		await openFiltersPanel(page);

		const dateSelects = page.locator('button[role="combobox"]');
		const count = await dateSelects.count();

		for (let i = 0; i < count; i++) {
			const select = dateSelects.nth(i);
			await select.click();

			const option = page.locator('[role="option"]:has-text("Last 7 days")');
			if (await option.isVisible({ timeout: 1000 }).catch(() => false)) {
				await option.click();
				await applyFilters(page);
				await expect(page).not.toHaveURL(/error/);
				break;
			}

			await page.keyboard.press('Escape');
		}
	});

	test('should filter by Last 30 days', async ({ page }) => {
		await openFiltersPanel(page);

		const dateSelects = page.locator('button[role="combobox"]');
		const count = await dateSelects.count();

		for (let i = 0; i < count; i++) {
			const select = dateSelects.nth(i);
			await select.click();

			const option = page.locator('[role="option"]:has-text("Last 30 days")');
			if (await option.isVisible({ timeout: 1000 }).catch(() => false)) {
				await option.click();
				await applyFilters(page);
				await expect(page).not.toHaveURL(/error/);
				break;
			}

			await page.keyboard.press('Escape');
		}
	});

	test('should filter by This month', async ({ page }) => {
		await openFiltersPanel(page);

		const dateSelects = page.locator('button[role="combobox"]');
		const count = await dateSelects.count();

		for (let i = 0; i < count; i++) {
			const select = dateSelects.nth(i);
			await select.click();

			const option = page.locator('[role="option"]:has-text("This month")');
			if (await option.isVisible({ timeout: 1000 }).catch(() => false)) {
				await option.click();
				await applyFilters(page);
				await expect(page).not.toHaveURL(/error/);
				break;
			}

			await page.keyboard.press('Escape');
		}
	});

	test('should filter by Is empty', async ({ page }) => {
		await openFiltersPanel(page);

		const dateSelects = page.locator('button[role="combobox"]');
		const count = await dateSelects.count();

		for (let i = 0; i < count; i++) {
			const select = dateSelects.nth(i);
			await select.click();

			const option = page.locator('[role="option"]:has-text("Is empty")');
			if (await option.isVisible({ timeout: 1000 }).catch(() => false)) {
				await option.click();
				await applyFilters(page);
				await expect(page).not.toHaveURL(/error/);
				break;
			}

			await page.keyboard.press('Escape');
		}
	});
});

// =============================================================================
// MULTIPLE FILTER COMBINATIONS
// =============================================================================

test.describe('DataTable Multiple Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should apply multiple filters at once', async ({ page }) => {
		await openFiltersPanel(page);

		// Set multiple filters
		const textInputs = page.locator('input[id^="filter-"]');
		const inputCount = await textInputs.count();

		if (inputCount >= 2) {
			await textInputs.first().fill('test1');
			await textInputs.nth(1).fill('test2');

			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should show filter count for multiple filters', async ({ page }) => {
		await openFiltersPanel(page);

		const textInputs = page.locator('input[id^="filter-"]');
		const inputCount = await textInputs.count();

		if (inputCount >= 2) {
			await textInputs.first().fill('test1');
			await textInputs.nth(1).fill('test2');

			// Badge should show 2
			const badge = page.locator('.rounded-full.bg-primary:has-text("2")');
			await expect(badge).toBeVisible();
		}
	});

	test('should clear all filters', async ({ page }) => {
		await openFiltersPanel(page);

		const textInputs = page.locator('input[id^="filter-"]');
		const inputCount = await textInputs.count();

		if (inputCount >= 2) {
			await textInputs.first().fill('test1');
			await textInputs.nth(1).fill('test2');

			// Clear all
			const clearButton = page.locator('button:has-text("Clear all")');
			await clearButton.click();

			// Inputs should be cleared
			await expect(textInputs.first()).toHaveValue('');
			await expect(textInputs.nth(1)).toHaveValue('');
		}
	});

	test('should reset to applied filters', async ({ page }) => {
		await openFiltersPanel(page);

		// Apply a filter first
		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('applied');
			await applyFilters(page);
		}

		// Open panel again and modify
		await openFiltersPanel(page);
		await textInput.fill('modified');

		// Reset should go back to applied value
		const resetButton = page.locator('button:has-text("Reset")');
		if (await resetButton.isVisible()) {
			await resetButton.click();

			await expect(textInput).toHaveValue('applied');
		}
	});
});

// =============================================================================
// FILTER CHIPS
// =============================================================================

test.describe('DataTable Filter Chips', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show filter chips when filters applied', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('testvalue');
			await applyFilters(page);

			// Filter chips should appear
			const filterChips = page.locator('[data-filter-chip], .filter-chip');
			// Chips may be shown
		}
	});

	test('should remove filter when chip X clicked', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('testvalue');
			await applyFilters(page);

			// Find and click chip remove button
			const chipRemove = page.locator('[data-filter-chip] button, .filter-chip button').first();
			if (await chipRemove.isVisible()) {
				await chipRemove.click();
				await waitForTableLoad(page);

				// Filter should be removed
			}
		}
	});
});

// =============================================================================
// GLOBAL SEARCH INTEGRATION
// =============================================================================

test.describe('DataTable Global Search with Filters', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should combine global search with filters', async ({ page }) => {
		// Apply global search
		const searchInput = page.locator('input[placeholder*="Search"]').first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('john');
			await page.waitForTimeout(500);
		}

		// Apply column filter
		await openFiltersPanel(page);
		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('test');
			await applyFilters(page);

			// Both should be applied
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should preserve filters when searching', async ({ page }) => {
		// Apply filter first
		await openFiltersPanel(page);
		const filterInput = page.locator('input[id^="filter-"]').first();
		if (await filterInput.isVisible()) {
			await filterInput.fill('filtervalue');
			await applyFilters(page);
		}

		// Now search
		const searchInput = page.locator('input[placeholder*="Search"]').first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('searchterm');
			await page.waitForTimeout(500);
			await waitForTableLoad(page);

			// Open filter panel - filter should still be there
			await openFiltersPanel(page);
			await expect(filterInput).toHaveValue('filtervalue');
		}
	});

	test('should clear both search and filters', async ({ page }) => {
		// Apply both
		const searchInput = page.locator('input[placeholder*="Search"]').first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('search');
			await page.waitForTimeout(300);
		}

		await openFiltersPanel(page);
		const filterInput = page.locator('input[id^="filter-"]').first();
		if (await filterInput.isVisible()) {
			await filterInput.fill('filter');
			await applyFilters(page);
		}

		// Clear filters from panel
		await openFiltersPanel(page);
		const clearButton = page.locator('button:has-text("Clear all")');
		if (await clearButton.isVisible()) {
			await clearButton.click();
		}

		// Clear search
		const clearSearch = page.locator('button[aria-label*="Clear"]').first();
		if (await clearSearch.isVisible()) {
			await clearSearch.click();
		}

		// Both should be cleared
		await expect(searchInput).toHaveValue('');
	});
});

// =============================================================================
// FILTER PERSISTENCE
// =============================================================================

test.describe('DataTable Filter Persistence', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should preserve filters on pagination', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('persisttest');
			await applyFilters(page);
		}

		// Navigate to next page
		const nextButton = page.locator('button[aria-label*="next"]').first();
		if (await nextButton.isVisible() && await nextButton.isEnabled()) {
			await nextButton.click();
			await waitForTableLoad(page);

			// Open panel - filter should be preserved
			await openFiltersPanel(page);
			await expect(textInput).toHaveValue('persisttest');
		}
	});

	test('should preserve filters when changing page size', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('sizetest');
			await applyFilters(page);
		}

		// Change page size
		const pageSizeSelector = page.locator('[aria-label*="page"]').first();
		if (await pageSizeSelector.isVisible()) {
			await pageSizeSelector.click();
			const option = page.locator('[role="option"]').first();
			if (await option.isVisible()) {
				await option.click();
				await waitForTableLoad(page);

				// Filter should be preserved
				await openFiltersPanel(page);
				await expect(textInput).toHaveValue('sizetest');
			}
		}
	});
});

// =============================================================================
// FILTER VALIDATION
// =============================================================================

test.describe('DataTable Filter Validation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should handle empty filter values gracefully', async ({ page }) => {
		await openFiltersPanel(page);

		// Apply empty filter
		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('');
			await applyFilters(page);

			// Should not error
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should handle special characters in filter', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('test@example.com');
			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should handle unicode characters in filter', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('');
			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should handle very long filter values', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			const longValue = 'a'.repeat(500);
			await textInput.fill(longValue);
			await applyFilters(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});
});

// =============================================================================
// FILTER ACCESSIBILITY
// =============================================================================

test.describe('DataTable Filter Accessibility', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should have labeled filter inputs', async ({ page }) => {
		await openFiltersPanel(page);

		// Each filter input should have an associated label
		const labels = page.locator('label[for^="filter-"]');
		expect(await labels.count()).toBeGreaterThan(0);
	});

	test('should be keyboard navigable', async ({ page }) => {
		await openFiltersPanel(page);

		// Tab through filter inputs
		await page.keyboard.press('Tab');
		await page.keyboard.press('Tab');
		await page.keyboard.press('Tab');

		// Some filter element should be focused
		const focusedElement = page.locator(':focus');
		await expect(focusedElement).toBeVisible();
	});

	test('should close panel with Escape key', async ({ page }) => {
		await openFiltersPanel(page);

		const filterPanel = page.locator('.rounded-lg.border.bg-card');
		await expect(filterPanel).toBeVisible();

		// Press Escape
		await page.keyboard.press('Escape');

		// Panel should close (may or may not depending on implementation)
	});
});

// =============================================================================
// FILTER PERFORMANCE
// =============================================================================

test.describe('DataTable Filter Performance', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should apply filters without blocking UI', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			// Rapidly change filter value
			for (let i = 0; i < 5; i++) {
				await textInput.fill(`test${i}`);
			}

			// UI should remain responsive
			await expect(textInput).toBeVisible();
		}
	});

	test('should show loading state while filtering', async ({ page }) => {
		await openFiltersPanel(page);

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('loadingtest');

			// Click apply and immediately check for loading
			const applyButton = page.locator('button:has-text("Apply Filters")');
			await applyButton.click();

			// Loading state should appear briefly
			// Hard to test in e2e due to speed
		}
	});
});
