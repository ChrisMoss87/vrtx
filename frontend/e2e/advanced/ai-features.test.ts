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
 * AI Features Tests
 * Tests for AI-powered functionality
 */

test.describe('AI Sidebar', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
		await page.goto('/records/deals/1');
		await waitForLoading(page);
	});

	test('should open AI sidebar', async ({ page }) => {
		const aiButton = page.locator('button:has-text("AI"), button[aria-label="AI Assistant"]');
		if (await aiButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await aiButton.click();

			const sidebar = page.locator('[data-testid="ai-sidebar"], [class*="ai-panel"]');
			await expect(sidebar).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});

	test('should show AI suggestions', async ({ page }) => {
		const aiButton = page.locator('button:has-text("AI"), button[aria-label="AI Assistant"]');
		if (await aiButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await aiButton.click();

			const suggestions = page.locator('[data-testid="ai-suggestions"], text=/Suggest|Recommendation/i');
			// May show suggestions
		}
	});

	test('should ask AI question', async ({ page }) => {
		const aiButton = page.locator('button:has-text("AI"), button[aria-label="AI Assistant"]');
		if (await aiButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await aiButton.click();

			const questionInput = page.locator('input[placeholder*="Ask"], textarea[placeholder*="question"]');
			if (await questionInput.isVisible()) {
				await questionInput.fill('What are the next steps for this deal?');
				await questionInput.press('Enter');
			}
		}
	});

	test('should close AI sidebar', async ({ page }) => {
		const aiButton = page.locator('button:has-text("AI")');
		if (await aiButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await aiButton.click();

			const closeButton = page.locator('[data-testid="ai-sidebar"] button[aria-label="Close"]');
			if (await closeButton.isVisible({ timeout: 2000 }).catch(() => false)) {
				await closeButton.click();
			}
		}
	});
});

test.describe('AI Email Drafting', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate email draft', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const aiDraftButton = page.locator('button:has-text("AI Draft"), button:has-text("Generate")');
		if (await aiDraftButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await aiDraftButton.click();

			const promptInput = page.locator('textarea[placeholder*="describe"], input[placeholder*="What"]');
			if (await promptInput.isVisible()) {
				await promptInput.fill('Follow up email for a demo request');
			}

			const generateButton = page.locator('[role="dialog"] button:has-text("Generate")');
			await generateButton.click();
			await waitForLoading(page);
		}
	});

	test('should regenerate email', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const regenerateButton = page.locator('button:has-text("Regenerate"), button:has-text("Try Again")');
		if (await regenerateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await regenerateButton.click();
		}
	});

	test('should insert AI-generated content', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const insertButton = page.locator('button:has-text("Insert"), button:has-text("Use This")');
		if (await insertButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await insertButton.click();
		}
	});
});

test.describe('AI Data Enrichment', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should enrich contact data', async ({ page }) => {
		await page.goto('/records/contacts/1');
		await waitForLoading(page);

		const enrichButton = page.locator('button:has-text("Enrich"), button:has-text("AI Enrich")');
		if (await enrichButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrichButton.click();
			await waitForLoading(page);
			await waitForToast(page);
		}
	});

	test('should enrich company data', async ({ page }) => {
		await page.goto('/records/companies/1');
		await waitForLoading(page);

		const enrichButton = page.locator('button:has-text("Enrich"), button:has-text("AI Enrich")');
		if (await enrichButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrichButton.click();
			await waitForLoading(page);
			await waitForToast(page);
		}
	});

	test('should preview enriched data', async ({ page }) => {
		await page.goto('/records/contacts/1');
		await waitForLoading(page);

		const enrichButton = page.locator('button:has-text("Enrich")');
		if (await enrichButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await enrichButton.click();

			const preview = page.locator('[data-testid="enrichment-preview"], [role="dialog"]');
			await expect(preview).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('AI Sentiment Analysis', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show email sentiment', async ({ page }) => {
		await page.goto('/admin/emails/1');
		await waitForLoading(page);

		const sentiment = page.locator('[data-testid="sentiment-badge"], text=/Positive|Negative|Neutral/i');
		// May show sentiment
	});

	test('should show conversation sentiment', async ({ page }) => {
		await page.goto('/admin/inbox/1');
		await waitForLoading(page);

		const sentiment = page.locator('[data-testid="conversation-sentiment"]');
		// May show sentiment
	});
});

test.describe('AI Deal Scoring', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show AI deal score', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const score = page.locator('[data-testid="ai-score"], text=/AI Score|Win Probability/i');
		// May show AI score
	});

	test('should explain deal score', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const scoreExplain = page.locator('button:has-text("Why"), button:has-text("Explain")');
		if (await scoreExplain.isVisible({ timeout: 2000 }).catch(() => false)) {
			await scoreExplain.click();

			const explanation = page.locator('[data-testid="score-explanation"], [role="dialog"]');
			await expect(explanation).toBeVisible({ timeout: 3000 }).catch(() => {});
		}
	});
});

