<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import VariableInserter from '../VariableInserter.svelte';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let subject = $state<string>((config.subject as string) || '');
	let description = $state<string>((config.description as string) || '');
	let priority = $state<string>((config.priority as string) || 'medium');
	let status = $state<string>((config.status as string) || 'open');
	let assigneeType = $state<string>((config.assignee_type as string) || 'record_owner');
	let assigneeUserId = $state<number | null>((config.assignee_user_id as number) || null);
	let assigneeField = $state<string>((config.assignee_field as string) || '');
	let dueDateType = $state<string>((config.due_date_type as string) || 'relative');
	let dueDateOffset = $state<number>((config.due_date_offset as number) || 1);
	let dueDateUnit = $state<string>((config.due_date_unit as string) || 'days');
	let dueDateField = $state<string>((config.due_date_field as string) || '');

	function emitChange() {
		onConfigChange?.({
			subject,
			description,
			priority,
			status,
			assignee_type: assigneeType,
			assignee_user_id: assigneeUserId,
			assignee_field: assigneeField,
			due_date_type: dueDateType,
			due_date_offset: dueDateOffset,
			due_date_unit: dueDateUnit,
			due_date_field: dueDateField
		});
	}

	// User fields for assignee
	const userFields = $derived(moduleFields.filter((f) => f.type === 'lookup' || f.type === 'user'));

	// Date fields for due date
	const dateFields = $derived(moduleFields.filter((f) => ['date', 'datetime'].includes(f.type)));

	function insertVariable(variable: string, target: 'subject' | 'description') {
		if (target === 'subject') {
			subject = `${subject}{{${variable}}}`;
		} else {
			description = `${description}{{${variable}}}`;
		}
		emitChange();
	}
</script>

<div class="space-y-4">
	<h4 class="font-medium">Create Task Configuration</h4>

	<!-- Subject -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Subject</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'subject')} />
		</div>
		<Input
			value={subject}
			oninput={(e) => {
				subject = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Task subject - use variables like record.name"
		/>
	</div>

	<!-- Description -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Description (optional)</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'description')} />
		</div>
		<Textarea
			value={description}
			oninput={(e) => {
				description = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Task description"
			rows={3}
		/>
	</div>

	<!-- Priority & Status -->
	<div class="grid gap-4 sm:grid-cols-2">
		<div class="space-y-2">
			<Label>Priority</Label>
			<Select.Root
				type="single"
				value={priority}
				onValueChange={(v) => {
					if (v) {
						priority = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{priority === 'low' ? 'Low' : priority === 'medium' ? 'Medium' : priority === 'high' ? 'High' : 'Urgent'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="low">Low</Select.Item>
					<Select.Item value="medium">Medium</Select.Item>
					<Select.Item value="high">High</Select.Item>
					<Select.Item value="urgent">Urgent</Select.Item>
				</Select.Content>
			</Select.Root>
		</div>

		<div class="space-y-2">
			<Label>Status</Label>
			<Select.Root
				type="single"
				value={status}
				onValueChange={(v) => {
					if (v) {
						status = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{status === 'open' ? 'Open' : status === 'in_progress' ? 'In Progress' : 'Completed'}
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="open">Open</Select.Item>
					<Select.Item value="in_progress">In Progress</Select.Item>
				</Select.Content>
			</Select.Root>
		</div>
	</div>

	<!-- Assignee -->
	<div class="space-y-2">
		<Label>Assign To</Label>
		<Select.Root
			type="single"
			value={assigneeType}
			onValueChange={(v) => {
				if (v) {
					assigneeType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{assigneeType === 'record_owner'
					? 'Record Owner'
					: assigneeType === 'current_user'
						? 'Current User (Trigger User)'
						: assigneeType === 'specific'
							? 'Specific User'
							: assigneeType === 'from_field'
								? 'From Field Value'
								: 'Select assignee'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="record_owner">Record Owner</Select.Item>
				<Select.Item value="current_user">Current User (Trigger User)</Select.Item>
				<Select.Item value="specific">Specific User</Select.Item>
				<Select.Item value="from_field">From Field Value</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	{#if assigneeType === 'from_field'}
		<div class="space-y-2">
			<Label>User Field</Label>
			<Select.Root
				type="single"
				value={assigneeField}
				onValueChange={(v) => {
					if (v) {
						assigneeField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{userFields.find((f) => f.api_name === assigneeField)?.label || 'Select field'}
				</Select.Trigger>
				<Select.Content>
					{#each userFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Due Date -->
	<div class="space-y-2">
		<Label>Due Date</Label>
		<Select.Root
			type="single"
			value={dueDateType}
			onValueChange={(v) => {
				if (v) {
					dueDateType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{dueDateType === 'relative'
					? 'Relative to Now'
					: dueDateType === 'from_field'
						? 'From Field Value'
						: 'No Due Date'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="relative">Relative to Now</Select.Item>
				<Select.Item value="from_field">From Field Value</Select.Item>
				<Select.Item value="none">No Due Date</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	{#if dueDateType === 'relative'}
		<div class="grid gap-4 sm:grid-cols-2">
			<div class="space-y-2">
				<Label>Offset</Label>
				<Input
					type="number"
					min="0"
					value={String(dueDateOffset)}
					oninput={(e) => {
						dueDateOffset = parseInt(e.currentTarget.value) || 0;
						emitChange();
					}}
				/>
			</div>
			<div class="space-y-2">
				<Label>Unit</Label>
				<Select.Root
					type="single"
					value={dueDateUnit}
					onValueChange={(v) => {
						if (v) {
							dueDateUnit = v;
							emitChange();
						}
					}}
				>
					<Select.Trigger>
						{dueDateUnit === 'hours'
							? 'Hours'
							: dueDateUnit === 'days'
								? 'Days'
								: 'Weeks'}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="hours">Hours</Select.Item>
						<Select.Item value="days">Days</Select.Item>
						<Select.Item value="weeks">Weeks</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>
		</div>
		<p class="text-xs text-muted-foreground">
			Due date will be {dueDateOffset} {dueDateUnit} from when the workflow runs
		</p>
	{/if}

	{#if dueDateType === 'from_field'}
		<div class="space-y-2">
			<Label>Date Field</Label>
			<Select.Root
				type="single"
				value={dueDateField}
				onValueChange={(v) => {
					if (v) {
						dueDateField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{dateFields.find((f) => f.api_name === dueDateField)?.label || 'Select date field'}
				</Select.Trigger>
				<Select.Content>
					{#each dateFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Info -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			The task will be automatically linked to the triggering record.
		</p>
	</div>
</div>
