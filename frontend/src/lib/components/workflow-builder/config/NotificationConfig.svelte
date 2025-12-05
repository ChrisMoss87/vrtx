<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { X, Plus, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import VariableInserter from '../VariableInserter.svelte';

	interface Props {
		config: Record<string, unknown>;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, moduleFields = [], onConfigChange }: Props = $props();

	// Local state
	let recipientType = $state<string>((config.recipient_type as string) || 'record_owner');
	let specificUserIds = $state<number[]>((config.user_ids as number[]) || []);
	let roleId = $state<number | null>((config.role_id as number) || null);
	let title = $state<string>((config.title as string) || '');
	let message = $state<string>((config.message as string) || '');
	let notificationType = $state<string>((config.notification_type as string) || 'info');
	let linkToRecord = $state<boolean>((config.link_to_record as boolean) ?? true);

	function emitChange() {
		onConfigChange?.({
			recipient_type: recipientType,
			user_ids: specificUserIds,
			role_id: roleId,
			title,
			message,
			notification_type: notificationType,
			link_to_record: linkToRecord
		});
	}

	function insertVariable(variable: string, target: 'title' | 'message') {
		if (target === 'title') {
			title = `${title}{{${variable}}}`;
		} else {
			message = `${message}{{${variable}}}`;
		}
		emitChange();
	}
</script>

<div class="space-y-4">
	<h4 class="font-medium">Notification Configuration</h4>

	<!-- Notification Type -->
	<div class="space-y-2">
		<Label>Notification Type</Label>
		<Select.Root
			type="single"
			value={notificationType}
			onValueChange={(v) => {
				if (v) {
					notificationType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				<div class="flex items-center gap-2">
					<div
						class="h-2 w-2 rounded-full"
						class:bg-blue-500={notificationType === 'info'}
						class:bg-green-500={notificationType === 'success'}
						class:bg-yellow-500={notificationType === 'warning'}
						class:bg-red-500={notificationType === 'error'}
					></div>
					{notificationType === 'info'
						? 'Info'
						: notificationType === 'success'
							? 'Success'
							: notificationType === 'warning'
								? 'Warning'
								: 'Error'}
				</div>
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="info">
					<div class="flex items-center gap-2">
						<div class="h-2 w-2 rounded-full bg-blue-500"></div>
						Info
					</div>
				</Select.Item>
				<Select.Item value="success">
					<div class="flex items-center gap-2">
						<div class="h-2 w-2 rounded-full bg-green-500"></div>
						Success
					</div>
				</Select.Item>
				<Select.Item value="warning">
					<div class="flex items-center gap-2">
						<div class="h-2 w-2 rounded-full bg-yellow-500"></div>
						Warning
					</div>
				</Select.Item>
				<Select.Item value="error">
					<div class="flex items-center gap-2">
						<div class="h-2 w-2 rounded-full bg-red-500"></div>
						Error
					</div>
				</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Recipient Type -->
	<div class="space-y-2">
		<Label>Send To</Label>
		<Select.Root
			type="single"
			value={recipientType}
			onValueChange={(v) => {
				if (v) {
					recipientType = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{recipientType === 'record_owner'
					? 'Record Owner'
					: recipientType === 'current_user'
						? 'Current User (Trigger User)'
						: recipientType === 'specific'
							? 'Specific Users'
							: recipientType === 'role'
								? 'Users in Role'
								: recipientType === 'all_admins'
									? 'All Admins'
									: 'Select recipients'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="record_owner">Record Owner</Select.Item>
				<Select.Item value="current_user">Current User (Trigger User)</Select.Item>
				<Select.Item value="specific">Specific Users</Select.Item>
				<Select.Item value="role">Users in Role</Select.Item>
				<Select.Item value="all_admins">All Admins</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Specific Users (would need user selector component) -->
	{#if recipientType === 'specific'}
		<div class="space-y-2">
			<Label>Select Users</Label>
			<p class="text-xs text-muted-foreground">
				User selection would be implemented with a user lookup component
			</p>
		</div>
	{/if}

	<!-- Role Selection -->
	{#if recipientType === 'role'}
		<div class="space-y-2">
			<Label>Role</Label>
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
				</Select.Content>
			</Select.Root>
		</div>
	{/if}

	<!-- Title -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Title</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'title')} />
		</div>
		<Input
			value={title}
			oninput={(e) => {
				title = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Notification title"
		/>
	</div>

	<!-- Message -->
	<div class="space-y-2">
		<div class="flex items-center justify-between">
			<Label>Message</Label>
			<VariableInserter fields={moduleFields} onInsert={(v) => insertVariable(v, 'message')} />
		</div>
		<Textarea
			value={message}
			oninput={(e) => {
				message = e.currentTarget.value;
				emitChange();
			}}
			placeholder="Notification message"
			rows={3}
		/>
	</div>

	<!-- Link to Record -->
	<div class="flex items-center gap-2">
		<input
			type="checkbox"
			id="link-to-record"
			checked={linkToRecord}
			onchange={(e) => {
				linkToRecord = e.currentTarget.checked;
				emitChange();
			}}
			class="h-4 w-4 rounded border-gray-300"
		/>
		<Label for="link-to-record" class="cursor-pointer text-sm font-normal">
			Include link to the record in notification
		</Label>
	</div>

	<!-- Info -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			In-app notifications appear in the user's notification center.
			Use <code class="rounded bg-muted px-1">{'{{field_name}}'}</code> to include record data.
		</p>
	</div>
</div>
