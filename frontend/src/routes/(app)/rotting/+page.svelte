<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		getRottingDeals,
		getRottingSummary,
		getRottingAlerts,
		acknowledgeAlert,
		acknowledgeAllAlerts,
		type RottingDeal,
		type RottingSummary,
		type RottingAlert,
		type RotStatus
	} from '$lib/api/rotting';
	import { RottingIndicator, RottingBadge, RottingSettingsPanel } from '$lib/components/rotting';
	import { goto } from '$app/navigation';
	import { toast } from 'svelte-sonner';

	// State
	let activeTab = $state('deals');
	let selectedStatus = $state<RotStatus | 'all'>('all');
	let selectedPipeline = $state<number | undefined>(undefined);

	// Data
	let deals = $state<RottingDeal[]>([]);
	let summary = $state<RottingSummary | null>(null);
	let alerts = $state<RottingAlert[]>([]);

	// Loading states
	let loadingDeals = $state(true);
	let loadingSummary = $state(true);
	let loadingAlerts = $state(true);
	let acknowledgingAll = $state(false);

	// Pagination
	let currentPage = $state(1);
	let totalPages = $state(1);
	let perPage = 20;

	async function loadDeals() {
		loadingDeals = true;
		try {
			const response = await getRottingDeals({
				pipeline_id: selectedPipeline,
				status: selectedStatus === 'all' ? undefined : selectedStatus,
				page: currentPage,
				per_page: perPage
			});
			deals = response.data;
			totalPages = response.meta.last_page;
		} catch (e) {
			toast.error('Failed to load rotting deals');
		} finally {
			loadingDeals = false;
		}
	}

	async function loadSummary() {
		if (!selectedPipeline) {
			summary = null;
			return;
		}
		loadingSummary = true;
		try {
			summary = await getRottingSummary(selectedPipeline);
		} catch (e) {
			summary = null;
		} finally {
			loadingSummary = false;
		}
	}

	async function loadAlerts() {
		loadingAlerts = true;
		try {
			const response = await getRottingAlerts({
				acknowledged: false,
				per_page: 50
			});
			alerts = response.data;
		} catch (e) {
			toast.error('Failed to load alerts');
		} finally {
			loadingAlerts = false;
		}
	}

	async function handleAcknowledge(alertId: number) {
		try {
			await acknowledgeAlert(alertId);
			alerts = alerts.filter((a) => a.id !== alertId);
			toast.success('Alert acknowledged');
		} catch (e) {
			toast.error('Failed to acknowledge alert');
		}
	}

	async function handleAcknowledgeAll() {
		acknowledgingAll = true;
		try {
			const count = await acknowledgeAllAlerts();
			alerts = [];
			toast.success(`${count} alerts acknowledged`);
		} catch (e) {
			toast.error('Failed to acknowledge alerts');
		} finally {
			acknowledgingAll = false;
		}
	}

	function getDealName(deal: RottingDeal): string {
		const data = deal.record.data;
		return (data.name as string) || (data.title as string) || `Deal #${deal.record.id}`;
	}

	function navigateToDeal(deal: RottingDeal) {
		goto(`/records/deals/${deal.record.id}`);
	}

	// Load initial data
	$effect(() => {
		loadDeals();
	});

	$effect(() => {
		if (activeTab === 'alerts') {
			loadAlerts();
		}
	});

	$effect(() => {
		loadSummary();
	});

	const statusFilters = [
		{ value: 'all', label: 'All Statuses' },
		{ value: 'rotting', label: 'Rotting' },
		{ value: 'stale', label: 'Stale' },
		{ value: 'warming', label: 'Warming' },
		{ value: 'fresh', label: 'Fresh' }
	];
</script>

