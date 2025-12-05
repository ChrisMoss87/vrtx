<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import {
		createWorkflow,
		getTriggerTypes,
		getActionTypes,
		type WorkflowInput,
		type WorkflowStepInput,
		type TriggerType,
		type ActionType,
		type TriggerTypeInfo,
		type ActionTypeInfo
	} from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';
	import Plus from 'lucide-svelte/icons/plus';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import GripVertical from 'lucide-svelte/icons/grip-vertical';
	import Zap from 'lucide-svelte/icons/zap';

	let modules = $state<Module[]>([]);
	let triggerTypes = $state<TriggerTypeInfo>({});
	let actionTypes = $state<ActionTypeInfo>({});
	let loading = $state(true);
	let saving = $state(false);
	let error = $state<string | null>(null);

	// Form state
	let name = $state('');
	let description = $state('');
	let moduleId = $state<number | null>(null);
	let triggerType = $state<TriggerType>('record_created');
	let isActive = $state(false);
	let steps = $state<WorkflowStepInput[]>([]);

	onMount(async () => {
		try {
			const [modulesData, triggerTypesData, actionTypesData] = await Promise.all([
				getModules(),
				getTriggerTypes(),
				getActionTypes()
			]);
			modules = modulesData;
			triggerTypes = triggerTypesData;
			actionTypes = actionTypesData;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load data';
		} finally {
			loading = false;
		}
	});

	function addStep() {
		steps = [
			...steps,
			{
				name: '',
				action_type: 'update_field',
				action_config: {}
			}
		];
	}

	function removeStep(index: number) {
		steps = steps.filter((_, i) => i !== index);
	}

	function updateStep(index: number, field: keyof WorkflowStepInput, value: unknown) {
		steps = steps.map((step, i) => {
			if (i === index) {
				return { ...step, [field]: value };
			}
			return step;
		});
	}

	async function handleSubmit() {
		if (!name.trim()) {
			error = 'Workflow name is required';
			return;
		}

		try {
			saving = true;
			error = null;

			const data: WorkflowInput = {
				name: name.trim(),
				description: description.trim() || undefined,
				module_id: moduleId,
				trigger_type: triggerType,
				is_active: isActive,
				steps: steps.length > 0 ? steps : undefined
			};

			const workflow = await createWorkflow(data);
			goto(`/admin/workflows/${workflow.id}`);
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to create workflow';
		} finally {
			saving = false;
		}
	}

	function getActionLabel(type: string): string {
		return actionTypes[type]?.label || type;
	}

	function getActionIcon(type: string): string {
		return actionTypes[type]?.icon || 'zap';
	}
</script>

