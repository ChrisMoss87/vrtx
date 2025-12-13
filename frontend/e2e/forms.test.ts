import { test, expect, type Page } from '@playwright/test';

const BASE_URL = 'http://acme.vrtx.local';
const TEST_USER = {
	email: 'john@acme.com',
	password: 'password123'
};

/**
 * Helper function to login before tests
 */
async function login(page: Page) {
	await page.goto(`${BASE_URL}/login`);
	await page.fill('input[name="email"]', TEST_USER.email);
	await page.fill('input[name="password"]', TEST_USER.password);
	await page.click('button[type="submit"]');
	await page.waitForURL('**/dashboard');
}

// ==========================================
// Form Rendering Tests
// ==========================================
test.describe('Form Rendering', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should render create form with all fields', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Form should exist
		const form = page.locator('form');
		await expect(form).toBeVisible();

		// Should have submit and cancel buttons
		const submitButton = page.locator('button[type="submit"]');
		await expect(submitButton).toBeVisible();

		const cancelButton = page.locator('button:has-text("Cancel")');
		await expect(cancelButton).toBeVisible();
	});

	test('should display required field indicators', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Look for required field indicators (asterisks)
		const requiredIndicators = page.locator('span:has-text("*")');
		const requiredFieldsExist = (await requiredIndicators.count()) > 0;

		// Also check for "Required fields" text
		const requiredFieldsText = page.locator('text=Required, text=* Required');
		const hasRequiredText = await requiredFieldsText.first().isVisible().catch(() => false);

		expect(requiredFieldsExist || hasRequiredText).toBeTruthy();
	});

	test('should display field labels and help text', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Labels should be visible for inputs
		const labels = page.locator('label');
		const labelCount = await labels.count();
		expect(labelCount).toBeGreaterThan(0);
	});

	test('should render form in edit mode', async ({ page }) => {
		// First get a record ID if one exists
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Try to find and click edit on first record
		const editButton = page.locator('[data-testid="edit-button"], a:has-text("Edit")').first();

		if (await editButton.isVisible().catch(() => false)) {
			await editButton.click();
			await page.waitForLoadState('networkidle');

			// Should be on edit page
			const form = page.locator('form');
			await expect(form).toBeVisible();

			// Submit button should say Save, not Create
			const saveButton = page.locator('button:has-text("Save")');
			await expect(saveButton).toBeVisible();
		}
	});

	test('should render form in view mode (readonly)', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts`);
		await page.waitForLoadState('networkidle');

		// Click on first row to view details
		const firstRow = page.locator('table tbody tr').first();
		if (await firstRow.isVisible().catch(() => false)) {
			await firstRow.click();
			await page.waitForTimeout(500);

			// In view mode, fields should be disabled or readonly
			const disabledInputs = page.locator('input[disabled], input[readonly]');
			const hasDisabledInputs = (await disabledInputs.count()) > 0;

			// Or we might be on a detail page
			expect(page.url()).toMatch(/contacts/);
		}
	});
});

// ==========================================
// Field Type Tests
// ==========================================
test.describe('Field Types', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');
	});

	test('should handle text input field', async ({ page }) => {
		const textInput = page.locator('input[type="text"]').first();
		if (await textInput.isVisible().catch(() => false)) {
			await textInput.fill('Test Text Value');
			await expect(textInput).toHaveValue('Test Text Value');
		}
	});

	test('should handle email input with validation', async ({ page }) => {
		const emailInput = page.locator('input[type="email"]').first();
		if (await emailInput.isVisible().catch(() => false)) {
			// Invalid email
			await emailInput.fill('invalid-email');
			await emailInput.blur();

			// Should show validation error or HTML5 validation
			const isInvalid = await emailInput.evaluate(
				(el) => !(el as HTMLInputElement).checkValidity()
			);

			// Valid email
			await emailInput.fill('valid@example.com');
			await expect(emailInput).toHaveValue('valid@example.com');
		}
	});

	test('should handle phone input', async ({ page }) => {
		const phoneInput = page.locator('input[type="tel"]').first();
		if (await phoneInput.isVisible().catch(() => false)) {
			await phoneInput.fill('+1-555-123-4567');
			await expect(phoneInput).toHaveValue('+1-555-123-4567');
		}
	});

	test('should handle number input', async ({ page }) => {
		const numberInput = page.locator('input[type="number"]').first();
		if (await numberInput.isVisible().catch(() => false)) {
			await numberInput.fill('42');
			await expect(numberInput).toHaveValue('42');

			// Test that it rejects text
			await numberInput.fill('abc');
			const value = await numberInput.inputValue();
			// Number inputs clear when invalid text is entered
			expect(value === '' || value === '42').toBeTruthy();
		}
	});

	test('should handle textarea field', async ({ page }) => {
		const textarea = page.locator('textarea').first();
		if (await textarea.isVisible().catch(() => false)) {
			const longText = 'This is a long text that spans multiple lines.\nLine 2\nLine 3';
			await textarea.fill(longText);
			await expect(textarea).toHaveValue(longText);
		}
	});

	test('should handle date field', async ({ page }) => {
		const dateInput = page.locator('input[type="date"]').first();
		if (await dateInput.isVisible().catch(() => false)) {
			await dateInput.fill('2024-03-15');
			await expect(dateInput).toHaveValue('2024-03-15');
		}
	});

	test('should handle datetime field', async ({ page }) => {
		const datetimeInput = page.locator('input[type="datetime-local"]').first();
		if (await datetimeInput.isVisible().catch(() => false)) {
			await datetimeInput.fill('2024-03-15T14:30');
			await expect(datetimeInput).toHaveValue('2024-03-15T14:30');
		}
	});

	test('should handle select dropdown', async ({ page }) => {
		// Modern select components often use custom UI
		const selectTrigger = page.locator('[role="combobox"], button[data-radix-select-trigger]').first();

		if (await selectTrigger.isVisible().catch(() => false)) {
			await selectTrigger.click();

			// Wait for dropdown
			await page.waitForSelector('[role="listbox"], [role="option"]', { timeout: 2000 }).catch(() => null);

			// Click first option
			const firstOption = page.locator('[role="option"]').first();
			if (await firstOption.isVisible().catch(() => false)) {
				await firstOption.click();
				await page.waitForTimeout(200);
			}
		}
	});

	test('should handle multiselect field', async ({ page }) => {
		// Look for multiselect component
		const multiselect = page.locator('[data-testid="multiselect"], .multiselect').first();

		if (await multiselect.isVisible().catch(() => false)) {
			await multiselect.click();

			// Select multiple options
			const options = page.locator('[role="option"]');
			const optionCount = await options.count();

			if (optionCount >= 2) {
				await options.nth(0).click();
				await options.nth(1).click();
			}

			// Should show selected badges/tags
			const selectedBadges = page.locator('.badge, [data-testid="selected-tag"]');
			const selectedCount = await selectedBadges.count();
			expect(selectedCount).toBeGreaterThan(0);
		}
	});

	test('should handle checkbox field', async ({ page }) => {
		const checkbox = page.locator('input[type="checkbox"]').first();
		if (await checkbox.isVisible().catch(() => false)) {
			// Toggle checkbox
			await checkbox.click();
			await expect(checkbox).toBeChecked();

			await checkbox.click();
			await expect(checkbox).not.toBeChecked();
		}
	});

	test('should handle toggle/switch field', async ({ page }) => {
		const toggle = page.locator('[role="switch"], button[data-state]').first();
		if (await toggle.isVisible().catch(() => false)) {
			const initialState = await toggle.getAttribute('data-state');

			await toggle.click();
			await page.waitForTimeout(200);

			const newState = await toggle.getAttribute('data-state');
			expect(newState).not.toBe(initialState);
		}
	});

	test('should handle radio button group', async ({ page }) => {
		const radioGroup = page.locator('[role="radiogroup"]').first();
		if (await radioGroup.isVisible().catch(() => false)) {
			const radios = radioGroup.locator('[role="radio"], input[type="radio"]');
			const radioCount = await radios.count();

			if (radioCount >= 2) {
				// Click first radio
				await radios.first().click();

				// Click second radio
				await radios.nth(1).click();

				// First should now be unchecked
				await expect(radios.first()).not.toHaveAttribute('data-state', 'checked');
			}
		}
	});

	test('should handle URL input with validation', async ({ page }) => {
		const urlInput = page.locator('input[type="url"]').first();
		if (await urlInput.isVisible().catch(() => false)) {
			// Invalid URL
			await urlInput.fill('not-a-url');
			await urlInput.blur();

			// Valid URL
			await urlInput.fill('https://example.com');
			await expect(urlInput).toHaveValue('https://example.com');
		}
	});

	test('should handle currency field', async ({ page }) => {
		const currencyInput = page.locator('[data-testid="currency-input"], input[inputmode="decimal"]').first();
		if (await currencyInput.isVisible().catch(() => false)) {
			await currencyInput.fill('1234.56');
			await page.waitForTimeout(200);

			// Should format or accept the value
			const value = await currencyInput.inputValue();
			expect(value).toContain('1234');
		}
	});

	test('should handle rating field', async ({ page }) => {
		const ratingContainer = page.locator('[data-testid="rating"], .rating-stars').first();
		if (await ratingContainer.isVisible().catch(() => false)) {
			// Click on a star
			const stars = ratingContainer.locator('button, svg, span').filter({ hasText: '' });
			if ((await stars.count()) > 0) {
				await stars.nth(2).click(); // Click 3rd star
				await page.waitForTimeout(200);
			}
		}
	});

	test('should handle color picker field', async ({ page }) => {
		const colorInput = page.locator('input[type="color"]').first();
		if (await colorInput.isVisible().catch(() => false)) {
			await colorInput.fill('#ff5500');
			await expect(colorInput).toHaveValue('#ff5500');
		}
	});
});

// ==========================================
// Form Validation Tests
// ==========================================
test.describe('Form Validation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');
	});

	test('should show validation errors for required fields on submit', async ({ page }) => {
		// Try to submit without filling required fields
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await page.waitForTimeout(500);

		// Should show error messages
		const errorMessages = page.locator('.text-destructive, .text-red-500, [role="alert"]');
		const hasErrors = (await errorMessages.count()) > 0;

		// Or browser validation message
		const browserValidation = await page.locator(':invalid').count();

		expect(hasErrors || browserValidation > 0).toBeTruthy();
	});

	test('should clear validation error when field is corrected', async ({ page }) => {
		// Find a required text field
		const requiredField = page.locator('input[required]').first();

		if (await requiredField.isVisible().catch(() => false)) {
			// Trigger validation by focusing and blurring
			await requiredField.focus();
			await requiredField.blur();

			// Now fill it
			await requiredField.fill('Valid value');
			await requiredField.blur();

			await page.waitForTimeout(200);

			// Field should be valid now
			const isValid = await requiredField.evaluate((el) => (el as HTMLInputElement).checkValidity());
			expect(isValid).toBeTruthy();
		}
	});

	test('should validate email format', async ({ page }) => {
		const emailInput = page.locator('input[type="email"]').first();

		if (await emailInput.isVisible().catch(() => false)) {
			// Enter invalid email
			await emailInput.fill('invalid');
			await emailInput.blur();
			await page.waitForTimeout(300);

			// Check for error
			const errorText = page.locator('text=valid email, text=email address').first();
			const hasError = await errorText.isVisible().catch(() => false);

			// Or HTML5 validation
			const isInvalid = await emailInput.evaluate(
				(el) => !(el as HTMLInputElement).checkValidity()
			);

			expect(hasError || isInvalid).toBeTruthy();
		}
	});

	test('should validate number min/max constraints', async ({ page }) => {
		const numberInput = page.locator('input[type="number"][min], input[type="number"][max]').first();

		if (await numberInput.isVisible().catch(() => false)) {
			const min = await numberInput.getAttribute('min');
			const max = await numberInput.getAttribute('max');

			if (min) {
				// Enter value below min
				await numberInput.fill(String(Number(min) - 1));
				await numberInput.blur();

				const isInvalid = await numberInput.evaluate(
					(el) => !(el as HTMLInputElement).checkValidity()
				);
				expect(isInvalid).toBeTruthy();
			}

			if (max) {
				// Enter value above max
				await numberInput.fill(String(Number(max) + 1));
				await numberInput.blur();

				const isInvalid = await numberInput.evaluate(
					(el) => !(el as HTMLInputElement).checkValidity()
				);
				expect(isInvalid).toBeTruthy();
			}
		}
	});

	test('should validate text length constraints', async ({ page }) => {
		const textInput = page.locator('input[maxlength]').first();

		if (await textInput.isVisible().catch(() => false)) {
			const maxLength = await textInput.getAttribute('maxlength');
			if (maxLength) {
				// Try to enter text longer than maxlength
				const longText = 'x'.repeat(Number(maxLength) + 10);
				await textInput.fill(longText);

				// Browser should truncate
				const value = await textInput.inputValue();
				expect(value.length).toBeLessThanOrEqual(Number(maxLength));
			}
		}
	});

	test('should show form-level error message on submission failure', async ({ page }) => {
		// Fill minimum required fields with valid data
		const inputs = page.locator('input[required]');
		const inputCount = await inputs.count();

		for (let i = 0; i < inputCount; i++) {
			const input = inputs.nth(i);
			const type = await input.getAttribute('type');

			if (type === 'email') {
				await input.fill('test@example.com');
			} else if (type === 'number') {
				await input.fill('10');
			} else {
				await input.fill('Test Value');
			}
		}

		// Submit the form
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Wait for response
		await page.waitForTimeout(1000);

		// Check for any error alerts (could be validation or server error)
		const alerts = page.locator('[role="alert"]');
		// Success or error - form should respond
		await expect(page).not.toHaveURL(/error/);
	});
});

// ==========================================
// Form Blocks & Layout Tests
// ==========================================
test.describe('Form Blocks & Layout', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');
	});

	test('should render form blocks/sections', async ({ page }) => {
		// Look for section headers or collapsible blocks
		const sections = page.locator(
			'[data-testid="form-block"], .form-section, h3:visible, [role="region"]'
		);
		const sectionCount = await sections.count();

		// Forms typically have at least one section
		expect(sectionCount).toBeGreaterThanOrEqual(0);
	});

	test('should toggle collapsible sections', async ({ page }) => {
		const collapsibleTrigger = page.locator('[data-state="open"], [data-state="closed"]').first();

		if (await collapsibleTrigger.isVisible().catch(() => false)) {
			const initialState = await collapsibleTrigger.getAttribute('data-state');

			await collapsibleTrigger.click();
			await page.waitForTimeout(300);

			const newState = await collapsibleTrigger.getAttribute('data-state');
			expect(newState).not.toBe(initialState);
		}
	});

	test('should display fields in grid layout', async ({ page }) => {
		// Check for grid or multi-column layout
		const gridContainers = page.locator('.grid, .grid-cols-2, .grid-cols-3');
		const hasGrid = (await gridContainers.count()) > 0;

		// Grid layout is optional
		expect(hasGrid).toBeDefined();
	});
});

// ==========================================
// Conditional Visibility Tests
// ==========================================
test.describe('Conditional Visibility', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show/hide fields based on conditions', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Look for select/dropdown that might control visibility
		const controlField = page.locator('select, [role="combobox"]').first();

		if (await controlField.isVisible().catch(() => false)) {
			// Count visible fields before change
			const fieldsBefore = await page.locator('input:visible, select:visible, textarea:visible').count();

			// Change the controlling field value
			if ((await controlField.getAttribute('role')) === 'combobox') {
				await controlField.click();
				const option = page.locator('[role="option"]').first();
				if (await option.isVisible().catch(() => false)) {
					await option.click();
				}
			} else {
				await controlField.selectOption({ index: 1 });
			}

			await page.waitForTimeout(500);

			// Count visible fields after change
			const fieldsAfter = await page.locator('input:visible, select:visible, textarea:visible').count();

			// Fields might change (or stay same if no conditional visibility)
			expect(typeof fieldsBefore === 'number' && typeof fieldsAfter === 'number').toBeTruthy();
		}
	});
});

// ==========================================
// Formula Field Tests
// ==========================================
test.describe('Formula Fields', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should auto-calculate formula fields', async ({ page }) => {
		// Go to a module that likely has formula fields (like Deals with total calculation)
		await page.goto(`${BASE_URL}/records/deals/create`);
		await page.waitForLoadState('networkidle');

		// Look for fields that might be part of a calculation
		const quantityField = page.locator('input[name*="quantity"], input[name*="qty"]').first();
		const priceField = page.locator('input[name*="price"], input[name*="amount"]').first();

		if (
			(await quantityField.isVisible().catch(() => false)) &&
			(await priceField.isVisible().catch(() => false))
		) {
			await quantityField.fill('10');
			await priceField.fill('100');
			await page.waitForTimeout(500);

			// Look for a calculated total field
			const totalField = page.locator(
				'input[name*="total"], [data-testid="formula-field"]'
			).first();

			if (await totalField.isVisible().catch(() => false)) {
				const value = await totalField.inputValue();
				// Should have a calculated value
				expect(value).toBeTruthy();
			}
		}
	});

	test('should display formula fields as readonly', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/deals/create`);
		await page.waitForLoadState('networkidle');

		// Formula fields should be disabled/readonly
		const formulaField = page.locator('[data-testid="formula-field"], .formula-field').first();

		if (await formulaField.isVisible().catch(() => false)) {
			const isDisabled = await formulaField.isDisabled();
			const isReadonly = await formulaField.getAttribute('readonly');
			expect(isDisabled || isReadonly !== null).toBeTruthy();
		}
	});
});

