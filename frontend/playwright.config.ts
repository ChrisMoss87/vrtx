import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Test Configuration for VRTX CRM
 *
 * Tests run against a local development server with the multi-tenant setup.
 * The default test tenant is 'acme' with base URL http://acme.vrtx.local
 */
export default defineConfig({
	testDir: 'e2e',

	// Test execution settings
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: [
		['html', { open: 'never' }],
		['list'],
		...(process.env.CI ? [['github' as const]] : [])
	],

	// Global test settings
	use: {
		baseURL: process.env.BASE_URL || 'http://techco.vrtx.local',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 10000,
		navigationTimeout: 30000
	},

	// Test timeout
	timeout: 60000,
	expect: {
		timeout: 10000
	},

	// Output directory for test artifacts
	outputDir: 'test-results',

	// Projects for different browsers
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] }
		},
		// Uncomment for cross-browser testing
		// {
		// 	name: 'firefox',
		// 	use: { ...devices['Desktop Firefox'] }
		// },
		// {
		// 	name: 'webkit',
		// 	use: { ...devices['Desktop Safari'] }
		// },
		// Mobile viewports
		// {
		// 	name: 'mobile-chrome',
		// 	use: { ...devices['Pixel 5'] }
		// }
	],

	// Run local dev server before starting tests
	// Comment out webServer when running against an already-running dev server
	webServer: process.env.SKIP_WEBSERVER
		? undefined
		: {
				command: 'pnpm dev',
				url: 'http://techco.vrtx.local',
				reuseExistingServer: true,
				timeout: 120000
			}
});
