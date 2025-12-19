<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { toast } from 'svelte-sonner';
	import { Button } from '$lib/components/ui/button';
	import WorkflowBuilder from '$lib/components/workflow-builder/WorkflowBuilder.svelte';
	import { TemplateGallery } from '$lib/components/workflow-builder/templates';
	import {
		createWorkflow,
		useWorkflowTemplate,
		type WorkflowInput,
		type WorkflowTemplate
	} from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';
	import { LayoutTemplate, PenLine } from 'lucide-svelte';

	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let showTemplates = $state(true);
	let initialData = $state<Partial<WorkflowInput> | null>(null);

	// Check URL params for direct mode
	$effect(() => {
		const mode = $page.url.searchParams.get('mode');
		if (mode === 'blank') {
			showTemplates = false;
		}
	});

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

	async function handleSelectTemplate(template: WorkflowTemplate) {
		try {
			const result = await useWorkflowTemplate(template.id);
			initialData = result.workflow_data as Partial<WorkflowInput>;
			showTemplates = false;
			toast.success(`Template "${template.name}" loaded`);
		} catch (error) {
			console.error('Failed to load template:', error);
			toast.error('Failed to load template');
		}
	}

	function handleStartBlank() {
		showTemplates = false;
		initialData = null;
	}

	async function handleSave(data: WorkflowInput) {
		const workflow = await createWorkflow(data);
		goto(`/workflows/${workflow.id}`);
		return workflow;
	}

	function handleCancel() {
		if (!showTemplates && initialData === null) {
			// If we were in blank mode, go back to templates
			showTemplates = true;
		} else {
			goto('/workflows');
		}
	}
</script>

<svelte:head>
	<title>Create Workflow | VRTX CRM</title>
</svelte:head>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<div class="text-muted-foreground">Loading...</div>
	</div>
{:else if showTemplates}
	<div class="flex h-[calc(100vh-4rem)] flex-col">
		<!-- Start from scratch option -->
		<div class="flex items-center justify-between border-b bg-muted/30 px-6 py-3">
			<div class="flex items-center gap-2 text-sm text-muted-foreground">
				<LayoutTemplate class="h-4 w-4" />
				<span>Choose a template or start from scratch</span>
			</div>
			<Button variant="outline" size="sm" onclick={handleStartBlank} class="gap-2">
				<PenLine class="h-4 w-4" />
				Start from Scratch
			</Button>
		</div>

		<div class="flex-1 overflow-hidden">
			<TemplateGallery
				onSelectTemplate={handleSelectTemplate}
				onClose={() => goto('/workflows')}
			/>
		</div>
	</div>
{:else}
	<div class="container mx-auto p-6">
		<WorkflowBuilder
			{modules}
			workflow={initialData ?? undefined}
			onSave={handleSave}
			onCancel={handleCancel}
		/>
	</div>
{/if}
