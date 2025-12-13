<script lang="ts">
	import { goto } from '$app/navigation';
	import { ABTestBuilder } from '$lib/components/ab-testing';
	import { abTestApi, type AbTest, type AbTestEntityType } from '$lib/api/ab-tests';
	import { toast } from 'svelte-sonner';

	// In a real app, these would be fetched from respective APIs
	let entityOptions = $state<{ type: AbTestEntityType; id: number; name: string }[]>([
		{ type: 'email_template', id: 1, name: 'Welcome Email' },
		{ type: 'email_template', id: 2, name: 'Newsletter Template' },
		{ type: 'campaign', id: 1, name: 'Summer Sale Campaign' },
		{ type: 'campaign', id: 2, name: 'Product Launch' },
		{ type: 'web_form', id: 1, name: 'Contact Form' },
		{ type: 'web_form', id: 2, name: 'Newsletter Signup' }
	]);

	async function handleSave(data: Partial<AbTest>) {
		try {
			const test = await abTestApi.create({
				name: data.name!,
				description: data.description || undefined,
				type: data.type!,
				entity_type: data.entity_type!,
				entity_id: data.entity_id!,
				goal: data.goal,
				min_sample_size: data.min_sample_size,
				confidence_level: data.confidence_level,
				auto_select_winner: data.auto_select_winner,
				scheduled_end_at: data.scheduled_end_at
			});

			toast.success('A/B test created successfully');
			goto(`/ab-tests/${test.id}`);
		} catch (error) {
			toast.error('Failed to create A/B test');
			console.error(error);
		}
	}

	function handleCancel() {
		goto('/ab-tests');
	}
</script>

<svelte:head>
	<title>New A/B Test | VRTX</title>
</svelte:head>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<h1 class="text-2xl font-bold">Create A/B Test</h1>
		<p class="text-muted-foreground">
			Set up an experiment to test different variations of your content
		</p>
	</div>

	<ABTestBuilder
		onSave={handleSave}
		onCancel={handleCancel}
		{entityOptions}
	/>
</div>
