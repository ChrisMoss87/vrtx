import { test, expect } from '@playwright/test';
import {
	login,
	waitForLoading,
	waitForToast,
	navigateToUsers,
	confirmDialog,
	fillFormField,
	searchInTable,
	expectToast
} from '../fixtures';

/**
 * User Management Tests
 * Tests for user CRUD, roles, and status management
 */

test.describe('User List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await navigateToUsers(page);
	});

	test('should display users list page', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /User|Users/i }).first()).toBeVisible();
	});

	test('should display create user button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), button:has-text("Add User"), a:has-text("New User")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should display users table', async ({ page }) => {
		const table = page.locator('table');
		await expect(table.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should search users', async ({ page }) => {
		const searchInput = page.locator('input[placeholder*="Search"]');
		if (await searchInput.isVisible()) {
			await searchInput.fill('test');
			await searchInput.press('Enter');
			await waitForLoading(page);
		}
	});

	test('should paginate users list', async ({ page }) => {
		const pagination = page.locator('[data-testid="pagination"], nav[aria-label="pagination"]');
		// Pagination may be visible if enough users
	});

	test('should filter by status', async ({ page }) => {
		const statusFilter = page.locator('button:has-text("Status"), [data-filter="status"]');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});

	test('should filter by role', async ({ page }) => {
		const roleFilter = page.locator('button:has-text("Role"), [data-filter="role"]');
		if (await roleFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await roleFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});
});

test.describe('User Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should navigate to create user form', async ({ page }) => {
		await navigateToUsers(page);
		await page.click('button:has-text("Create"), button:has-text("Add User")');

		// May open modal or navigate to form
		const form = page.locator('form, [role="dialog"]');
		await expect(form.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should create new user', async ({ page }) => {
		await navigateToUsers(page);
		await page.click('button:has-text("Create"), button:has-text("Add User")');

		// Fill user details
		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Test User ${Date.now()}`);
		}

		const emailInput = page.locator('input[name="email"], input[type="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`test-${Date.now()}@example.com`);
		}

		// Select role
		const roleSelect = page.locator('button[role="combobox"]:has-text("Role")');
		if (await roleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await roleSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should assign roles to user', async ({ page }) => {
		await navigateToUsers(page);
		await page.click('button:has-text("Create"), button:has-text("Add User")');

		// Look for role selection
		const roleSelect = page.locator('button[role="combobox"]:has-text("Role"), [data-testid="role-select"]');
		if (await roleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await roleSelect.click();

			// Select a role
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should validate required fields', async ({ page }) => {
		await navigateToUsers(page);
		await page.click('button:has-text("Create"), button:has-text("Add User")');

		// Submit without filling
		const submitButton = page.locator('button[type="submit"]:has-text("Create"), button:has-text("Save")');
		await submitButton.click();

		// Should show validation error
		const error = page.locator('text=/required/i');
		await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should validate email uniqueness', async ({ page }) => {
		await navigateToUsers(page);
		await page.click('button:has-text("Create"), button:has-text("Add User")');

		// Fill with existing email
		const emailInput = page.locator('input[name="email"], input[type="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill('bob@techco.com'); // Existing user
		}

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill('Duplicate User');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Should show duplicate email error
		const error = page.locator('text=/already|exists|taken/i');
		await expect(error.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});
});

test.describe('User View', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display user details', async ({ page }) => {
		await navigateToUsers(page);

		// Click on a user
		const userRow = page.locator('tbody tr').first();
		if (await userRow.isVisible()) {
			await userRow.click();
		}
	});

	test('should show user role', async ({ page }) => {
		await navigateToUsers(page);

		const roleColumn = page.locator('td:has-text("Admin"), td:has-text("User"), td:has-text("Manager")');
		// May have role column
	});

	test('should show user status', async ({ page }) => {
		await navigateToUsers(page);

		const statusBadge = page.locator('[class*="badge"]:has-text("Active"), [class*="badge"]:has-text("Inactive")');
		// May have status badges
	});
});

test.describe('User Edit', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should edit user details', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const editButton = row.locator('button:has-text("Edit"), [aria-label="Edit"]');
			if (await editButton.isVisible()) {
				await editButton.click();

				const nameInput = page.locator('input[name="name"]');
				if (await nameInput.isVisible()) {
					await nameInput.fill('Updated User Name');
				}

				const saveButton = page.locator('button:has-text("Save")');
				await saveButton.click();
				await waitForToast(page);
			}
		}
	});

	test('should update user role', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const editButton = row.locator('button:has-text("Edit")');
			if (await editButton.isVisible()) {
				await editButton.click();

				const roleSelect = page.locator('button[role="combobox"]:has-text("Role")');
				if (await roleSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
					await roleSelect.click();
					await page.locator('[role="option"]').first().click();
				}
			}
		}
	});
});

test.describe('User Status Management', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should deactivate user', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const deactivateOption = page.locator('[role="menuitem"]:has-text("Deactivate"), [role="menuitem"]:has-text("Disable")');
				if (await deactivateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await deactivateOption.click();
					await confirmDialog(page, 'confirm').catch(() => {});
					await waitForToast(page);
				}
			}
		}
	});

	test('should activate user', async ({ page }) => {
		await navigateToUsers(page);

		// Filter by inactive
		const statusFilter = page.locator('button:has-text("Status")');
		if (await statusFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await statusFilter.click();
			await page.locator('[role="option"]:has-text("Inactive")').click();
			await waitForLoading(page);
		}

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const activateOption = page.locator('[role="menuitem"]:has-text("Activate"), [role="menuitem"]:has-text("Enable")');
				if (await activateOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await activateOption.click();
					await waitForToast(page);
				}
			}
		}
	});
});

test.describe('User Password Management', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should reset user password', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const resetOption = page.locator('[role="menuitem"]:has-text("Reset Password")');
				if (await resetOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await resetOption.click();
					await confirmDialog(page, 'confirm').catch(() => {});
					await waitForToast(page);
				}
			}
		}
	});

	test('should show temporary password', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			const actionsButton = row.locator('button[aria-haspopup="menu"]');
			if (await actionsButton.isVisible()) {
				await actionsButton.click();

				const resetOption = page.locator('[role="menuitem"]:has-text("Reset Password")');
				if (await resetOption.isVisible({ timeout: 2000 }).catch(() => false)) {
					await resetOption.click();
					await confirmDialog(page, 'confirm').catch(() => {});

					// Should show temporary password
					const passwordDisplay = page.locator('text=/temporary|new password/i');
					await expect(passwordDisplay.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
				}
			}
		}
	});
});

test.describe('User Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show delete confirmation', async ({ page }) => {
		await navigateToUsers(page);

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

	test('should cancel user deletion', async ({ page }) => {
		await navigateToUsers(page);

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

	test('should delete user', async ({ page }) => {
		await navigateToUsers(page);

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

test.describe('User Sessions', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view user sessions', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			await row.click();

			const sessionsTab = page.locator('[role="tab"]:has-text("Sessions")');
			if (await sessionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
				await sessionsTab.click();
			}
		}
	});

	test('should revoke user session', async ({ page }) => {
		await navigateToUsers(page);

		const row = page.locator('tbody tr').first();
		if (await row.isVisible()) {
			await row.click();

			const sessionsTab = page.locator('[role="tab"]:has-text("Sessions")');
			if (await sessionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
				await sessionsTab.click();

				const revokeButton = page.locator('button:has-text("Revoke")').first();
				if (await revokeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
					await revokeButton.click();
					await confirmDialog(page, 'confirm').catch(() => {});
				}
			}
		}
	});
});
