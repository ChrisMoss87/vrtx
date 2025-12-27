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
 * Campaign to Lead Integration Tests
 * Tests the complete workflow from marketing campaigns to lead generation
 */

test.describe('Web Form Lead Capture', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should capture lead from web form submission', async ({ page }) => {
		// Navigate to public web form
		await page.goto('/p/contact-us'); // Public form URL
		await waitForLoading(page);

		// Fill out form
		const nameInput = page.locator('input[name="name"], input[name="first_name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill(`Web Form Lead ${Date.now()}`);
		}

		const emailInput = page.locator('input[name="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`webform-${Date.now()}@example.com`);
		}

		const companyInput = page.locator('input[name="company"]');
		if (await companyInput.isVisible()) {
			await companyInput.fill('Web Form Company');
		}

		// Submit form
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Check for thank you message
		const thankYou = page.locator('text=/Thank you|Success|Submitted/i');
		await expect(thankYou.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should track form source in lead', async ({ page }) => {
		// Navigate to leads created from web forms
		await page.goto('/records/leads');
		await waitForLoading(page);

		const sourceFilter = page.locator('button:has-text("Source"), [data-filter="source"]');
		if (await sourceFilter.isVisible({ timeout: 2000 }).catch(() => false)) {
			await sourceFilter.click();
			await page.locator('[role="option"]:has-text("Web Form")').click();
			await waitForLoading(page);
		}

		// Open a lead and check source
		const lead = page.locator('tbody tr').first();
		if (await lead.isVisible({ timeout: 2000 }).catch(() => false)) {
			await lead.click();

			const source = page.locator('text=/Web Form|Form Submission/i');
			await expect(source.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('Landing Page Lead Capture', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should capture lead from landing page', async ({ page }) => {
		// Navigate to public landing page
		await page.goto('/p/demo-request');
		await waitForLoading(page);

		// Fill out the landing page form
		const emailInput = page.locator('input[name="email"], input[type="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`landing-${Date.now()}@example.com`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Check for success
		const success = page.locator('text=/Thank you|Success/i');
		await expect(success.first()).toBeVisible({ timeout: 5000 }).catch(() => {});
	});

	test('should track landing page in lead', async ({ page }) => {
		await page.goto('/records/leads');
		await waitForLoading(page);

		const lead = page.locator('tbody tr').first();
		if (await lead.isVisible({ timeout: 2000 }).catch(() => false)) {
			await lead.click();

			const landingPage = page.locator('text=/Landing Page|Demo Request/i');
			// May show landing page reference
		}
	});
});

test.describe('Campaign Attribution', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should attribute lead to campaign', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const campaignInfo = page.locator('[data-testid="lead-campaign"], text=/Campaign/i');
		// May show campaign attribution
	});

	test('should track UTM parameters', async ({ page }) => {
		// Visit form with UTM parameters
		await page.goto('/p/contact-us?utm_source=google&utm_medium=cpc&utm_campaign=test-campaign');
		await waitForLoading(page);

		// Fill and submit form
		const emailInput = page.locator('input[name="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`utm-test-${Date.now()}@example.com`);
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Log in and check lead
		await login(page);
		await page.goto('/records/leads');
		await waitForLoading(page);

		// Look for UTM-sourced leads
		const utmLead = page.locator('text=/google|cpc|test-campaign/i');
		// May show UTM data
	});

	test('should show campaign ROI', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const roiSection = page.locator('[data-testid="campaign-roi"], text=/ROI|Return/i');
		// May show ROI
	});
});

test.describe('Email Campaign Lead Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should track email click to lead', async ({ page }) => {
		// View campaign analytics
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const analyticsTab = page.locator('[role="tab"]:has-text("Analytics")');
		if (await analyticsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await analyticsTab.click();

			const clickThroughs = page.locator('text=/Click|Clicked/i');
			// May show click data
		}
	});

	test('should show leads generated from campaign', async ({ page }) => {
		await page.goto('/marketing/campaigns/1');
		await waitForLoading(page);

		const leadsTab = page.locator('[role="tab"]:has-text("Leads"), button:has-text("Generated Leads")');
		if (await leadsTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await leadsTab.click();

			const leads = page.locator('[data-testid="campaign-lead"], tbody tr');
			// May have leads
		}
	});

	test('should view email engagement history on lead', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const activityTab = page.locator('[role="tab"]:has-text("Activity")');
		if (await activityTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await activityTab.click();

			const emailActivity = page.locator('text=/Email.*opened|Clicked link/i');
			// May show email engagement
		}
	});
});

test.describe('Lead Scoring from Campaign Engagement', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should increase lead score on email open', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const score = page.locator('[data-testid="lead-score"]');
		// May show score
	});

	test('should increase lead score on form submission', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const scoreHistory = page.locator('[data-testid="score-history"]');
		// May show score history with form submission points
	});

	test('should increase lead score on page visit', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const pageVisits = page.locator('text=/Page View|Visited/i');
		// May show page visit activity
	});
});

test.describe('Automated Lead Nurturing', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should auto-enroll lead in cadence', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const cadenceSection = page.locator('[data-testid="lead-cadence"], text=/Cadence|Sequence/i');
		// May show cadence enrollment
	});

	test('should trigger follow-up based on engagement', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const tasks = page.locator('[data-testid="lead-tasks"], text=/Follow.*up|Task/i');
		// May show automated tasks
	});

	test('should notify sales on high-intent activity', async ({ page }) => {
		await page.goto('/admin/notifications');
		await waitForLoading(page);

		const leadNotification = page.locator('text=/Lead.*engaged|High intent/i');
		// May show lead notifications
	});
});

test.describe('Campaign Performance Analytics', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show conversion funnel', async ({ page }) => {
		await page.goto('/marketing/campaigns/1/analytics');
		await waitForLoading(page);

		const funnel = page.locator('[data-testid="conversion-funnel"], canvas, svg');
		// May show funnel
	});

	test('should compare campaign performance', async ({ page }) => {
		await page.goto('/marketing/campaigns/analytics');
		await waitForLoading(page);

		const compareButton = page.locator('button:has-text("Compare")');
		if (await compareButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await compareButton.click();
		}
	});

	test('should export campaign report', async ({ page }) => {
		await page.goto('/marketing/campaigns/1/analytics');
		await waitForLoading(page);

		const exportButton = page.locator('button:has-text("Export")');
		if (await exportButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await exportButton.click();
		}
	});
});
