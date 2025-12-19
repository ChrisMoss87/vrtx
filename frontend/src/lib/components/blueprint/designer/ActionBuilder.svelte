<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import type { BlueprintTransitionAction } from '$lib/api/blueprints';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import TrashIcon from '@lucide/svelte/icons/trash-2';
	import ZapIcon from '@lucide/svelte/icons/zap';
	import GripVerticalIcon from '@lucide/svelte/icons/grip-vertical';
	import MailIcon from '@lucide/svelte/icons/mail';
	import EditIcon from '@lucide/svelte/icons/edit';
	import PlusCircleIcon from '@lucide/svelte/icons/plus-circle';
	import CheckSquareIcon from '@lucide/svelte/icons/check-square';
	import GlobeIcon from '@lucide/svelte/icons/globe';
	import BellIcon from '@lucide/svelte/icons/bell';
	import TagIcon from '@lucide/svelte/icons/tag';
	import CalendarIcon from '@lucide/svelte/icons/calendar';
	import ShuffleIcon from '@lucide/svelte/icons/shuffle';
	import LinkIcon from '@lucide/svelte/icons/link';
	import MessageSquareIcon from '@lucide/svelte/icons/message-square';
	import UserIcon from '@lucide/svelte/icons/user';
	import UsersIcon from '@lucide/svelte/icons/users';

	interface Field {
		id: number;
		api_name: string;
		label: string;
		type: string;
	}

	interface Module {
		id: number;
		name: string;
		api_name: string;
	}

	interface Props {
		actions: BlueprintTransitionAction[];
		fields: Field[];
		modules?: Module[];
		readonly?: boolean;
		onAdd?: (action: Partial<BlueprintTransitionAction>) => void;
		onUpdate?: (id: number, action: Partial<BlueprintTransitionAction>) => void;
		onDelete?: (id: number) => void;
	}

	let {
		actions = [],
		fields = [],
		modules = [],
		readonly = false,
		onAdd,
		onUpdate,
		onDelete
	}: Props = $props();

	let showAddForm = $state(false);
	let newAction = $state<{
		type: string;
		config: Record<string, unknown>;
		is_active: boolean;
	}>({
		type: 'send_email',
		config: {},
		is_active: true
	});

	const actionTypes = [
		{
			value: 'send_email',
			label: 'Send Email',
			description: 'Send an email to specified recipients',
			icon: MailIcon,
			category: 'communication',
			color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
		},
		{
			value: 'update_field',
			label: 'Update Field',
			description: 'Update a field value on the record',
			icon: EditIcon,
			category: 'record',
			color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
		},
		{
			value: 'create_record',
			label: 'Create Record',
			description: 'Create a new record in a module',
			icon: PlusCircleIcon,
			category: 'record',
			color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
		},
		{
			value: 'create_task',
			label: 'Create Task',
			description: 'Create a follow-up task',
			icon: CheckSquareIcon,
			category: 'record',
			color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
		},
		{
			value: 'webhook',
			label: 'Call Webhook',
			description: 'Send data to an external URL',
			icon: GlobeIcon,
			category: 'integration',
			color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
		},
		{
			value: 'notify_user',
			label: 'Notify User',
			description: 'Send an in-app notification',
			icon: BellIcon,
			category: 'communication',
			color: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
		},
		{
			value: 'add_tag',
			label: 'Add Tag',
			description: 'Add tags to the record',
			icon: TagIcon,
			category: 'record',
			color: 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'
		},
		{
			value: 'schedule_followup',
			label: 'Schedule Follow-up',
			description: 'Schedule a follow-up date/time',
			icon: CalendarIcon,
			category: 'scheduling',
			color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
		},
		{
			value: 'slack_message',
			label: 'Send Slack Message',
			description: 'Post a message to Slack',
			icon: MessageSquareIcon,
			category: 'integration',
			color: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400'
		},
		{
			value: 'assign_owner',
			label: 'Assign Owner',
			description: 'Assign record to a user or team',
			icon: UserIcon,
			category: 'record',
			color: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400'
		},
		{
			value: 'round_robin',
			label: 'Round Robin Assignment',
			description: 'Assign to users in rotation',
			icon: UsersIcon,
			category: 'record',
			color: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400'
		}
	];

	function getTypeInfo(type: string) {
		return actionTypes.find((t) => t.value === type);
	}

	function getFieldById(id: number | null | undefined): Field | undefined {
		if (!id) return undefined;
		return fields.find((f) => f.id === id);
	}

	function handleAddAction() {
		onAdd?.({
			type: newAction.type,
			config: newAction.config,
			is_active: newAction.is_active,
			display_order: actions.length
		});

		// Reset form
		resetForm();
	}

	function resetForm() {
		newAction = {
			type: 'send_email',
			config: {},
			is_active: true
		};
		showAddForm = false;
	}

	function handleDeleteAction(id: number) {
		if (confirm('Delete this action?')) {
			onDelete?.(id);
		}
	}

	function handleToggleActive(id: number, currentActive: boolean) {
		onUpdate?.(id, { is_active: !currentActive });
	}

	function getActionSummary(action: BlueprintTransitionAction): string {
		const config = action.config || {};

		switch (action.type) {
			case 'send_email':
				return `To: ${config.to || 'Not set'}`;
			case 'update_field': {
				const field = getFieldById(config.field_id as number);
				return `${field?.label || 'Field'} = ${config.value || 'value'}`;
			}
			case 'create_record':
				return `In: ${config.module_name || 'module'}`;
			case 'create_task':
				return config.subject as string || 'Task';
			case 'webhook':
				return config.url as string || 'URL not set';
			case 'notify_user':
				return config.message as string || 'Notification';
			case 'add_tag':
			case 'remove_tag':
				return (config.tags as string[])?.join(', ') || 'tags';
			case 'slack_message':
				return config.channel as string || '#channel';
			case 'assign_owner':
				if (config.assignment_type === 'specific_user') {
					return `To user: ${config.user_id || 'Not set'}`;
				} else if (config.assignment_type === 'field_value') {
					return `From field: ${config.field_api_name || 'Not set'}`;
				}
				return config.assignment_type as string || 'Not configured';
			case 'round_robin':
				return `${(config.user_ids as string[])?.length || 0} users in rotation`;
			default:
				return JSON.stringify(config).slice(0, 30) + '...';
		}
	}

	const canAdd = $derived(() => {
		switch (newAction.type) {
			case 'send_email':
				return !!(newAction.config.to && newAction.config.subject);
			case 'update_field':
				return !!newAction.config.field_id;
			case 'webhook':
				return !!newAction.config.url;
			case 'notify_user':
				return !!newAction.config.message;
			default:
				return true;
		}
	});
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center gap-2">
			<ZapIcon class="h-5 w-5 text-green-500" />
			<Card.Title class="text-base">After-Phase Actions</Card.Title>
		</div>
		<Card.Description>
			Automated actions to execute after transition completes.
		</Card.Description>
	</Card.Header>

	<Card.Content class="space-y-4">
		{#if actions.length === 0 && !showAddForm}
			<div class="rounded-lg border border-dashed p-4 text-center">
				<p class="text-sm text-muted-foreground">No actions configured</p>
				<p class="mt-1 text-xs text-muted-foreground">
					The transition will complete without any automated actions.
				</p>
				{#if !readonly}
					<Button variant="outline" size="sm" class="mt-3" onclick={() => (showAddForm = true)}>
						<PlusIcon class="mr-2 h-4 w-4" />
						Add Action
					</Button>
				{/if}
			</div>
		{:else}
			<!-- Existing actions -->
			<div class="space-y-2">
				{#each actions as action (action.id)}
					{@const typeInfo = getTypeInfo(action.type)}
					{@const ActionIcon = typeInfo?.icon || ZapIcon}
					<div
						class="flex items-center gap-2 rounded-lg border bg-card p-3 {!action.is_active
							? 'opacity-50'
							: ''}"
					>
						{#if !readonly}
							<GripVerticalIcon class="h-4 w-4 cursor-move text-muted-foreground" />
						{/if}

						<div
							class="flex h-8 w-8 shrink-0 items-center justify-center rounded {typeInfo?.color}"
						>
							<ActionIcon class="h-4 w-4" />
						</div>

						<div class="flex-1">
							<div class="flex items-center gap-2">
								<span class="font-medium">{typeInfo?.label || action.type}</span>
								{#if !action.is_active}
									<Badge variant="outline" class="h-5 text-[10px]">Disabled</Badge>
								{/if}
							</div>
							<p class="mt-0.5 text-xs text-muted-foreground">
								{getActionSummary(action)}
							</p>
						</div>

						{#if !readonly}
							<Switch
								checked={action.is_active}
								onCheckedChange={() => handleToggleActive(action.id, action.is_active)}
								class="scale-75"
							/>
							<Button
								variant="ghost"
								size="icon"
								class="h-8 w-8 shrink-0 text-destructive hover:bg-destructive/10"
								onclick={() => handleDeleteAction(action.id)}
							>
								<TrashIcon class="h-4 w-4" />
							</Button>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Add new action form -->
			{#if showAddForm && !readonly}
				<div class="rounded-lg border bg-muted/50 p-4">
					<div class="mb-3 text-sm font-medium">New Action</div>

					<div class="grid gap-4">
						<!-- Action Type -->
						<div class="space-y-1.5">
							<Label class="text-xs">Action Type</Label>
							<Select.Root
								type="single"
								value={newAction.type}
								onValueChange={(v) => {
									newAction.type = v;
									newAction.config = {};
								}}
							>
								<Select.Trigger>
									{getTypeInfo(newAction.type)?.label || 'Select action...'}
								</Select.Trigger>
								<Select.Content class="max-h-[300px]">
									{#each actionTypes as type}
										{@const TypeIcon = type.icon}
										<Select.Item value={type.value}>
											<div class="flex items-center gap-2">
												<div
													class="flex h-6 w-6 items-center justify-center rounded {type.color}"
												>
													<TypeIcon class="h-3 w-3" />
												</div>
												<div>
													<div>{type.label}</div>
													<div class="text-xs text-muted-foreground">{type.description}</div>
												</div>
											</div>
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>

						<!-- Type-specific configuration -->
						{#if newAction.type === 'send_email'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">To (email or field)</Label>
									<Input
										placeholder={'user@example.com or {{record.email}}'}
										value={(newAction.config.to as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, to: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Subject</Label>
									<Input
										placeholder="Email subject line"
										value={(newAction.config.subject as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, subject: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Body</Label>
									<Textarea
										placeholder={'Email body (supports {{field}} placeholders)'}
										value={(newAction.config.body as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, body: e.currentTarget.value })}
										rows={3}
									/>
								</div>
							</div>
						{:else if newAction.type === 'update_field'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">Field</Label>
									<Select.Root
										type="single"
										value={(newAction.config.field_id as number)?.toString() || ''}
										onValueChange={(v) =>
											(newAction.config = { ...newAction.config, field_id: parseInt(v) })}
									>
										<Select.Trigger>
											{getFieldById(newAction.config.field_id as number)?.label ||
												'Select field...'}
										</Select.Trigger>
										<Select.Content class="max-h-[300px]">
											{#each fields as field}
												<Select.Item value={field.id.toString()}>
													<div class="flex items-center gap-2">
														<span>{field.label}</span>
														<span class="text-xs text-muted-foreground">({field.type})</span>
													</div>
												</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Value</Label>
									<Input
										placeholder={'New value (or {{field}} placeholder)'}
										value={(newAction.config.value as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, value: e.currentTarget.value })}
									/>
								</div>
							</div>
						{:else if newAction.type === 'create_task'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">Task Subject</Label>
									<Input
										placeholder="Follow up with customer"
										value={(newAction.config.subject as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, subject: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Due in (days)</Label>
									<Input
										type="number"
										placeholder="3"
										value={(newAction.config.due_days as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, due_days: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Assign to</Label>
									<Input
										placeholder={'User ID or {{record.owner_id}}'}
										value={(newAction.config.assign_to as string) || ''}
										oninput={(e) =>
											(newAction.config = {
												...newAction.config,
												assign_to: e.currentTarget.value
											})}
									/>
								</div>
							</div>
						{:else if newAction.type === 'webhook'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">URL</Label>
									<Input
										placeholder="https://api.example.com/webhook"
										value={(newAction.config.url as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, url: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Method</Label>
									<Select.Root
										type="single"
										value={(newAction.config.method as string) || 'POST'}
										onValueChange={(v) => (newAction.config = { ...newAction.config, method: v })}
									>
										<Select.Trigger>{newAction.config.method || 'POST'}</Select.Trigger>
										<Select.Content>
											<Select.Item value="POST">POST</Select.Item>
											<Select.Item value="PUT">PUT</Select.Item>
											<Select.Item value="PATCH">PATCH</Select.Item>
										</Select.Content>
									</Select.Root>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Headers (JSON)</Label>
									<Textarea
										placeholder={`{"Authorization": "Bearer token"}`}
										value={(newAction.config.headers as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, headers: e.currentTarget.value })}
										rows={2}
									/>
								</div>
							</div>
						{:else if newAction.type === 'notify_user'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">User ID (or field)</Label>
									<Input
										placeholder={'User ID or {{record.owner_id}}'}
										value={(newAction.config.user_id as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, user_id: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Message</Label>
									<Textarea
										placeholder={'Notification message (supports {{field}} placeholders)'}
										value={(newAction.config.message as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, message: e.currentTarget.value })}
										rows={2}
									/>
								</div>
							</div>
						{:else if newAction.type === 'add_tag' || newAction.type === 'remove_tag'}
							<div class="space-y-1.5">
								<Label class="text-xs">Tags (comma separated)</Label>
								<Input
									placeholder="tag1, tag2, tag3"
									value={(newAction.config.tags as string) || ''}
									oninput={(e) =>
										(newAction.config = { ...newAction.config, tags: e.currentTarget.value })}
								/>
							</div>
						{:else if newAction.type === 'slack_message'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">Channel</Label>
									<Input
										placeholder="#channel or @user"
										value={(newAction.config.channel as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, channel: e.currentTarget.value })}
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Message</Label>
									<Textarea
										placeholder={'Slack message (supports {{field}} placeholders)'}
										value={(newAction.config.message as string) || ''}
										oninput={(e) =>
											(newAction.config = { ...newAction.config, message: e.currentTarget.value })}
										rows={2}
									/>
								</div>
							</div>
						{:else if newAction.type === 'assign_owner'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">Assignment Type</Label>
									<Select.Root
										type="single"
										value={(newAction.config.assignment_type as string) || 'specific_user'}
										onValueChange={(v) => (newAction.config = { ...newAction.config, assignment_type: v })}
									>
										<Select.Trigger>{(newAction.config.assignment_type as string) === 'field_value' ? 'From Field Value' : (newAction.config.assignment_type as string) === 'record_creator' ? 'Record Creator' : 'Specific User'}</Select.Trigger>
										<Select.Content>
											<Select.Item value="specific_user">Specific User</Select.Item>
											<Select.Item value="field_value">From Field Value</Select.Item>
											<Select.Item value="record_creator">Record Creator</Select.Item>
										</Select.Content>
									</Select.Root>
								</div>
								{#if newAction.config.assignment_type === 'specific_user' || !newAction.config.assignment_type}
									<div class="space-y-1.5">
										<Label class="text-xs">User ID</Label>
										<Input
											placeholder={'User ID or {{record.created_by}}'}
											value={(newAction.config.user_id as string) || ''}
											oninput={(e) =>
												(newAction.config = { ...newAction.config, user_id: e.currentTarget.value })}
										/>
									</div>
								{:else if newAction.config.assignment_type === 'field_value'}
									<div class="space-y-1.5">
										<Label class="text-xs">Field</Label>
										<Select.Root
											type="single"
											value={(newAction.config.field_api_name as string) || ''}
											onValueChange={(v) => (newAction.config = { ...newAction.config, field_api_name: v })}
										>
											<Select.Trigger>
												{fields.find(f => f.api_name === newAction.config.field_api_name)?.label || 'Select field...'}
											</Select.Trigger>
											<Select.Content class="max-h-[300px]">
												{#each fields.filter(f => f.type === 'lookup' || f.type === 'user') as field}
													<Select.Item value={field.api_name}>{field.label}</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
										<p class="text-xs text-muted-foreground">Select a user or lookup field</p>
									</div>
								{/if}
							</div>
						{:else if newAction.type === 'round_robin'}
							<div class="space-y-3">
								<div class="space-y-1.5">
									<Label class="text-xs">User IDs (comma-separated)</Label>
									<Textarea
										placeholder="1, 2, 3 (or user IDs from your team)"
										value={(newAction.config.user_ids_text as string) || ''}
										oninput={(e) =>
											(newAction.config = {
												...newAction.config,
												user_ids_text: e.currentTarget.value,
												user_ids: e.currentTarget.value.split(',').map(s => s.trim()).filter(Boolean)
											})}
										rows={2}
									/>
									<p class="text-xs text-muted-foreground">Records will be assigned to these users in rotation</p>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs">Assignment Field</Label>
									<Select.Root
										type="single"
										value={(newAction.config.target_field as string) || 'owner_id'}
										onValueChange={(v) => (newAction.config = { ...newAction.config, target_field: v })}
									>
										<Select.Trigger>{(newAction.config.target_field as string) || 'owner_id'}</Select.Trigger>
										<Select.Content>
											<Select.Item value="owner_id">Owner</Select.Item>
											<Select.Item value="assigned_to">Assigned To</Select.Item>
										</Select.Content>
									</Select.Root>
								</div>
							</div>
						{:else}
							<div class="rounded-lg bg-muted p-3 text-center text-sm text-muted-foreground">
								Configuration for {getTypeInfo(newAction.type)?.label || newAction.type} coming
								soon
							</div>
						{/if}

						<!-- Active toggle -->
						<div class="flex items-center justify-between">
							<div>
								<Label>Active</Label>
								<p class="text-xs text-muted-foreground">Enable this action</p>
							</div>
							<Switch
								checked={newAction.is_active}
								onCheckedChange={(checked) => (newAction.is_active = checked)}
							/>
						</div>
					</div>

					<div class="mt-4 flex justify-end gap-2">
						<Button variant="ghost" size="sm" onclick={resetForm}>
							Cancel
						</Button>
						<Button size="sm" onclick={handleAddAction} disabled={!canAdd()}>
							Add Action
						</Button>
					</div>
				</div>
			{:else if !readonly}
				<Button variant="outline" size="sm" onclick={() => (showAddForm = true)}>
					<PlusIcon class="mr-2 h-4 w-4" />
					Add Action
				</Button>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>
