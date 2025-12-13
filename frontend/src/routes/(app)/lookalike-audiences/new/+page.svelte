<script lang="ts">
	import { goto } from '$app/navigation';
	import { LookalikeBuilder } from '$lib/components/lookalike';
	import { lookalikeApi, type LookalikeAudience } from '$lib/api/lookalike';
	import { toast } from 'svelte-sonner';

	// In production, these would be fetched from respective APIs
	let savedSearches = $state([
		{ id: 1, name: 'High-value customers' },
		{ id: 2, name: 'Recently converted leads' }
	]);

	let segments = $state([
		{ id: 1, name: 'Enterprise customers' },
		{ id: 2, name: 'Active users' }
	]);

	async function handleSave(data: Partial<LookalikeAudience>) {
		try {
			const audience = await lookalikeApi.create({
				name: data.name!,
				description: data.description || undefined,
				source_type: data.source_type!,
				source_id: data.source_id || undefined,
				source_criteria: data.source_criteria || undefined,
				match_criteria: data.match_criteria || undefined,
				weights: data.weights || undefined,
				min_similarity_score: data.min_similarity_score,
				size_limit: data.size_limit || undefined,
				auto_refresh: data.auto_refresh,
				refresh_frequency: data.refresh_frequency || undefined
			});

			toast.success('Lookalike audience created successfully');
			goto(`/lookalike-audiences/${audience.id}`);
		} catch (error) {
			toast.error('Failed to create lookalike audience');
			console.error(error);
		}
	}

	function handleCancel() {
		goto('/lookalike-audiences');
	}
</script>

<svelte:head>
	<title>New Lookalike Audience | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<h1 class="text-2xl font-bold">Create Lookalike Audience</h1>
		<p class="text-muted-foreground">
			Find prospects similar to your best customers based on behavioral and demographic patterns
		</p>
	</div>

	<LookalikeBuilder
		onSave={handleSave}
		onCancel={handleCancel}
		{savedSearches}
		{segments}
	/>
</div>
