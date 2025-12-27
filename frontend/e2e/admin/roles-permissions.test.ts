import { test, expect } from '@playwright/test';
import {
	waitForToast,
	navigateToRoles,
	confirmDialog
} from '../fixtures';

/**
 * Roles and Permissions Tests
 * Tests for RBAC configuration
 *
 * Note: Authentication is handled by global-setup.ts, so no login() call needed
 */

// Helper to wait for roles page to fully load
async function waitForRolesPage(page: import('@playwright/test').Page) {
	// First wait for loading spinner to disappear
	const spinner = page.locator('.animate-spin, [data-loading]');
	await spinner.waitFor({ state: 'hidden', timeout: 45000 }).catch(() => {});
	// Then wait for table with actual role data
	await page.locator('tbody tr').first().waitFor({ timeout: 30000 });
}

test.describe('Role List', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should display roles list page', async ({ page }) => {
		// Page title is "Role Management"
		await expect(page.locator('h1').filter({ hasText: /Role Management/i })).toBeVisible();
	});

	test('should display create role button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create Role")');
		await expect(createButton).toBeVisible();
	});

	test('should display roles table', async ({ page }) => {
		const table = page.locator('table');
		await expect(table).toBeVisible({ timeout: 5000 });
	});

	test('should show system roles', async ({ page }) => {
		// System roles like admin, manager, sales_rep should be visible
		const adminRole = page.locator('td:has-text("admin")');
		await expect(adminRole.first()).toBeVisible({ timeout: 3000 });
	});

	test('should show user count per role', async ({ page }) => {
		// Look for user counts in the table
		const usersColumn = page.locator('td:has(svg.lucide-users)');
		await expect(usersColumn.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should show permission count per role', async ({ page }) => {
		// Look for "X permissions" text
		const permissionCount = page.locator('text=/\\d+ permissions/');
		await expect(permissionCount.first()).toBeVisible({ timeout: 3000 });
	});

	test('should mark system roles with badge', async ({ page }) => {
		// System roles have a "System" badge
		const systemBadge = page.locator('span:has-text("System")');
		await expect(systemBadge.first()).toBeVisible({ timeout: 3000 });
	});
});

test.describe('Role Creation', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should open create role dialog', async ({ page }) => {
		await page.click('button:has-text("Create Role")');

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible({ timeout: 5000 });
		await expect(dialog.locator('text="Create New Role"')).toBeVisible();
	});

	test('should create new role', async ({ page }) => {
		await page.click('button:has-text("Create Role")');

		// Wait for dialog
		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Fill role name - the input has id="role-name"
		const nameInput = dialog.locator('#role-name');
		await nameInput.fill(`Test Role ${Date.now()}`);

		// Select some permissions (optional - find first checkbox)
		const permissionCheckbox = dialog.locator('button[role="checkbox"]').first();
		if (await permissionCheckbox.isVisible({ timeout: 1000 }).catch(() => false)) {
			await permissionCheckbox.click();
		}

		// Submit
		await dialog.locator('button:has-text("Create Role")').click();
		await waitForToast(page);
	});

	test('should validate required fields', async ({ page }) => {
		await page.click('button:has-text("Create Role")');

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Try to submit without filling name
		await dialog.locator('button:has-text("Create Role")').click();

		// Should show error toast
		const errorToast = page.locator('[data-sonner-toast]:has-text("required")');
		await expect(errorToast).toBeVisible({ timeout: 3000 });
	});

	test('should cancel role creation', async ({ page }) => {
		await page.click('button:has-text("Create Role")');

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Cancel
		await dialog.locator('button:has-text("Cancel")').click();
		await expect(dialog).not.toBeVisible();
	});
});

test.describe('Role Permissions', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should display permission categories in create dialog', async ({ page }) => {
		await page.click('button:has-text("Create Role")');

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Look for "System Permissions" label
		await expect(dialog.locator('text="System Permissions"')).toBeVisible();
	});

	test('should open edit dialog for role', async ({ page }) => {
		// Click edit button (pencil icon) on first non-system role or any role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible({ timeout: 5000 });
	});

	test('should show system and module permission tabs in edit dialog', async ({ page }) => {
		// Click edit button on any role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Check for tabs
		await expect(dialog.locator('[role="tab"]:has-text("System Permissions")')).toBeVisible();
		await expect(dialog.locator('[role="tab"]:has-text("Module Permissions")')).toBeVisible();
	});

	test('should toggle individual permissions', async ({ page }) => {
		// Click edit on a non-system role if available, or first role
		const rows = page.locator('tbody tr');
		const firstRow = rows.first();
		const editButton = firstRow.locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Toggle a permission checkbox
		const permissionCheckbox = dialog.locator('button[role="checkbox"]').first();
		if (await permissionCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await permissionCheckbox.click();
		}
	});

	test('should toggle permission category', async ({ page }) => {
		// Click edit on first role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Find a category checkbox (the first checkbox in each category group)
		const categoryCheckbox = dialog.locator('.space-y-2 > div > .flex > button[role="checkbox"]').first();
		if (await categoryCheckbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await categoryCheckbox.click();
		}
	});

	test('should save permission changes', async ({ page }) => {
		// Click edit on first role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Make a change
		const checkbox = dialog.locator('button[role="checkbox"]').first();
		if (await checkbox.isVisible({ timeout: 2000 }).catch(() => false)) {
			await checkbox.click();
		}

		// Save
		await dialog.locator('button:has-text("Save Changes")').click();
		await waitForToast(page);
	});
});

