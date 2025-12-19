<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { AudienceCard } from '$lib/components/lookalike';
	import { Plus, Search, Users, Filter } from 'lucide-svelte';
	import {
		lookalikeApi,
		type LookalikeAudience,
		type AudienceStatus
	} from '$lib/api/lookalike';
	import { toast } from 'svelte-sonner';

	let audiences = $state<LookalikeAudience[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let statusFilter = $state<AudienceStatus | ''>('');
	let currentPage = $state(1);
	let totalPages = $state(1);

	const statuses: { value: AudienceStatus | ''; label: string }[] = [
		{ value: '', label: 'All Statuses' },
		{ value: 'draft', label: 'Draft' },
		{ value: 'building', label: 'Building' },
		{ value: 'ready', label: 'Ready' },
		{ value: 'expired', label: 'Expired' }
	];

	async function loadAudiences() {
		loading = true;
		try {
			const response = await lookalikeApi.list({
				search: searchQuery || undefined,
				status: statusFilter || undefined,
				page: currentPage,
				per_page: 12
			});
			audiences = response.data;
			totalPages = response.meta.last_page;
		} catch (error) {
			toast.error('Failed to load lookalike audiences');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function handleBuild(audience: LookalikeAudience) {
		try {
			await lookalikeApi.build(audience.id);
			toast.success('Audience build started');
			loadAudiences();
		} catch (error) {
			toast.error('Failed to start build');
		}
	}

	async function handleDelete(audience: LookalikeAudience) {
		if (!confirm(`Are you sure you want to delete "${audience.name}"?`)) return;
		try {
			await lookalikeApi.delete(audience.id);
			toast.success('Audience deleted');
			loadAudiences();
		} catch (error) {
			toast.error('Failed to delete audience');
		}
	}

	onMount(() => {
		loadAudiences();
	});

	$effect(() => {
		searchQuery;
		statusFilter;
		currentPage;
		loadAudiences();
	});

	const readyCount = $derived(audiences.filter((a) => a.status === 'ready').length);
	const totalMatches = $derived(audiences.reduce((sum, a) => sum + (a.matches_count || a.match_count || 0), 0));
</script>

<svelte:head>
	<title>Lookalike Audiences | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<Users class="h-6 w-6" />
				Lookalike Audiences
			</h1>
			<p class="text-muted-foreground">
				Find prospects similar to your best customers
			</p>
		</div>
		<Button onclick={() => goto('/lookalike-audiences/new')}>
			<Plus class="mr-2 h-4 w-4" />
			New Audience
		</Button>
	</div>

	<!-- Stats -->
	<div class="flex items-center gap-4">
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Ready:</span>
			{readyCount}
		</Badge>
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Total Matches:</span>
			{totalMatches.toLocaleString()}
		</Badge>
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Audiences:</span>
			{audiences.length}
		</Badge>
	</div>

	<!-- Filters -->
	<div class="flex flex-wrap items-center gap-4">
		<div class="relative flex-1 max-w-sm">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				placeholder="Search audiences..."
				class="pl-9"
				bind:value={searchQuery}
			/>
		</div>

		<Select.Root
			type="single"
			value={statusFilter}
			onValueChange={(v) => (statusFilter = v as AudienceStatus | '')}
		>
			<Select.Trigger class="w-40">
				<Filter class="mr-2 h-4 w-4" />
				{statuses.find((s) => s.value === statusFilter)?.label || 'All Statuses'}
			</Select.Trigger>
			<Select.Content>
				{#each statuses as status}
					<Select.Item value={status.value}>{status.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Audiences Grid -->
	{#if loading}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each Array(6) as _}
				<Skeleton class="h-48" />
			{/each}
		</div>
	{:else if audiences.length === 0}
		<div class="flex flex-col items-center justify-center py-12 text-center">
			<Users class="h-12 w-12 text-muted-foreground mb-4" />
			<h3 class="text-lg font-medium">No lookalike audiences found</h3>
			<p class="text-muted-foreground mb-4">
				{searchQuery || statusFilter
					? 'Try adjusting your filters'
					: 'Create your first audience to find similar prospects'}
			</p>
			<Button onclick={() => goto('/lookalike-audiences/new')}>
				<Plus class="mr-2 h-4 w-4" />
				Create Audience
			</Button>
		</div>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each audiences as audience (audience.id)}
				<AudienceCard
					{audience}
					onBuild={() => handleBuild(audience)}
					onDelete={() => handleDelete(audience)}
				/>
			{/each}
		</div>

		<!-- Pagination -->
		{#if totalPages > 1}
			<div class="flex items-center justify-center gap-2">
				<Button
					variant="outline"
					size="sm"
					disabled={currentPage === 1}
					onclick={() => (currentPage = currentPage - 1)}
				>
					Previous
				</Button>
				<span class="text-sm text-muted-foreground">
					Page {currentPage} of {totalPages}
				</span>
				<Button
					variant="outline"
					size="sm"
					disabled={currentPage === totalPages}
					onclick={() => (currentPage = currentPage + 1)}
				>
					Next
				</Button>
			</div>
		{/if}
	{/if}
</div>