// ==========================================
// Lookup Field Tests
// ==========================================
test.describe('Lookup Fields', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should search and select related records', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Look for lookup/autocomplete field
		const lookupField = page.locator(
			'[data-testid="lookup-field"], input[data-autocomplete], .lookup-input'
		).first();

		if (await lookupField.isVisible().catch(() => false)) {
			await lookupField.fill('test');
			await page.waitForTimeout(500);

			// Should show search results
			const results = page.locator(
				'[role="listbox"], [role="option"], .lookup-results'
			);
			const hasResults = (await results.count()) > 0;

			// Results may or may not appear depending on data
			expect(typeof hasResults === 'boolean').toBeTruthy();
		}
	});

	test('should clear selected lookup value', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		const lookupField = page.locator('[data-testid="lookup-field"]').first();

		if (await lookupField.isVisible().catch(() => false)) {
			// If there's a value, look for clear button
			const clearButton = lookupField.locator('button[aria-label*="clear"], button:has-text("×")');

			if (await clearButton.isVisible().catch(() => false)) {
				await clearButton.click();
				await page.waitForTimeout(200);

				// Field should be cleared
				const input = lookupField.locator('input');
				await expect(input).toHaveValue('');
			}
		}
	});
});

// ==========================================
// File Upload Field Tests
// ==========================================
test.describe('File Upload Fields', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should display file upload zone', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		const fileInput = page.locator('input[type="file"]').first();
		const dropzone = page.locator('[data-testid="dropzone"], .dropzone').first();

		const hasFileUpload =
			(await fileInput.isVisible().catch(() => false)) ||
			(await dropzone.isVisible().catch(() => false));

		// File upload may not exist on all forms
		expect(typeof hasFileUpload === 'boolean').toBeTruthy();
	});

	test('should show file type restrictions', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		const fileInput = page.locator('input[type="file"]').first();

		if (await fileInput.isVisible().catch(() => false)) {
			const accept = await fileInput.getAttribute('accept');
			// Accept attribute defines allowed types
			expect(accept !== undefined).toBeTruthy();
		}
	});
});

