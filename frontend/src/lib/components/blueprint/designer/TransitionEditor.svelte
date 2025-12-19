<script lang="ts">
	import type {
		BlueprintState,
		BlueprintTransition,
		BlueprintTransitionCondition,
		BlueprintTransitionRequirement,
		BlueprintTransitionAction,
		BlueprintApproval
	} from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as ScrollArea from '$lib/components/ui/scroll-area';
	import { toast } from 'svelte-sonner';
	import XIcon from '@lucide/svelte/icons/x';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';
	import ConditionBuilder from './ConditionBuilder.svelte';
	import RequirementBuilder from './RequirementBuilder.svelte';
	import ActionBuilder from './ActionBuilder.svelte';
	import ApprovalBuilder from './ApprovalBuilder.svelte';

	interface Field {
		id: number;
		api_name: string;
		label: string;
		type: string;
	}

	interface Props {
		transition: BlueprintTransition;
		states: BlueprintState[];
		fields: Field[];
		blueprintId: number;
		readonly?: boolean;
		onUpdate?: (transition: BlueprintTransition) => void;
		onDelete?: () => void;
		onClose?: () => void;
	}

	let {
		transition,
		states,
		fields = [],
		blueprintId,
		readonly = false,
		onUpdate,
		onDelete,
		onClose
	}: Props = $props();

	let saving = $state(false);
	let activeTab = $state('general');

	// Local copy of transition data for editing
	let localTransition = $state<BlueprintTransition>({ ...transition });

	// Form state
	let name = $state(transition.name);
	let description = $state(transition.description || '');
	let buttonLabel = $state(transition.button_label || '');
	let isActive = $state(transition.is_active);
	let fromStateId = $state<number | null>(transition.from_state_id);
	let toStateId = $state(transition.to_state_id);

	// Update form state when transition prop changes
	$effect(() => {
		localTransition = { ...transition };
		name = transition.name;
		description = transition.description || '';
		buttonLabel = transition.button_label || '';
		isActive = transition.is_active;
		fromStateId = transition.from_state_id;
		toStateId = transition.to_state_id;
	});

	// Get stage options for dropdowns
	const fromStageOptions = $derived([
		{ value: 'null', label: 'Any Stage (Initial)' },
		...states.map((s) => ({ value: s.id.toString(), label: s.name }))
	]);

	const toStageOptions = $derived(states.map((s) => ({ value: s.id.toString(), label: s.name })));

	async function handleSave() {
		saving = true;
		try {
			const updated = await blueprintApi.updateTransition(blueprintId, transition.id, {
				name,
				description: description || undefined,
				button_label: buttonLabel || undefined,
				is_active: isActive,
				from_state_id: fromStateId,
				to_state_id: toStateId
			});
			// Preserve the sub-items from local state
			updated.conditions = localTransition.conditions;
			updated.requirements = localTransition.requirements;
			updated.actions = localTransition.actions;
			updated.approval = localTransition.approval;
			onUpdate?.(updated);
			toast.success('Transition updated');
		} catch (error) {
			console.error('Failed to update transition:', error);
			toast.error('Failed to update transition');
		} finally {
			saving = false;
		}
	}

	async function handleDelete() {
		if (!confirm('Are you sure you want to delete this transition?')) {
			return;
		}
		onDelete?.();
	}

	// Condition handlers
	async function handleAddCondition(condition: Partial<BlueprintTransitionCondition>) {
		try {
			const created = await blueprintApi.createCondition(transition.id, {
				field_id: condition.field_id!,
				operator: condition.operator!,
				value: condition.value,
				logical_group: condition.logical_group,
				display_order: condition.display_order
			});
			localTransition.conditions = [...(localTransition.conditions || []), created];
			toast.success('Condition added');
		} catch (error) {
			console.error('Failed to add condition:', error);
			toast.error('Failed to add condition');
		}
	}

	async function handleUpdateCondition(id: number, data: Partial<BlueprintTransitionCondition>) {
		try {
			const updated = await blueprintApi.updateCondition(transition.id, id, data);
			localTransition.conditions = (localTransition.conditions || []).map((c) =>
				c.id === id ? updated : c
			);
			toast.success('Condition updated');
		} catch (error) {
			console.error('Failed to update condition:', error);
			toast.error('Failed to update condition');
		}
	}

	async function handleDeleteCondition(id: number) {
		try {
			await blueprintApi.deleteCondition(transition.id, id);
			localTransition.conditions = (localTransition.conditions || []).filter((c) => c.id !== id);
			toast.success('Condition deleted');
		} catch (error) {
			console.error('Failed to delete condition:', error);
			toast.error('Failed to delete condition');
		}
	}

	// Requirement handlers
	async function handleAddRequirement(requirement: Partial<BlueprintTransitionRequirement>) {
		try {
			const created = await blueprintApi.createRequirement(transition.id, {
				type: requirement.type!,
				field_id: requirement.field_id ?? undefined,
				label: requirement.label ?? undefined,
				description: requirement.description ?? undefined,
				is_required: requirement.is_required,
				config: requirement.config ?? undefined,
				display_order: requirement.display_order
			});
			localTransition.requirements = [...(localTransition.requirements || []), created];
			toast.success('Requirement added');
		} catch (error) {
			console.error('Failed to add requirement:', error);
			toast.error('Failed to add requirement');
		}
	}

	async function handleUpdateRequirement(
		id: number,
		data: Partial<BlueprintTransitionRequirement>
	) {
		try {
			const updated = await blueprintApi.updateRequirement(transition.id, id, data);
			localTransition.requirements = (localTransition.requirements || []).map((r) =>
				r.id === id ? updated : r
			);
			toast.success('Requirement updated');
		} catch (error) {
			console.error('Failed to update requirement:', error);
			toast.error('Failed to update requirement');
		}
	}

	async function handleDeleteRequirement(id: number) {
		try {
			await blueprintApi.deleteRequirement(transition.id, id);
			localTransition.requirements = (localTransition.requirements || []).filter(
				(r) => r.id !== id
			);
			toast.success('Requirement deleted');
		} catch (error) {
			console.error('Failed to delete requirement:', error);
			toast.error('Failed to delete requirement');
		}
	}

	// Action handlers
	async function handleAddAction(action: Partial<BlueprintTransitionAction>) {
		try {
			const created = await blueprintApi.createAction(transition.id, {
				type: action.type!,
				config: action.config!,
				display_order: action.display_order,
				is_active: action.is_active
			});
			localTransition.actions = [...(localTransition.actions || []), created];
			toast.success('Action added');
		} catch (error) {
			console.error('Failed to add action:', error);
			toast.error('Failed to add action');
		}
	}

	async function handleUpdateAction(id: number, data: Partial<BlueprintTransitionAction>) {
		try {
			const updated = await blueprintApi.updateAction(transition.id, id, data);
			localTransition.actions = (localTransition.actions || []).map((a) =>
				a.id === id ? updated : a
			);
			toast.success('Action updated');
		} catch (error) {
			console.error('Failed to update action:', error);
			toast.error('Failed to update action');
		}
	}

	async function handleDeleteAction(id: number) {
		try {
			await blueprintApi.deleteAction(transition.id, id);
			localTransition.actions = (localTransition.actions || []).filter((a) => a.id !== id);
			toast.success('Action deleted');
		} catch (error) {
			console.error('Failed to delete action:', error);
			toast.error('Failed to delete action');
		}
	}

	// Approval handlers
	async function handleSaveApproval(approval: Partial<BlueprintApproval>) {
		try {
			const saved = await blueprintApi.setApproval(transition.id, approval);
			localTransition.approval = saved;
			toast.success('Approval settings saved');
		} catch (error) {
			console.error('Failed to save approval:', error);
			toast.error('Failed to save approval settings');
		}
	}

	async function handleDeleteApproval() {
		try {
			await blueprintApi.removeApproval(transition.id);
			localTransition.approval = null;
			toast.success('Approval removed');
		} catch (error) {
			console.error('Failed to remove approval:', error);
			toast.error('Failed to remove approval');
		}
	}

	// Computed counts
	const conditionCount = $derived(localTransition.conditions?.length || 0);
	const requirementCount = $derived(localTransition.requirements?.length || 0);
	const actionCount = $derived(localTransition.actions?.length || 0);
	const hasApproval = $derived(!!localTransition.approval);
