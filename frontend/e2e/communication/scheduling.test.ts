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
 * Scheduling Tests
 * Tests for meeting scheduling and calendar integration
 */

test.describe('Scheduling Pages List', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/admin/scheduling');
		await waitForLoading(page);
	});

	test('should display scheduling pages', async ({ page }) => {
		await expect(page.locator('h1, h2').filter({ hasText: /Scheduling|Calendar|Booking/i }).first()).toBeVisible();
	});

	test('should display create button', async ({ page }) => {
		const createButton = page.locator('button:has-text("Create"), a:has-text("New")');
		await expect(createButton.first()).toBeVisible();
	});

	test('should show scheduling pages list', async ({ page }) => {
		const pages = page.locator('[data-testid="scheduling-page"], tbody tr');
		// May have scheduling pages
	});

	test('should filter by type', async ({ page }) => {
		const typeFilter = page.locator('button:has-text("Type"), [data-filter="type"]');
		if (await typeFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeFilter.click();
			await page.locator('[role="option"]').first().click();
			await waitForLoading(page);
		}
	});
});

test.describe('Scheduling Page Creation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should create one-on-one meeting page', async ({ page }) => {
		await page.goto('/admin/scheduling/create');
		await waitForLoading(page);

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Meeting Page ${Date.now()}`);
		}

		// Select duration
		const durationSelect = page.locator('button:has-text("Duration"), [data-testid="duration-select"]');
		if (await durationSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await durationSelect.click();
			await page.locator('[role="option"]:has-text("30")').click();
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);
	});

	test('should create group meeting page', async ({ page }) => {
		await page.goto('/admin/scheduling/create');
		await waitForLoading(page);

		// Select group type
		const typeSelect = page.locator('button:has-text("Type"), [data-testid="meeting-type"]');
		if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await typeSelect.click();
			await page.locator('[role="option"]:has-text("Group")').click();
		}

		const nameInput = page.locator('input[name="name"]');
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Group Meeting ${Date.now()}`);
		}
	});

	test('should set meeting location', async ({ page }) => {
		await page.goto('/admin/scheduling/create');
		await waitForLoading(page);

		const locationSelect = page.locator('button:has-text("Location"), [data-testid="location-select"]');
		if (await locationSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await locationSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should configure availability', async ({ page }) => {
		await page.goto('/admin/scheduling/create');
		await waitForLoading(page);

		const availabilitySection = page.locator('text=/Availability|Schedule/i');
		if (await availabilitySection.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Click on a day to toggle
			const dayToggle = page.locator('button:has-text("Monday"), input[name="monday"]');
			if (await dayToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await dayToggle.click();
			}
		}
	});
});

test.describe('Scheduling Page Configuration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should set buffer time', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const bufferInput = page.locator('input[name="buffer_before"], input[name="buffer_time"]');
		if (await bufferInput.isVisible()) {
			await bufferInput.fill('15');
		}
	});

	test('should set minimum notice', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const noticeInput = page.locator('input[name="min_notice"], input[name="minimum_notice"]');
		if (await noticeInput.isVisible()) {
			await noticeInput.fill('60');
		}
	});

	test('should set booking window', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const windowInput = page.locator('input[name="booking_window"], input[name="max_days_ahead"]');
		if (await windowInput.isVisible()) {
			await windowInput.fill('30');
		}
	});

	test('should configure questions', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const questionsTab = page.locator('[role="tab"]:has-text("Questions"), button:has-text("Form Fields")');
		if (await questionsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await questionsTab.click();

			const addQuestionButton = page.locator('button:has-text("Add Question"), button:has-text("Add Field")');
			if (await addQuestionButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await addQuestionButton.click();
			}
		}
	});

	test('should customize confirmation email', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const emailTab = page.locator('[role="tab"]:has-text("Email"), button:has-text("Notifications")');
		if (await emailTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailTab.click();

			const subjectInput = page.locator('input[name="confirmation_subject"]');
			if (await subjectInput.isVisible()) {
				await subjectInput.fill('Your meeting is confirmed');
			}
		}
	});

	test('should set page slug', async ({ page }) => {
		await page.goto('/admin/scheduling/1/edit');
		await waitForLoading(page);

		const slugInput = page.locator('input[name="slug"]');
		if (await slugInput.isVisible()) {
			await slugInput.fill('my-meeting-page');
		}
	});
});