<div class="container mx-auto max-w-4xl py-6">
	<div class="mb-6">
		<Button variant="ghost" href="/admin/workflows" class="mb-4">
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Workflows
		</Button>
		<h1 class="text-2xl font-bold">Create Workflow</h1>
		<p class="text-muted-foreground">Set up an automated workflow with triggers and actions.</p>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<div class="text-muted-foreground">Loading...</div>
		</div>
	{:else}
		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-6">
			{#if error}
				<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
					{error}
				</div>
			{/if}

			<!-- Basic Info -->
			<Card>
				<CardHeader>
					<CardTitle>Basic Information</CardTitle>
					<CardDescription>Give your workflow a name and description.</CardDescription>
				</CardHeader>
				<CardContent class="space-y-4">
					<div class="space-y-2">
						<Label for="name">Workflow Name *</Label>
						<Input
							id="name"
							bind:value={name}
							placeholder="e.g., Send welcome email on contact creation"
						/>
					</div>

					<div class="space-y-2">
						<Label for="description">Description</Label>
						<Textarea
							id="description"
							bind:value={description}
							placeholder="Describe what this workflow does..."
							rows={3}
						/>
					</div>

					<div class="flex items-center gap-3">
						<Switch id="is_active" bind:checked={isActive} />
						<Label for="is_active">Activate workflow immediately</Label>
					</div>
				</CardContent>
			</Card>

			<!-- Trigger -->
			<Card>
				<CardHeader>
					<CardTitle>Trigger</CardTitle>
					<CardDescription>Choose when this workflow should run.</CardDescription>
				</CardHeader>
				<CardContent class="space-y-4">
					<div class="space-y-2">
						<Label for="module">Module</Label>
						<Select.Root
							type="single"
							onValueChange={(v) => {
								moduleId = v ? Number(v) : null;
							}}
						>
							<Select.Trigger class="w-full">
								{modules.find((m) => m.id === moduleId)?.name || 'Select a module (optional)'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="">No specific module</Select.Item>
								{#each modules as module}
									<Select.Item value={String(module.id)}>{module.name}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
						<p class="text-sm text-muted-foreground">
							The module this workflow applies to. Leave empty for module-agnostic workflows.
						</p>
					</div>

					<div class="space-y-2">
						<Label for="trigger_type">Trigger Type *</Label>
						<Select.Root
							type="single"
							value={triggerType}
							onValueChange={(v) => {
								if (v) triggerType = v as TriggerType;
							}}
						>
							<Select.Trigger class="w-full">
								{triggerTypes[triggerType] || 'Select trigger type'}
							</Select.Trigger>
							<Select.Content>
								{#each Object.entries(triggerTypes) as [value, label]}
									<Select.Item {value}>{label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				</CardContent>
			</Card>

			<!-- Steps -->
			<Card>
				<CardHeader>
					<div class="flex items-center justify-between">
						<div>
							<CardTitle>Actions</CardTitle>
							<CardDescription>Define what happens when this workflow is triggered.</CardDescription>
						</div>
						<Button type="button" variant="outline" size="sm" onclick={addStep}>
							<Plus class="mr-2 h-4 w-4" />
							Add Action
						</Button>
					</div>
				</CardHeader>
				<CardContent>
					{#if steps.length === 0}
						<div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed py-8">
							<Zap class="mb-2 h-8 w-8 text-muted-foreground" />
							<p class="mb-2 text-muted-foreground">No actions added yet</p>
							<Button type="button" variant="outline" size="sm" onclick={addStep}>
								<Plus class="mr-2 h-4 w-4" />
								Add Your First Action
							</Button>
						</div>
					{:else}
						<div class="space-y-3">
							{#each steps as step, index}
								<div class="flex items-start gap-3 rounded-lg border p-4">
									<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
										{index + 1}
									</div>
									<div class="flex-1 space-y-3">
										<div class="flex items-center gap-3">
											<div class="flex-1">
												<Label>Action Type</Label>
												<Select.Root
													type="single"
													value={step.action_type}
													onValueChange={(v) => {
														if (v) updateStep(index, 'action_type', v as ActionType);
													}}
												>
													<Select.Trigger class="w-full">
														{getActionLabel(step.action_type)}
													</Select.Trigger>
													<Select.Content>
														{#each Object.entries(actionTypes) as [value, info]}
															<Select.Item {value}>
																{info.label}
																<span class="ml-2 text-xs text-muted-foreground">
																	{info.description}
																</span>
															</Select.Item>
														{/each}
													</Select.Content>
												</Select.Root>
											</div>
											<Button
												type="button"
												variant="ghost"
												size="icon"
												class="mt-5 text-destructive"
												onclick={() => removeStep(index)}
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>

										<div>
											<Label>Step Name (optional)</Label>
											<Input
												value={step.name || ''}
												oninput={(e) => updateStep(index, 'name', e.currentTarget.value)}
												placeholder="e.g., Send notification email"
											/>
										</div>

										<div class="rounded bg-muted/50 p-3 text-sm text-muted-foreground">
											Action configuration will be available after creation. Edit the workflow to
											configure this action's specific settings.
										</div>
									</div>
								</div>
							{/each}
						</div>
					{/if}
				</CardContent>
			</Card>

			<!-- Actions -->
			<div class="flex justify-end gap-3">
				<Button type="button" variant="outline" href="/admin/workflows">Cancel</Button>
				<Button type="submit" disabled={saving}>
					{saving ? 'Creating...' : 'Create Workflow'}
				</Button>
			</div>
		</form>
	{/if}
</div>
