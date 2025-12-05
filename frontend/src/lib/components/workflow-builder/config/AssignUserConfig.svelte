<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let assignmentType = $state<string>((config.assignment_type as string) || 'specific');
	let specificUserId = $state<number | null>((config.user_id as number) || null);
	let roleId = $state<number | null>((config.role_id as number) || null);
	let roundRobinGroup = $state<string>((config.round_robin_group as string) || '');
	let fromField = $state<string>((config.from_field as string) || '');

	function emitChange() {
		onConfigChange?.({
			assignment_type: assignmentType,
			user_id: specificUserId,
			role_id: roleId,
			round_robin_group: roundRobinGroup,
			from_field: fromField
		});
	}

	// User lookup fields
	const userFields = $derived(moduleFields.filter((f) => f.type === 'lookup' || f.type === 'user'));
</script>

<div class="space-y-4">
	<h4 class="font-medium">Assign User Configuration</h4>

	<!-- Assignment Type -->
	<div class="space-y-2">
		<Label>Assignment Method</Label>
		<Select.Root
			type="single"
			value={assignmentType}
			onValueChange={(v) => {
				if (v) {
					assignmentType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{assignmentType === 'specific'
					? 'Specific User'
					: assignmentType === 'round_robin'
						? 'Round Robin'
						: assignmentType === 'role_based'
							? 'Role-Based'
							: assignmentType === 'from_field'
								? 'From Field Value'
								: assignmentType === 'record_owner'
									? 'Record Owner'
									: assignmentType === 'current_user'
										? 'Current User'
										: 'Select method'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="specific">Specific User</Select.Item>
				<Select.Item value="round_robin">Round Robin</Select.Item>
				<Select.Item value="role_based">Role-Based Assignment</Select.Item>
				<Select.Item value="from_field">From Field Value</Select.Item>
				<Select.Item value="record_owner">Record Owner</Select.Item>
				<Select.Item value="current_user">Current User (Trigger User)</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Specific User Selection -->
	{#if assignmentType === 'specific'}
		<div class="space-y-2">
			<Label>Select User</Label>
			<Select.Root
				type="single"
				value={specificUserId ? String(specificUserId) : ''}
				onValueChange={(v) => {
					specificUserId = v ? parseInt(v) : null;
					emitChange();
				}}
			>
				<Select.Trigger>
					{specificUserId ? `User #${specificUserId}` : 'Select user'}
				</Select.Trigger>
				<Select.Content>
					<!-- In real implementation, would load users from API -->
					<Select.Item value="1">Admin User</Select.Item>
					<Select.Item value="2">Sales Manager</Select.Item>
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">
				The record will always be assigned to this specific user
			</p>
		</div>
	{/if}

	<!-- Round Robin Configuration -->
	{#if assignmentType === 'round_robin'}
		<div class="space-y-2">
			<Label>Round Robin Group</Label>
			<Input
				value={roundRobinGroup}
				oninput={(e) => {
					roundRobinGroup = e.currentTarget.value;
					emitChange();
				}}
				placeholder="e.g., sales_team"
			/>
			<p class="text-xs text-muted-foreground">
				Records will be distributed evenly among users in this group
			</p>
		</div>

		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				Round robin assignment requires a user group to be configured in admin settings.
				Users will be assigned in rotation to distribute workload evenly.
			</p>
		</div>
	{/if}

	<!-- Role-Based Assignment -->
	{#if assignmentType === 'role_based'}
		<div class="space-y-2">
			<Label>Assign to Role</Label>
			<Select.Root
				type="single"
				value={roleId ? String(roleId) : ''}
				onValueChange={(v) => {
					roleId = v ? parseInt(v) : null;
					emitChange();
				}}
			>
				<Select.Trigger>
					{roleId ? `Role #${roleId}` : 'Select role'}
				</Select.Trigger>
				<Select.Content>
					<!-- In real implementation, would load roles from API -->
					<Select.Item value="1">Sales Manager</Select.Item>
					<Select.Item value="2">Support Agent</Select.Item>
					<Select.Item value="3">Account Manager</Select.Item>
				</Select.Content>
			</Select.Root>
			<p class="text-xs text-muted-foreground">
				Assigns to the first available user with this role
			</p>
		</div>
	{/if}

	<!-- From Field -->
	{#if assignmentType === 'from_field'}
		<div class="space-y-2">
			<Label>User Field</Label>
			<Select.Root
				type="single"
				value={fromField}
				onValueChange={(v) => {
					if (v) {
						fromField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{userFields.find((f) => f.api_name === fromField)?.label || 'Select field'}
				</Select.Trigger>
				<Select.Content>
					{#each userFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			{#if userFields.length === 0}
				<p class="text-xs text-muted-foreground">
					No user or lookup fields found in this module
				</p>
			{/if}
		</div>
	{/if}

	<!-- Info for non-configurable types -->
	{#if assignmentType === 'record_owner'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be assigned to its current owner. Useful for re-assigning tasks or
				notifications to the person who owns the record.
			</p>
		</div>
	{/if}

	{#if assignmentType === 'current_user'}
		<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
			<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
			<p class="text-xs text-muted-foreground">
				The record will be assigned to the user who triggered the workflow.
				For scheduled workflows, this will be the system user.
			</p>
		</div>
	{/if}
</div>
