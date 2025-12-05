<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Plus,
		GripVertical,
		Trash2,
		ChevronDown,
		ChevronUp,
		Mail,
		FilePlus,
		FileEdit,
		FileX,
		Webhook,
		UserPlus,
		Tag,
		Bell,
		Clock,
		GitBranch,
		CheckSquare,
		ArrowRight,
		Copy,
		Settings,
		ToggleLeft
	} from 'lucide-svelte';
	import type { ActionType, WorkflowStepInput } from '$lib/api/workflows';
	import type { Field } from '$lib/api/modules';
	import StepEditor from './StepEditor.svelte';

	interface Props {
		steps: WorkflowStepInput[];
		moduleFields?: Field[];
		onStepsChange?: (steps: WorkflowStepInput[]) => void;
	}

	let { steps = $bindable(), moduleFields = [], onStepsChange }: Props = $props();

	let expandedStepIndex = $state<number | null>(null);
	let draggedIndex = $state<number | null>(null);

	// Action type metadata
	const actionTypes: {
		value: ActionType;
		label: string;
		description: string;
		icon: typeof Mail;
		category: string;
	}[] = [
		{
			value: 'send_email',
			label: 'Send Email',
			description: 'Send an email notification',
			icon: Mail,
			category: 'Communication'
		},
		{
			value: 'send_notification',
			label: 'Send Notification',
			description: 'Send an in-app notification',
			icon: Bell,
			category: 'Communication'
		},
		{
			value: 'create_record',
			label: 'Create Record',
			description: 'Create a new record in a module',
			icon: FilePlus,
			category: 'Record Operations'
		},
		{
			value: 'update_record',
			label: 'Update Record',
			description: 'Update the current or related record',
			icon: FileEdit,
			category: 'Record Operations'
		},
		{
			value: 'update_field',
			label: 'Update Field',
			description: 'Update a specific field value',
			icon: Settings,
			category: 'Record Operations'
		},
		{
			value: 'delete_record',
			label: 'Delete Record',
			description: 'Delete a record',
			icon: FileX,
			category: 'Record Operations'
		},
		{
			value: 'assign_user',
			label: 'Assign User',
			description: 'Assign record to a user',
			icon: UserPlus,
			category: 'Assignment'
		},
		{
			value: 'create_task',
			label: 'Create Task',
			description: 'Create a task linked to the record',
			icon: CheckSquare,
			category: 'Tasks'
		},
		{
			value: 'move_stage',
			label: 'Move Pipeline Stage',
			description: 'Move record to a different stage',
			icon: ArrowRight,
			category: 'Pipeline'
		},
		{
			value: 'add_tag',
			label: 'Add Tag',
			description: 'Add a tag to the record',
			icon: Tag,
			category: 'Tags'
		},
		{
			value: 'remove_tag',
			label: 'Remove Tag',
			description: 'Remove a tag from the record',
			icon: Tag,
			category: 'Tags'
		},
		{
			value: 'webhook',
			label: 'Call Webhook',
			description: 'Send data to an external URL',
			icon: Webhook,
			category: 'Integration'
		},
		{
			value: 'delay',
			label: 'Delay',
			description: 'Wait before next step',
			icon: Clock,
			category: 'Flow Control'
		},
		{
			value: 'condition',
			label: 'Condition Branch',
			description: 'Branch based on conditions',
			icon: GitBranch,
			category: 'Flow Control'
		}
	];

	// Group actions by category
	const groupedActions = $derived(() => {
		const groups: Record<string, typeof actionTypes> = {};
		for (const action of actionTypes) {
			if (!groups[action.category]) {
				groups[action.category] = [];
			}
			groups[action.category].push(action);
		}
		return groups;
	});

	function getActionInfo(actionType: ActionType) {
		return actionTypes.find((a) => a.value === actionType);
	}

	function emitChange() {
		onStepsChange?.(steps);
	}

	function addStep(actionType: ActionType) {
		const actionInfo = getActionInfo(actionType);
		const newStep: WorkflowStepInput = {
			name: actionInfo?.label || actionType,
			action_type: actionType,
			action_config: {},
			continue_on_error: false,
			retry_count: 0,
			retry_delay_seconds: 60
		};
		steps = [...steps, newStep];
		expandedStepIndex = steps.length - 1;
		emitChange();
	}

	function removeStep(index: number) {
		steps = steps.filter((_, i) => i !== index);
		if (expandedStepIndex === index) {
			expandedStepIndex = null;
		} else if (expandedStepIndex !== null && expandedStepIndex > index) {
			expandedStepIndex--;
		}
		emitChange();
	}

	function duplicateStep(index: number) {
		const original = steps[index];
		const duplicate: WorkflowStepInput = {
			...original,
			id: undefined,
			name: `${original.name || 'Step'} (copy)`
		};
		steps = [...steps.slice(0, index + 1), duplicate, ...steps.slice(index + 1)];
		expandedStepIndex = index + 1;
		emitChange();
	}

	function moveStep(fromIndex: number, toIndex: number) {
		if (toIndex < 0 || toIndex >= steps.length) return;
		const newSteps = [...steps];
		const [removed] = newSteps.splice(fromIndex, 1);
		newSteps.splice(toIndex, 0, removed);
		steps = newSteps;
		if (expandedStepIndex === fromIndex) {
			expandedStepIndex = toIndex;
		}
		emitChange();
	}

	function updateStep(index: number, updates: Partial<WorkflowStepInput>) {
		steps = steps.map((step, i) => (i === index ? { ...step, ...updates } : step));
		emitChange();
	}

	function toggleExpanded(index: number) {
		expandedStepIndex = expandedStepIndex === index ? null : index;
	}

	function handleDragStart(index: number) {
		draggedIndex = index;
	}

	function handleDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (draggedIndex !== null && draggedIndex !== index) {
			moveStep(draggedIndex, index);
			draggedIndex = index;
		}
	}

	function handleDragEnd() {
		draggedIndex = null;
	}
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<Card.Title class="text-base">Actions</Card.Title>
		<Card.Description>
			Define what happens when this workflow runs. Actions execute in order.
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-3">
		{#if steps.length === 0}
			<div class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8">
				<p class="mb-4 text-sm text-muted-foreground">No actions configured yet</p>
				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						{#snippet child({ props })}
							<Button variant="outline" {...props}>
								<Plus class="mr-2 h-4 w-4" />
								Add Action
							</Button>
						{/snippet}
					</DropdownMenu.Trigger>
					<DropdownMenu.Content class="w-72">
						{#each Object.entries(groupedActions()) as [category, actions]}
							<DropdownMenu.Group>
								<DropdownMenu.GroupHeading>{category}</DropdownMenu.GroupHeading>
								{#each actions as action}
									{@const Icon = action.icon}
									<DropdownMenu.Item onclick={() => addStep(action.value)}>
										<Icon class="mr-2 h-4 w-4" />
										<div class="flex flex-col">
											<span>{action.label}</span>
											<span class="text-xs text-muted-foreground">{action.description}</span>
										</div>
									</DropdownMenu.Item>
								{/each}
							</DropdownMenu.Group>
							<DropdownMenu.Separator />
						{/each}
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			</div>
		{:else}
			<div class="space-y-2">
				{#each steps as step, index (step.id ?? `new-${index}`)}
					{@const actionInfo = getActionInfo(step.action_type)}
					{@const Icon = actionInfo?.icon || Settings}
					<div
						class="rounded-lg border bg-card transition-shadow hover:shadow-sm"
						class:ring-2={draggedIndex === index}
						class:ring-primary={draggedIndex === index}
						draggable="true"
						ondragstart={() => handleDragStart(index)}
						ondragover={(e) => handleDragOver(e, index)}
						ondragend={handleDragEnd}
						role="listitem"
						aria-label="Workflow step {index + 1}: {step.name || actionInfo?.label}"
					>
						<!-- Step Header -->
						<div
							class="flex cursor-pointer items-center gap-3 p-3"
							onclick={() => toggleExpanded(index)}
							onkeydown={(e) => e.key === 'Enter' && toggleExpanded(index)}
							role="button"
							tabindex="0"
						>
							<div
								class="cursor-grab text-muted-foreground hover:text-foreground"
								onclick={(e) => e.stopPropagation()}
								onkeydown={(e) => e.stopPropagation()}
								role="presentation"
								aria-hidden="true"
							>
								<GripVertical class="h-4 w-4" />
							</div>

							<div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-md bg-primary/10">
								<Icon class="h-4 w-4 text-primary" />
							</div>

							<div class="min-w-0 flex-1">
								<div class="flex items-center gap-2">
									<span class="font-medium">{step.name || actionInfo?.label || 'Unnamed Step'}</span>
									<Badge variant="secondary" class="text-xs">
										{index + 1}
									</Badge>
									{#if step.continue_on_error}
										<Badge variant="outline" class="text-xs">
											<ToggleLeft class="mr-1 h-3 w-3" />
											Continue on error
										</Badge>
									{/if}
								</div>
								<p class="truncate text-sm text-muted-foreground">
									{actionInfo?.description || step.action_type}
								</p>
							</div>

							<div class="flex items-center gap-1">
								<Button
									variant="ghost"
									size="icon"
									class="h-7 w-7"
									onclick={(e) => {
										e.stopPropagation();
										moveStep(index, index - 1);
									}}
									disabled={index === 0}
								>
									<ChevronUp class="h-4 w-4" />
								</Button>
								<Button
									variant="ghost"
									size="icon"
									class="h-7 w-7"
									onclick={(e) => {
										e.stopPropagation();
										moveStep(index, index + 1);
									}}
									disabled={index === steps.length - 1}
								>
									<ChevronDown class="h-4 w-4" />
								</Button>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<Button
												variant="ghost"
												size="icon"
												class="h-7 w-7"
												{...props}
												onclick={(e: MouseEvent) => e.stopPropagation()}
											>
												<Settings class="h-4 w-4" />
											</Button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => duplicateStep(index)}>
											<Copy class="mr-2 h-4 w-4" />
											Duplicate
										</DropdownMenu.Item>
										<DropdownMenu.Separator />
										<DropdownMenu.Item
											class="text-destructive focus:text-destructive"
											onclick={() => removeStep(index)}
										>
											<Trash2 class="mr-2 h-4 w-4" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</div>
						</div>

						<!-- Step Editor (expanded) -->
						{#if expandedStepIndex === index}
							<div class="border-t px-3 pb-3 pt-3">
								<StepEditor
									{step}
									{moduleFields}
									onStepChange={(updates) => updateStep(index, updates)}
								/>
							</div>
						{/if}
					</div>

					{#if index < steps.length - 1}
						<div class="flex justify-center py-1">
							<div class="h-4 w-px bg-border"></div>
						</div>
					{/if}
				{/each}
			</div>

			<!-- Add Step Button -->
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button variant="outline" class="w-full" {...props}>
							<Plus class="mr-2 h-4 w-4" />
							Add Action
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content class="w-72">
					{#each Object.entries(groupedActions()) as [category, actions]}
						<DropdownMenu.Group>
							<DropdownMenu.GroupHeading>{category}</DropdownMenu.GroupHeading>
							{#each actions as action}
								{@const Icon = action.icon}
								<DropdownMenu.Item onclick={() => addStep(action.value)}>
									<Icon class="mr-2 h-4 w-4" />
									<div class="flex flex-col">
										<span>{action.label}</span>
										<span class="text-xs text-muted-foreground">{action.description}</span>
									</div>
								</DropdownMenu.Item>
							{/each}
						</DropdownMenu.Group>
						<DropdownMenu.Separator />
					{/each}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		{/if}
	</Card.Content>
</Card.Root>
