<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { toast } from 'svelte-sonner';
	import WorkflowBuilder from '$lib/components/workflow-builder/WorkflowBuilder.svelte';
	import { createWorkflow, type WorkflowInput } from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';

	let modules = $state<Module[]>([]);
	let loading = $state(true);

	onMount(async () => {
		try {
			modules = await getModules();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loading = false;
		}
	});

	async function handleSave(data: WorkflowInput) {
		const workflow = await createWorkflow(data);
		goto(`/workflows/${workflow.id}`);
		return workflow;
	}

	function handleCancel() {
		goto('/workflows');
	}
</script>

<svelte:head>
	<title>Create Workflow | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading...</div>
		</div>
	{:else}
		<WorkflowBuilder
			{modules}
			onSave={handleSave}
			onCancel={handleCancel}
		/>
	{/if}
</div>
