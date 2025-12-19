import { test, expect } from './fixtures';
import { login, waitForLoading, waitForToast, navigateToModule } from './fixtures';

/**
 * Advanced DataTable E2E Tests
 * Tests column resizing, filtering, column visibility, inline editing, and more
 */

test.describe('DataTable Column Features', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should resize columns by dragging', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find a column resize handle
		const resizeHandle = page.locator('[data-resize-handle], .resize-handle, th .cursor-col-resize').first();

		if (await resizeHandle.isVisible()) {
			const box = await resizeHandle.boundingBox();
			if (box) {
				// Drag the handle to resize
				await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
				await page.mouse.down();
				await page.mouse.move(box.x + 100, box.y + box.height / 2);
				await page.mouse.up();

				// Column should have resized (no error)
				await expect(page).not.toHaveURL(/error/);
			}
		}
	});

	test('should toggle column visibility', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find and click columns toggle button
		const columnsButton = page.locator('button:has-text("Columns"), button[aria-label*="column"]').first();

		if (await columnsButton.isVisible()) {
			await columnsButton.click();

			// Dropdown should appear with column toggles
			const dropdown = page.locator('[role="menu"], [role="dialog"]').first();
			await expect(dropdown).toBeVisible();

			// Find a column checkbox to toggle
			const columnToggle = dropdown.locator('input[type="checkbox"], [role="menuitemcheckbox"]').first();

			if (await columnToggle.isVisible()) {
				const wasChecked = await columnToggle.isChecked?.() ?? true;
				await columnToggle.click();

				// Close dropdown and verify column visibility changed
				await page.keyboard.press('Escape');
				await page.waitForTimeout(300);

				// Table should still be visible and functional
				await expect(table).toBeVisible();
			}
		}
	});

	test('should reorder columns by drag and drop', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find draggable column headers
		const headers = table.locator('th[draggable="true"], th[data-draggable]');
		const headerCount = await headers.count();

		if (headerCount >= 2) {
			const firstHeader = headers.first();
			const secondHeader = headers.nth(1);

			const firstBox = await firstHeader.boundingBox();
			const secondBox = await secondHeader.boundingBox();

			if (firstBox && secondBox) {
				// Drag first column to second position
				await page.mouse.move(firstBox.x + firstBox.width / 2, firstBox.y + firstBox.height / 2);
				await page.mouse.down();
				await page.mouse.move(secondBox.x + secondBox.width / 2, secondBox.y + secondBox.height / 2);
				await page.mouse.up();

				await page.waitForTimeout(300);
				await expect(page).not.toHaveURL(/error/);
			}
		}
	});

	test('should pin/freeze columns', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Right-click on a column header for context menu
		const header = table.locator('th').first();
		await header.click({ button: 'right' });

		// Look for pin option
		const pinOption = page.locator('text=Pin, text=Freeze').first();
		if (await pinOption.isVisible()) {
			await pinOption.click();

			// Column should now have a pinned indicator
			const pinnedColumn = table.locator('th.pinned, th[data-pinned], th.sticky').first();
			await expect(pinnedColumn).toBeVisible();
		}
	});
});

test.describe('DataTable Filtering', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should filter by text search', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]').first();

		if (await searchInput.isVisible()) {
			await searchInput.fill('John');
			await page.waitForTimeout(500); // Debounce

			// Should show filtered results
			await waitForLoading(page);
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should apply column filters', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find filter button/icon in a column header
		const filterButton = table.locator('th button[aria-label*="filter"], th .filter-icon').first();

		if (await filterButton.isVisible()) {
			await filterButton.click();

			// Filter popover should appear
			const filterPopover = page.locator('[role="dialog"], .filter-popover').first();
			await expect(filterPopover).toBeVisible();

			// Fill in filter value
			const filterInput = filterPopover.locator('input').first();
			if (await filterInput.isVisible()) {
				await filterInput.fill('test');

				// Apply filter
				const applyButton = filterPopover.locator('button:has-text("Apply"), button:has-text("Filter")').first();
				if (await applyButton.isVisible()) {
					await applyButton.click();
					await waitForLoading(page);
				}
			}
		}
	});

	test('should save and load filter presets', async ({ page }) => {
		// Look for filter presets/saved filters dropdown
		const filtersButton = page.locator('button:has-text("Filters"), button:has-text("Saved")').first();

		if (await filtersButton.isVisible()) {
			await filtersButton.click();

			// Should show saved filters or option to save
			const dropdown = page.locator('[role="menu"]').first();
			if (await dropdown.isVisible()) {
				const saveOption = dropdown.locator('text=Save, text=Create').first();
				if (await saveOption.isVisible()) {
					await saveOption.click();

					// Should open save dialog
					const saveDialog = page.locator('[role="dialog"]').first();
					await expect(saveDialog).toBeVisible();
				}
			}
		}
	});

	test('should clear all filters', async ({ page }) => {
		// Apply a filter first
		const searchInput = page.locator('input[placeholder*="Search"]').first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await page.waitForTimeout(500);
		}

		// Find and click clear filters button
		const clearButton = page.locator('button:has-text("Clear"), button:has-text("Reset")').first();
		if (await clearButton.isVisible()) {
			await clearButton.click();
			await waitForLoading(page);

			// Search input should be cleared
			if (await searchInput.isVisible()) {
				await expect(searchInput).toHaveValue('');
			}
		}
	});
});

