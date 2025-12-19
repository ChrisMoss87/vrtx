<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import { ArrowLeft, Edit, Globe, ExternalLink, Copy } from 'lucide-svelte';
	import {
		landingPageApi,
		landingPageVariantApi,
		type LandingPage,
		type PageAnalytics as PageAnalyticsType,
		type VariantComparison,
		getStatusColor
	} from '$lib/api/landing-pages';
	import { PageAnalytics, VariantManager } from '$lib/components/landing-pages';
	import { toast } from 'svelte-sonner';

	const pageId = $derived(parseInt($page.params.id || '0'));

	let landingPage = $state<LandingPage | null>(null);
	let analytics = $state<PageAnalyticsType | null>(null);
	let variants = $state<VariantComparison[]>([]);
	let loading = $state(true);
	let analyticsLoading = $state(false);

	let activeTab = $state('analytics');

	onMount(() => {
		loadPage();
	});

	async function loadPage() {
		loading = true;
		try {
			landingPage = await landingPageApi.get(pageId);
			await Promise.all([loadAnalytics(), loadVariants()]);
		} catch (error) {
			toast.error('Failed to load landing page');
		} finally {
			loading = false;
		}
	}

	async function loadAnalytics(startDate?: string, endDate?: string) {
		analyticsLoading = true;
		try {
			analytics = await landingPageApi.analytics(pageId, {
				start_date: startDate,
				end_date: endDate
			});
		} catch (error) {
			console.error('Failed to load analytics:', error);
		} finally {
			analyticsLoading = false;
		}
	}

	async function loadVariants() {
		try {
			variants = await landingPageVariantApi.list(pageId);
		} catch (error) {
			console.error('Failed to load variants:', error);
		}
	}

	async function handleCreateVariant() {
		try {
			await landingPageVariantApi.create(pageId, {});
			toast.success('Variant created');
			loadVariants();
		} catch (error) {
			toast.error('Failed to create variant');
		}
	}

	async function handleDeleteVariant(variantId: number) {
		if (!confirm('Are you sure you want to delete this variant?')) return;
		try {
			await landingPageVariantApi.delete(pageId, variantId);
			toast.success('Variant deleted');
			loadVariants();
		} catch (error) {
			toast.error('Failed to delete variant');
		}
	}

	async function handleToggleVariant(variantId: number, isActive: boolean) {
		try {
			await landingPageVariantApi.update(pageId, variantId, { is_active: isActive });
			toast.success(isActive ? 'Variant activated' : 'Variant paused');
			loadVariants();
		} catch (error) {
			toast.error('Failed to update variant');
		}
	}

	async function handleDeclareWinner(variantId: number) {
		if (!confirm('Declare this variant as the winner? This will end the A/B test.')) return;
		try {
			await landingPageVariantApi.declareWinner(pageId, variantId);
			toast.success('Winner declared!');
			loadVariants();
			loadPage();
		} catch (error) {
			toast.error('Failed to declare winner');
		}
	}

	async function handleUpdateTraffic(variantId: number, percentage: number) {
		try {
			await landingPageVariantApi.update(pageId, variantId, { traffic_percentage: percentage });
			loadVariants();
		} catch (error) {
			toast.error('Failed to update traffic split');
		}
	}

	function getPageUrl(): string {
		return landingPage ? `/p/${landingPage.slug}` : '';
	}

	function copyPageUrl() {
		const url = window.location.origin + getPageUrl();
		navigator.clipboard.writeText(url);
		toast.success('URL copied to clipboard');
	}
</script>

<div class="container py-6">
	<Button variant="ghost" href="/landing-pages" class="mb-4">
		<ArrowLeft class="mr-1 h-4 w-4" />
		Back to Landing Pages
	</Button>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading...</div>
		</div>
	{:else if landingPage}
		<!-- Header -->
		<div class="mb-6 flex items-start justify-between">
			<div>
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-bold">{landingPage.name}</h1>
					<Badge class={getStatusColor(landingPage.status)}>
						{landingPage.status}
					</Badge>
				</div>
				{#if landingPage.description}
					<p class="text-muted-foreground mt-1">{landingPage.description}</p>
				{/if}
				<div class="mt-2 flex items-center gap-2">
					<code class="bg-muted rounded px-2 py-1 text-sm">{getPageUrl()}</code>
					<Button variant="ghost" size="sm" onclick={copyPageUrl}>
						<Copy class="h-4 w-4" />
					</Button>
					{#if landingPage.status === 'published'}
						<a
							href={getPageUrl()}
							target="_blank"
							rel="noopener noreferrer"
							class="text-primary inline-flex items-center gap-1 text-sm hover:underline"
						>
							View live <ExternalLink class="h-3 w-3" />
						</a>
					{/if}
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Button variant="outline" href={`/landing-pages/${pageId}/edit`}>
					<Edit class="mr-1 h-4 w-4" />
					Edit Page
				</Button>
			</div>
		</div>

		<!-- Stats Summary -->
		<div class="mb-6 grid gap-4 sm:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-muted-foreground text-sm">Total Views</div>
					<div class="text-2xl font-bold">
						{analytics?.totals.views.toLocaleString() || '—'}
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-muted-foreground text-sm">Unique Visitors</div>
					<div class="text-2xl font-bold">
						{analytics?.totals.unique_visitors.toLocaleString() || '—'}
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-muted-foreground text-sm">Conversions</div>
					<div class="text-2xl font-bold">
						{analytics?.totals.form_submissions.toLocaleString() || '—'}
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="text-muted-foreground text-sm">Conversion Rate</div>
					<div class="text-2xl font-bold">
						{analytics ? ((analytics.totals.conversion_rate * 100).toFixed(1) + '%') : '—'}
					</div>
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List class="mb-6">
				<Tabs.Trigger value="analytics">Analytics</Tabs.Trigger>
				<Tabs.Trigger value="variants">A/B Testing</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="analytics">
				<PageAnalytics
					{analytics}
					loading={analyticsLoading}
					onDateRangeChange={(start, end) => loadAnalytics(start, end)}
				/>
			</Tabs.Content>

			<Tabs.Content value="variants">
				<VariantManager
					{variants}
					isAbTestingEnabled={landingPage.is_ab_testing_enabled}
					onCreateVariant={handleCreateVariant}
					onDeleteVariant={handleDeleteVariant}
					onToggleVariant={handleToggleVariant}
					onDeclareWinner={handleDeclareWinner}
					onUpdateTraffic={handleUpdateTraffic}
				/>
			</Tabs.Content>
		</Tabs.Root>
	{:else}
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<p class="text-muted-foreground">Landing page not found</p>
			</Card.Content>
		</Card.Root>
	{/if}
</div>
