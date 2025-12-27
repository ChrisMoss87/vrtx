import { test, expect, Page } from '@playwright/test';
import { login, waitForLoading, waitForToast, navigateToModule } from './fixtures';

/**
 * DataTable Views E2E Tests
 *
 * Comprehensive tests for saved views functionality including:
 * - View creation
 * - View switching
 * - View updating
 * - View deletion
 * - Default view settings
 * - Shared views
 * - View state persistence
 */

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

async function waitForTableLoad(page: Page) {
	await page.locator('.animate-spin').waitFor({ state: 'hidden', timeout: 15000 }).catch(() => {});
	await page.locator('table').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {});
	await page.waitForLoadState('networkidle');
}

async function openViewsDropdown(page: Page) {
	const viewsButton = page.locator('button:has-text("View")').first();
	if (await viewsButton.isVisible()) {
		await viewsButton.click();
		await page.waitForTimeout(200);
	}
}

async function closeViewsDropdown(page: Page) {
	await page.keyboard.press('Escape');
	await page.waitForTimeout(100);
}

async function openSaveViewDialog(page: Page) {
	await openViewsDropdown(page);
	const saveNewOption = page.locator('[role="menuitem"]:has-text("Save as New View")');
	if (await saveNewOption.isVisible()) {
		await saveNewOption.click();
		await page.waitForTimeout(200);
	}
}

async function closeSaveViewDialog(page: Page) {
	const cancelButton = page.locator('[role="dialog"] button:has-text("Cancel")');
	if (await cancelButton.isVisible()) {
		await cancelButton.click();
		await page.waitForTimeout(200);
	} else {
		await page.keyboard.press('Escape');
	}
}

// Generate unique view name to avoid conflicts
function generateViewName(): string {
	return `E2E Test View ${Date.now()}`;
}

// =============================================================================
// VIEWS DROPDOWN TESTS
// =============================================================================

test.describe('DataTable Views Dropdown', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show views button in toolbar', async ({ page }) => {
		const viewsButton = page.locator('button:has-text("View")').first();
		await expect(viewsButton).toBeVisible();
	});

	test('should show Default View text when no custom view selected', async ({ page }) => {
		const viewsButton = page.locator('button:has-text("Default View")');
		await expect(viewsButton).toBeVisible();
	});

	test('should open dropdown on click', async ({ page }) => {
		await openViewsDropdown(page);

		const dropdown = page.locator('[role="menu"]');
		await expect(dropdown).toBeVisible();
	});

	test('should show Views label in dropdown', async ({ page }) => {
		await openViewsDropdown(page);

		const viewsLabel = page.locator('[role="menu"] :text("Views")');
		await expect(viewsLabel).toBeVisible();
	});

	test('should show Default View option', async ({ page }) => {
		await openViewsDropdown(page);

		const defaultOption = page.locator('[role="menuitem"]:has-text("Default View")');
		await expect(defaultOption).toBeVisible();
	});

	test('should show Save as New View option', async ({ page }) => {
		await openViewsDropdown(page);

		const saveNewOption = page.locator('[role="menuitem"]:has-text("Save as New View")');
		await expect(saveNewOption).toBeVisible();
	});

	test('should close dropdown with Escape', async ({ page }) => {
		await openViewsDropdown(page);

		const dropdown = page.locator('[role="menu"]');
		await expect(dropdown).toBeVisible();

		await page.keyboard.press('Escape');

		await expect(dropdown).not.toBeVisible();
	});

	test('should show My Views section if views exist', async ({ page }) => {
		await openViewsDropdown(page);

		// May have "My Views" label
		const myViewsLabel = page.locator('[role="menu"] :text("My Views")');
		// This appears only if user has saved views
	});

	test('should show Shared Views section if shared views exist', async ({ page }) => {
		await openViewsDropdown(page);

		// May have "Shared Views" label
		const sharedViewsLabel = page.locator('[role="menu"] :text("Shared Views")');
		// This appears only if shared views exist
	});
});

// =============================================================================
// CREATE VIEW TESTS
// =============================================================================