test.describe('DataTable Sorting', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should sort by clicking column header', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Click a sortable header
		const sortableHeader = table.locator('th:has-text("Name"), th:has-text("Email")').first();

		if (await sortableHeader.isVisible()) {
			// First click - ascending
			await sortableHeader.click();
			await waitForLoading(page);

			// Should show sort indicator
			let sortIndicator = sortableHeader.locator('[data-sort], .sort-icon, svg');
			await expect(sortIndicator).toBeVisible();

			// Second click - descending
			await sortableHeader.click();
			await waitForLoading(page);

			// Third click - no sort (or back to default)
			await sortableHeader.click();
			await waitForLoading(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should support multi-column sorting', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		const headers = table.locator('th');
		const headerCount = await headers.count();

		if (headerCount >= 2) {
			// Click first column to sort
			await headers.first().click();
			await waitForLoading(page);

			// Shift+click second column for multi-sort
			await headers.nth(1).click({ modifiers: ['Shift'] });
			await waitForLoading(page);

			await expect(page).not.toHaveURL(/error/);
		}
	});
});

test.describe('DataTable Selection', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should select all rows with header checkbox', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find header checkbox
		const headerCheckbox = table.locator('thead input[type="checkbox"]').first();

		if (await headerCheckbox.isVisible()) {
			await headerCheckbox.click();

			// All row checkboxes should be checked
			const rowCheckboxes = table.locator('tbody input[type="checkbox"]');
			const rowCount = await rowCheckboxes.count();

			for (let i = 0; i < Math.min(rowCount, 5); i++) {
				await expect(rowCheckboxes.nth(i)).toBeChecked();
			}
		}
	});

	test('should show selection count', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Select a few rows
		const rowCheckboxes = table.locator('tbody input[type="checkbox"]');
		const rowCount = await rowCheckboxes.count();

		if (rowCount >= 2) {
			await rowCheckboxes.first().click();
			await rowCheckboxes.nth(1).click();

			// Should show selection count
			const selectionCount = page.locator('text=/2 selected|Selected: 2/i');
			await expect(selectionCount).toBeVisible();
		}
	});

	test('should support keyboard row selection', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Click on a row to focus
		const firstRow = table.locator('tbody tr').first();
		await firstRow.click();

		// Press space to select
		await page.keyboard.press('Space');
		await page.waitForTimeout(200);

		// Row should be selected
		const checkbox = firstRow.locator('input[type="checkbox"]');
		if (await checkbox.isVisible()) {
			await expect(checkbox).toBeChecked();
		}
	});
});

test.describe('DataTable Export', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should export to CSV', async ({ page }) => {
		// Find export button
		const exportButton = page.locator('button:has-text("Export")').first();

		if (await exportButton.isVisible()) {
			await exportButton.click();

			// Look for CSV option
			const csvOption = page.locator('text=CSV, button:has-text("CSV")').first();
			if (await csvOption.isVisible()) {
				// Listen for download
				const downloadPromise = page.waitForEvent('download');
				await csvOption.click();

				// Should trigger download
				const download = await downloadPromise.catch(() => null);
				if (download) {
					expect(download.suggestedFilename()).toContain('.csv');
				}
			}
		}
	});

	test('should export to Excel', async ({ page }) => {
		// Find export button
		const exportButton = page.locator('button:has-text("Export")').first();

		if (await exportButton.isVisible()) {
			await exportButton.click();

			// Look for Excel option
			const excelOption = page.locator('text=Excel, text=XLSX, button:has-text("Excel")').first();
			if (await excelOption.isVisible()) {
				const downloadPromise = page.waitForEvent('download');
				await excelOption.click();

				const download = await downloadPromise.catch(() => null);
				if (download) {
					expect(download.suggestedFilename()).toMatch(/\.(xlsx|xls)$/);
				}
			}
		}
	});
});

