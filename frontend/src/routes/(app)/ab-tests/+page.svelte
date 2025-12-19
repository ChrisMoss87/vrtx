<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { TestCard } from '$lib/components/ab-testing';
	import {
		Plus,
		Search,
		FlaskConical,
		Filter,
		LayoutGrid,
		List
	} from 'lucide-svelte';
	import {
		abTestApi,
		type AbTest,
		type AbTestStatus,
		type AbTestType
	} from '$lib/api/ab-tests';
	import { toast } from 'svelte-sonner';

	let tests = $state<AbTest[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let statusFilter = $state<AbTestStatus | ''>('');
	let typeFilter = $state<AbTestType | ''>('');
	let viewMode = $state<'grid' | 'list'>('grid');
	let currentPage = $state(1);
	let totalPages = $state(1);

	const statuses: { value: AbTestStatus | ''; label: string }[] = [
		{ value: '', label: 'All Statuses' },
		{ value: 'draft', label: 'Draft' },
		{ value: 'running', label: 'Running' },
		{ value: 'paused', label: 'Paused' },
		{ value: 'completed', label: 'Completed' }
	];

	const types: { value: AbTestType | ''; label: string }[] = [
		{ value: '', label: 'All Types' },
		{ value: 'email_subject', label: 'Email Subject' },
		{ value: 'email_content', label: 'Email Content' },
		{ value: 'cta_button', label: 'CTA Button' },
		{ value: 'send_time', label: 'Send Time' },
		{ value: 'form_layout', label: 'Form Layout' }
	];

	async function loadTests() {
		loading = true;
		try {
			const response = await abTestApi.list({
				search: searchQuery || undefined,
				status: statusFilter || undefined,
				type: typeFilter || undefined,
				page: currentPage,
				per_page: 12
			});
			tests = response.data;
			totalPages = response.meta.last_page;
		} catch (error) {
			toast.error('Failed to load A/B tests');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function handleStart(test: AbTest) {
		try {
			await abTestApi.start(test.id);
			toast.success('Test started');
			loadTests();
		} catch (error) {
			toast.error('Failed to start test');
		}
	}

	async function handlePause(test: AbTest) {
		try {
			await abTestApi.pause(test.id);
			toast.success('Test paused');
			loadTests();
		} catch (error) {
			toast.error('Failed to pause test');
		}
	}

	async function handleResume(test: AbTest) {
		try {
			await abTestApi.resume(test.id);
			toast.success('Test resumed');
			loadTests();
		} catch (error) {
			toast.error('Failed to resume test');
		}
	}

	async function handleComplete(test: AbTest) {
		try {
			await abTestApi.complete(test.id);
			toast.success('Test completed');
			loadTests();
		} catch (error) {
			toast.error('Failed to complete test');
		}
	}

	async function handleDelete(test: AbTest) {
		if (!confirm(`Are you sure you want to delete "${test.name}"?`)) return;
		try {
			await abTestApi.delete(test.id);
			toast.success('Test deleted');
			loadTests();
		} catch (error) {
			toast.error('Failed to delete test');
		}
	}

	onMount(() => {
		loadTests();
	});

	$effect(() => {
		// Re-fetch when filters change
		searchQuery;
		statusFilter;
		typeFilter;
		currentPage;
		loadTests();
	});

	const runningCount = $derived(tests.filter((t) => t.status === 'running').length);
	const completedCount = $derived(tests.filter((t) => t.status === 'completed').length);
</script>

<svelte:head>
	<title>A/B Tests | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<FlaskConical class="h-6 w-6" />
				A/B Tests
			</h1>
			<p class="text-muted-foreground">
				Create and manage experiments to optimize your content
			</p>
		</div>
		<Button onclick={() => goto('/ab-tests/new')}>
			<Plus class="mr-2 h-4 w-4" />
			New Test
		</Button>
	</div>

	<!-- Stats -->
	<div class="flex items-center gap-4">
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Running:</span>
			{runningCount}
		</Badge>
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Completed:</span>
			{completedCount}
		</Badge>
		<Badge variant="outline" class="px-3 py-1">
			<span class="font-normal text-muted-foreground mr-1">Total:</span>
			{tests.length}
		</Badge>
	</div>

	<!-- Filters -->
	<div class="flex flex-wrap items-center gap-4">
		<div class="relative flex-1 max-w-sm">
			<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
			<Input
				placeholder="Search tests..."
				class="pl-9"
				bind:value={searchQuery}
			/>
		</div>

		<Select.Root
			type="single"
			value={statusFilter}
			onValueChange={(v) => (statusFilter = v as AbTestStatus | '')}
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

		<Select.Root
			type="single"
			value={typeFilter}
			onValueChange={(v) => (typeFilter = v as AbTestType | '')}
		>
			<Select.Trigger class="w-40">
				{types.find((t) => t.value === typeFilter)?.label || 'All Types'}
			</Select.Trigger>
			<Select.Content>
				{#each types as type}
					<Select.Item value={type.value}>{type.label}</Select.Item>
				{/each}
			</Select.Content>
		</Select.Root>

		<div class="flex items-center border rounded-md">
			<Button
				variant={viewMode === 'grid' ? 'secondary' : 'ghost'}
				size="icon"
				class="rounded-r-none"
				onclick={() => (viewMode = 'grid')}
			>
				<LayoutGrid class="h-4 w-4" />
			</Button>
			<Button
				variant={viewMode === 'list' ? 'secondary' : 'ghost'}
				size="icon"
				class="rounded-l-none"
				onclick={() => (viewMode = 'list')}
			>
				<List class="h-4 w-4" />
			</Button>
		</div>
	</div>

	<!-- Tests Grid/List -->
	{#if loading}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each Array(6) as _}
				<Skeleton class="h-48" />
			{/each}
		</div>
	{:else if tests.length === 0}
		<div class="flex flex-col items-center justify-center py-12 text-center">
			<FlaskConical class="h-12 w-12 text-muted-foreground mb-4" />
			<h3 class="text-lg font-medium">No A/B tests found</h3>
			<p class="text-muted-foreground mb-4">
				{searchQuery || statusFilter || typeFilter
					? 'Try adjusting your filters'
					: 'Create your first test to start optimizing'}
			</p>
			<Button onclick={() => goto('/ab-tests/new')}>
				<Plus class="mr-2 h-4 w-4" />
				Create Test
			</Button>
		</div>
	{:else}
		<div
			class={viewMode === 'grid'
				? 'grid gap-4 md:grid-cols-2 lg:grid-cols-3'
				: 'space-y-4'}
		>
			{#each tests as test (test.id)}
				<TestCard
					{test}
					onStart={() => handleStart(test)}
					onPause={() => handlePause(test)}
					onResume={() => handleResume(test)}
					onComplete={() => handleComplete(test)}
					onDelete={() => handleDelete(test)}
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