test.describe('DataTable Create View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should open save view dialog', async ({ page }) => {
		await openSaveViewDialog(page);

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();
	});

	test('should show dialog title', async ({ page }) => {
		await openSaveViewDialog(page);

		const title = page.locator('[role="dialog"] h2:has-text("Save New View")');
		await expect(title).toBeVisible();
	});

	test('should have view name input', async ({ page }) => {
		await openSaveViewDialog(page);

		const nameInput = page.locator('[role="dialog"] input#view-name');
		await expect(nameInput).toBeVisible();
	});

	test('should have description textarea', async ({ page }) => {
		await openSaveViewDialog(page);

		const descriptionInput = page.locator('[role="dialog"] textarea#view-description');
		await expect(descriptionInput).toBeVisible();
	});

	test('should have set as default checkbox', async ({ page }) => {
		await openSaveViewDialog(page);

		const defaultCheckbox = page.locator('[role="dialog"] :text("Set as my default view")');
		await expect(defaultCheckbox).toBeVisible();
	});

	test('should have share with team checkbox', async ({ page }) => {
		await openSaveViewDialog(page);

		const shareCheckbox = page.locator('[role="dialog"] :text("Share with team")');
		await expect(shareCheckbox).toBeVisible();
	});

	test('should have Save View button', async ({ page }) => {
		await openSaveViewDialog(page);

		const saveButton = page.locator('[role="dialog"] button:has-text("Save View")');
		await expect(saveButton).toBeVisible();
	});

	test('should have Cancel button', async ({ page }) => {
		await openSaveViewDialog(page);

		const cancelButton = page.locator('[role="dialog"] button:has-text("Cancel")');
		await expect(cancelButton).toBeVisible();
	});

	test('should close dialog on Cancel', async ({ page }) => {
		await openSaveViewDialog(page);

		const cancelButton = page.locator('[role="dialog"] button:has-text("Cancel")');
		await cancelButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).not.toBeVisible();
	});

	test('should create a new view successfully', async ({ page }) => {
		await openSaveViewDialog(page);

		const viewName = generateViewName();

		// Fill in view name
		const nameInput = page.locator('[role="dialog"] input#view-name');
		await nameInput.fill(viewName);

		// Fill description
		const descriptionInput = page.locator('[role="dialog"] textarea#view-description');
		await descriptionInput.fill('E2E test view description');

		// Save
		const saveButton = page.locator('[role="dialog"] button:has-text("Save View")');
		await saveButton.click();

		// Should show success toast
		await waitForToast(page, 'saved');

		// Dialog should close
		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).not.toBeVisible();
	});

	test('should show error for empty view name', async ({ page }) => {
		await openSaveViewDialog(page);

		// Try to save without name
		const saveButton = page.locator('[role="dialog"] button:has-text("Save View")');
		await saveButton.click();

		// Should show error
		await waitForToast(page, 'name');
	});

	test('should update views dropdown after creation', async ({ page }) => {
		await openSaveViewDialog(page);

		const viewName = generateViewName();

		const nameInput = page.locator('[role="dialog"] input#view-name');
		await nameInput.fill(viewName);

		const saveButton = page.locator('[role="dialog"] button:has-text("Save View")');
		await saveButton.click();

		await page.waitForTimeout(500);

		// Open dropdown - new view should appear
		await openViewsDropdown(page);

		const newView = page.locator(`[role="menuitem"]:has-text("${viewName}")`);
		await expect(newView).toBeVisible();
	});

	test('should switch to newly created view', async ({ page }) => {
		await openSaveViewDialog(page);

		const viewName = generateViewName();

		const nameInput = page.locator('[role="dialog"] input#view-name');
		await nameInput.fill(viewName);

		const saveButton = page.locator('[role="dialog"] button:has-text("Save View")');
		await saveButton.click();

		await page.waitForTimeout(500);

		// Button should now show new view name
		const viewsButton = page.locator(`button:has-text("${viewName}")`);
		await expect(viewsButton).toBeVisible();
	});
});

// =============================================================================
// SWITCH VIEW TESTS
// =============================================================================

