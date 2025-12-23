<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { Bell, Plus, Trash2, Loader2 } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		dashboardAlertsApi,
		type DashboardWidgetAlert,
		type CreateAlertRequest,
		type ConditionType,
		type Severity,
		getConditionTypeLabel,
		getSeverityLabel,
		getSeverityColor
	} from '$lib/api/dashboard-alerts';

	interface Props {
		dashboardId: number;
		widgetId: number;
		widgetTitle: string;
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { dashboardId, widgetId, widgetTitle, open = $bindable(false), onOpenChange }: Props = $props();

	let alerts = $state<DashboardWidgetAlert[]>([]);
	let loading = $state(false);
	let saving = $state(false);
	let showCreateForm = $state(false);

	// Form state
	let formData = $state<CreateAlertRequest>({
		name: '',
		condition_type: 'above',
		threshold_value: 0,
		severity: 'warning',
		notification_channels: ['in_app'],
		cooldown_minutes: 60,
		is_active: true
	});

	const conditionTypes: { value: ConditionType; label: string }[] = [
		{ value: 'above', label: 'Above threshold' },
		{ value: 'below', label: 'Below threshold' },
		{ value: 'percent_change', label: 'Percent change' },
		{ value: 'equals', label: 'Equals value' }
	];

	const severities: { value: Severity; label: string }[] = [
		{ value: 'info', label: 'Info' },
		{ value: 'warning', label: 'Warning' },
		{ value: 'critical', label: 'Critical' }
	];

	const comparisonPeriods = [
		{ value: '', label: 'None' },
		{ value: 'previous_day', label: 'Previous Day' },
		{ value: 'previous_week', label: 'Previous Week' },
		{ value: 'previous_month', label: 'Previous Month' }
	];

	$effect(() => {
		if (open) {
			loadAlerts();
		}
	});

	async function loadAlerts() {
		loading = true;
		try {
			alerts = await dashboardAlertsApi.listForWidget(dashboardId, widgetId);
		} catch (error) {
			console.error('Failed to load alerts:', error);
			toast.error('Failed to load alerts');
		} finally {
			loading = false;
		}
	}

	function resetForm() {
		formData = {
			name: '',
			condition_type: 'above',
			threshold_value: 0,
			severity: 'warning',
			notification_channels: ['in_app'],
			cooldown_minutes: 60,
			is_active: true
		};
		showCreateForm = false;
	}

	async function handleCreate() {
		if (!formData.name.trim()) {
			toast.error('Please enter an alert name');
			return;
		}

		saving = true;
		try {
			const newAlert = await dashboardAlertsApi.create(dashboardId, widgetId, formData);
			alerts = [...alerts, newAlert];
			toast.success('Alert created');
			resetForm();
		} catch (error) {
			console.error('Failed to create alert:', error);
			toast.error('Failed to create alert');
		} finally {
			saving = false;
		}
	}

	async function handleToggle(alert: DashboardWidgetAlert) {
		try {
			const updated = await dashboardAlertsApi.toggle(dashboardId, alert.id);
			alerts = alerts.map((a) => (a.id === alert.id ? updated : a));
			toast.success(updated.is_active ? 'Alert enabled' : 'Alert disabled');
		} catch (error) {
			console.error('Failed to toggle alert:', error);
			toast.error('Failed to toggle alert');
		}
	}

	async function handleDelete(alert: DashboardWidgetAlert) {
		try {
			await dashboardAlertsApi.delete(dashboardId, alert.id);
			alerts = alerts.filter((a) => a.id !== alert.id);
			toast.success('Alert deleted');
		} catch (error) {
			console.error('Failed to delete alert:', error);
			toast.error('Failed to delete alert');
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		if (!value) {
			resetForm();
		}
		onOpenChange?.(value);
	}

	function toggleNotificationChannel(channel: 'in_app' | 'email') {
		if (formData.notification_channels?.includes(channel)) {
			formData.notification_channels = formData.notification_channels.filter((c) => c !== channel);
		} else {
			formData.notification_channels = [...(formData.notification_channels || []), channel];
		}
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-lg max-h-[80vh] flex flex-col">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<Bell class="h-5 w-5" />
				Alerts for {widgetTitle}
			</Dialog.Title>
			<Dialog.Description>
				Configure alerts to be notified when widget values meet certain conditions
			</Dialog.Description>
		</Dialog.Header>

		<div class="flex-1 overflow-auto space-y-4 py-4">
			{#if loading}
				<div class="flex items-center justify-center py-8">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else}
				<!-- Existing Alerts -->
				{#if alerts.length > 0}
					<div class="space-y-2">
						<Label class="text-sm font-medium">Active Alerts</Label>
						<div class="space-y-2">
							{#each alerts as alert}
								<div class="flex items-center justify-between rounded-lg border p-3">
									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2">
											<span class="font-medium truncate">{alert.name}</span>
											<Badge variant="outline" class="text-xs {getSeverityColor(alert.severity)}">
												{getSeverityLabel(alert.severity)}
											</Badge>
										</div>
										<p class="text-xs text-muted-foreground mt-1">
											{getConditionTypeLabel(alert.condition_type)}: {alert.threshold_value}
											{#if alert.trigger_count > 0}
												<span class="ml-2">Triggered {alert.trigger_count}x</span>
											{/if}
										</p>
									</div>
									<div class="flex items-center gap-2">
										<Switch
											checked={alert.is_active}
											onCheckedChange={() => handleToggle(alert)}
										/>
										<Button
											variant="ghost"
											size="icon"
											class="h-8 w-8"
											onclick={() => handleDelete(alert)}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Create New Alert -->
				{#if showCreateForm}
					<div class="space-y-4 border rounded-lg p-4">
						<div class="space-y-2">
							<Label for="alert-name">Alert Name</Label>
							<Input
								id="alert-name"
								bind:value={formData.name}
								placeholder="e.g., Revenue Drop Alert"
							/>
						</div>

						<div class="grid grid-cols-2 gap-4">
							<div class="space-y-2">
								<Label>Condition</Label>
								<Select.Root
									type="single"
									value={formData.condition_type}
									onValueChange={(v) => { if (v) formData.condition_type = v as ConditionType; }}
								>
									<Select.Trigger>
										<span>{conditionTypes.find((c) => c.value === formData.condition_type)?.label}</span>
									</Select.Trigger>
									<Select.Content>
										{#each conditionTypes as type}
											<Select.Item value={type.value}>{type.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<div class="space-y-2">
								<Label for="threshold">Threshold</Label>
								<Input
									id="threshold"
									type="number"
									bind:value={formData.threshold_value}
								/>
							</div>
						</div>

						{#if formData.condition_type === 'percent_change'}
							<div class="space-y-2">
								<Label>Compare to</Label>
								<Select.Root
									type="single"
									value={formData.comparison_period || ''}
									onValueChange={(v) => { formData.comparison_period = v || undefined; }}
								>
									<Select.Trigger>
										<span>{comparisonPeriods.find((p) => p.value === (formData.comparison_period || ''))?.label}</span>
									</Select.Trigger>
									<Select.Content>
										{#each comparisonPeriods as period}
											<Select.Item value={period.value}>{period.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>
						{/if}

						<div class="grid grid-cols-2 gap-4">
							<div class="space-y-2">
								<Label>Severity</Label>
								<Select.Root
									type="single"
									value={formData.severity}
									onValueChange={(v) => { if (v) formData.severity = v as Severity; }}
								>
									<Select.Trigger>
										<span>{severities.find((s) => s.value === formData.severity)?.label}</span>
									</Select.Trigger>
									<Select.Content>
										{#each severities as severity}
											<Select.Item value={severity.value}>{severity.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							</div>

							<div class="space-y-2">
								<Label for="cooldown">Cooldown (min)</Label>
								<Input
									id="cooldown"
									type="number"
									bind:value={formData.cooldown_minutes}
									min="1"
								/>
							</div>
						</div>

						<div class="space-y-2">
							<Label>Notification Channels</Label>
							<div class="flex gap-4">
								<label class="flex items-center gap-2 cursor-pointer">
									<input
										type="checkbox"
										checked={formData.notification_channels?.includes('in_app')}
										onchange={() => toggleNotificationChannel('in_app')}
										class="rounded"
									/>
									<span class="text-sm">In-App</span>
								</label>
								<label class="flex items-center gap-2 cursor-pointer">
									<input
										type="checkbox"
										checked={formData.notification_channels?.includes('email')}
										onchange={() => toggleNotificationChannel('email')}
										class="rounded"
									/>
									<span class="text-sm">Email</span>
								</label>
							</div>
						</div>

						<div class="flex gap-2 pt-2">
							<Button onclick={handleCreate} disabled={saving} class="flex-1">
								{#if saving}
									<Loader2 class="mr-2 h-4 w-4 animate-spin" />
								{/if}
								Create Alert
							</Button>
							<Button variant="outline" onclick={resetForm}>
								Cancel
							</Button>
						</div>
					</div>
				{:else}
					<Button variant="outline" onclick={() => (showCreateForm = true)} class="w-full">
						<Plus class="mr-2 h-4 w-4" />
						Add Alert
					</Button>
				{/if}
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)}>Done</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