<svelte:head>
	<title>Rotting Deals | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Rotting Deals</h1>
			<p class="text-muted-foreground">Monitor and manage deals that need attention</p>
		</div>
	</div>

	{#if summary}
		<div class="grid grid-cols-2 md:grid-cols-5 gap-4">
			<Card>
				<CardContent class="pt-4">
					<div class="text-2xl font-bold">{summary.total}</div>
					<div class="text-sm text-muted-foreground">Total Deals</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="text-2xl font-bold text-green-600">{summary.fresh}</div>
					<div class="text-sm text-muted-foreground">Fresh</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="text-2xl font-bold text-yellow-600">{summary.warming}</div>
					<div class="text-sm text-muted-foreground">Warming</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="text-2xl font-bold text-orange-600">{summary.stale}</div>
					<div class="text-sm text-muted-foreground">Stale</div>
				</CardContent>
			</Card>
			<Card>
				<CardContent class="pt-4">
					<div class="text-2xl font-bold text-red-600">{summary.rotting}</div>
					<div class="text-sm text-muted-foreground">Rotting</div>
				</CardContent>
			</Card>
		</div>
	{/if}

	<Tabs.Root bind:value={activeTab}>
		<Tabs.List>
			<Tabs.Trigger value="deals">Deals</Tabs.Trigger>
			<Tabs.Trigger value="alerts">
				Alerts
				{#if alerts.length > 0}
					<Badge variant="destructive" class="ml-2">{alerts.length}</Badge>
				{/if}
			</Tabs.Trigger>
			<Tabs.Trigger value="settings">Settings</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="deals" class="space-y-4">
			<div class="flex items-center gap-4">
				<Select.Root type="single" bind:value={selectedStatus} onValueChange={() => { currentPage = 1; loadDeals(); }}>
					<Select.Trigger class="w-48">
						{statusFilters.find((s) => s.value === selectedStatus)?.label ?? 'All Statuses'}
					</Select.Trigger>
					<Select.Content>
						{#each statusFilters as filter}
							<Select.Item value={filter.value}>{filter.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			{#if loadingDeals}
				<div class="space-y-3">
					{#each Array(5) as _}
						<Card>
							<CardContent class="p-4">
								<div class="flex items-center gap-4">
									<Skeleton class="h-10 w-10 rounded-full" />
									<div class="flex-1 space-y-2">
										<Skeleton class="h-4 w-1/3" />
										<Skeleton class="h-3 w-1/2" />
									</div>
								</div>
							</CardContent>
						</Card>
					{/each}
				</div>
			{:else if deals.length === 0}
				<Card>
					<CardContent class="py-12 text-center">
						<p class="text-muted-foreground">No rotting deals found</p>
						<p class="text-sm text-muted-foreground mt-1">All your deals are looking healthy!</p>
					</CardContent>
				</Card>
			{:else}
				<div class="space-y-3">
					{#each deals as deal}
						<Card class="hover:bg-muted/50 transition-colors cursor-pointer" onclick={() => navigateToDeal(deal)}>
							<CardContent class="p-4">
								<div class="flex items-center gap-4">
									<RottingIndicator status={deal.rot_status} size="lg" showDays={false} />
									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2">
											<p class="font-medium truncate">{getDealName(deal)}</p>
											<RottingBadge status={deal.rot_status} showDays={false} />
										</div>
										<p class="text-sm text-muted-foreground">
											{deal.pipeline.name} &bull; {deal.stage.name}
										</p>
									</div>
									<div class="text-right">
										<p class="text-sm font-medium">{deal.rot_status.days_inactive} days</p>
										<p class="text-xs text-muted-foreground">inactive</p>
									</div>
								</div>
							</CardContent>
						</Card>
					{/each}
				</div>

				{#if totalPages > 1}
					<div class="flex items-center justify-center gap-2">
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage === 1}
							onclick={() => { currentPage--; loadDeals(); }}
						>
							Previous
						</Button>
						<span class="text-sm text-muted-foreground">
							Page {currentPage} of {totalPages}
						</span>
						<Button
							variant="outline"
							size="sm"
							disabled={currentPage === totalPages}
							onclick={() => { currentPage++; loadDeals(); }}
						>
							Next
						</Button>
					</div>
				{/if}
			{/if}
		</Tabs.Content>

		<Tabs.Content value="alerts" class="space-y-4">
			{#if alerts.length > 0}
				<div class="flex justify-end">
					<Button
						variant="outline"
						size="sm"
						disabled={acknowledgingAll}
						onclick={handleAcknowledgeAll}
					>
						{acknowledgingAll ? 'Acknowledging...' : 'Acknowledge All'}
					</Button>
				</div>
			{/if}

			{#if loadingAlerts}
				<div class="space-y-3">
					{#each Array(3) as _}
						<Card>
							<CardContent class="p-4">
								<div class="flex items-center gap-4">
									<Skeleton class="h-8 w-8 rounded" />
									<div class="flex-1 space-y-2">
										<Skeleton class="h-4 w-1/2" />
										<Skeleton class="h-3 w-1/3" />
									</div>
								</div>
							</CardContent>
						</Card>
					{/each}
				</div>
			{:else if alerts.length === 0}
				<Card>
					<CardContent class="py-12 text-center">
						<p class="text-muted-foreground">No pending alerts</p>
						<p class="text-sm text-muted-foreground mt-1">You're all caught up!</p>
					</CardContent>
				</Card>
			{:else}
				<div class="space-y-3">
					{#each alerts as alert}
						<Card>
							<CardContent class="p-4">
								<div class="flex items-center gap-4">
									<Badge
										variant={alert.alert_type === 'rotting' ? 'destructive' : 'secondary'}
									>
										{alert.alert_type}
									</Badge>
									<div class="flex-1 min-w-0">
										<p class="font-medium">
											{alert.moduleRecord?.data?.name || `Record #${alert.module_record_id}`}
										</p>
										<p class="text-sm text-muted-foreground">
											{alert.stage?.name} &bull; {alert.days_inactive} days inactive
										</p>
									</div>
									<Button
										variant="ghost"
										size="sm"
										onclick={() => handleAcknowledge(alert.id)}
									>
										Acknowledge
									</Button>
								</div>
							</CardContent>
						</Card>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<Tabs.Content value="settings">
			<RottingSettingsPanel class="max-w-2xl" />
		</Tabs.Content>
	</Tabs.Root>
</div>
