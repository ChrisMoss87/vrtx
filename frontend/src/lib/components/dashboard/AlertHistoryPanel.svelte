<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import * as Sheet from '$lib/components/ui/sheet';
	import {
		Bell,
		Check,
		X,
		AlertCircle,
		AlertTriangle,
		Info,
		Loader2,
		ExternalLink
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import {
		dashboardAlertsApi,
		type DashboardWidgetAlertHistory,
		getSeverityColor,
		getSeverityBgColor
	} from '$lib/api/dashboard-alerts';

	interface Props {
		dashboardId: number;
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { dashboardId, open = $bindable(false), onOpenChange }: Props = $props();

	let history = $state<DashboardWidgetAlertHistory[]>([]);
	let loading = $state(false);

	$effect(() => {
		if (open) {
			loadHistory();
		}
	});

	async function loadHistory() {
		loading = true;
		try {
			history = await dashboardAlertsApi.getHistory(dashboardId);
		} catch (error) {
			console.error('Failed to load alert history:', error);
			toast.error('Failed to load alert history');
		} finally {
			loading = false;
		}
	}

	async function handleAcknowledge(entry: DashboardWidgetAlertHistory) {
		try {
			const updated = await dashboardAlertsApi.acknowledge(dashboardId, entry.id);
			history = history.map((h) => (h.id === entry.id ? updated : h));
			toast.success('Alert acknowledged');
		} catch (error) {
			console.error('Failed to acknowledge alert:', error);
			toast.error('Failed to acknowledge alert');
		}
	}

	async function handleDismiss(entry: DashboardWidgetAlertHistory) {
		try {
			const updated = await dashboardAlertsApi.dismiss(dashboardId, entry.id);
			history = history.map((h) => (h.id === entry.id ? updated : h));
			toast.success('Alert dismissed');
		} catch (error) {
			console.error('Failed to dismiss alert:', error);
			toast.error('Failed to dismiss alert');
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		onOpenChange?.(value);
	}

	function getSeverityIcon(severity: string) {
		switch (severity) {
			case 'critical':
				return AlertCircle;
			case 'warning':
				return AlertTriangle;
			default:
				return Info;
		}
	}

	function formatDate(dateString: string): string {
		const date = new Date(dateString);
		const now = new Date();
		const diff = now.getTime() - date.getTime();

		if (diff < 60000) return 'Just now';
		if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
		if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
		if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;

		return date.toLocaleDateString();
	}

	function getStatusBadge(status: string) {
		switch (status) {
			case 'triggered':
				return { variant: 'destructive' as const, label: 'Active' };
			case 'acknowledged':
				return { variant: 'secondary' as const, label: 'Acknowledged' };
			case 'dismissed':
				return { variant: 'outline' as const, label: 'Dismissed' };
			default:
				return { variant: 'secondary' as const, label: status };
		}
	}

	const unacknowledgedCount = $derived(history.filter((h) => h.status === 'triggered').length);
</script>

<Sheet.Root bind:open onOpenChange={handleOpenChange}>
	<Sheet.Content side="right" class="w-96">
		<Sheet.Header>
			<Sheet.Title class="flex items-center gap-2">
				<Bell class="h-5 w-5" />
				Alert History
				{#if unacknowledgedCount > 0}
					<Badge variant="destructive">{unacknowledgedCount}</Badge>
				{/if}
			</Sheet.Title>
			<Sheet.Description>
				Recent alerts triggered for this dashboard
			</Sheet.Description>
		</Sheet.Header>

		<div class="py-4">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if history.length === 0}
				<div class="flex flex-col items-center justify-center py-12 text-center">
					<Bell class="h-12 w-12 text-muted-foreground mb-4" />
					<h3 class="font-medium">No alerts yet</h3>
					<p class="text-sm text-muted-foreground mt-1">
						Alerts will appear here when triggered
					</p>
				</div>
			{:else}
				<ScrollArea class="h-[calc(100vh-200px)]">
					<div class="space-y-3 pr-4">
						{#each history as entry}
							{@const severity = entry.alert?.severity || 'info'}
							{@const SeverityIcon = getSeverityIcon(severity)}
							{@const statusBadge = getStatusBadge(entry.status)}

							<div
								class="rounded-lg border p-3 {entry.status === 'triggered'
									? getSeverityBgColor(severity)
									: ''}"
							>
								<div class="flex items-start gap-3">
									<div class="mt-0.5">
										<SeverityIcon class="h-5 w-5 {getSeverityColor(severity)}" />
									</div>
									<div class="flex-1 min-w-0">
										<div class="flex items-center justify-between gap-2">
											<span class="font-medium text-sm truncate">
												{entry.alert?.name || 'Alert'}
											</span>
											<Badge variant={statusBadge.variant} class="text-xs shrink-0">
												{statusBadge.label}
											</Badge>
										</div>

										<p class="text-xs text-muted-foreground mt-1">
											Value: {entry.triggered_value.toLocaleString()} / Threshold: {entry.threshold_value.toLocaleString()}
										</p>

										{#if entry.alert?.widget}
											<p class="text-xs text-muted-foreground">
												Widget: {entry.alert.widget.title}
											</p>
										{/if}

										<p class="text-xs text-muted-foreground mt-1">
											{formatDate(entry.created_at)}
										</p>

										{#if entry.status === 'triggered'}
											<div class="flex gap-2 mt-2">
												<Button
													variant="outline"
													size="sm"
													class="h-7 text-xs"
													onclick={() => handleAcknowledge(entry)}
												>
													<Check class="mr-1 h-3 w-3" />
													Acknowledge
												</Button>
												<Button
													variant="ghost"
													size="sm"
													class="h-7 text-xs"
													onclick={() => handleDismiss(entry)}
												>
													<X class="mr-1 h-3 w-3" />
													Dismiss
												</Button>
											</div>
										{/if}
									</div>
								</div>
							</div>
						{/each}
					</div>
				</ScrollArea>
			{/if}
		</div>
	</Sheet.Content>
</Sheet.Root>