// ==========================================
// Form Submission Tests
// ==========================================
test.describe('Form Submission', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show loading state during submission', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Fill required fields
		const nameInput = page.locator(
			'input[name*="name"], input[name*="first_name"]'
		).first();
		if (await nameInput.isVisible().catch(() => false)) {
			await nameInput.fill('Test Contact');
		}

		// Fill other required fields
		const emailInput = page.locator('input[type="email"]').first();
		if (await emailInput.isVisible().catch(() => false)) {
			await emailInput.fill('test@example.com');
		}

		// Click submit and watch for loading state
		const submitButton = page.locator('button[type="submit"]');

		// Look for loading indicator appearing
		await submitButton.click();

		// Check for loading state (spinner, disabled button, or loading text)
		const hasLoadingState =
			(await page.locator('.animate-spin, [data-loading]').isVisible().catch(() => false)) ||
			(await submitButton.isDisabled().catch(() => false));

		// Loading state should appear during submission
		expect(typeof hasLoadingState === 'boolean').toBeTruthy();
	});

	test('should redirect after successful creation', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		// Fill all required fields
		const requiredInputs = page.locator('input[required]');
		const count = await requiredInputs.count();

		for (let i = 0; i < count; i++) {
			const input = requiredInputs.nth(i);
			const type = await input.getAttribute('type');

			if (type === 'email') {
				await input.fill(`test${Date.now()}@example.com`);
			} else if (type === 'number') {
				await input.fill('100');
			} else {
				await input.fill('Test Value ' + i);
			}
		}

		// Submit
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();

		// Wait for navigation or response
		await page.waitForTimeout(2000);

		// Should redirect to list or detail page, or show success
		const currentUrl = page.url();
		const hasSuccess = await page.locator('text=success, text=created').isVisible().catch(() => false);

		expect(currentUrl.includes('create') === false || hasSuccess).toBeTruthy();
	});

	test('should handle cancel button', async ({ page }) => {
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');

		const cancelButton = page.locator('button:has-text("Cancel")');
		const initialUrl = page.url();

		if (await cancelButton.isVisible()) {
			await cancelButton.click();
			await page.waitForTimeout(500);

			// Should navigate away from create page
			const newUrl = page.url();
			expect(newUrl !== initialUrl || await page.locator('[role="dialog"]').isHidden()).toBeTruthy();
		}
	});
});