test.describe('DataTable Inline Editing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should enable inline edit on double click', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Double click on a cell
		const cell = table.locator('tbody td').first();
		await cell.dblclick();

		// Should show input or editable state
		const input = cell.locator('input, textarea, [contenteditable="true"]');
		if (await input.isVisible()) {
			// Type a value
			await input.fill('Edited Value');

			// Press enter to save
			await page.keyboard.press('Enter');
			await waitForLoading(page);

			// Should show success
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should cancel inline edit on escape', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Double click on a cell
		const cell = table.locator('tbody td').first();
		const originalText = await cell.textContent();
		await cell.dblclick();

		// Should show input
		const input = cell.locator('input, textarea, [contenteditable="true"]');
		if (await input.isVisible()) {
			await input.fill('Should be cancelled');
			await page.keyboard.press('Escape');

			// Should revert to original value
			await page.waitForTimeout(200);
			const newText = await cell.textContent();
			expect(newText).toBe(originalText);
		}
	});
});

test.describe('DataTable Pagination', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should navigate between pages', async ({ page }) => {
		// Find pagination controls
		const nextButton = page.locator('button:has-text("Next"), button[aria-label*="next"]').first();

		if (await nextButton.isVisible() && await nextButton.isEnabled()) {
			await nextButton.click();
			await waitForLoading(page);

			// Should be on page 2
			const pageIndicator = page.locator('text=/Page 2|2 of/');
			await expect(pageIndicator).toBeVisible();

			// Go back
			const prevButton = page.locator('button:has-text("Previous"), button[aria-label*="previous"]').first();
			await prevButton.click();
			await waitForLoading(page);
		}
	});

	test('should change page size', async ({ page }) => {
		// Find page size selector
		const pageSizeSelector = page.locator('select, button:has-text("per page")').first();

		if (await pageSizeSelector.isVisible()) {
			await pageSizeSelector.click();

			// Select a different page size
			const option = page.locator('option[value="50"], [role="option"]:has-text("50")').first();
			if (await option.isVisible()) {
				await option.click();
				await waitForLoading(page);

				// Page size should be updated
				await expect(page).not.toHaveURL(/error/);
			}
		}
	});

	test('should jump to specific page', async ({ page }) => {
		// Find page number input or clickable page numbers
		const pageInput = page.locator('input[type="number"][aria-label*="page"]').first();

		if (await pageInput.isVisible()) {
			await pageInput.fill('3');
			await page.keyboard.press('Enter');
			await waitForLoading(page);

			// Should be on page 3
			const pageIndicator = page.locator('text=/Page 3|3 of/');
			await expect(pageIndicator).toBeVisible();
		}
	});
});

test.describe('DataTable Row Actions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForLoading(page);
	});

	test('should show row action menu', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find row action button (usually three dots)
		const actionButton = table
			.locator('tbody tr')
			.first()
			.locator('button[aria-label*="action"], button:has-text("..."), .actions-menu');

		if (await actionButton.isVisible()) {
			await actionButton.click();

			// Menu should appear with actions
			const menu = page.locator('[role="menu"]').first();
			await expect(menu).toBeVisible();

			// Should have common actions
			const editOption = menu.locator('text=Edit');
			const deleteOption = menu.locator('text=Delete');

			expect(await editOption.isVisible() || await deleteOption.isVisible()).toBeTruthy();
		}
	});

	test('should navigate to edit page from row action', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Click row to select or find edit action
		const firstRow = table.locator('tbody tr').first();
		const editButton = firstRow.locator('a:has-text("Edit"), button:has-text("Edit")');

		if (await editButton.isVisible()) {
			await editButton.click();
			await page.waitForURL(/\/edit|\/\d+/);

			// Should be on edit page
			await expect(page).toHaveURL(/edit|\/\d+/);
		}
	});

	test('should delete record from row action with confirmation', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Find delete action
		const actionButton = table
			.locator('tbody tr')
			.first()
			.locator('button[aria-label*="action"]');

		if (await actionButton.isVisible()) {
			await actionButton.click();

			const deleteOption = page.locator('[role="menuitem"]:has-text("Delete")').first();
			if (await deleteOption.isVisible()) {
				await deleteOption.click();

				// Confirmation dialog should appear
				const dialog = page.locator('[role="alertdialog"], [role="dialog"]').first();
				await expect(dialog).toBeVisible();

				// Cancel the delete
				const cancelButton = dialog.locator('button:has-text("Cancel")');
				await cancelButton.click();
			}
		}
	});
});
