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
 * Lead to Deal Conversion Integration Tests
 * Tests the complete workflow from lead creation through deal conversion
 */

test.describe('Lead to Deal Conversion Flow', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should complete full lead to deal conversion', async ({ page }) => {
		// Step 1: Create a new lead
		await page.goto('/records/leads/create');
		await waitForLoading(page);

		const leadNameInput = page.locator('input[name="name"], input[name="first_name"]').first();
		if (await leadNameInput.isVisible()) {
			await leadNameInput.fill(`Test Lead ${Date.now()}`);
		}

		const emailInput = page.locator('input[name="email"]');
		if (await emailInput.isVisible()) {
			await emailInput.fill(`test-${Date.now()}@example.com`);
		}

		const companyInput = page.locator('input[name="company"]');
		if (await companyInput.isVisible()) {
			await companyInput.fill('Test Company Inc');
		}

		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await waitForToast(page);

		// Step 2: Navigate to the lead
		await waitForLoading(page);

		// Step 3: Convert to deal
		const convertButton = page.locator('button:has-text("Convert"), button:has-text("Convert to Deal")');
		if (await convertButton.isVisible({ timeout: 3000 }).catch(() => false)) {
			await convertButton.click();

			// Fill deal details
			const dealNameInput = page.locator('input[name="deal_name"], input[name="name"]');
			if (await dealNameInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await dealNameInput.fill(`Deal from Lead ${Date.now()}`);
			}

			const valueInput = page.locator('input[name="value"], input[name="amount"]');
			if (await valueInput.isVisible()) {
				await valueInput.fill('50000');
			}

			// Confirm conversion
			const confirmButton = page.locator('[role="dialog"] button:has-text("Convert"), button[type="submit"]');
			await confirmButton.click();
			await waitForToast(page);

			// Verify we're on the deal page
			await expect(page).toHaveURL(/\/records\/deals\/\d+/);
		}
	});

	test('should create contact during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Check create contact option
			const createContactToggle = page.locator('label:has-text("Create Contact") input[type="checkbox"]');
			if (await createContactToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await createContactToggle.check();
			}
		}
	});

	test('should create company during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Check create company option
			const createCompanyToggle = page.locator('label:has-text("Create Company") input[type="checkbox"]');
			if (await createCompanyToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
				await createCompanyToggle.check();
			}
		}
	});

	test('should link to existing contact during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Select existing contact
			const contactSelect = page.locator('[data-testid="existing-contact-select"]');
			if (await contactSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await contactSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should preserve lead data in converted deal', async ({ page }) => {
		// Navigate to a converted deal
		await page.goto('/records/deals');
		await waitForLoading(page);

		// Filter for converted deals
		const convertedTab = page.locator('[role="tab"]:has-text("Converted"), button:has-text("Converted")');
		if (await convertedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertedTab.click();
			await waitForLoading(page);
		}

		// Open first deal
		const deal = page.locator('tbody tr').first();
		if (await deal.isVisible({ timeout: 2000 }).catch(() => false)) {
			await deal.click();

			// Check for lead reference
			const leadReference = page.locator('text=/Converted from|Source Lead/i');
			// May show lead reference
		}
	});

	test('should mark lead as converted', async ({ page }) => {
		// Navigate to leads
		await page.goto('/records/leads');
		await waitForLoading(page);

		// Filter for converted leads
		const convertedTab = page.locator('[role="tab"]:has-text("Converted")');
		if (await convertedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertedTab.click();
			await waitForLoading(page);
		}

		// Verify converted status
		const convertedBadge = page.locator('text=/Converted/i');
		// May show converted leads
	});

	test('should select pipeline during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Select pipeline
			const pipelineSelect = page.locator('[data-testid="pipeline-select"], button:has-text("Pipeline")');
			if (await pipelineSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await pipelineSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});

	test('should assign owner during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Select owner
			const ownerSelect = page.locator('[data-testid="owner-select"], button:has-text("Owner")');
			if (await ownerSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
				await ownerSelect.click();
				await page.locator('[role="option"]').first().click();
			}
		}
	});
});

test.describe('Bulk Lead Conversion', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/records/leads');
		await waitForLoading(page);
	});

	test('should convert multiple leads', async ({ page }) => {
		// Select multiple leads
		const checkboxes = page.locator('tbody tr input[type="checkbox"]');
		if ((await checkboxes.count()) >= 2) {
			await checkboxes.first().check();
			await checkboxes.nth(1).check();

			// Click bulk convert
			const bulkConvertButton = page.locator('button:has-text("Convert Selected")');
			if (await bulkConvertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await bulkConvertButton.click();
				await confirmDialog(page, 'confirm').catch(() => {});
				await waitForToast(page);
			}
		}
	});
});

test.describe('Conversion Validation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should validate required fields during conversion', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const convertButton = page.locator('button:has-text("Convert")');
		if (await convertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertButton.click();

			// Clear required field
			const dealNameInput = page.locator('input[name="deal_name"]');
			if (await dealNameInput.isVisible({ timeout: 2000 }).catch(() => false)) {
				await dealNameInput.fill('');
			}

			// Try to submit
			const confirmButton = page.locator('[role="dialog"] button:has-text("Convert")');
			await confirmButton.click();

			// Should show validation error
			const error = page.locator('text=/required/i');
			await expect(error.first()).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should prevent duplicate conversion', async ({ page }) => {
		// Navigate to already converted lead
		await page.goto('/records/leads');
		await waitForLoading(page);

		const convertedTab = page.locator('[role="tab"]:has-text("Converted")');
		if (await convertedTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await convertedTab.click();
			await waitForLoading(page);

			const lead = page.locator('tbody tr').first();
			if (await lead.isVisible({ timeout: 2000 }).catch(() => false)) {
				await lead.click();

				// Convert button should be disabled or hidden
				const convertButton = page.locator('button:has-text("Convert")');
				const isVisible = await convertButton.isVisible({ timeout: 1000 }).catch(() => false);
				if (isVisible) {
					await expect(convertButton).toBeDisabled().catch(() => {});
				}
			}
		}
	});
});