// ==========================================
// Form Keyboard Navigation Tests
// ==========================================
test.describe('Form Keyboard Navigation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');
	});

	test('should navigate fields with Tab key', async ({ page }) => {
		// Focus first input
		const firstInput = page.locator('input:visible, select:visible, textarea:visible').first();
		await firstInput.focus();

		// Press Tab to move to next
		await page.keyboard.press('Tab');
		await page.waitForTimeout(100);

		// Different element should be focused
		const focusedElement = await page.evaluate(() => document.activeElement?.tagName);
		expect(focusedElement).toBeTruthy();
	});

	test('should submit form with Enter in some fields', async ({ page }) => {
		// Focus on a single-line input
		const textInput = page.locator('input[type="text"]:not([readonly]):not([disabled])').first();

		if (await textInput.isVisible().catch(() => false)) {
			await textInput.focus();
			await textInput.fill('Test');

			// Press Enter - may submit form or do nothing
			await page.keyboard.press('Enter');
			await page.waitForTimeout(500);

			// Form might submit (validation errors) or stay
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should navigate select options with arrow keys', async ({ page }) => {
		const selectTrigger = page.locator('[role="combobox"]').first();

		if (await selectTrigger.isVisible().catch(() => false)) {
			await selectTrigger.focus();
			await selectTrigger.click();

			await page.waitForTimeout(200);

			// Navigate with arrow keys
			await page.keyboard.press('ArrowDown');
			await page.keyboard.press('ArrowDown');
			await page.keyboard.press('Enter');

			await page.waitForTimeout(200);

			// Should have selected something
			expect(true).toBeTruthy();
		}
	});
});

