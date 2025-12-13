<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import type { BlueprintApproval } from '$lib/api/blueprints';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import TrashIcon from '@lucide/svelte/icons/trash-2';
	import UsersIcon from '@lucide/svelte/icons/users';
	import ShieldCheckIcon from '@lucide/svelte/icons/shield-check';

	interface Props {
		approval?: BlueprintApproval | null;
		readonly?: boolean;
		onSave?: (approval: Partial<BlueprintApproval>) => void;
		onDelete?: () => void;
	}

	let { approval = null, readonly = false, onSave, onDelete }: Props = $props();

	let hasApproval = $state(!!approval);
	let approvalType = $state<string>(approval?.approval_type || 'specific_users');
	let requireAll = $state(approval?.require_all ?? false);
	let autoRejectDays = $state<number | null>(approval?.auto_reject_days ?? null);
	let notifyOnPending = $state(approval?.notify_on_pending ?? true);
	let notifyOnComplete = $state(approval?.notify_on_complete ?? true);

	// Config values
	let userIds = $state<number[]>((approval?.config?.user_ids as number[]) || []);
	let roleIds = $state<number[]>((approval?.config?.role_ids as number[]) || []);
	let fieldId = $state<number | null>((approval?.config?.field_id as number) || null);

	// For UI - would normally fetch from API
	let newUserId = $state('');
	let newRoleId = $state('');

	const approvalTypes = [
		{
			value: 'specific_users',
			label: 'Specific Users',
			description: 'Require approval from specific users'
		},
		{
			value: 'role_based',
			label: 'Role Based',
			description: 'Require approval from users with specific roles'
		},
		{ value: 'manager', label: 'Manager', description: "Require approval from record owner's manager" },
		{
			value: 'field_value',
			label: 'Field Value',
			description: 'Require approval from user specified in a field'
		}
	];

	function handleToggleApproval(enabled: boolean) {
		hasApproval = enabled;
		if (!enabled) {
			onDelete?.();
		}
	}

	function handleSave() {
		const config: Record<string, unknown> = {};

		if (approvalType === 'specific_users') {
			config.user_ids = userIds;
		} else if (approvalType === 'role_based') {
			config.role_ids = roleIds;
		} else if (approvalType === 'field_value') {
			config.field_id = fieldId;
		}

		onSave?.({
			approval_type: approvalType as BlueprintApproval['approval_type'],
			config,
			require_all: requireAll,
			auto_reject_days: autoRejectDays,
			notify_on_pending: notifyOnPending,
			notify_on_complete: notifyOnComplete
		});
	}

	function addUserId() {
		const id = parseInt(newUserId);
		if (id && !userIds.includes(id)) {
			userIds = [...userIds, id];
			newUserId = '';
		}
	}

	function removeUserId(id: number) {
		userIds = userIds.filter((u) => u !== id);
	}

	function addRoleId() {
		const id = parseInt(newRoleId);
		if (id && !roleIds.includes(id)) {
			roleIds = [...roleIds, id];
			newRoleId = '';
		}
	}

	function removeRoleId(id: number) {
		roleIds = roleIds.filter((r) => r !== id);
	}
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-2">
				<ShieldCheckIcon class="h-5 w-5 text-purple-500" />
				<Card.Title class="text-base">Approval Required</Card.Title>
			</div>
			{#if !readonly}
				<Switch checked={hasApproval} onCheckedChange={handleToggleApproval} />
			{/if}
		</div>
		<Card.Description>
			Require approval before the transition can be completed.
		</Card.Description>
	</Card.Header>

	{#if hasApproval}
		<Card.Content class="space-y-4">
			<!-- Approval Type -->
			<div class="space-y-2">
				<Label>Approval Type</Label>
				<Select.Root
					type="single"
					value={approvalType}
					onValueChange={(v) => (approvalType = v)}
					disabled={readonly}
				>
					<Select.Trigger class="w-full">
						{approvalTypes.find((t) => t.value === approvalType)?.label || 'Select type'}
					</Select.Trigger>
					<Select.Content>
						{#each approvalTypes as type}
							<Select.Item value={type.value}>
								<div>
									<div>{type.label}</div>
									<div class="text-xs text-muted-foreground">{type.description}</div>
								</div>
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<!-- Type-specific configuration -->
			{#if approvalType === 'specific_users'}
				<div class="space-y-2">
					<Label>User IDs</Label>
					<div class="flex flex-wrap gap-2">
						{#each userIds as userId}
							<Badge variant="secondary" class="gap-1">
								User #{userId}
								{#if !readonly}
									<button type="button" class="ml-1 hover:text-destructive" onclick={() => removeUserId(userId)}>
										&times;
									</button>
								{/if}
							</Badge>
						{/each}
					</div>
					{#if !readonly}
						<div class="flex gap-2">
							<Input
								type="number"
								placeholder="User ID"
								bind:value={newUserId}
								class="w-32"
								onkeydown={(e) => e.key === 'Enter' && addUserId()}
							/>
							<Button variant="outline" size="sm" onclick={addUserId}>
								<PlusIcon class="h-4 w-4" />
							</Button>
						</div>
					{/if}
				</div>
			{:else if approvalType === 'role_based'}
				<div class="space-y-2">
					<Label>Role IDs</Label>
					<div class="flex flex-wrap gap-2">
						{#each roleIds as roleId}
							<Badge variant="secondary" class="gap-1">
								Role #{roleId}
								{#if !readonly}
									<button type="button" class="ml-1 hover:text-destructive" onclick={() => removeRoleId(roleId)}>
										&times;
									</button>
								{/if}
							</Badge>
						{/each}
					</div>
					{#if !readonly}
						<div class="flex gap-2">
							<Input
								type="number"
								placeholder="Role ID"
								bind:value={newRoleId}
								class="w-32"
								onkeydown={(e) => e.key === 'Enter' && addRoleId()}
							/>
							<Button variant="outline" size="sm" onclick={addRoleId}>
								<PlusIcon class="h-4 w-4" />
							</Button>
						</div>
					{/if}
				</div>
			{:else if approvalType === 'field_value'}
				<div class="space-y-2">
					<Label>Approver Field ID</Label>
					<Input
						type="number"
						placeholder="Field ID (user lookup field)"
						value={fieldId || ''}
						oninput={(e) => (fieldId = parseInt(e.currentTarget.value) || null)}
						disabled={readonly}
					/>
					<p class="text-xs text-muted-foreground">
						Select a user lookup field that contains the approver
					</p>
				</div>
			{:else if approvalType === 'manager'}
				<p class="text-sm text-muted-foreground">
					Approval will be required from the record owner's manager.
				</p>
			{/if}

			<!-- Common settings -->
			<div class="space-y-3 border-t pt-4">
				<div class="flex items-center justify-between">
					<div>
						<Label>Require All Approvers</Label>
						<p class="text-xs text-muted-foreground">All approvers must approve</p>
					</div>
					<Switch
						checked={requireAll}
						onCheckedChange={(checked) => (requireAll = checked)}
						disabled={readonly}
					/>
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Notify on Pending</Label>
						<p class="text-xs text-muted-foreground">Notify approvers of new requests</p>
					</div>
					<Switch
						checked={notifyOnPending}
						onCheckedChange={(checked) => (notifyOnPending = checked)}
						disabled={readonly}
					/>
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label>Notify on Complete</Label>
						<p class="text-xs text-muted-foreground">Notify requester of outcome</p>
					</div>
					<Switch
						checked={notifyOnComplete}
						onCheckedChange={(checked) => (notifyOnComplete = checked)}
						disabled={readonly}
					/>
				</div>

				<div class="space-y-2">
					<Label>Auto-reject after (days)</Label>
					<Input
						type="number"
						placeholder="Leave empty to never auto-reject"
						value={autoRejectDays || ''}
						oninput={(e) => (autoRejectDays = parseInt(e.currentTarget.value) || null)}
						disabled={readonly}
						class="w-32"
					/>
				</div>
			</div>

			{#if !readonly}
				<div class="flex justify-end gap-2 border-t pt-4">
					<Button variant="outline" onclick={() => handleToggleApproval(false)}>
						<TrashIcon class="mr-2 h-4 w-4" />
						Remove Approval
					</Button>
					<Button onclick={handleSave}>Save Approval</Button>
				</div>
			{/if}
		</Card.Content>
	{/if}
</Card.Root>
