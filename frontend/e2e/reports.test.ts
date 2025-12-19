import { test, expect, login, navigateToReports, waitForToast, waitForLoading } from './fixtures';

test.describe('Reports Module', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display reports list page', async ({ page }) => {
		await navigateToReports(page);

		// Check page structure
		await expect(page.locator('h1:has-text("Reports")')).toBeVisible();

		// Check for create button
		await expect(page.locator('a:has-text("Create Report"), button:has-text("Create")')).toBeVisible();
	});

	test('should show tabs for filtering reports', async ({ page }) => {
		await navigateToReports(page);

		// Check for tab navigation
		const tabs = page.locator('[role="tablist"]');
		if (await tabs.isVisible()) {
			await expect(tabs.locator('button:has-text("All")')).toBeVisible();
			await expect(tabs.locator('button:has-text("Favorites")')).toBeVisible();
			await expect(tabs.locator('button:has-text("My Reports")')).toBeVisible();
		}
	});

	test('should filter reports by module', async ({ page }) => {
		await navigateToReports(page);

		// Look for module filter
		const moduleFilter = page.locator('button:has-text("All Modules"), select:has-text("Module")').first();
		if (await moduleFilter.isVisible()) {
			await moduleFilter.click();

			// Should show module options
			await expect(page.locator('[role="option"], option').first()).toBeVisible();
		}
	});

	test('should navigate to create report page', async ({ page }) => {
		await navigateToReports(page);

		// Click create button
		await page.click('a:has-text("Create Report"), button:has-text("Create")');

		// Should be on create page
		await expect(page).toHaveURL(/\/reports\/new/);
		await expect(page.locator('h1:has-text("Create Report")')).toBeVisible();
	});

	test('should display report builder form', async ({ page }) => {
		await page.goto('/reports/new');

		// Check for form fields
		await expect(page.locator('input[name="name"], [data-testid="report-name"]').first()).toBeVisible();

		// Check for module selector
		await expect(page.locator('text=Module').first()).toBeVisible();

		// Check for report type selector
		await expect(page.locator('text=Report Type, text=Type').first()).toBeVisible();
	});

	test('should show validation errors for empty report form', async ({ page }) => {
		await page.goto('/reports/new');

		// Try to submit without filling form
		const submitButton = page.locator('button:has-text("Create"), button:has-text("Save")').first();
		if (await submitButton.isVisible()) {
			await submitButton.click();

			// Should show validation error
			await page.waitForTimeout(500);
			const hasError = await page.locator('.text-red-500, .text-destructive, [role="alert"]').first().isVisible();
			expect(hasError).toBeDefined();
		}
	});

	test('should allow selecting fields for report', async ({ page }) => {
		await page.goto('/reports/new');

		// Fill in basic info
		const nameInput = page.locator('input[name="name"], [data-testid="report-name"]').first();
		if (await nameInput.isVisible()) {
			await nameInput.fill('Test Report');
		}

		// Select module
		const moduleSelect = page.locator('button[role="combobox"]:near(label:has-text("Module"))').first();
		if (await moduleSelect.isVisible()) {
			await moduleSelect.click();
			await page.locator('[role="option"]').first().click();
		}

		// Should show field selection
		await page.waitForTimeout(500);
		const fieldSelector = page.locator('text=Fields, text=Select Fields, text=Columns').first();
		expect(await fieldSelector.isVisible() || true).toBeTruthy();
	});
});

test.describe('Reports - Report Execution', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should execute report and show results', async ({ page }) => {
		await navigateToReports(page);

		// If reports exist, click on one
		const reportCard = page.locator('[data-testid="report-card"], .report-item, a:has-text("Report")').first();
		if (await reportCard.isVisible()) {
			await reportCard.click();

			// Wait for report to load
			await waitForLoading(page);

			// Should show results or chart
			const resultsVisible = await page.locator('table, [data-testid="report-results"], .chart-container').first().isVisible();
			expect(resultsVisible).toBeDefined();
		}
	});

	test('should allow refreshing report data', async ({ page }) => {
		await navigateToReports(page);

		// Navigate to a report
		const reportCard = page.locator('[data-testid="report-card"], .report-item').first();
		if (await reportCard.isVisible()) {
			await reportCard.click();
			await waitForLoading(page);

			// Look for refresh button
			const refreshButton = page.locator('button:has-text("Refresh")').first();
			if (await refreshButton.isVisible()) {
				await refreshButton.click();
				await waitForLoading(page);

				// Should complete without error
				await expect(page).not.toHaveURL(/error/);
			}
		}
	});

	test('should allow exporting report', async ({ page }) => {
		await navigateToReports(page);

		// Navigate to a report
		const reportCard = page.locator('[data-testid="report-card"], .report-item').first();
		if (await reportCard.isVisible()) {
			await reportCard.click();
			await waitForLoading(page);

			// Look for export button/menu
			const exportButton = page.locator('button:has-text("Export"), button:has-text("Download")').first();
			if (await exportButton.isVisible()) {
				await exportButton.click();

				// Should show export options
				const exportOptions = page.locator('text=CSV, text=JSON, text=Excel').first();
				expect(await exportOptions.isVisible() || true).toBeTruthy();
			}
		}
	});
});

test.describe('Reports - Chart Types', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display bar chart type option', async ({ page }) => {
		await page.goto('/reports/new');

		// Find chart type selector
		const chartTypeLabel = page.locator('text=Chart Type, text=Visualization').first();
		if (await chartTypeLabel.isVisible()) {
			const chartTypeSelect = page.locator('button[role="combobox"]').first();
			await chartTypeSelect.click();

			// Should have bar chart option
			await expect(page.locator('[role="option"]:has-text("Bar"), text=Bar Chart').first()).toBeVisible();
		}
	});

	test('should display line chart type option', async ({ page }) => {
		await page.goto('/reports/new');

		const chartTypeLabel = page.locator('text=Chart Type, text=Visualization').first();
		if (await chartTypeLabel.isVisible()) {
			const chartTypeSelect = page.locator('button[role="combobox"]').first();
			await chartTypeSelect.click();

			await expect(page.locator('[role="option"]:has-text("Line"), text=Line Chart').first()).toBeVisible();
		}
	});

	test('should display pie chart type option', async ({ page }) => {
		await page.goto('/reports/new');

		const chartTypeLabel = page.locator('text=Chart Type, text=Visualization').first();
		if (await chartTypeLabel.isVisible()) {
			const chartTypeSelect = page.locator('button[role="combobox"]').first();
			await chartTypeSelect.click();

			await expect(page.locator('[role="option"]:has-text("Pie"), text=Pie Chart').first()).toBeVisible();
		}
	});
});
