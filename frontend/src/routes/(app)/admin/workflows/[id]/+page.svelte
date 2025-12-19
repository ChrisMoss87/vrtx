<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { goto } from '$app/navigation';
	import {
		getWorkflow,
		updateWorkflow,
		getTriggerTypes,
		getActionTypes,
		triggerWorkflow,
		getWorkflowExecutions,
		reorderWorkflowSteps,
		type Workflow,
		type WorkflowStep,
		type WorkflowStepInput,
		type TriggerType,
		type ActionType,
		type TriggerTypeInfo,
		type ActionTypeInfo,
		type WorkflowExecution
	} from '$lib/api/workflows';
	import { getModules, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import * as Dialog from '$lib/components/ui/dialog';
	import { toast } from 'svelte-sonner';
	import { ConditionBuilder } from '$lib/components/workflow';
	import ArrowLeft from 'lucide-svelte/icons/arrow-left';
	import Plus from 'lucide-svelte/icons/plus';
	import Trash2 from 'lucide-svelte/icons/trash-2';
	import GripVertical from 'lucide-svelte/icons/grip-vertical';
	import Zap from 'lucide-svelte/icons/zap';
	import Play from 'lucide-svelte/icons/play';
	import Clock from 'lucide-svelte/icons/clock';
	import CheckCircle from 'lucide-svelte/icons/check-circle';
	import XCircle from 'lucide-svelte/icons/x-circle';
	import AlertCircle from 'lucide-svelte/icons/alert-circle';
	import Settings from 'lucide-svelte/icons/settings';
	import History from 'lucide-svelte/icons/history';
	import ChevronDown from 'lucide-svelte/icons/chevron-down';
	import ChevronUp from 'lucide-svelte/icons/chevron-up';
	import Filter from 'lucide-svelte/icons/filter';

	let workflowId = $derived(parseInt(page.params.id || '0'));

	let workflow = $state<Workflow | null>(null);
	let modules = $state<Module[]>([]);
	let triggerTypes = $state<TriggerTypeInfo>({});
	let actionTypes = $state<ActionTypeInfo>({});
	let executions = $state<WorkflowExecution[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let triggering = $state(false);
	let error = $state<string | null>(null);
	let activeTab = $state('settings');

	// Form state
	let name = $state('');
	let description = $state('');
	let moduleId = $state<number | null>(null);
	let triggerType = $state<TriggerType>('record_created');
	let isActive = $state(false);
	let steps = $state<WorkflowStepInput[]>([]);
	let conditionGroups = $state<Array<{ logic: 'and' | 'or'; conditions: Array<{ field: string; operator: string; value: unknown }> }>>([]);
	let conditionLogic = $state<'and' | 'or'>('and');

	// Step configuration dialog
	let configDialogOpen = $state(false);
	let editingStepIndex = $state<number | null>(null);
	let stepConfig = $state<Record<string, unknown>>({});

	// Expanded steps for showing config
	let expandedSteps = $state<Set<number>>(new Set());

	onMount(async () => {
		try {
			const [workflowData, modulesData, triggerTypesData, actionTypesData] = await Promise.all([
				getWorkflow(workflowId),
				getModules(),
				getTriggerTypes(),
				getActionTypes()
			]);

			workflow = workflowData;
			modules = modulesData;
			triggerTypes = triggerTypesData;
			actionTypes = actionTypesData;

			// Initialize form state
			name = workflow.name;
			description = workflow.description || '';
			moduleId = workflow.module_id;
			triggerType = workflow.trigger_type;
			isActive = workflow.is_active;
			steps = (workflow.steps || []).map((s) => ({
				id: s.id,
				name: s.name || '',
				action_type: s.action_type,
				action_config: s.action_config || {},
				conditions: s.conditions || undefined,
				continue_on_error: s.continue_on_error,
				retry_count: s.retry_count,
				retry_delay_seconds: s.retry_delay_seconds
			}));

			// Initialize conditions from workflow
			if (workflow.conditions) {
				if ('groups' in workflow.conditions) {
					conditionGroups = workflow.conditions.groups || [];
					conditionLogic = workflow.conditions.logic || 'and';
				} else if (Array.isArray(workflow.conditions)) {
					// Legacy format: array of conditions
					if (workflow.conditions.length > 0) {
						conditionGroups = [{ logic: 'and', conditions: workflow.conditions }];
					}
				}
			}

			// Load executions
			await loadExecutions();
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load workflow';
			toast.error(error);
		} finally {
			loading = false;
		}
	});

	async function loadExecutions() {
		try {
			const result = await getWorkflowExecutions(workflowId, { per_page: 10 });
			executions = result.data;
		} catch (e) {
			console.error('Failed to load executions:', e);
		}
	}

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

	function openStepConfig(index: number) {
		editingStepIndex = index;
		stepConfig = { ...(steps[index].action_config || {}) };
		configDialogOpen = true;
	}

	function saveStepConfig() {
		if (editingStepIndex !== null) {
			updateStep(editingStepIndex, 'action_config', { ...stepConfig });
		}
		configDialogOpen = false;
		editingStepIndex = null;
		stepConfig = {};
	}

	function toggleStepExpanded(index: number) {
		const newSet = new Set(expandedSteps);
		if (newSet.has(index)) {
			newSet.delete(index);
		} else {
			newSet.add(index);
		}
		expandedSteps = newSet;
	}

	async function handleSave() {
		if (!name.trim()) {
			error = 'Workflow name is required';
			return;
		}

		try {
			saving = true;
			error = null;

			await updateWorkflow(workflowId, {
				name: name.trim(),
				description: description.trim() || undefined,
				module_id: moduleId,
				trigger_type: triggerType,
				is_active: isActive,
				conditions: conditionGroups.length > 0 ? { logic: conditionLogic, groups: conditionGroups } : undefined,
				steps: steps.length > 0 ? steps : undefined
			});

			toast.success('Workflow saved successfully');
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to save workflow';
			toast.error(error);
		} finally {
			saving = false;
		}
	}

	async function handleTrigger() {
		try {
			triggering = true;
			const execution = await triggerWorkflow(workflowId);
			toast.success('Workflow triggered successfully');
			await loadExecutions();
		} catch (e) {
			toast.error(e instanceof Error ? e.message : 'Failed to trigger workflow');
		} finally {
			triggering = false;
		}
	}

	function getActionLabel(type: string): string {
		return actionTypes[type]?.label || type;
	}

	function getActionDescription(type: string): string {
		return actionTypes[type]?.description || '';
	}

	function getStatusColor(status: string): string {
		const colors: Record<string, string> = {
			pending: 'bg-yellow-100 text-yellow-800',
			queued: 'bg-blue-100 text-blue-800',
			running: 'bg-blue-100 text-blue-800',
			completed: 'bg-green-100 text-green-800',
			failed: 'bg-red-100 text-red-800',
			cancelled: 'bg-gray-100 text-gray-800'
		};
		return colors[status] || 'bg-gray-100 text-gray-800';
	}

	function formatDate(dateStr: string | null): string {
		if (!dateStr) return '-';
		return new Date(dateStr).toLocaleString();
	}

	function formatDuration(ms: number | null): string {
		if (!ms) return '-';
		if (ms < 1000) return `${ms}ms`;
		return `${(ms / 1000).toFixed(2)}s`;
	}

	// Get config fields for an action type
	function getConfigFields(actionType: string): Array<{ name: string; label: string; type: string; required?: boolean; options?: Array<{ value: string; label: string }> }> {
		const configs: Record<string, Array<{ name: string; label: string; type: string; required?: boolean; options?: Array<{ value: string; label: string }> }>> = {
			send_email: [
				{ name: 'to', label: 'To (email or {{field}})', type: 'text', required: true },
				{ name: 'subject', label: 'Subject', type: 'text', required: true },
				{ name: 'body', label: 'Body (supports {{field}} variables)', type: 'textarea', required: true }
			],
			update_field: [
				{ name: 'field', label: 'Field API Name', type: 'text', required: true },
				{ name: 'value', label: 'Value (or {{field}})', type: 'text', required: true }
			],
			create_record: [
				{ name: 'module_api_name', label: 'Target Module API Name', type: 'text', required: true },
				{ name: 'data', label: 'Record Data (JSON)', type: 'textarea', required: true }
			],
			update_record: [
				{ name: 'record_id', label: 'Record ID (or {{record.id}})', type: 'text', required: true },
				{ name: 'data', label: 'Fields to Update (JSON)', type: 'textarea', required: true }
			],
			delete_record: [
				{ name: 'record_id', label: 'Record ID (or {{record.id}})', type: 'text', required: true }
			],
			webhook: [
				{ name: 'url', label: 'Webhook URL', type: 'text', required: true },
				{ name: 'method', label: 'HTTP Method', type: 'select', required: true, options: [
					{ value: 'POST', label: 'POST' },
					{ value: 'GET', label: 'GET' },
					{ value: 'PUT', label: 'PUT' },
					{ value: 'PATCH', label: 'PATCH' },
					{ value: 'DELETE', label: 'DELETE' }
				]},
				{ name: 'headers', label: 'Headers (JSON)', type: 'textarea' },
				{ name: 'body', label: 'Body (JSON)', type: 'textarea' }
			],
			assign_user: [
				{ name: 'user_id', label: 'User ID', type: 'text', required: true },
				{ name: 'field', label: 'Assignment Field (e.g., assigned_to)', type: 'text', required: true }
			],
			send_notification: [
				{ name: 'user_id', label: 'User ID (or {{record.owner_id}})', type: 'text', required: true },
				{ name: 'title', label: 'Notification Title', type: 'text', required: true },
				{ name: 'message', label: 'Message', type: 'textarea', required: true }
			],
			delay: [
				{ name: 'seconds', label: 'Delay (seconds)', type: 'number', required: true }
			],
			add_tag: [
				{ name: 'tag', label: 'Tag Name', type: 'text', required: true }
			],
			remove_tag: [
				{ name: 'tag', label: 'Tag Name', type: 'text', required: true }
			],
			create_task: [
				{ name: 'title', label: 'Task Title', type: 'text', required: true },
				{ name: 'description', label: 'Description', type: 'textarea' },
				{ name: 'assigned_to', label: 'Assigned To (User ID)', type: 'text' },
				{ name: 'due_date', label: 'Due Date (or +Xd for days)', type: 'text' }
			],
			move_stage: [
				{ name: 'pipeline_id', label: 'Pipeline ID', type: 'text', required: true },
				{ name: 'stage_id', label: 'Stage ID', type: 'text', required: true }
			]
		};
		return configs[actionType] || [];
	}
</script>

<svelte:head>
	<title>{workflow?.name || 'Workflow'} | Admin</title>
</svelte:head>

<div class="container mx-auto max-w-5xl py-6">
	<div class="mb-6">
		<Button variant="ghost" href="/admin/workflows" class="mb-4">
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Workflows
		</Button>
		<div class="flex items-center justify-between">
			<div>
				<h1 class="text-2xl font-bold">{workflow?.name || 'Loading...'}</h1>
				<p class="text-muted-foreground">Configure triggers, actions, and conditions</p>
			</div>
			<div class="flex items-center gap-2">
				{#if workflow?.allow_manual_trigger || triggerType === 'manual'}
					<Button variant="outline" onclick={handleTrigger} disabled={triggering}>
						<Play class="mr-2 h-4 w-4" />
						{triggering ? 'Running...' : 'Run Now'}
					</Button>
				{/if}
				<Button onclick={handleSave} disabled={saving}>
					{saving ? 'Saving...' : 'Save Changes'}
				</Button>
			</div>
		</div>
	</div>

	{#if loading}
		<div class="flex h-64 items-center justify-center">
			<div class="text-muted-foreground">Loading...</div>
		</div>
	{:else if error && !workflow}
		<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
			{error}
		</div>
	{:else if workflow}
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List class="mb-6">
				<Tabs.Trigger value="settings">
					<Settings class="mr-2 h-4 w-4" />
					Settings
				</Tabs.Trigger>
				<Tabs.Trigger value="conditions">
					<Filter class="mr-2 h-4 w-4" />
					Conditions
				</Tabs.Trigger>
				<Tabs.Trigger value="actions">
					<Zap class="mr-2 h-4 w-4" />
					Actions ({steps.length})
				</Tabs.Trigger>
				<Tabs.Trigger value="history">
					<History class="mr-2 h-4 w-4" />
					History
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="settings">
				<div class="space-y-6">
					{#if error}
						<div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-800">
							{error}
						</div>
					{/if}

					<!-- Basic Info -->
					<Card>
						<CardHeader>
							<CardTitle>Basic Information</CardTitle>
							<CardDescription>Workflow name and description</CardDescription>
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
								<Label for="is_active">Workflow is active</Label>
							</div>
						</CardContent>
					</Card>

					<!-- Trigger -->
					<Card>
						<CardHeader>
							<CardTitle>Trigger</CardTitle>
							<CardDescription>When this workflow should run</CardDescription>
						</CardHeader>
						<CardContent class="space-y-4">
							<div class="space-y-2">
								<Label for="module">Module</Label>
								<Select.Root
									type="single"
									value={moduleId ? String(moduleId) : ''}
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

					<!-- Stats -->
					<Card>
						<CardHeader>
							<CardTitle>Statistics</CardTitle>
							<CardDescription>Workflow execution statistics</CardDescription>
						</CardHeader>
						<CardContent>
							<div class="grid grid-cols-3 gap-4">
								<div class="rounded-lg border p-4 text-center">
									<div class="text-2xl font-bold">{workflow.execution_count}</div>
									<div class="text-sm text-muted-foreground">Total Runs</div>
								</div>
								<div class="rounded-lg border p-4 text-center">
									<div class="text-2xl font-bold text-green-600">{workflow.success_count}</div>
									<div class="text-sm text-muted-foreground">Successful</div>
								</div>
								<div class="rounded-lg border p-4 text-center">
									<div class="text-2xl font-bold text-red-600">{workflow.failure_count}</div>
									<div class="text-sm text-muted-foreground">Failed</div>
								</div>
							</div>
							{#if workflow.last_run_at}
								<div class="mt-4 text-sm text-muted-foreground">
									Last run: {formatDate(workflow.last_run_at)}
								</div>
							{/if}
						</CardContent>
					</Card>
				</div>
			</Tabs.Content>

			<Tabs.Content value="conditions">
				<Card>
					<CardHeader>
						<CardTitle>Workflow Conditions</CardTitle>
						<CardDescription>
							Define conditions that must be met for this workflow to execute.
							If no conditions are set, the workflow runs for all matching triggers.
						</CardDescription>
					</CardHeader>
					<CardContent>
						<ConditionBuilder
							bind:conditions={conditionGroups}
							bind:logic={conditionLogic}
							onChange={(groups, logic) => {
								conditionGroups = groups;
								conditionLogic = logic;
							}}
						/>
					</CardContent>
				</Card>
			</Tabs.Content>

			<Tabs.Content value="actions">
				<Card>
					<CardHeader>
						<div class="flex items-center justify-between">
							<div>
								<CardTitle>Actions</CardTitle>
								<CardDescription>Define what happens when this workflow is triggered</CardDescription>
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
									<div class="rounded-lg border">
										<div class="flex items-center gap-3 p-4">
											<div class="cursor-move text-muted-foreground">
												<GripVertical class="h-5 w-5" />
											</div>
											<div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
												{index + 1}
											</div>
											<div class="flex-1">
												<div class="flex items-center gap-2">
													<span class="font-medium">{step.name || getActionLabel(step.action_type)}</span>
													<Badge variant="secondary">{getActionLabel(step.action_type)}</Badge>
												</div>
												<p class="text-sm text-muted-foreground">{getActionDescription(step.action_type)}</p>
											</div>
											<Button
												type="button"
												variant="ghost"
												size="sm"
												onclick={() => toggleStepExpanded(index)}
											>
												{#if expandedSteps.has(index)}
													<ChevronUp class="h-4 w-4" />
												{:else}
													<ChevronDown class="h-4 w-4" />
												{/if}
											</Button>
											<Button
												type="button"
												variant="ghost"
												size="icon"
												class="text-destructive"
												onclick={() => removeStep(index)}
											>
												<Trash2 class="h-4 w-4" />
											</Button>
										</div>

										{#if expandedSteps.has(index)}
											<div class="border-t bg-muted/30 p-4">
												<div class="space-y-4">
													<div class="grid gap-4 md:grid-cols-2">
														<div class="space-y-2">
															<Label>Step Name (optional)</Label>
															<Input
																value={step.name || ''}
																oninput={(e) => updateStep(index, 'name', e.currentTarget.value)}
																placeholder="e.g., Send notification email"
															/>
														</div>
														<div class="space-y-2">
															<Label>Action Type</Label>
															<Select.Root
																type="single"
																value={step.action_type}
																onValueChange={(v) => {
																	if (v) {
																		updateStep(index, 'action_type', v as ActionType);
																		updateStep(index, 'action_config', {});
																	}
																}}
															>
																<Select.Trigger class="w-full">
																	{getActionLabel(step.action_type)}
																</Select.Trigger>
																<Select.Content>
																	{#each Object.entries(actionTypes) as [value, info]}
																		<Select.Item {value}>
																			{info.label}
																		</Select.Item>
																	{/each}
																</Select.Content>
															</Select.Root>
														</div>
													</div>

													<!-- Action-specific configuration -->
													{#if getConfigFields(step.action_type).length > 0}
														<div class="space-y-3">
															<Label class="text-sm font-semibold">Configuration</Label>
															{#each getConfigFields(step.action_type) as field}
																<div class="space-y-1">
																	<Label class="text-sm">{field.label}{field.required ? ' *' : ''}</Label>
																	{#if field.type === 'textarea'}
																		<Textarea
																			value={(step.action_config[field.name] as string) || ''}
																			oninput={(e) => {
																				const newConfig = { ...step.action_config, [field.name]: e.currentTarget.value };
																				updateStep(index, 'action_config', newConfig);
																			}}
																			rows={3}
																		/>
																	{:else if field.type === 'select' && field.options}
																		<Select.Root
																			type="single"
																			value={(step.action_config[field.name] as string) || ''}
																			onValueChange={(v) => {
																				const newConfig = { ...step.action_config, [field.name]: v };
																				updateStep(index, 'action_config', newConfig);
																			}}
																		>
																			<Select.Trigger class="w-full">
																				{field.options.find((o) => o.value === step.action_config[field.name])?.label || 'Select...'}
																			</Select.Trigger>
																			<Select.Content>
																				{#each field.options as option}
																					<Select.Item value={option.value}>{option.label}</Select.Item>
																				{/each}
																			</Select.Content>
																		</Select.Root>
																	{:else if field.type === 'number'}
																		<Input
																			type="number"
																			value={(step.action_config[field.name] as string) || ''}
																			oninput={(e) => {
																				const newConfig = { ...step.action_config, [field.name]: Number(e.currentTarget.value) };
																				updateStep(index, 'action_config', newConfig);
																			}}
																		/>
																	{:else}
																		<Input
																			value={(step.action_config[field.name] as string) || ''}
																			oninput={(e) => {
																				const newConfig = { ...step.action_config, [field.name]: e.currentTarget.value };
																				updateStep(index, 'action_config', newConfig);
																			}}
																		/>
																	{/if}
																</div>
															{/each}
														</div>
													{/if}

													<!-- Advanced options -->
													<div class="grid gap-4 border-t pt-4 md:grid-cols-3">
														<div class="flex items-center gap-2">
															<Switch
																checked={step.continue_on_error || false}
																onCheckedChange={(checked) => updateStep(index, 'continue_on_error', checked)}
															/>
															<Label class="text-sm">Continue on error</Label>
														</div>
														<div class="space-y-1">
															<Label class="text-sm">Retry count</Label>
															<Input
																type="number"
																min="0"
																max="5"
																value={step.retry_count || 0}
																oninput={(e) => updateStep(index, 'retry_count', Number(e.currentTarget.value))}
															/>
														</div>
														<div class="space-y-1">
															<Label class="text-sm">Retry delay (seconds)</Label>
															<Input
																type="number"
																min="0"
																value={step.retry_delay_seconds || 0}
																oninput={(e) => updateStep(index, 'retry_delay_seconds', Number(e.currentTarget.value))}
															/>
														</div>
													</div>
												</div>
											</div>
										{/if}
									</div>
								{/each}
							</div>
						{/if}
					</CardContent>
				</Card>
			</Tabs.Content>

			<Tabs.Content value="history">
				<Card>
					<CardHeader>
						<CardTitle>Execution History</CardTitle>
						<CardDescription>Recent workflow executions</CardDescription>
					</CardHeader>
					<CardContent>
						{#if executions.length === 0}
							<div class="flex flex-col items-center justify-center py-8 text-center">
								<History class="mb-2 h-8 w-8 text-muted-foreground" />
								<p class="text-muted-foreground">No executions yet</p>
								<p class="text-sm text-muted-foreground">
									Run the workflow manually or wait for it to be triggered.
								</p>
							</div>
						{:else}
							<div class="space-y-3">
								{#each executions as execution}
									<div class="flex items-center gap-4 rounded-lg border p-4">
										<div class="flex h-10 w-10 items-center justify-center rounded-full {execution.status === 'completed' ? 'bg-green-100' : execution.status === 'failed' ? 'bg-red-100' : 'bg-blue-100'}">
											{#if execution.status === 'completed'}
												<CheckCircle class="h-5 w-5 text-green-600" />
											{:else if execution.status === 'failed'}
												<XCircle class="h-5 w-5 text-red-600" />
											{:else if execution.status === 'running'}
												<Play class="h-5 w-5 text-blue-600" />
											{:else}
												<Clock class="h-5 w-5 text-blue-600" />
											{/if}
										</div>
										<div class="flex-1">
											<div class="flex items-center gap-2">
												<Badge variant="secondary" class={getStatusColor(execution.status)}>
													{execution.status}
												</Badge>
												<span class="text-sm text-muted-foreground">
													{execution.trigger_type === 'manual' ? 'Manual trigger' : execution.trigger_type}
												</span>
											</div>
											<div class="mt-1 text-sm text-muted-foreground">
												Started: {formatDate(execution.started_at || execution.created_at)}
												{#if execution.duration_ms}
													<span class="ml-2">Duration: {formatDuration(execution.duration_ms)}</span>
												{/if}
											</div>
											{#if execution.error_message}
												<div class="mt-1 flex items-center gap-1 text-sm text-red-600">
													<AlertCircle class="h-3 w-3" />
													{execution.error_message}
												</div>
											{/if}
										</div>
										<div class="text-right text-sm text-muted-foreground">
											<div>{execution.steps_completed} completed</div>
											{#if execution.steps_failed > 0}
												<div class="text-red-600">{execution.steps_failed} failed</div>
											{/if}
										</div>
									</div>
								{/each}
							</div>
						{/if}
					</CardContent>
				</Card>
			</Tabs.Content>
		</Tabs.Root>
	{/if}
</div>