</script>

<div class="flex h-full flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b pb-3">
		<h3 class="font-semibold">Edit Transition</h3>
		<Button variant="ghost" size="icon" onclick={onClose}>
			<XIcon class="h-4 w-4" />
		</Button>
	</div>

	<!-- Tabs -->
	<Tabs.Root bind:value={activeTab} class="mt-4 flex min-h-0 flex-1 flex-col">
		<Tabs.List class="grid w-full shrink-0 grid-cols-4">
			<Tabs.Trigger value="general">General</Tabs.Trigger>
			<Tabs.Trigger value="conditions" class="relative">
				Conditions
				{#if conditionCount > 0}
					<span
						class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] text-white"
					>
						{conditionCount}
					</span>
				{/if}
			</Tabs.Trigger>
			<Tabs.Trigger value="requirements" class="relative">
				During
				{#if requirementCount > 0}
					<span
						class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[10px] text-white"
					>
						{requirementCount}
					</span>
				{/if}
			</Tabs.Trigger>
			<Tabs.Trigger value="actions" class="relative">
				After
				{#if actionCount > 0 || hasApproval}
					<span
						class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-green-500 text-[10px] text-white"
					>
						{actionCount + (hasApproval ? 1 : 0)}
					</span>
				{/if}
			</Tabs.Trigger>
		</Tabs.List>

		<div class="min-h-0 flex-1 overflow-y-auto">
			<!-- General Tab -->
			<Tabs.Content value="general" class="mt-4 space-y-4 pr-2">
				<!-- Name -->
				<div class="space-y-2">
					<Label for="transition-name">Name</Label>
					<Input
						id="transition-name"
						bind:value={name}
						disabled={readonly}
						placeholder="Transition name"
					/>
				</div>

				<!-- Button Label -->
				<div class="space-y-2">
					<Label for="button-label">Button Label (optional)</Label>
					<Input
						id="button-label"
						bind:value={buttonLabel}
						disabled={readonly}
						placeholder="Custom button text"
					/>
					<p class="text-xs text-muted-foreground">
						Text shown on the transition button. Defaults to transition name if empty.
					</p>
				</div>

				<!-- Description -->
				<div class="space-y-2">
					<Label for="description">Description</Label>
					<Textarea
						id="description"
						bind:value={description}
						disabled={readonly}
						placeholder="Describe this transition..."
						rows={3}
					/>
				</div>

				<!-- From Stage -->
				<div class="space-y-2">
					<Label>From Stage</Label>
					<Select.Root
						type="single"
						value={fromStateId?.toString() || 'null'}
						onValueChange={(v) => (fromStateId = v === 'null' ? null : parseInt(v))}
						disabled={readonly}
					>
						<Select.Trigger class="w-full">
							{fromStateId
								? states.find((s) => s.id === fromStateId)?.name || 'Select stage'
								: 'Any Stage (Initial)'}
						</Select.Trigger>
						<Select.Content>
							{#each fromStageOptions as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- To Stage -->
				<div class="space-y-2">
					<Label>To Stage</Label>
					<Select.Root
						type="single"
						value={toStateId.toString()}
						onValueChange={(v) => (toStateId = parseInt(v))}
						disabled={readonly}
					>
						<Select.Trigger class="w-full">
							{states.find((s) => s.id === toStateId)?.name || 'Select stage'}
						</Select.Trigger>
						<Select.Content>
							{#each toStageOptions as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<!-- Is Active -->
				<div class="flex items-center justify-between">
					<div>
						<Label for="is-active">Active</Label>
						<p class="text-xs text-muted-foreground">Enable this transition</p>
					</div>
					<Switch
						id="is-active"
						checked={isActive}
						onCheckedChange={(checked) => (isActive = checked)}
						disabled={readonly}
					/>
				</div>
			</Tabs.Content>

			<!-- Conditions Tab -->
			<Tabs.Content value="conditions" class="mt-4 pr-2">
				<ConditionBuilder
					conditions={localTransition.conditions || []}
					{fields}
					{readonly}
					onAdd={handleAddCondition}
					onUpdate={handleUpdateCondition}
					onDelete={handleDeleteCondition}
				/>
			</Tabs.Content>

			<!-- Requirements Tab -->
			<Tabs.Content value="requirements" class="mt-4 pr-2">
				<RequirementBuilder
					requirements={localTransition.requirements || []}
					{fields}
					{readonly}
					onAdd={handleAddRequirement}
					onUpdate={handleUpdateRequirement}
					onDelete={handleDeleteRequirement}
				/>
			</Tabs.Content>

			<!-- Actions Tab -->
			<Tabs.Content value="actions" class="mt-4 space-y-4 pr-2">
				<ApprovalBuilder
					approval={localTransition.approval}
					{readonly}
					onSave={handleSaveApproval}
					onDelete={handleDeleteApproval}
				/>

				<ActionBuilder
					actions={localTransition.actions || []}
					{fields}
					{readonly}
					onAdd={handleAddAction}
					onUpdate={handleUpdateAction}
					onDelete={handleDeleteAction}
				/>
			</Tabs.Content>
		</div>
	</Tabs.Root>

	<!-- Actions -->
	{#if !readonly}
		<div class="mt-4 flex gap-2 border-t pt-4">
			<Button variant="destructive" size="sm" onclick={handleDelete}>
				<Trash2Icon class="mr-2 h-4 w-4" />
				Delete
			</Button>
			<Button class="flex-1" size="sm" onclick={handleSave} disabled={saving}>
				{saving ? 'Saving...' : 'Save Changes'}
			</Button>
		</div>
	{/if}
</div>
