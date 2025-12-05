import { test, expect } from '@playwright/test';

test('debug wizard demo console errors', async ({ page }) => {
	// Collect console messages
	const consoleMessages: string[] = [];
	page.on('console', (msg) => {
		consoleMessages.push(`[${msg.type()}] ${msg.text()}`);
	});

	// Collect page errors
	const pageErrors: string[] = [];
	page.on('pageerror', (err) => {
		pageErrors.push(err.message);
	});

	// Navigate to wizard demo
	await page.goto('http://acme.vrtx.local/wizard-demo');
	await page.waitForLoadState('networkidle');

	// Wait a bit for any deferred effects
	await page.waitForTimeout(2000);

	// Log console messages
	console.log('\n=== Console Messages ===');
	for (const msg of consoleMessages) {
		console.log(msg);
	}

	// Log page errors
	console.log('\n=== Page Errors ===');
	for (const err of pageErrors) {
		console.log(err);
	}

	// Take screenshot
	await page.screenshot({ path: '/tmp/wizard-demo.png', fullPage: true });
	console.log('\nScreenshot saved to /tmp/wizard-demo.png');

	// Check button state
	const nextBtn = page.locator('button:has-text("Next")');
	if (await nextBtn.count() > 0) {
		console.log(`\nNext button disabled: ${await nextBtn.isDisabled()}`);
	}

	// Fail test if there were page errors
	if (pageErrors.length > 0) {
		console.log('\n=== ERRORS FOUND ===');
		expect(pageErrors).toHaveLength(0);
	}
});
