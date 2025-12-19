<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { ABTestBuilder } from '$lib/components/ab-testing';
	import { abTestApi, type AbTest, type AbTestEntityType } from '$lib/api/ab-tests';
	import { toast } from 'svelte-sonner';

	let test = $state<AbTest | null>(null);
	let loading = $state(true);

	const testId = $derived(parseInt($page.params.id || '0'));

	// In a real app, these would be fetched from respective APIs
	let entityOptions = $state<{ type: AbTestEntityType; id: number; name: string }[]>([
		{ type: 'email_template', id: 1, name: 'Welcome Email' },
		{ type: 'email_template', id: 2, name: 'Newsletter Template' },
		{ type: 'campaign', id: 1, name: 'Summer Sale Campaign' },
		{ type: 'campaign', id: 2, name: 'Product Launch' },
		{ type: 'web_form', id: 1, name: 'Contact Form' },
		{ type: 'web_form', id: 2, name: 'Newsletter Signup' }
	]);

	async function loadTest() {
		loading = true;
		try {
			test = await abTestApi.get(testId);
		} catch (error) {
			toast.error('Failed to load test');
			console.error(error);
		} finally {
			loading = false;
		}
	}

	async function handleSave(data: Partial<AbTest>) {
		if (!test) return;
		try {
			await abTestApi.update(test.id, {
				name: data.name,
				description: data.description || undefined,
				goal: data.goal,
				min_sample_size: data.min_sample_size,
				confidence_level: data.confidence_level,
				auto_select_winner: data.auto_select_winner,
				scheduled_end_at: data.scheduled_end_at
			});

			toast.success('A/B test updated successfully');
			goto(`/ab-tests/${test.id}`);
		} catch (error) {
			toast.error('Failed to update A/B test');
			console.error(error);
		}
	}

	function handleCancel() {
		goto(`/ab-tests/${testId}`);
	}

	onMount(() => {
		loadTest();
	});
</script>

<svelte:head>
	<title>Edit A/B Test | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<h1 class="text-2xl font-bold">Edit A/B Test</h1>
		<p class="text-muted-foreground">
			Modify your experiment settings
		</p>
	</div>

	{#if loading}
		<div class="space-y-4">
			<Skeleton class="h-48" />
			<Skeleton class="h-48" />
			<Skeleton class="h-48" />
		</div>
	{:else if test}
		<ABTestBuilder
			{test}
			onSave={handleSave}
			onCancel={handleCancel}
			{entityOptions}
		/>
	{:else}
		<div class="text-center py-12">
			<p class="text-muted-foreground">Test not found</p>
		</div>
	{/if}
</div>