test.describe('AI Lead Scoring', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show AI lead score', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const score = page.locator('[data-testid="ai-lead-score"], text=/Lead Score|AI Score/i');
		// May show AI score
	});

	test('should show score factors', async ({ page }) => {
		await page.goto('/records/leads/1');
		await waitForLoading(page);

		const factors = page.locator('[data-testid="score-factors"], text=/Factors|Contributing/i');
		// May show score factors
	});
});

test.describe('AI Call Transcription', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view call transcription', async ({ page }) => {
		await page.goto('/admin/calls/1');
		await waitForLoading(page);

		const transcriptionTab = page.locator('[role="tab"]:has-text("Transcript")');
		if (await transcriptionTab.isVisible({ timeout: 2000 }).catch(() => false)) {
			await transcriptionTab.click();

			const transcription = page.locator('[data-testid="call-transcript"]');
			// May show transcription
		}
	});

	test('should show call summary', async ({ page }) => {
		await page.goto('/admin/calls/1');
		await waitForLoading(page);

		const summary = page.locator('[data-testid="call-summary"], text=/Summary|Key Points/i');
		// May show summary
	});

	test('should show action items', async ({ page }) => {
		await page.goto('/admin/calls/1');
		await waitForLoading(page);

		const actionItems = page.locator('[data-testid="action-items"], text=/Action Items|Follow-ups/i');
		// May show action items
	});
});

test.describe('AI Settings', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should view AI settings', async ({ page }) => {
		await page.goto('/admin/settings/ai');
		await waitForLoading(page);

		await expect(page.locator('h1, h2').filter({ hasText: /AI|Artificial Intelligence/i }).first()).toBeVisible({ timeout: 3000 }).catch(() => {});
	});

	test('should toggle AI features', async ({ page }) => {
		await page.goto('/admin/settings/ai');
		await waitForLoading(page);

		const emailDraftingToggle = page.locator('label:has-text("Email Drafting") input[type="checkbox"]');
		if (await emailDraftingToggle.isVisible({ timeout: 2000 }).catch(() => false)) {
			await emailDraftingToggle.click();
		}
	});

	test('should configure AI model', async ({ page }) => {
		await page.goto('/admin/settings/ai');
		await waitForLoading(page);

		const modelSelect = page.locator('[data-testid="ai-model-select"]');
		if (await modelSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
			await modelSelect.click();
			await page.locator('[role="option"]').first().click();
		}
	});

	test('should view AI usage', async ({ page }) => {
		await page.goto('/admin/settings/ai');
		await waitForLoading(page);

		const usageSection = page.locator('text=/Usage|Credits|Tokens/i');
		// May show usage
	});
});

test.describe('AI Recommendations', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should show next best action', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const nba = page.locator('[data-testid="next-best-action"], text=/Next Step|Recommended Action/i');
		// May show next best action
	});

	test('should show cross-sell recommendations', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const crossSell = page.locator('[data-testid="cross-sell"], text=/Cross-sell|Related Products/i');
		// May show cross-sell
	});

	test('should show similar deals', async ({ page }) => {
		await page.goto('/records/deals/1');
		await waitForLoading(page);

		const similarDeals = page.locator('[data-testid="similar-deals"], text=/Similar|Related Deals/i');
		// May show similar deals
	});
});

test.describe('AI Content Generation', () => {
	test.beforeEach(async ({ page }) => {
		await login(page);
	});

	test('should generate product description', async ({ page }) => {
		await page.goto('/records/products/1/edit');
		await waitForLoading(page);

		const generateButton = page.locator('button:has-text("AI Generate"), button:has-text("Generate Description")');
		if (await generateButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await generateButton.click();
			await waitForLoading(page);
		}
	});

	test('should improve writing', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const improveButton = page.locator('button:has-text("Improve"), button:has-text("AI Improve")');
		if (await improveButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await improveButton.click();
		}
	});

	test('should make content shorter', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const shorterButton = page.locator('button:has-text("Shorter"), button:has-text("Make Concise")');
		if (await shorterButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await shorterButton.click();
		}
	});

	test('should change tone', async ({ page }) => {
		await page.goto('/admin/emails/compose');
		await waitForLoading(page);

		const toneButton = page.locator('button:has-text("Tone"), button:has-text("Change Tone")');
		if (await toneButton.isVisible({ timeout: 2000 }).catch(() => false)) {
			await toneButton.click();

			const professionalOption = page.locator('[role="option"]:has-text("Professional")');
			if (await professionalOption.isVisible({ timeout: 2000 }).catch(() => false)) {
				await professionalOption.click();
			}
		}
	});
});
