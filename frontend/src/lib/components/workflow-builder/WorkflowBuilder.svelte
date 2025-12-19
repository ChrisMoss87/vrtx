<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { toast } from 'svelte-sonner';
	import { Save, Play, ArrowLeft, Loader2 } from 'lucide-svelte';
	import type {
		Workflow,
		WorkflowInput,
		TriggerType,
		TriggerTiming,
		TriggerConfig,
		WorkflowConditions,
		Condition,
		WorkflowStepInput
	} from '$lib/api/workflows';
	import type { Module, Field } from '$lib/api/modules';
	import TriggerConfigComponent from './TriggerConfig.svelte';
	import ConditionBuilder from './ConditionBuilder.svelte';
	import StepList from './StepList.svelte';

	interface Props {
		workflow?: Workflow | Partial<WorkflowInput>;
		modules: Module[];
		onSave: (data: WorkflowInput) => Promise<Workflow>;
		onCancel: () => void;
		onTest?: (workflow: Workflow) => void;
	}

	let { workflow, modules, onSave, onCancel, onTest }: Props = $props();

	const isNew = !workflow || !('id' in workflow);

	// Form state
	let name = $state(workflow?.name || '');
	let description = $state(workflow?.description || '');
	let moduleId = $state<number | null>(workflow?.module_id || null);
	let isActive = $state(workflow?.is_active ?? false);
	let priority = $state(workflow?.priority ?? 100);
	let triggerType = $state<TriggerType>(workflow?.trigger_type || 'record_created');
	let triggerConfig = $state<TriggerConfig>(workflow?.trigger_config || {});
	let triggerTiming = $state<TriggerTiming>(workflow?.trigger_timing || 'all');
	let watchedFields = $state<string[]>(workflow?.watched_fields || []);
	let conditions = $state<WorkflowConditions | Condition[] | null>(workflow?.conditions || null);
	let stopOnFirstMatch = $state(workflow?.stop_on_first_match ?? false);
	let maxExecutionsPerDay = $state<number | null>(workflow?.max_executions_per_day || null);
	let runOncePerRecord = $state(workflow?.run_once_per_record ?? false);
	let allowManualTrigger = $state(workflow?.allow_manual_trigger ?? true);
	let delaySeconds = $state(workflow?.delay_seconds ?? 0);
	let scheduleCron = $state(workflow?.schedule_cron || '');
	let steps = $state<WorkflowStepInput[]>(
		workflow?.steps?.map((s) => ({
			id: s.id,
			name: s.name || undefined,
			action_type: s.action_type,
			action_config: s.action_config,
			conditions: s.conditions || undefined,
			continue_on_error: s.continue_on_error,
			retry_count: s.retry_count,
			retry_delay_seconds: s.retry_delay_seconds
		})) || []
	);

	let saving = $state(false);
	let testing = $state(false);

	// Get module fields for the selected module
	const selectedModule = $derived(modules.find((m) => m.id === moduleId));
	const moduleFields = $derived<Field[]>(selectedModule?.fields || []);

	// Validation
	const isValid = $derived(() => {
		if (!name.trim()) return false;
		if (!triggerType) return false;
		// Module is required for record-based triggers
		if (
			['record_created', 'record_updated', 'record_deleted', 'record_saved', 'field_changed'].includes(
				triggerType
			) &&
			!moduleId
		) {
			return false;
		}
		return true;
	});

	async function handleSave() {
		if (!isValid()) {
			toast.error('Please fill in all required fields');
			return;
		}

		saving = true;
		try {
			const data: WorkflowInput = {
				name,
				description: description || undefined,
				module_id: moduleId,
				is_active: isActive,
				priority,
				trigger_type: triggerType,
				trigger_config: triggerConfig,
				trigger_timing: triggerTiming,
				watched_fields: watchedFields.length > 0 ? watchedFields : undefined,
				conditions: conditions || undefined,
				stop_on_first_match: stopOnFirstMatch,
				max_executions_per_day: maxExecutionsPerDay,
				run_once_per_record: runOncePerRecord,
				allow_manual_trigger: allowManualTrigger,
				delay_seconds: delaySeconds,
				schedule_cron: scheduleCron || undefined,
				steps
			};

			await onSave(data);
			toast.success(isNew ? 'Workflow created' : 'Workflow saved');
		} catch (error) {
			console.error('Failed to save workflow:', error);
			toast.error('Failed to save workflow');
		} finally {
			saving = false;
		}
	}

	async function handleTest() {
		if (!workflow || !onTest || !('id' in workflow)) return;
		testing = true;
		try {
			onTest(workflow as Workflow);
		} finally {
			testing = false;
		}
	}
