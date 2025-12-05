<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { toast } from 'svelte-sonner';
	import WorkflowBuilder from '$lib/components/workflow-builder/WorkflowBuilder.svelte';
	import {
		getWorkflow,
		updateWorkflow,
		triggerWorkflow,
		type Workflow,
		type WorkflowInput
	} from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';

	let workflow = $state<Workflow | null>(null);
	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	const workflowId = $derived(parseInt($page.params.id || '0'));

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		error = null;

		try {
			const [workflowData, moduleData] = await Promise.all([
				getWorkflow(workflowId),
				getModules()
			]);
			workflow = workflowData;
			modules = moduleData;
		} catch (err) {
			console.error('Failed to load workflow:', err);
			error = 'Failed to load workflow';
			toast.error('Failed to load workflow');
		} finally {
			loading = false;
		}
	}

	async function handleSave(data: WorkflowInput) {
		const updated = await updateWorkflow(workflowId, data);
		workflow = updated;
		return updated;
	}

	function handleCancel() {
		goto('/workflows');
	}

	async function handleTest(wf: Workflow) {
		try {
			const execution = await triggerWorkflow(wf.id);
			toast.success('Workflow triggered successfully');
			goto(`/workflows/${wf.id}/executions`);
		} catch (err) {
			console.error('Failed to trigger workflow:', err);
			toast.error('Failed to trigger workflow');
		}
	}
</script>

<svelte:head>
	<title>{workflow?.name || 'Workflow'} | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-muted-foreground">Loading workflow...</div>
		</div>
	{:else if error}
		<div class="flex flex-col items-center justify-center py-12">
			<p class="mb-4 text-destructive">{error}</p>
			<button
				class="text-primary underline"
				onclick={() => goto('/workflows')}
			>
				Back to workflows
			</button>
		</div>
	{:else if workflow}
		<WorkflowBuilder
			{workflow}
			{modules}
			onSave={handleSave}
			onCancel={handleCancel}
			onTest={handleTest}
		/>
	{/if}
</div>