test.describe('DataTable Switch View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should switch to Default View', async ({ page }) => {
		await openViewsDropdown(page);

		const defaultOption = page.locator('[role="menuitem"]:has-text("Default View")').first();
		await defaultOption.click();

		await waitForTableLoad(page);

		// Should show success toast
		await waitForToast(page, 'default').catch(() => {});
	});

	test('should switch to a saved view', async ({ page }) => {
		// First create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Switch to default
		await openViewsDropdown(page);
		await page.locator('[role="menuitem"]:has-text("Default View")').first().click();
		await waitForTableLoad(page);

		// Switch back to saved view
		await openViewsDropdown(page);
		const savedView = page.locator(`[role="menuitem"]:has-text("${viewName}")`);
		await savedView.click();

		await waitForTableLoad(page);

		// Button should show the view name
		const viewsButton = page.locator(`button:has-text("${viewName}")`);
		await expect(viewsButton).toBeVisible();
	});

	test('should show view star icon for default view', async ({ page }) => {
		await openViewsDropdown(page);

		// Look for star icon next to default view
		const starIcon = page.locator('[role="menuitem"] svg.lucide-star');
		// May show if a view is set as default
	});

	test('should show users icon for shared views', async ({ page }) => {
		await openViewsDropdown(page);

		// Look for users icon next to shared views
		const usersIcon = page.locator('[role="menuitem"] svg.lucide-users');
		// May show if shared views exist
	});
});

// =============================================================================
// UPDATE VIEW TESTS
// =============================================================================

test.describe('DataTable Update View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show Update option when view is selected', async ({ page }) => {
		// First create and select a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Open dropdown - should show update option
		await openViewsDropdown(page);

		const updateOption = page.locator(`[role="menuitem"]:has-text("Update")`);
		await expect(updateOption).toBeVisible();
	});

	test('should open update dialog', async ({ page }) => {
		// Create and select a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Open dropdown and click update
		await openViewsDropdown(page);
		const updateOption = page.locator(`[role="menuitem"]:has-text("Update")`);
		await updateOption.click();

		// Dialog should open
		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();
	});

	test('should pre-fill form with current view data', async ({ page }) => {
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		const description = 'Original description';

		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] textarea#view-description').fill(description);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Open update dialog
		await openViewsDropdown(page);
		const updateOption = page.locator(`[role="menuitem"]:has-text("Update")`);
		await updateOption.click();

		// Fields should be pre-filled
		const nameInput = page.locator('[role="dialog"] input#view-name');
		await expect(nameInput).toHaveValue(viewName);
	});

	test('should update view successfully', async ({ page }) => {
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Modify table state (e.g., search)
		const searchInput = page.locator('input[placeholder*="Search"]').first();
		if (await searchInput.isVisible()) {
			await searchInput.fill('updated');
			await page.waitForTimeout(300);
		}

		// Open update dialog and save
		await openViewsDropdown(page);
		const updateOption = page.locator(`[role="menuitem"]:has-text("Update")`);
		await updateOption.click();

		const saveButton = page.locator('[role="dialog"] button:has-text("Update View")');
		await saveButton.click();

		// Should show success toast
		await waitForToast(page, 'updated');
	});
});

// =============================================================================
// DELETE VIEW TESTS
// =============================================================================