</script>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={onCancel}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">{isNew ? 'Create Workflow' : 'Edit Workflow'}</h1>
				<p class="text-muted-foreground">
					{isNew ? 'Set up a new automated workflow' : 'Modify workflow settings and actions'}
				</p>
			</div>
		</div>
		<div class="flex items-center gap-2">
			{#if !isNew && onTest}
				<Button variant="outline" onclick={handleTest} disabled={testing}>
					{#if testing}
						<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					{:else}
						<Play class="mr-2 h-4 w-4" />
					{/if}
					Test
				</Button>
			{/if}
			<Button onclick={handleSave} disabled={saving || !isValid()}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{:else}
					<Save class="mr-2 h-4 w-4" />
				{/if}
				{isNew ? 'Create' : 'Save'}
			</Button>
		</div>
	</div>

	<div class="grid gap-6 lg:grid-cols-3">
		<!-- Left Column: Basic Info & Settings -->
		<div class="space-y-6 lg:col-span-1">
			<!-- Basic Information -->
			<Card.Root>
				<Card.Header class="pb-3">
					<Card.Title class="text-base">Basic Information</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="space-y-2">
						<Label>Name *</Label>
						<Input
							bind:value={name}
							placeholder="e.g., Send welcome email on lead creation"
						/>
					</div>

					<div class="space-y-2">
						<Label>Description</Label>
						<Textarea
							bind:value={description}
							placeholder="What does this workflow do?"
							rows={3}
						/>
					</div>

					<div class="space-y-2">
						<Label>Module *</Label>
						<Select.Root
							type="single"
							value={moduleId ? String(moduleId) : ''}
							onValueChange={(v) => {
								moduleId = v ? parseInt(v) : null;
								// Reset watched fields when module changes
								watchedFields = [];
							}}
						>
							<Select.Trigger>
								{selectedModule?.name || 'Select module'}
							</Select.Trigger>
							<Select.Content>
								{#each modules as module}
									<Select.Item value={String(module.id)}>{module.name}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>

					<div class="flex items-center justify-between">
						<div>
							<Label>Active</Label>
							<p class="text-xs text-muted-foreground">Enable this workflow</p>
						</div>
						<Switch bind:checked={isActive} />
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Advanced Settings -->
			<Card.Root>
				<Card.Header class="pb-3">
					<Card.Title class="text-base">Advanced Settings</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="space-y-2">
						<Label>Priority</Label>
						<Input
							type="number"
							min="1"
							max="1000"
							bind:value={priority}
						/>
						<p class="text-xs text-muted-foreground">
							Lower numbers run first (1-1000)
						</p>
					</div>

					<div class="space-y-2">
						<Label>Max Executions Per Day</Label>
						<Input
							type="number"
							min="0"
							value={maxExecutionsPerDay ? String(maxExecutionsPerDay) : ''}
							oninput={(e) => {
								const val = parseInt(e.currentTarget.value);
								maxExecutionsPerDay = val > 0 ? val : null;
							}}
							placeholder="Unlimited"
						/>
						<p class="text-xs text-muted-foreground">
							Leave empty for unlimited
						</p>
					</div>

					<div class="space-y-2">
						<Label>Initial Delay (seconds)</Label>
						<Input
							type="number"
							min="0"
							bind:value={delaySeconds}
						/>
						<p class="text-xs text-muted-foreground">
							Wait before starting execution
						</p>
					</div>

					<div class="flex items-center justify-between">
						<div>
							<Label>Run Once Per Record</Label>
							<p class="text-xs text-muted-foreground">
								Only trigger once per record
							</p>
						</div>
						<Switch bind:checked={runOncePerRecord} />
					</div>

					<div class="flex items-center justify-between">
						<div>
							<Label>Allow Manual Trigger</Label>
							<p class="text-xs text-muted-foreground">
								Allow users to run manually
							</p>
						</div>
						<Switch bind:checked={allowManualTrigger} />
					</div>

					<div class="flex items-center justify-between">
						<div>
							<Label>Stop on First Match</Label>
							<p class="text-xs text-muted-foreground">
								Stop if this workflow runs
							</p>
						</div>
						<Switch bind:checked={stopOnFirstMatch} />
					</div>
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Right Column: Trigger, Conditions, Actions -->
		<div class="space-y-6 lg:col-span-2">
			<!-- Trigger Configuration -->
			<TriggerConfigComponent
				bind:triggerType
				bind:triggerConfig
				bind:triggerTiming
				bind:watchedFields
				moduleFields={moduleFields}
			/>

			<!-- Conditions -->
			<ConditionBuilder
				bind:conditions
				moduleFields={moduleFields}
			/>

			<!-- Actions -->
			<StepList
				bind:steps
				moduleFields={moduleFields}
			/>
		</div>
	</div>
</div>
