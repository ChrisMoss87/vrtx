<script lang="ts">
	import { page } from '$app/stores';
	import { onMount, onDestroy } from 'svelte';
	import { browser } from '$app/environment';
	import type { PageElement, PageStyles, PageSettings, SeoSettings } from '$lib/api/landing-pages';
	import { PageElementRenderer } from '$lib/components/landing-pages';

	const slug = $derived($page.params.slug);

	/**
	 * Sanitize custom CSS to prevent CSS injection attacks.
	 * Removes dangerous patterns like javascript:, expression(), @import, behavior, etc.
	 */
	function sanitizeCss(css: string): string {
		if (!css) return '';

		// Remove JavaScript URLs
		let sanitized = css.replace(/javascript\s*:/gi, '');

		// Remove expression() (IE-specific but still dangerous)
		sanitized = sanitized.replace(/expression\s*\([^)]*\)/gi, '');

		// Remove behavior property (IE-specific)
		sanitized = sanitized.replace(/behavior\s*:\s*[^;]+;?/gi, '');

		// Remove @import to prevent loading external stylesheets
		sanitized = sanitized.replace(/@import\s+[^;]+;?/gi, '');

		// Remove url() with data: or javascript: protocols
		sanitized = sanitized.replace(/url\s*\(\s*['"]?\s*(data:|javascript:)[^)]*\)/gi, 'url()');

		// Remove -moz-binding (Firefox XBL binding)
		sanitized = sanitized.replace(/-moz-binding\s*:\s*[^;]+;?/gi, '');

		return sanitized;
	}

	let pageData = $state<{
		id: number;
		name: string;
		slug: string;
		content: PageElement[];
		styles: PageStyles;
		settings: PageSettings;
		seo_settings: SeoSettings;
		web_form_id: number | null;
		thank_you_page_type: 'message' | 'redirect' | 'page';
		thank_you_message: string | null;
		thank_you_redirect_url: string | null;
		favicon_url: string | null;
		og_image_url: string | null;
	} | null>(null);

	let visitId = $state<number | null>(null);
	let variantId = $state<number | null>(null);
	let loading = $state(true);
	let error = $state<string | null>(null);

	let startTime = 0;
	let maxScrollDepth = 0;
	let engagementInterval: ReturnType<typeof setInterval> | null = null;

	onMount(async () => {
		try {
			const response = await fetch(`/api/v1/p/${slug}`, {
				credentials: 'include'
			});

			if (!response.ok) {
				if (response.status === 404) {
					error = 'Page not found';
				} else {
					error = 'Failed to load page';
				}
				return;
			}

			const data = await response.json();
			if (data.success) {
				pageData = data.data;
				visitId = data.visit_id;
				variantId = data.variant_id;

				// Set up engagement tracking
				startTime = Date.now();
				setupScrollTracking();
				startEngagementTracking();

				// Set document title
				if (pageData?.seo_settings?.title) {
					document.title = pageData.seo_settings.title;
				} else if (pageData?.name) {
					document.title = pageData.name;
				}
			}
		} catch (err) {
			error = 'Failed to load page';
		} finally {
			loading = false;
		}
	});

	onDestroy(() => {
		if (engagementInterval) {
			clearInterval(engagementInterval);
		}
		// Send final engagement data
		if (browser && visitId) {
			sendEngagement();
		}
	});

	function setupScrollTracking() {
		if (!browser) return;

		const handleScroll = () => {
			const scrollTop = window.scrollY;
			const docHeight = document.documentElement.scrollHeight - window.innerHeight;
			const scrollPercent = docHeight > 0 ? Math.round((scrollTop / docHeight) * 100) : 0;
			maxScrollDepth = Math.max(maxScrollDepth, scrollPercent);
		};

		window.addEventListener('scroll', handleScroll);
		return () => window.removeEventListener('scroll', handleScroll);
	}

	function startEngagementTracking() {
		// Send engagement data every 30 seconds
		engagementInterval = setInterval(sendEngagement, 30000);
	}

	async function sendEngagement() {
		if (!visitId) return;

		const timeOnPage = Math.round((Date.now() - startTime) / 1000);

		try {
			await fetch(`/api/v1/p/${slug}/engagement`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					visit_id: visitId,
					time_on_page: timeOnPage,
					scroll_depth: maxScrollDepth
				})
			});
		} catch (err) {
			// Silent fail
		}
	}

	function getBackgroundStyle(): string {
		const parts: string[] = [];
		if (pageData?.settings?.background_color) {
			parts.push(`background-color: ${pageData.settings.background_color}`);
		}
		if (pageData?.settings?.background_image) {
			parts.push(`background-image: url(${pageData.settings.background_image})`);
			parts.push('background-size: cover');
			parts.push('background-position: center');
		}
		return parts.join('; ');
	}

	function getFontStyle(): string {
		const parts: string[] = [];
		if (pageData?.styles?.body_font) {
			parts.push(`font-family: ${pageData.styles.body_font}`);
		}
		return parts.join('; ');
	}
</script>

<svelte:head>
	{#if pageData}
		<title>{pageData.seo_settings?.title || pageData.name}</title>
		{#if pageData.seo_settings?.description}
			<meta name="description" content={pageData.seo_settings.description} />
		{/if}
		{#if pageData.seo_settings?.keywords?.length}
			<meta name="keywords" content={pageData.seo_settings.keywords.join(', ')} />
		{/if}
		{#if pageData.seo_settings?.canonical_url}
			<link rel="canonical" href={pageData.seo_settings.canonical_url} />
		{/if}
		{#if pageData.seo_settings?.no_index}
			<meta name="robots" content="noindex" />
		{/if}
		{#if pageData.og_image_url}
			<meta property="og:image" content={pageData.og_image_url} />
		{/if}
		{#if pageData.favicon_url}
			<link rel="icon" href={pageData.favicon_url} />
		{/if}
		{#if pageData.styles?.custom_css}
			{@html `<style>${sanitizeCss(pageData.styles.custom_css)}</style>`}
		{/if}
	{/if}
</svelte:head>

{#if loading}
	<div class="flex min-h-screen items-center justify-center">
		<div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-blue-500"></div>
	</div>
{:else if error}
	<div class="flex min-h-screen flex-col items-center justify-center">
		<h1 class="mb-2 text-2xl font-bold text-gray-900">Page Not Found</h1>
		<p class="text-gray-600">The page you're looking for doesn't exist or has been removed.</p>
	</div>
{:else if pageData}
	<div class="min-h-screen" style={`${getBackgroundStyle()}; ${getFontStyle()}`}>
		<div
			class="mx-auto"
			style={pageData.settings?.max_width ? `max-width: ${pageData.settings.max_width}` : ''}
		>
			{#if pageData.content.length === 0}
				<div class="flex min-h-screen items-center justify-center">
					<p class="text-gray-500">This page is empty</p>
				</div>
			{:else}
				{#each pageData.content as element}
					<PageElementRenderer {element} styles={pageData.styles} />
				{/each}
			{/if}
		</div>
	</div>
{/if}
