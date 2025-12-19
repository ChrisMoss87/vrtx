<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { TestResults, VariantEditor } from '$lib/components/ab-testing';
	import {
		ArrowLeft,
		Play,
		Pause,
		Square,
		Edit,
		Trash2,
		FlaskConical,
		Settings,
		BarChart3,
		Layers
	} from 'lucide-svelte';
	import {
		abTestApi,
		abTestVariantApi,
		getTestTypeLabel,
		getGoalLabel,
		type AbTest,
		type TestStatistics
	} from '$lib/api/ab-tests';
	import { toast } from 'svelte-sonner';

	let test = $state<AbTest | null>(null);
	let statistics = $state<TestStatistics | null>(null);
	let loading = $state(true);
	let activeTab = $state('results');

	const testId = $derived(parseInt($page.params.id || '0'));

	async function loadTest() {
		loading = true;
		try {
			const [testData, statsData] = await Promise.all([
				abTestApi.get(testId),
				abTestApi.statistics(testId)
			]);
			test = testData;
			statistics = statsData;
		} catch (error) {
			toast.error('Failed to load test');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function handleStart() {
		if (!test) return;
		try {
			await abTestApi.start(test.id);
			toast.success('Test started');
			loadTest();
		} catch (error: any) {
			toast.error(error.response?.data?.message || 'Failed to start test');
		}
	}

	async function handlePause() {
		if (!test) return;
		try {
			await abTestApi.pause(test.id);
			toast.success('Test paused');
			loadTest();
		} catch (error) {
			toast.error('Failed to pause test');
		}
	}

	async function handleResume() {
		if (!test) return;
		try {
			await abTestApi.resume(test.id);
			toast.success('Test resumed');
			loadTest();
		} catch (error) {
			toast.error('Failed to resume test');
		}
	}

	async function handleComplete() {
		if (!test) return;
		try {
			await abTestApi.complete(test.id);
			toast.success('Test completed');
			loadTest();
		} catch (error) {
			toast.error('Failed to complete test');
		}
	}

	async function handleDelete() {
		if (!test) return;
		if (!confirm(`Are you sure you want to delete "${test.name}"?`)) return;
		try {
			await abTestApi.delete(test.id);
			toast.success('Test deleted');
			goto('/ab-tests');
		} catch (error) {
			toast.error('Failed to delete test');
		}
	}

	async function handleDeclareWinner(variantId: number) {
		if (!test) return;
		try {
			await abTestVariantApi.declareWinner(test.id, variantId);
			toast.success('Winner declared');
			loadTest();
		} catch (error) {
			toast.error('Failed to declare winner');
		}
	}

	async function handleVariantSave(variantId: number, content: Record<string, unknown>) {
		if (!test) return;
		try {
			await abTestVariantApi.update(test.id, variantId, { content });
			toast.success('Variant updated');
			loadTest();
		} catch (error) {
			toast.error('Failed to update variant');
		}
	}

	onMount(() => {
		loadTest();
	});

	const statusConfig = $derived.by(() => {
		if (!test) return { label: '', class: '' };
		switch (test.status) {
			case 'draft':
				return { label: 'Draft', class: 'bg-gray-100 text-gray-700' };
			case 'running':
				return { label: 'Running', class: 'bg-green-100 text-green-700' };
			case 'paused':
				return { label: 'Paused', class: 'bg-yellow-100 text-yellow-700' };
			case 'completed':
				return { label: 'Completed', class: 'bg-blue-100 text-blue-700' };
			default:
				return { label: test.status, class: 'bg-gray-100 text-gray-700' };
		}
	});

	function formatDate(date: string | null): string {
		if (!date) return '-';
		return new Date(date).toLocaleString();
	}
</script>

<svelte:head>
	<title>{test?.name || 'A/B Test'} | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	{#if loading}
		<Skeleton class="h-12 w-64" />
		<Skeleton class="h-96" />
	{:else if test}
		<!-- Header -->
		<div class="flex items-start justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/ab-tests')}>
					<ArrowLeft class="h-5 w-5" />
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<FlaskConical class="h-6 w-6" />
						<h1 class="text-2xl font-bold">{test.name}</h1>
						<Badge class={statusConfig.class}>{statusConfig.label}</Badge>
					</div>
					<p class="text-muted-foreground">{test.description || 'No description'}</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				{#if test.status === 'draft'}
					<Button onclick={handleStart}>
						<Play class="mr-2 h-4 w-4" />
						Start Test
					</Button>
				{:else if test.status === 'running'}
					<Button variant="outline" onclick={handlePause}>
						<Pause class="mr-2 h-4 w-4" />
						Pause
					</Button>
					<Button variant="outline" onclick={handleComplete}>
						<Square class="mr-2 h-4 w-4" />
						End Test
					</Button>
				{:else if test.status === 'paused'}
					<Button onclick={handleResume}>
						<Play class="mr-2 h-4 w-4" />
						Resume
					</Button>
					<Button variant="outline" onclick={handleComplete}>
						<Square class="mr-2 h-4 w-4" />
						End Test
					</Button>
				{/if}
				{#if test.status === 'draft' || test.status === 'paused'}
					<Button variant="outline" onclick={() => goto(`/ab-tests/${test!.id}/edit`)}>
						<Edit class="mr-2 h-4 w-4" />
						Edit
					</Button>
				{/if}
				{#if test.status !== 'running'}
					<Button variant="outline" onclick={handleDelete}>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete
					</Button>
				{/if}
			</div>
		</div>

		<!-- Test Info -->
		<Card.Root>
			<Card.Content class="pt-6">
				<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
					<div>
						<span class="text-muted-foreground block">Type</span>
						<span class="font-medium">{getTestTypeLabel(test.type)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Goal</span>
						<span class="font-medium">{getGoalLabel(test.goal)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Confidence Level</span>
						<span class="font-medium">{test.confidence_level}%</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Min Sample Size</span>
						<span class="font-medium">{test.min_sample_size} per variant</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Created</span>
						<span class="font-medium">{formatDate(test.created_at)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Started</span>
						<span class="font-medium">{formatDate(test.started_at)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Ended</span>
						<span class="font-medium">{formatDate(test.ended_at)}</span>
					</div>
					<div>
						<span class="text-muted-foreground block">Auto-select Winner</span>
						<span class="font-medium">{test.auto_select_winner ? 'Yes' : 'No'}</span>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List>
				<Tabs.Trigger value="results">
					<BarChart3 class="mr-2 h-4 w-4" />
					Results
				</Tabs.Trigger>
				<Tabs.Trigger value="variants">
					<Layers class="mr-2 h-4 w-4" />
					Variants
				</Tabs.Trigger>
				<Tabs.Trigger value="settings">
					<Settings class="mr-2 h-4 w-4" />
					Settings
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="results" class="pt-4">
				{#if statistics}
					<TestResults
						{statistics}
						goal={test.goal}
						minSampleSize={test.min_sample_size}
						confidenceLevel={test.confidence_level}
						onDeclareWinner={test.status !== 'completed' ? handleDeclareWinner : undefined}
					/>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="variants" class="pt-4 space-y-4">
				{#each test.variants || [] as variant}
					<VariantEditor
						{variant}
						testType={test.type}
						onSave={(content) => handleVariantSave(variant.id, content)}
					/>
				{/each}
			</Tabs.Content>

			<Tabs.Content value="settings" class="pt-4">
				<Card.Root>
					<Card.Header>
						<Card.Title>Test Configuration</Card.Title>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="grid grid-cols-2 gap-4">
							<div>
								<label class="text-sm text-muted-foreground">Test Name</label>
								<p class="font-medium">{test.name}</p>
							</div>
							<div>
								<label class="text-sm text-muted-foreground">Entity Type</label>
								<p class="font-medium capitalize">{test.entity_type.replace('_', ' ')}</p>
							</div>
							<div>
								<label class="text-sm text-muted-foreground">Entity ID</label>
								<p class="font-medium">{test.entity_id}</p>
							</div>
							<div>
								<label class="text-sm text-muted-foreground">Scheduled End</label>
								<p class="font-medium">{formatDate(test.scheduled_end_at)}</p>
							</div>
						</div>
						{#if test.description}
							<div>
								<label class="text-sm text-muted-foreground">Description</label>
								<p class="font-medium">{test.description}</p>
							</div>
						{/if}
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	{:else}
		<div class="text-center py-12">
			<p class="text-muted-foreground">Test not found</p>
			<Button variant="outline" class="mt-4" onclick={() => goto('/ab-tests')}>
				Go Back
			</Button>
		</div>
	{/if}
</div>