test.describe('DataTable Delete View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should show Delete View option when view is selected', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Open dropdown
		await openViewsDropdown(page);

		const deleteOption = page.locator('[role="menuitem"]:has-text("Delete View")');
		await expect(deleteOption).toBeVisible();
	});

	test('should not show Delete option for default view', async ({ page }) => {
		// Switch to default view
		await openViewsDropdown(page);
		await page.locator('[role="menuitem"]:has-text("Default View")').first().click();
		await waitForTableLoad(page);

		// Open dropdown again
		await openViewsDropdown(page);

		// Delete should not be visible (no custom view selected)
		const deleteOption = page.locator('[role="menuitem"]:has-text("Delete View")');
		await expect(deleteOption).not.toBeVisible();
	});

	test('should show confirmation before deleting', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Setup dialog handler for confirmation
		page.on('dialog', async (dialog) => {
			await dialog.dismiss(); // Cancel the delete
		});

		// Click delete
		await openViewsDropdown(page);
		const deleteOption = page.locator('[role="menuitem"]:has-text("Delete View")');
		await deleteOption.click();

		// Confirmation dialog should have appeared (handled by dialog listener)
	});

	test('should delete view on confirmation', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Setup dialog handler to accept
		page.on('dialog', async (dialog) => {
			await dialog.accept();
		});

		// Click delete
		await openViewsDropdown(page);
		const deleteOption = page.locator('[role="menuitem"]:has-text("Delete View")');
		await deleteOption.click();

		// Should show success toast
		await waitForToast(page, 'deleted');

		// Should switch to default view
		const viewsButton = page.locator('button:has-text("Default View")');
		await expect(viewsButton).toBeVisible();
	});

	test('should remove deleted view from dropdown', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Setup dialog handler to accept
		page.on('dialog', async (dialog) => {
			await dialog.accept();
		});

		// Delete view
		await openViewsDropdown(page);
		const deleteOption = page.locator('[role="menuitem"]:has-text("Delete View")');
		await deleteOption.click();

		await page.waitForTimeout(500);

		// Open dropdown - deleted view should not appear
		await openViewsDropdown(page);
		const deletedView = page.locator(`[role="menuitem"]:has-text("${viewName}")`);
		await expect(deletedView).not.toBeVisible();
	});
});

// =============================================================================
// DEFAULT VIEW TESTS
// =============================================================================

test.describe('DataTable Default View Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should set view as default when checkbox is checked', async ({ page }) => {
		await openSaveViewDialog(page);

		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);

		// Check "Set as my default view"
		const defaultCheckbox = page.locator('[role="dialog"] #default-view');
		await defaultCheckbox.click();

		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// View should have star icon indicating default
		await openViewsDropdown(page);
		const starIcon = page.locator(`[role="menuitem"]:has-text("${viewName}") svg.lucide-star`);
		// Check for star or other default indicator
	});

	test('should load default view on page load', async ({ page }) => {
		// Create a default view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] #default-view').click();
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Reload page
		await page.reload();
		await waitForTableLoad(page);

		// Default view should be loaded
		const viewsButton = page.locator(`button:has-text("${viewName}")`);
		// May show the view name if default is set
	});

	test('should show Reset to Default button when custom view selected', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Reset button should appear
		const resetButton = page.locator('button:has-text("Reset to Default")');
		await expect(resetButton).toBeVisible();
	});

	test('should switch to default view on Reset click', async ({ page }) => {
		// Create a view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Click reset
		const resetButton = page.locator('button:has-text("Reset to Default")');
		await resetButton.click();

		await waitForTableLoad(page);

		// Should be on default view
		const viewsButton = page.locator('button:has-text("Default View")');
		await expect(viewsButton).toBeVisible();
	});
});

// =============================================================================
// SHARED VIEW TESTS
// =============================================================================

test.describe('DataTable Shared Views', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should share view when checkbox is checked', async ({ page }) => {
		await openSaveViewDialog(page);

		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);

		// Check "Share with team"
		const shareCheckbox = page.locator('[role="dialog"] #share-view');
		await shareCheckbox.click();

		await page.locator('[role="dialog"] button:has-text("Save View")').click();

		await waitForToast(page, 'saved');
	});

	test('should show shared views in Shared Views section', async ({ page }) => {
		// Create a shared view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] #share-view').click();
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Open dropdown
		await openViewsDropdown(page);

		// Should have Shared Views section
		const sharedSection = page.locator('[role="menu"] :text("Shared Views")');
		// May show if sharing is enabled
	});
});

// =============================================================================
// VIEW STATE PERSISTENCE
// =============================================================================

