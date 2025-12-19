<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import type { BlueprintSla, BlueprintSlaEscalation, BlueprintState } from '$lib/api/blueprints';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import TrashIcon from '@lucide/svelte/icons/trash-2';
	import ClockIcon from '@lucide/svelte/icons/clock';
	import AlertTriangleIcon from '@lucide/svelte/icons/alert-triangle';
	import BellIcon from '@lucide/svelte/icons/bell';
	import MailIcon from '@lucide/svelte/icons/mail';
	import EditIcon from '@lucide/svelte/icons/edit';
	import CheckSquareIcon from '@lucide/svelte/icons/check-square';

	interface Props {
		slas: BlueprintSla[];
		states: BlueprintState[];
		readonly?: boolean;
		onAddSla?: (sla: Partial<BlueprintSla>) => void;
		onUpdateSla?: (id: number, sla: Partial<BlueprintSla>) => void;
		onDeleteSla?: (id: number) => void;
		onAddEscalation?: (slaId: number, escalation: Partial<BlueprintSlaEscalation>) => void;
		onDeleteEscalation?: (slaId: number, escalationId: number) => void;
	}

	let {
		slas = [],
		states = [],
		readonly = false,
		onAddSla,
		onUpdateSla,
		onDeleteSla,
		onAddEscalation,
		onDeleteEscalation
	}: Props = $props();

	let showAddForm = $state(false);
	let expandedSlaId = $state<number | null>(null);
	let showAddEscalationFor = $state<number | null>(null);

	let newSla = $state<{
		state_id?: number;
		name: string;
		duration_hours: number;
		business_hours_only: boolean;
		exclude_weekends: boolean;
		is_active: boolean;
	}>({
		state_id: undefined,
		name: '',
		duration_hours: 24,
		business_hours_only: false,
		exclude_weekends: false,
		is_active: true
	});

	let newEscalation = $state<{
		trigger_type: 'approaching' | 'breached';
		trigger_value: number;
		action_type: string;
		config: Record<string, unknown>;
	}>({
		trigger_type: 'approaching',
		trigger_value: 80,
		action_type: 'notify_user',
		config: {}
	});

	const escalationActionTypes = [
		{
			value: 'notify_user',
			label: 'Notify User',
			description: 'Send an in-app notification',
			icon: BellIcon
		},
		{
			value: 'send_email',
			label: 'Send Email',
			description: 'Send an email alert',
			icon: MailIcon
		},
		{
			value: 'update_field',
			label: 'Update Field',
			description: 'Update a field on the record',
			icon: EditIcon
		},
		{
			value: 'create_task',
			label: 'Create Task',
			description: 'Create an escalation task',
			icon: CheckSquareIcon
		}
	];

	function getStateById(id: number | undefined): BlueprintState | undefined {
		if (!id) return undefined;
		return states.find((s) => s.id === id);
	}

	function formatDuration(hours: number): string {
		if (hours >= 24) {
			const days = Math.floor(hours / 24);
			const remainingHours = hours % 24;
			if (remainingHours === 0) {
				return `${days} day${days > 1 ? 's' : ''}`;
			}
			return `${days}d ${remainingHours}h`;
		}
		return `${hours} hour${hours > 1 ? 's' : ''}`;
	}

	function handleAddSla() {
		if (!newSla.state_id || !newSla.name) return;

		onAddSla?.({
			state_id: newSla.state_id,
			name: newSla.name,
			duration_hours: newSla.duration_hours,
			business_hours_only: newSla.business_hours_only,
			exclude_weekends: newSla.exclude_weekends,
			is_active: newSla.is_active
		});

		resetForm();
	}

	function resetForm() {
		newSla = {
			state_id: undefined,
			name: '',
			duration_hours: 24,
			business_hours_only: false,
			exclude_weekends: false,
			is_active: true
		};
		showAddForm = false;
	}

	function handleDeleteSla(id: number) {
		if (confirm('Delete this SLA and all its escalations?')) {
			onDeleteSla?.(id);
		}
	}

	function handleAddEscalation(slaId: number) {
		onAddEscalation?.(slaId, {
			trigger_type: newEscalation.trigger_type,
			trigger_value: newEscalation.trigger_value,
			action_type: newEscalation.action_type,
			config: newEscalation.config,
			display_order: 0
		});

		newEscalation = {
			trigger_type: 'approaching',
			trigger_value: 80,
			action_type: 'notify_user',
			config: {}
		};
		showAddEscalationFor = null;
	}

	function handleDeleteEscalation(slaId: number, escalationId: number) {
		if (confirm('Delete this escalation?')) {
			onDeleteEscalation?.(slaId, escalationId);
		}
	}

	function toggleExpanded(slaId: number) {
		expandedSlaId = expandedSlaId === slaId ? null : slaId;
	}

	// States that don't have an SLA yet
	const availableStates = $derived(
		states.filter((s) => !s.is_terminal && !slas.some((sla) => sla.state_id === s.id))
	);
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center gap-2">
			<ClockIcon class="h-5 w-5 text-orange-500" />
			<Card.Title class="text-base">SLA Configuration</Card.Title>
		</div>
		<Card.Description>
			Define time limits for records in each stage and escalation actions.
		</Card.Description>
	</Card.Header>

	<Card.Content class="space-y-4">
		{#if slas.length === 0 && !showAddForm}
			<div class="rounded-lg border border-dashed p-4 text-center">
				<p class="text-sm text-muted-foreground">No SLAs configured</p>
				<p class="mt-1 text-xs text-muted-foreground">
					Records can remain in any stage indefinitely.
				</p>
				{#if !readonly && availableStates.length > 0}
					<Button variant="outline" size="sm" class="mt-3" onclick={() => (showAddForm = true)}>
						<PlusIcon class="mr-2 h-4 w-4" />
						Add SLA
					</Button>
				{/if}
			</div>
		{:else}
			<!-- Existing SLAs -->
			<div class="space-y-3">
				{#each slas as sla (sla.id)}
					{@const state = getStateById(sla.state_id)}
					{@const isExpanded = expandedSlaId === sla.id}
					<div class="rounded-lg border bg-card">
						<!-- SLA Header -->
						<button
							type="button"
							class="flex w-full items-center gap-3 p-3 text-left hover:bg-muted/50"
							onclick={() => toggleExpanded(sla.id)}
						>
							<div
								class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400"
							>
								<ClockIcon class="h-5 w-5" />
							</div>

							<div class="flex-1">
								<div class="flex items-center gap-2">
									<span class="font-medium">{sla.name}</span>
									{#if !sla.is_active}
										<Badge variant="outline" class="h-5 text-[10px]">Inactive</Badge>
									{/if}
								</div>
								<div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
									<span>Stage: {state?.name || 'Unknown'}</span>
									<span class="text-muted-foreground/50">|</span>
									<span>Duration: {formatDuration(sla.duration_hours)}</span>
									{#if sla.business_hours_only}
										<Badge variant="secondary" class="h-4 text-[9px]">Business hours</Badge>
									{/if}
									{#if sla.exclude_weekends}
										<Badge variant="secondary" class="h-4 text-[9px]">Excludes weekends</Badge>
									{/if}
								</div>
							</div>

							<div class="flex items-center gap-2">
								<Badge variant="outline" class="h-6">
									{sla.escalations?.length || 0} escalation{(sla.escalations?.length || 0) !== 1
										? 's'
										: ''}
								</Badge>
								<svg
									class="h-4 w-4 text-muted-foreground transition-transform {isExpanded
										? 'rotate-180'
										: ''}"
									fill="none"
									stroke="currentColor"
									viewBox="0 0 24 24"
								>
									<path
										stroke-linecap="round"
										stroke-linejoin="round"
										stroke-width="2"
										d="M19 9l-7 7-7-7"
									/>
								</svg>
							</div>
						</button>

						<!-- Expanded content -->
						{#if isExpanded}
							<div class="border-t px-3 pb-3">
								<!-- Escalations -->
								<div class="mt-3 space-y-2">
									<div class="text-xs font-medium text-muted-foreground">Escalation Actions</div>

									{#if !sla.escalations?.length && showAddEscalationFor !== sla.id}
										<div class="rounded-lg border border-dashed p-3 text-center text-sm text-muted-foreground">
											No escalation actions configured
										</div>
									{:else}
										{#each sla.escalations || [] as escalation}
											{@const actionInfo = escalationActionTypes.find(
												(a) => a.value === escalation.action_type
											)}
											{@const ActionIcon = actionInfo?.icon || AlertTriangleIcon}
											<div class="flex items-center gap-2 rounded-lg bg-muted/50 p-2">
												<div
													class="{escalation.trigger_type === 'breached'
														? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
														: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'} flex h-7 w-7 items-center justify-center rounded"
												>
													<AlertTriangleIcon class="h-4 w-4" />
												</div>
												<div class="flex-1 text-sm">
													{#if escalation.trigger_type === 'approaching'}
														When {escalation.trigger_value}% of time elapsed
													{:else}
														When SLA is breached
													{/if}
													<span class="text-muted-foreground"> - </span>
													<span class="font-medium">{actionInfo?.label || escalation.action_type}</span>
												</div>
												{#if !readonly}
													<Button
														variant="ghost"
														size="icon"
														class="h-7 w-7 text-destructive hover:bg-destructive/10"
														onclick={() => handleDeleteEscalation(sla.id, escalation.id)}
													>
														<TrashIcon class="h-3 w-3" />
													</Button>
												{/if}
											</div>
										{/each}
									{/if}

									<!-- Add escalation form -->
									{#if showAddEscalationFor === sla.id && !readonly}
										<div class="rounded-lg border bg-background p-3">
											<div class="grid gap-3">
												<div class="grid grid-cols-2 gap-3">
													<div class="space-y-1.5">
														<Label class="text-xs">Trigger</Label>
														<Select.Root
															type="single"
															value={newEscalation.trigger_type}
															onValueChange={(v) =>
																(newEscalation.trigger_type = v as 'approaching' | 'breached')}
														>
															<Select.Trigger class="h-8">
																{newEscalation.trigger_type === 'approaching'
																	? 'Approaching'
																	: 'Breached'}
															</Select.Trigger>
															<Select.Content>
																<Select.Item value="approaching">Approaching (% of time)</Select.Item>
																<Select.Item value="breached">Breached</Select.Item>
															</Select.Content>
														</Select.Root>
													</div>

													{#if newEscalation.trigger_type === 'approaching'}
														<div class="space-y-1.5">
															<Label class="text-xs">At percentage</Label>
															<div class="flex items-center gap-2">
																<Input
																	type="number"
																	min="1"
																	max="100"
																	class="h-8"
																	bind:value={newEscalation.trigger_value}
																/>
																<span class="text-sm text-muted-foreground">%</span>
															</div>
														</div>
													{/if}
												</div>

												<div class="space-y-1.5">
													<Label class="text-xs">Action</Label>
													<Select.Root
														type="single"
														value={newEscalation.action_type}
														onValueChange={(v) => (newEscalation.action_type = v)}
													>
														<Select.Trigger class="h-8">
															{escalationActionTypes.find((a) => a.value === newEscalation.action_type)
																?.label || 'Select action...'}
														</Select.Trigger>
														<Select.Content>
															{#each escalationActionTypes as action}
																<Select.Item value={action.value}>
																	{action.label}
																</Select.Item>
															{/each}
														</Select.Content>
													</Select.Root>
												</div>

												{#if newEscalation.action_type === 'notify_user'}
													<div class="space-y-1.5">
														<Label class="text-xs">User ID (or field)</Label>
														<Input
															class="h-8"
															placeholder={'{{record.owner_id}}'}
															value={(newEscalation.config.user_id as string) || ''}
															oninput={(e) =>
																(newEscalation.config = {
																	...newEscalation.config,
																	user_id: e.currentTarget.value
																})}
														/>
													</div>
												{:else if newEscalation.action_type === 'send_email'}
													<div class="space-y-1.5">
														<Label class="text-xs">To (email or field)</Label>
														<Input
															class="h-8"
															placeholder={'{{record.owner.email}}'}
															value={(newEscalation.config.to as string) || ''}
															oninput={(e) =>
																(newEscalation.config = {
																	...newEscalation.config,
																	to: e.currentTarget.value
																})}
														/>
													</div>
												{/if}
											</div>

											<div class="mt-3 flex justify-end gap-2">
												<Button
													variant="ghost"
													size="sm"
													onclick={() => (showAddEscalationFor = null)}
												>
													Cancel
												</Button>
												<Button size="sm" onclick={() => handleAddEscalation(sla.id)}>
													Add Escalation
												</Button>
											</div>
										</div>
									{:else if !readonly}
										<Button
											variant="outline"
											size="sm"
											class="w-full"
											onclick={() => (showAddEscalationFor = sla.id)}
										>
											<PlusIcon class="mr-2 h-4 w-4" />
											Add Escalation
										</Button>
									{/if}
								</div>

								<!-- Delete SLA -->
								{#if !readonly}
									<div class="mt-4 border-t pt-3">
										<Button
											variant="outline"
											size="sm"
											class="text-destructive hover:bg-destructive/10"
											onclick={() => handleDeleteSla(sla.id)}
										>
											<TrashIcon class="mr-2 h-4 w-4" />
											Delete SLA
										</Button>
									</div>
								{/if}
							</div>
						{/if}
					</div>
				{/each}
			</div>

			<!-- Add new SLA form -->
			{#if showAddForm && !readonly}
				<div class="rounded-lg border bg-muted/50 p-4">
					<div class="mb-3 text-sm font-medium">New SLA</div>

					<div class="grid gap-4">
						<div class="space-y-1.5">
							<Label class="text-xs">Name</Label>
							<Input placeholder="e.g., New Lead Response Time" bind:value={newSla.name} />
						</div>

						<div class="space-y-1.5">
							<Label class="text-xs">State</Label>
							<Select.Root
								type="single"
								value={newSla.state_id?.toString() || ''}
								onValueChange={(v) => (newSla.state_id = parseInt(v))}
							>
								<Select.Trigger>
									{getStateById(newSla.state_id)?.name || 'Select state...'}
								</Select.Trigger>
								<Select.Content>
									{#each availableStates as state}
										<Select.Item value={state.id.toString()}>
											{state.name}
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
							{#if availableStates.length === 0}
								<p class="text-xs text-amber-600">
									All non-terminal states already have SLAs configured.
								</p>
							{/if}
						</div>

						<div class="grid grid-cols-2 gap-3">
							<div class="space-y-1.5">
								<Label class="text-xs">Duration (hours)</Label>
								<Input type="number" min="1" bind:value={newSla.duration_hours} />
							</div>
							<div class="flex items-end pb-0.5 text-sm text-muted-foreground">
								= {formatDuration(newSla.duration_hours)}
							</div>
						</div>

						<div class="space-y-3">
							<div class="flex items-center justify-between">
								<div>
									<Label>Business hours only</Label>
									<p class="text-xs text-muted-foreground">Only count 9-5 business hours</p>
								</div>
								<Switch
									checked={newSla.business_hours_only}
									onCheckedChange={(checked) => (newSla.business_hours_only = checked)}
								/>
							</div>

							<div class="flex items-center justify-between">
								<div>
									<Label>Exclude weekends</Label>
									<p class="text-xs text-muted-foreground">Don't count Saturday/Sunday</p>
								</div>
								<Switch
									checked={newSla.exclude_weekends}
									onCheckedChange={(checked) => (newSla.exclude_weekends = checked)}
								/>
							</div>
						</div>
					</div>

					<div class="mt-4 flex justify-end gap-2">
						<Button variant="ghost" size="sm" onclick={resetForm}>
							Cancel
						</Button>
						<Button
							size="sm"
							onclick={handleAddSla}
							disabled={!newSla.state_id || !newSla.name || newSla.duration_hours < 1}
						>
							Add SLA
						</Button>
					</div>
				</div>
			{:else if !readonly && availableStates.length > 0}
				<Button variant="outline" size="sm" onclick={() => (showAddForm = true)}>
					<PlusIcon class="mr-2 h-4 w-4" />
					Add SLA
				</Button>
			{/if}
		{/if}
	</Card.Content>
</Card.Root>
