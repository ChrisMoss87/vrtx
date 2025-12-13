<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { Progress } from '$lib/components/ui/progress';
	import { AudiencePreview } from '$lib/components/lookalike';
	import {
		ArrowLeft,
		Play,
		Edit,
		Trash2,
		Users,
		Settings,
		BarChart3,
		Download,
		RefreshCcw
	} from 'lucide-svelte';
	import {
		lookalikeApi,
		getSourceTypeLabel,
		getCriteriaLabel,
		type LookalikeAudience,
		type LookalikeMatch,
		type ExportDestination
	} from '$lib/api/lookalike';
	import { toast } from 'svelte-sonner';

	let audience = $state<LookalikeAudience | null>(null);
	let matches = $state<LookalikeMatch[]>([]);
	let loading = $state(true);
	let activeTab = $state('matches');

	const audienceId = $derived(parseInt($page.params.id || '0'));

	async function loadAudience() {
		loading = true;
		try {
			const [audienceData, matchesData] = await Promise.all([
				lookalikeApi.get(audienceId),
				lookalikeApi.matches(audienceId, { per_page: 100 }).catch(() => ({ data: [] }))
			]);
			audience = audienceData;
			matches = matchesData.data;
		} catch (error) {
			toast.error('Failed to load audience');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function handleBuild() {
		if (!audience) return;
		try {
			await lookalikeApi.build(audience.id);
			toast.success('Audience build started');
			loadAudience();
		} catch (error: any) {
			toast.error(error.response?.data?.message || 'Failed to start build');
		}
	}

	async function handleDelete() {
		if (!audience) return;
		if (!confirm(`Are you sure you want to delete "${audience.name}"?`)) return;
		try {
			await lookalikeApi.delete(audience.id);
			toast.success('Audience deleted');
			goto('/lookalike-audiences');
		} catch (error) {
			toast.error('Failed to delete audience');
		}
	}

	async function handleExport(destination: ExportDestination) {
		if (!audience) return;
		try {
			const result = await lookalikeApi.export(audience.id, destination);
			toast.success(`Exported ${result.records_exported} records to ${destination}`);
			loadAudience();
		} catch (error) {
			toast.error('Failed to export audience');
		}
	}

	function handleViewContact(contactId: number) {
		goto(`/records/contacts/${contactId}`);
	}

	onMount(() => {
		loadAudience();
	});

	const statusConfig = $derived.by(() => {
		if (!audience) return { label: '', class: '' };
		switch (audience.status) {
			case 'draft':
				return { label: 'Draft', class: 'bg-gray-100 text-gray-700' };
			case 'building':
				return { label: 'Building', class: 'bg-blue-100 text-blue-700' };
			case 'ready':
				return { label: 'Ready', class: 'bg-green-100 text-green-700' };
			case 'expired':
				return { label: 'Expired', class: 'bg-yellow-100 text-yellow-700' };
			default:
				return { label: audience.status, class: 'bg-gray-100 text-gray-700' };
		}
	});

	function formatDate(date: string | null): string {
		if (!date) return '-';
		return new Date(date).toLocaleString();
	}

	const latestJob = $derived(audience?.build_jobs?.[0]);
</script>

<svelte:head>
	<title>{audience?.name || 'Lookalike Audience'} | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	{#if loading}
		<Skeleton class="h-12 w-64" />
		<Skeleton class="h-96" />
	{:else if audience}
		<!-- Header -->
		<div class="flex items-start justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/lookalike-audiences')}>
					<ArrowLeft class="h-5 w-5" />
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<Users class="h-6 w-6" />
						<h1 class="text-2xl font-bold">{audience.name}</h1>
						<Badge class={statusConfig.class}>{statusConfig.label}</Badge>
					</div>
					<p class="text-muted-foreground">{audience.description || 'No description'}</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				{#if audience.status !== 'building'}
					<Button onclick={handleBuild}>
						{#if audience.status === 'draft'}
							<Play class="mr-2 h-4 w-4" />
							Build Audience
						{:else}
							<RefreshCcw class="mr-2 h-4 w-4" />
							Rebuild
						{/if}
					</Button>
					<Button variant="outline" onclick={() => goto(`/lookalike-audiences/${audience!.id}/edit`)}>
						<Edit class="mr-2 h-4 w-4" />
						Edit
					</Button>
					<Button variant="outline" onclick={handleDelete}>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete
					</Button>
				{/if}
			</div>
		</div>

		<!-- Building Progress -->
		{#if audience.status === 'building' && latestJob}
			<Card.Root class="border-blue-200 bg-blue-50">
				<Card.Content class="pt-6">
					<div class="space-y-2">
						<div class="flex justify-between text-sm">
							<span class="font-medium">Building audience...</span>
							<span>{latestJob.progress}%</span>
						</div>
						<Progress value={latestJob.progress} />
						<div class="text-sm text-muted-foreground">
							{latestJob.records_processed.toLocaleString()} records processed,
							{latestJob.matches_found.toLocaleString()} matches found
						</div>
					</div>
				</Card.Content>
			</Card.Root>
		{/if}

		<!-- Audience Info -->
		<Card.Root>
			<Card.Content class="pt-6">
				<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
					<div>
						<span class="text-muted-foreground block">Source Type</span>
						<span class="font-medium">{getSourceTypeLabel(audience.source_type)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Min Score</span>
						<span class="font-medium">{audience.min_similarity_score}%</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Size Limit</span>
						<span class="font-medium">{audience.size_limit?.toLocaleString() || 'No limit'}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Auto Refresh</span>
						<span class="font-medium">
							{audience.auto_refresh ? audience.refresh_frequency || 'On' : 'Off'}
						</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Source Records</span>
						<span class="font-medium">{audience.source_count.toLocaleString()}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Total Matches</span>
						<span class="font-medium">{audience.match_count.toLocaleString()}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Last Built</span>
						<span class="font-medium">{formatDate(audience.last_built_at)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Last Exported</span>
						<span class="font-medium">{formatDate(audience.last_exported_at)}</span>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List>
				<Tabs.Trigger value="matches">
					<BarChart3 class="mr-2 h-4 w-4" />
					Matches & Preview
				</Tabs.Trigger>
				<Tabs.Trigger value="criteria">
					<Settings class="mr-2 h-4 w-4" />
					Match Criteria
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="matches" class="pt-4">
				{#if audience.status === 'ready' && matches.length > 0}
					<AudiencePreview
						{audience}
						{matches}
						onExport={handleExport}
						onViewContact={handleViewContact}
					/>
				{:else if audience.status === 'draft'}
					<div class="text-center py-12">
						<Users class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
						<h3 class="text-lg font-medium">Audience not built yet</h3>
						<p class="text-muted-foreground mb-4">
							Build this audience to find lookalike contacts
						</p>
						<Button onclick={handleBuild}>
							<Play class="mr-2 h-4 w-4" />
							Build Audience
						</Button>
					</div>
				{:else if audience.status === 'building'}
					<div class="text-center py-12">
						<RefreshCcw class="mx-auto h-12 w-12 text-muted-foreground mb-4 animate-spin" />
						<h3 class="text-lg font-medium">Building audience...</h3>
						<p class="text-muted-foreground">
							Please wait while we find lookalike contacts
						</p>
					</div>
				{:else}
					<div class="text-center py-12">
						<Users class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
						<h3 class="text-lg font-medium">No matches found</h3>
						<p class="text-muted-foreground">
							Try adjusting the match criteria or rebuilding the audience
						</p>
					</div>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="criteria" class="pt-4">
				<Card.Root>
					<Card.Header>
						<Card.Title>Match Criteria & Weights</Card.Title>
						<Card.Description>
							Attributes used to calculate similarity scores
						</Card.Description>
					</Card.Header>
					<Card.Content>
						<div class="space-y-3">
							{#each Object.entries(audience.match_criteria) as [criterion, enabled]}
								<div class="flex items-center justify-between py-2 border-b last:border-0">
									<div class="flex items-center gap-2">
										<Badge variant={enabled ? 'default' : 'secondary'}>
											{enabled ? 'Enabled' : 'Disabled'}
										</Badge>
										<span class="font-medium">{getCriteriaLabel(criterion as any)}</span>
									</div>
									{#if enabled && audience.weights[criterion]}
										<span class="font-mono text-sm">
											Weight: {audience.weights[criterion]}%
										</span>
									{/if}
								</div>
							{/each}
						</div>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	{:else}
		<div class="text-center py-12">
			<p class="text-muted-foreground">Audience not found</p>
			<Button variant="outline" class="mt-4" onclick={() => goto('/lookalike-audiences')}>
				Go Back
			</Button>
		</div>
	{/if}
</div>
