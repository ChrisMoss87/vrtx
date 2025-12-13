<script lang="ts">
	import { ArrowRight, Zap, Clock, Play, FileText, Settings } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { previewWorkflow, generateWorkflow, type WorkflowPreview, type Recording } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { onMount, createEventDispatcher } from 'svelte';
	import { goto } from '$app/navigation';

	export let recording: Recording;

	const dispatch = createEventDispatcher<{
		generated: { workflow_id: number };
	}>();

	let preview: WorkflowPreview | null = null;
	let loading = true;
	let generating = false;

	// Workflow configuration
	let workflowName = '';
	let workflowDescription = '';
	let triggerType = 'manual';
	let triggerConfig: Record<string, unknown> = {};

	onMount(async () => {
		await loadPreview();
	});

	async function loadPreview() {
		loading = true;
		const { data, error } = await tryCatch(previewWorkflow(recording.id));
		loading = false;

		if (error) {
			toast.error('Failed to load workflow preview');
			return;
		}

		preview = data;
		workflowName = data.name || `Workflow from Recording #${recording.id}`;

		// Pre-select first suggested trigger
		if (data.suggested_triggers.length > 0) {
			triggerType = data.suggested_triggers[0].type;
			triggerConfig = data.suggested_triggers[0].config;
		}
	}

	async function handleGenerate() {
		if (!workflowName.trim()) {
			toast.error('Please enter a workflow name');
			return;
		}

		generating = true;
		const { data, error } = await tryCatch(
			generateWorkflow(
				recording.id,
				workflowName,
				triggerType,
				triggerConfig,
				workflowDescription || undefined
			)
		);
		generating = false;

		if (error) {
			toast.error('Failed to generate workflow');
			return;
		}

		toast.success('Workflow generated successfully');
		dispatch('generated', { workflow_id: data.workflow_id });
		goto(`/admin/workflows/${data.workflow_id}`);
	}

	function getTriggerIcon(type: string) {
		switch (type) {
			case 'manual': return Play;
			case 'record_created': return FileText;
			case 'field_change': return Settings;
			case 'stage_change': return ArrowRight;
			case 'scheduled': return Clock;
			default: return Zap;
		}
	}
</script>

<div class="space-y-6">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if preview}
		<!-- Workflow Configuration -->
		<div class="rounded-lg border p-4 space-y-4">
			<h3 class="font-semibold flex items-center gap-2">
				<Zap class="h-4 w-4" />
				Workflow Configuration
			</h3>

			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label for="workflow-name">Workflow Name</Label>
					<Input
						id="workflow-name"
						bind:value={workflowName}
						placeholder="Enter workflow name"
					/>
				</div>

				<div class="space-y-2">
					<Label>Trigger</Label>
					<Select.Root
						type="single"
						value={triggerType}
						onValueChange={(val) => {
							if (val) {
								triggerType = val;
								const trigger = preview?.suggested_triggers.find(t => t.type === triggerType);
								triggerConfig = trigger?.config ?? {};
							}
						}}
					>
						<Select.Trigger>
							<span>{preview.suggested_triggers.find(t => t.type === triggerType)?.label ?? triggerType}</span>
						</Select.Trigger>
						<Select.Content>
							{#each preview.suggested_triggers as trigger}
								<Select.Item value={trigger.type}>
									<span class="flex items-center gap-2">
										<svelte:component this={getTriggerIcon(trigger.type)} class="h-4 w-4" />
										{trigger.label}
									</span>
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="workflow-description">Description (optional)</Label>
				<Textarea
					id="workflow-description"
					bind:value={workflowDescription}
					placeholder="Describe what this workflow does..."
					rows={2}
				/>
			</div>
		</div>

		<!-- Steps Preview -->
		<div class="rounded-lg border p-4 space-y-4">
			<h3 class="font-semibold">Workflow Steps ({preview.step_count})</h3>

			<div class="space-y-2">
				{#each preview.steps as step, index}
					<div class="flex items-center gap-3 p-3 rounded-lg bg-muted/50">
						<div class="w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-medium flex items-center justify-center flex-shrink-0">
							{index + 1}
						</div>
						<div class="flex-1 min-w-0">
							<div class="flex items-center gap-2">
								<span class="text-sm font-medium">{step.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</span>
								{#if step.is_parameterized}
									<span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
										Dynamic
									</span>
								{/if}
							</div>
							<p class="text-xs text-muted-foreground truncate">{step.description}</p>
						</div>
						{#if index < preview.steps.length - 1}
							<ArrowRight class="h-4 w-4 text-muted-foreground flex-shrink-0" />
						{/if}
					</div>
				{/each}
			</div>
		</div>

		<!-- Warning messages -->
		<div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 p-4">
			<h4 class="font-medium text-amber-800 dark:text-amber-400 text-sm">Before generating:</h4>
			<ul class="mt-2 text-sm text-amber-700 dark:text-amber-300 space-y-1 list-disc list-inside">
				<li>The generated workflow will be inactive by default</li>
				<li>Review and test the workflow before activating</li>
				<li>Steps with specific values (emails, user IDs) should be parameterized</li>
			</ul>
		</div>

		<!-- Actions -->
		<div class="flex justify-end gap-2">
			<Button variant="outline" onclick={() => goto('/recordings')}>
				Cancel
			</Button>
			<Button onclick={handleGenerate} disabled={generating || !workflowName.trim()}>
				{#if generating}
					Generating...
				{:else}
					Generate Workflow
				{/if}
			</Button>
		</div>
	{/if}
</div>