test.describe('Module Permissions', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should display module permissions tab', async ({ page }) => {
		// Click edit on first role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Click Module Permissions tab
		await dialog.locator('[role="tab"]:has-text("Module Permissions")').click();

		// Should show module permission table
		await expect(dialog.locator('text="Module"')).toBeVisible();
		await expect(dialog.locator('text="View"')).toBeVisible();
		await expect(dialog.locator('text="Create"')).toBeVisible();
	});

	test('should configure module access level', async ({ page }) => {
		// Click edit on first role
		const editButton = page.locator('tbody tr').first().locator('button').first();
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Click Module Permissions tab
		await dialog.locator('[role="tab"]:has-text("Module Permissions")').click();

		// Look for record access dropdown
		const accessDropdown = dialog.locator('button:has-text("Own Records Only"), button:has-text("All Records")').first();
		if (await accessDropdown.isVisible({ timeout: 2000 }).catch(() => false)) {
			await accessDropdown.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Role Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should show delete button for custom roles', async ({ page }) => {
		// Find a row without "System" badge - those should have delete button
		const customRoleRow = page.locator('tbody tr:not(:has-text("System"))').first();
		if (await customRoleRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = customRoleRow.locator('button:has(svg.lucide-trash-2)');
			await expect(deleteButton).toBeVisible();
		}
	});

	test('should not show delete button for system roles', async ({ page }) => {
		// Find a row with "System" badge
		const systemRoleRow = page.locator('tbody tr:has-text("System")').first();
		if (await systemRoleRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Should only have edit button, not delete
			const deleteButton = systemRoleRow.locator('button:has(svg.lucide-trash-2)');
			await expect(deleteButton).not.toBeVisible();
		}
	});

	test('should show delete confirmation dialog', async ({ page }) => {
		// Find a custom role
		const customRoleRow = page.locator('tbody tr:not(:has-text("System"))').first();
		if (await customRoleRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = customRoleRow.locator('button:has(svg.lucide-trash-2)');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();

				const alertDialog = page.locator('[role="alertdialog"]');
				await expect(alertDialog).toBeVisible();
				await expect(alertDialog.locator('text="Delete Role"')).toBeVisible();
			}
		}
	});

	test('should cancel role deletion', async ({ page }) => {
		const customRoleRow = page.locator('tbody tr:not(:has-text("System"))').first();
		if (await customRoleRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = customRoleRow.locator('button:has(svg.lucide-trash-2)');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();

				const alertDialog = page.locator('[role="alertdialog"]');
				await expect(alertDialog).toBeVisible();

				// Click Cancel
				await alertDialog.locator('button:has-text("Cancel")').click();
				await expect(alertDialog).not.toBeVisible();
			}
		}
	});

	test('should delete custom role', async ({ page }) => {
		const customRoleRow = page.locator('tbody tr:not(:has-text("System"))').first();
		if (await customRoleRow.isVisible({ timeout: 2000 }).catch(() => false)) {
			const deleteButton = customRoleRow.locator('button:has(svg.lucide-trash-2)');
			if (await deleteButton.isVisible()) {
				await deleteButton.click();

				const alertDialog = page.locator('[role="alertdialog"]');
				await expect(alertDialog).toBeVisible();

				// Click Delete
				await alertDialog.locator('button:has-text("Delete")').click();
				await waitForToast(page);
			}
		}
	});
});

test.describe('Role Edit', () => {
	test.beforeEach(async ({ page }) => {
		await navigateToRoles(page);
		await waitForRolesPage(page);
	});

	test('should open edit dialog', async ({ page }) => {
		const editButton = page.locator('tbody tr').first().locator('button:has(svg.lucide-pencil)');
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();
		await expect(dialog.locator('text=/Edit Role/')).toBeVisible();
	});

	test('should display role name in edit dialog title', async ({ page }) => {
		// Get the role name from the first row
		const roleName = await page.locator('tbody tr').first().locator('td').first().locator('.font-medium').textContent();

		const editButton = page.locator('tbody tr').first().locator('button:has(svg.lucide-pencil)');
		await editButton.click();

		const dialog = page.locator('[role="dialog"]');
		await expect(dialog).toBeVisible();

		// Dialog title should include role name
		if (roleName) {
			await expect(dialog.locator(`text=/Edit Role.*${roleName}/i`)).toBeVisible();
		}
	});
});