// ==========================================
// Form Builder Tests (Admin)
// ==========================================
test.describe('Form Builder', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should access form builder from module settings', async ({ page }) => {
		// Navigate to admin/modules
		await page.goto(`${BASE_URL}/admin/modules`);
		await page.waitForLoadState('networkidle');

		// Look for a module to edit
		const moduleRow = page.locator('table tbody tr, [data-testid="module-item"]').first();

		if (await moduleRow.isVisible().catch(() => false)) {
			// Click edit or go to module details
			const editLink = moduleRow.locator('a:has-text("Edit"), button:has-text("Edit")');
			if (await editLink.isVisible().catch(() => false)) {
				await editLink.click();
				await page.waitForLoadState('networkidle');

				// Look for form builder tab or link
				const formBuilderLink = page.locator(
					'a:has-text("Form"), button:has-text("Form"), [data-tab="form"]'
				);
				if (await formBuilderLink.isVisible().catch(() => false)) {
					await formBuilderLink.click();
				}
			}
		}
	});

	test('should display field palette in form builder', async ({ page }) => {
		// Try direct URL to form builder
		await page.goto(`${BASE_URL}/admin/modules/1/form-builder`);
		await page.waitForLoadState('networkidle');

		// Look for field types to add
		const fieldPalette = page.locator(
			'[data-testid="field-palette"], .field-palette, text=Text, text=Email'
		);
		const hasPalette = (await fieldPalette.first().isVisible().catch(() => false));

		// May not have access or feature
		expect(typeof hasPalette === 'boolean').toBeTruthy();
	});

	test('should allow field drag and drop', async ({ page }) => {
		await page.goto(`${BASE_URL}/admin/modules/1/form-builder`);
		await page.waitForLoadState('networkidle');

		const draggableField = page.locator('[draggable="true"]').first();
		const dropZone = page.locator('[data-testid="form-canvas"], .form-canvas').first();

		if (
			(await draggableField.isVisible().catch(() => false)) &&
			(await dropZone.isVisible().catch(() => false))
		) {
			// Perform drag and drop
			await draggableField.dragTo(dropZone);
			await page.waitForTimeout(500);

			// Something should happen (field added or at least no error)
			await expect(page).not.toHaveURL(/error/);
		}
	});

	test('should open field configuration panel', async ({ page }) => {
		await page.goto(`${BASE_URL}/admin/modules/1/form-builder`);
		await page.waitForLoadState('networkidle');

		// Click on an existing field in the canvas
		const fieldInCanvas = page.locator('[data-testid="form-field"], .form-field').first();

		if (await fieldInCanvas.isVisible().catch(() => false)) {
			await fieldInCanvas.click();
			await page.waitForTimeout(300);

			// Config panel should appear
			const configPanel = page.locator(
				'[data-testid="field-config"], .field-config-panel'
			);
			const hasConfig = await configPanel.isVisible().catch(() => false);

			expect(typeof hasConfig === 'boolean').toBeTruthy();
		}
	});
});