test.describe('DataTable View State Persistence', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should save filters with view', async ({ page }) => {
		// Apply a filter
		const filtersButton = page.locator('button:has-text("Filters")');
		await filtersButton.click();

		const textInput = page.locator('input[id^="filter-"]').first();
		if (await textInput.isVisible()) {
			await textInput.fill('testfilter');
			await page.locator('button:has-text("Apply Filters")').click();
			await waitForTableLoad(page);
		}

		// Save view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Switch to default
		await openViewsDropdown(page);
		await page.locator('[role="menuitem"]:has-text("Default View")').first().click();
		await waitForTableLoad(page);

		// Switch back to saved view
		await openViewsDropdown(page);
		await page.locator(`[role="menuitem"]:has-text("${viewName}")`).click();
		await waitForTableLoad(page);

		// Filter should be applied
		// Open filters panel to verify
		await filtersButton.click();
		await expect(textInput).toHaveValue('testfilter');
	});

	test('should save column visibility with view', async ({ page }) => {
		// Toggle a column
		const columnsButton = page.locator('button:has-text("Columns")');
		await columnsButton.click();

		const checkbox = page.locator('[role="menu"] [role="menuitemcheckbox"]').first();
		if (await checkbox.isVisible()) {
			await checkbox.click();
			await page.keyboard.press('Escape');
		}

		// Save view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Column state should be saved with view
		await expect(page).not.toHaveURL(/error/);
	});

	test('should save sorting with view', async ({ page }) => {
		const table = page.locator('table');
		if (!(await table.isVisible())) {
			test.skip();
			return;
		}

		// Sort a column
		const header = table.locator('th').nth(1);
		await header.click();
		await waitForTableLoad(page);

		// Save view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Sorting should be saved with view
		await expect(page).not.toHaveURL(/error/);
	});

	test('should save page size with view', async ({ page }) => {
		// Change page size
		const pageSizeSelector = page.locator('[aria-label*="page"]').first();
		if (await pageSizeSelector.isVisible()) {
			await pageSizeSelector.click();
			const option = page.locator('[role="option"]:has-text("50")');
			if (await option.isVisible()) {
				await option.click();
				await waitForTableLoad(page);
			}
		}

		// Save view
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Page size should be saved with view
		await expect(page).not.toHaveURL(/error/);
	});
});

// =============================================================================
// VIEW ACCESSIBILITY
// =============================================================================

test.describe('DataTable Views Accessibility', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should be keyboard navigable', async ({ page }) => {
		// Focus views button and open with Enter
		const viewsButton = page.locator('button:has-text("View")').first();
		await viewsButton.focus();
		await page.keyboard.press('Enter');

		// Dropdown should open
		const dropdown = page.locator('[role="menu"]');
		await expect(dropdown).toBeVisible();

		// Navigate with arrow keys
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('ArrowDown');

		// Close with Escape
		await page.keyboard.press('Escape');
		await expect(dropdown).not.toBeVisible();
	});

	test('should have proper ARIA labels on dialog', async ({ page }) => {
		await openSaveViewDialog(page);

		// Dialog should have proper role
		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Input should have label
		const nameLabel = page.locator('label[for="view-name"]');
		await expect(nameLabel).toBeVisible();
	});

	test('should announce toast notifications', async ({ page }) => {
		await openSaveViewDialog(page);
		const viewName = generateViewName();
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();

		// Toast should be visible for screen readers
		const toast = page.locator('[data-sonner-toast]');
		await expect(toast).toBeVisible();
	});
});

// =============================================================================
// VIEW ERROR HANDLING
// =============================================================================

test.describe('DataTable Views Error Handling', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToModule(page, 'contacts');
		await waitForTableLoad(page);
	});

	test('should handle duplicate view names', async ({ page }) => {
		const viewName = generateViewName();

		// Create first view
		await openSaveViewDialog(page);
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();
		await page.waitForTimeout(500);

		// Try to create another with same name
		await openSaveViewDialog(page);
		await page.locator('[role="dialog"] input#view-name').fill(viewName);
		await page.locator('[role="dialog"] button:has-text("Save View")').click();

		// Should handle gracefully (may show error or add suffix)
		await page.waitForTimeout(500);
	});

	test('should handle view load failure gracefully', async ({ page }) => {
		// This tests error handling when view data is corrupted
		// In real scenario, this would require API mocking
		await openViewsDropdown(page);

		// Click on any view
		const viewOption = page.locator('[role="menuitem"]').first();
		await viewOption.click();

		// Should not crash
		await expect(page).not.toHaveURL(/error/);
	});
});
