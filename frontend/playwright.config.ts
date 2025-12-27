import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E Test Configuration for VRTX CRM
 *
 * Tests run against a local development server with the multi-tenant setup.
 * The default test tenant is 'techco' with base URL http://techco.vrtx.local
 *
 * Test Suites (run with --project flag):
 * - all: Run all tests (default)
 * - core: Core CRUD, datatable, views, pipelines tests
 * - sales: Quotes, invoices, proposals, deal rooms, forecasting, competitors, quotas
 * - admin: Users, roles/permissions, audit logs, settings, integrations
 * - marketing: Campaigns, landing pages, web forms, cadences, A/B tests
 * - communication: Email, live chat, SMS, shared inbox, scheduling
 * - advanced: Blueprints, approvals, document templates, signatures, wizards, playbooks, AI
 * - cms: CMS pages, media library, knowledge base
 * - support: Tickets, goals, customer health
 * - integration: Cross-feature workflow tests
 *
 * Examples:
 *   pnpm test:e2e --project=sales
 *   pnpm test:e2e --project=core --project=admin
 *   pnpm test:e2e (runs all tests)
 */

// Shared browser settings with authenticated state
const browserSettings = {
	...devices['Desktop Chrome'],
	viewport: { width: 1280, height: 720 },
	storageState: 'e2e/.auth/user.json'
};

export default defineConfig({
	testDir: 'e2e',

	// Global setup authenticates once before all tests
	globalSetup: './e2e/global-setup.ts',

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

	// Test suite projects organized by feature domain
	projects: [
		// Default: run all tests
		{
			name: 'all',
			testMatch: '**/*.test.ts',
			use: browserSettings
		},

		// Core functionality tests
		{
			name: 'core',
			testMatch: ['core/**/*.test.ts', 'datatable*.test.ts'],
			use: browserSettings
		},

		// Sales module tests
		{
			name: 'sales',
			testMatch: 'sales/**/*.test.ts',
			use: browserSettings
		},

		// Admin/settings tests
		{
			name: 'admin',
			testMatch: 'admin/**/*.test.ts',
			use: browserSettings
		},

		// Marketing module tests
		{
			name: 'marketing',
			testMatch: 'marketing/**/*.test.ts',
			use: browserSettings
		},

		// Communication module tests
		{
			name: 'communication',
			testMatch: 'communication/**/*.test.ts',
			use: browserSettings
		},

		// Advanced features tests
		{
			name: 'advanced',
			testMatch: 'advanced/**/*.test.ts',
			use: browserSettings
		},

		// CMS module tests
		{
			name: 'cms',
			testMatch: 'cms/**/*.test.ts',
			use: browserSettings
		},

		// Support module tests
		{
			name: 'support',
			testMatch: 'support/**/*.test.ts',
			use: browserSettings
		},

		// Cross-feature integration tests
		{
			name: 'integration',
			testMatch: 'integration/**/*.test.ts',
			use: browserSettings
		},

		// Smoke tests - quick sanity checks
		{
			name: 'smoke',
			testMatch: ['**/auth.test.ts', '**/navigation.test.ts', 'core/records*.test.ts'],
			use: browserSettings
		},

		// Cross-browser testing (uncomment when needed)
		// {
		// 	name: 'firefox',
		// 	testMatch: '**/*.test.ts',
		// 	use: { ...devices['Desktop Firefox'] }
		// },
		// {
		// 	name: 'webkit',
		// 	testMatch: '**/*.test.ts',
		// 	use: { ...devices['Desktop Safari'] }
		// },
		// {
		// 	name: 'mobile',
		// 	testMatch: '**/*.test.ts',
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