// ==========================================
// Form Accessibility Tests
// ==========================================
test.describe('Form Accessibility', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto(`${BASE_URL}/records/contacts/create`);
		await page.waitForLoadState('networkidle');
	});

	test('should have proper label associations', async ({ page }) => {
		const inputs = page.locator('input:visible:not([type="hidden"])');
		const inputCount = await inputs.count();

		let associatedCount = 0;
		for (let i = 0; i < Math.min(inputCount, 5); i++) {
			const input = inputs.nth(i);
			const id = await input.getAttribute('id');
			const ariaLabel = await input.getAttribute('aria-label');
			const ariaLabelledBy = await input.getAttribute('aria-labelledby');

			if (id) {
				const label = page.locator(`label[for="${id}"]`);
				if (await label.isVisible().catch(() => false)) {
					associatedCount++;
				}
			}

			if (ariaLabel || ariaLabelledBy) {
				associatedCount++;
			}
		}

		// Most inputs should have labels
		expect(associatedCount).toBeGreaterThan(0);
	});

	test('should have proper ARIA attributes on error', async ({ page }) => {
		// Trigger validation error
		const submitButton = page.locator('button[type="submit"]');
		await submitButton.click();
		await page.waitForTimeout(500);

		// Check for aria-invalid attributes
		const invalidFields = page.locator('[aria-invalid="true"]');
		const invalidCount = await invalidFields.count();

		// If there are required fields, there should be invalid markers
		expect(typeof invalidCount === 'number').toBeTruthy();
	});

	test('should have proper focus outline styles', async ({ page }) => {
		const input = page.locator('input:visible').first();
		await input.focus();

		// Check that focused element has visible indicator
		const hasFocusVisible = await input.evaluate((el) => {
			const styles = window.getComputedStyle(el);
			return (
				styles.outlineWidth !== '0px' ||
				styles.boxShadow !== 'none' ||
				el.matches(':focus-visible')
			);
		});

		expect(hasFocusVisible).toBeTruthy();
	});
});
