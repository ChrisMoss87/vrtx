<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { X, ExternalLink, Users, Building2, DollarSign, User, Mail, Globe, Briefcase, Calendar, TrendingUp } from 'lucide-svelte';
	import { getGraphMetrics, type GraphNode, type GraphMetrics } from '$lib/api/graph';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { onMount } from 'svelte';

	interface Props {
		node: GraphNode;
		onClose: () => void;
	}

	let { node, onClose }: Props = $props();

	let metrics = $state<GraphMetrics | null>(null);
	let loadingMetrics = $state(false);

	onMount(async () => {
		loadingMetrics = true;
		const { data } = await tryCatch(getGraphMetrics(node.entity_type, node.entity_id));
		metrics = data;
		loadingMetrics = false;
	});

	function getEntityIcon(type: string) {
		switch (type) {
			case 'contact':
				return Users;
			case 'company':
				return Building2;
			case 'deal':
				return DollarSign;
			case 'user':
				return User;
			default:
				return Users;
		}
	}

	function getEntityColor(type: string): string {
		switch (type) {
			case 'contact':
				return 'bg-emerald-500';
			case 'company':
				return 'bg-violet-500';
			case 'deal':
				return 'bg-amber-500';
			case 'user':
				return 'bg-indigo-500';
			default:
				return 'bg-slate-500';
		}
	}

	function formatCurrency(value: number | undefined): string {
		if (!value) return '-';
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	function formatPercent(value: number | null | undefined): string {
		if (value === null || value === undefined) return '-';
		return `${(value * 100).toFixed(1)}%`;
	}

	function getRecordUrl(): string {
		const moduleMap: Record<string, string> = {
			contact: 'contacts',
			company: 'accounts',
			deal: 'deals',
			user: 'users'
		};
		const module = moduleMap[node.entity_type] || node.entity_type;
		return `/records/${module}/${node.entity_id}`;
	}

	const Icon = $derived(getEntityIcon(node.entity_type));
</script>

<div class="h-full flex flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between p-4 border-b">
		<div class="flex items-center gap-3">
			<div class={`p-2 rounded-lg ${getEntityColor(node.entity_type)}`}>
				<svelte:component this={Icon} class="h-5 w-5 text-white" />
			</div>
			<div>
				<h3 class="font-semibold">{node.label}</h3>
				<p class="text-sm text-muted-foreground capitalize">{node.entity_type}</p>
			</div>
		</div>
		<Button variant="ghost" size="icon" onclick={onClose}>
			<X class="h-4 w-4" />
		</Button>
	</div>

	<!-- Content -->
	<div class="flex-1 overflow-y-auto p-4 space-y-6">
		<!-- Quick Stats -->
		{#if node.revenue || node.amount}
			<div class="bg-muted/50 rounded-lg p-4">
				<div class="flex items-center gap-2 text-sm text-muted-foreground mb-1">
					<DollarSign class="h-4 w-4" />
					{node.entity_type === 'deal' ? 'Deal Value' : 'Revenue'}
				</div>
				<div class="text-2xl font-bold">
					{formatCurrency(node.revenue || node.amount)}
				</div>
			</div>
		{/if}

		<!-- Entity Details -->
		<div>
			<h4 class="text-sm font-medium mb-3">Details</h4>
			<div class="space-y-3">
				{#if node.data.email}
					<div class="flex items-center gap-3 text-sm">
						<Mail class="h-4 w-4 text-muted-foreground" />
						<a href="mailto:{node.data.email}" class="text-primary hover:underline">
							{node.data.email}
						</a>
					</div>
				{/if}

				{#if node.data.website}
					<div class="flex items-center gap-3 text-sm">
						<Globe class="h-4 w-4 text-muted-foreground" />
						<a href={String(node.data.website)} target="_blank" rel="noopener" class="text-primary hover:underline">
							{node.data.website}
						</a>
					</div>
				{/if}

				{#if node.data.title}
					<div class="flex items-center gap-3 text-sm">
						<Briefcase class="h-4 w-4 text-muted-foreground" />
						<span>{node.data.title}</span>
					</div>
				{/if}

				{#if node.data.company}
					<div class="flex items-center gap-3 text-sm">
						<Building2 class="h-4 w-4 text-muted-foreground" />
						<span>{node.data.company}</span>
					</div>
				{/if}

				{#if node.data.industry}
					<div class="flex items-center gap-3 text-sm">
						<TrendingUp class="h-4 w-4 text-muted-foreground" />
						<span>{node.data.industry}</span>
					</div>
				{/if}

				{#if node.data.stage}
					<div class="flex items-center gap-3 text-sm">
						<TrendingUp class="h-4 w-4 text-muted-foreground" />
						<span>Stage: {node.data.stage}</span>
					</div>
				{/if}

				{#if node.data.close_date}
					<div class="flex items-center gap-3 text-sm">
						<Calendar class="h-4 w-4 text-muted-foreground" />
						<span>Close: {node.data.close_date}</span>
					</div>
				{/if}

				{#if node.data.probability}
					<div class="flex items-center gap-3 text-sm">
						<TrendingUp class="h-4 w-4 text-muted-foreground" />
						<span>Probability: {node.data.probability}%</span>
					</div>
				{/if}

				{#if node.data.employees}
					<div class="flex items-center gap-3 text-sm">
						<Users class="h-4 w-4 text-muted-foreground" />
						<span>{node.data.employees} employees</span>
					</div>
				{/if}
			</div>
		</div>

		<!-- Graph Metrics -->
		<div>
			<h4 class="text-sm font-medium mb-3">Network Metrics</h4>
			{#if loadingMetrics}
				<div class="text-sm text-muted-foreground">Loading metrics...</div>
			{:else if metrics}
				<div class="grid grid-cols-2 gap-3">
					<div class="bg-muted/50 rounded-lg p-3">
						<div class="text-xs text-muted-foreground mb-1">Centrality</div>
						<div class="font-semibold">{formatPercent(metrics.degree_centrality)}</div>
					</div>
					<div class="bg-muted/50 rounded-lg p-3">
						<div class="text-xs text-muted-foreground mb-1">Betweenness</div>
						<div class="font-semibold">{formatPercent(metrics.betweenness_centrality)}</div>
					</div>
					<div class="bg-muted/50 rounded-lg p-3">
						<div class="text-xs text-muted-foreground mb-1">Closeness</div>
						<div class="font-semibold">{formatPercent(metrics.closeness_centrality)}</div>
					</div>
					{#if metrics.cluster_id !== null}
						<div class="bg-muted/50 rounded-lg p-3">
							<div class="text-xs text-muted-foreground mb-1">Cluster</div>
							<div class="font-semibold">#{metrics.cluster_id}</div>
						</div>
					{/if}
				</div>

				{#if metrics.total_connected_revenue}
					<div class="mt-3 bg-muted/50 rounded-lg p-3">
						<div class="text-xs text-muted-foreground mb-1">Connected Revenue</div>
						<div class="font-semibold text-lg">{formatCurrency(metrics.total_connected_revenue)}</div>
					</div>
				{/if}
			{:else}
				<div class="text-sm text-muted-foreground">No metrics available</div>
			{/if}
		</div>
	</div>

	<!-- Footer -->
	<div class="p-4 border-t">
		<Button variant="outline" class="w-full" href={getRecordUrl()}>
			<ExternalLink class="h-4 w-4 mr-2" />
			View Full Record
		</Button>
	</div>
</div>