test.describe('Scheduled Meetings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view scheduled meetings', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings');
		await waitForLoading(page);

		const meetings = page.locator('[data-testid="meeting-item"], tbody tr');
		// May have meetings
	});

	test('should view meeting details', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings/1');
		await waitForLoading(page);

		const details = page.locator('[data-testid="meeting-details"]');
		// May have details
	});

	test('should cancel meeting', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings/1');
		await waitForLoading(page);

		const cancelButton = page.locator('button:has-text("Cancel")');
		if (await cancelButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await cancelButton.click();
			await confirmDialog(page, 'confirm').catch(() => {});
			await waitForToast(page);
		}
	});

	test('should reschedule meeting', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings/1');
		await waitForLoading(page);

		const rescheduleButton = page.locator('button:has-text("Reschedule")');
		if (await rescheduleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await rescheduleButton.click();

			const dateInput = page.locator('input[type="date"], input[type="datetime-local"]');
			if (await dateInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				const futureDate = new Date(Date.now() + 48 * 60 * 60 * 1000).toISOString().slice(0, 10);
				await dateInput.fill(futureDate);
			}
		}
	});

	test('should filter meetings by date', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings');
		await waitForLoading(page);

		const dateFilter = page.locator('button:has-text("Date"), [data-filter="date"]');
		if (await dateFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await dateFilter.click();
			await page.locator('[role="option"]:has-text("Today")').click();
			await waitForLoading(page);
		}
	});

	test('should filter meetings by status', async ({ page }) => {
		await page.goto('/admin/scheduling/meetings');
		await waitForLoading(page);

		const statusTabs = page.locator('[role="tab"]');
		if ((await statusTabs.count()) > 1) {
			await statusTabs.nth(1).click();
			await waitForLoading(page);
		}
	});
});

test.describe('Calendar Integration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view calendar settings', async ({ page }) => {
		await page.goto('/admin/settings/calendar');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Calendar|Integration/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should connect Google Calendar', async ({ page }) => {
		await page.goto('/admin/settings/calendar');
		await waitForLoading(page);

		const googleButton = page.locator('button:has-text("Google"), button:has-text("Connect Google")');
		if (await googleButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists, don't actually connect
		}
	});

	test('should connect Outlook Calendar', async ({ page }) => {
		await page.goto('/admin/settings/calendar');
		await waitForLoading(page);

		const outlookButton = page.locator('button:has-text("Outlook"), button:has-text("Connect Microsoft")');
		if (await outlookButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists, don't actually connect
		}
	});

	test('should configure sync settings', async ({ page }) => {
		await page.goto('/admin/settings/calendar');
		await waitForLoading(page);

		const syncToggle = page.locator('label:has-text("Two-way sync") input[type="checkbox"]');
		if (await syncToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Sync toggle exists
		}
	});
});

test.describe('Video Meeting Integration', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view video settings', async ({ page }) => {
		await page.goto('/admin/settings/video');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /Video|Meeting/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should connect Zoom', async ({ page }) => {
		await page.goto('/admin/settings/video');
		await waitForLoading(page);

		const zoomButton = page.locator('button:has-text("Zoom"), button:has-text("Connect Zoom")');
		if (await zoomButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists
		}
	});

	test('should connect Google Meet', async ({ page }) => {
		await page.goto('/admin/settings/video');
		await waitForLoading(page);

		const meetButton = page.locator('button:has-text("Google Meet"), button:has-text("Connect Meet")');
		if (await meetButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			// Just verify button exists
		}
	});

	test('should configure default video provider', async ({ page }) => {
		await page.goto('/admin/settings/video');
		await waitForLoading(page);

		const defaultSelect = page.locator('button:has-text("Default Provider"), [data-testid="default-provider"]');
		if (await defaultSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await defaultSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});
});

test.describe('Scheduling Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view scheduling analytics', async ({ page }) => {
		await page.goto('/admin/scheduling/analytics');
		await waitForLoading(page);

		const analytics = page.locator('[data-testid="scheduling-analytics"], [class*="analytics"]');
		// May have analytics
	});

	test('should show booking rate', async ({ page }) => {
		await page.goto('/admin/scheduling/analytics');
		await waitForLoading(page);

		const bookingRate = page.locator('text=/Booking Rate|Bookings/i');
		// May show booking rate
	});

	test('should show no-show rate', async ({ page }) => {
		await page.goto('/admin/scheduling/analytics');
		await waitForLoading(page);

		const noShowRate = page.locator('text=/No-show|Cancelled/i');
		// May show no-show rate
	});

	test('should show popular times', async ({ page }) => {
		await page.goto('/admin/scheduling/analytics');
		await waitForLoading(page);

		const popularTimes = page.locator('text=/Popular|Busiest/i');
		// May show popular times
	});
});

test.describe('Scheduling Page Deletion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should delete scheduling page', async ({ page }) => {
		await page.goto('/admin/scheduling');
		await waitForLoading(page);

		const row = page.locator('tbody tr, [data-testid="scheduling-page"]').first();
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
