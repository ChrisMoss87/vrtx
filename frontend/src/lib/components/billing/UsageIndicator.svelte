<script lang="ts">
	import { license } from '$lib/stores/license';
	import { Progress } from '$lib/components/ui/progress';
	import { AlertTriangle, CheckCircle, XCircle } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface Props {
		metric: string;
		label?: string;
		showLabel?: boolean;
		size?: 'sm' | 'md' | 'lg';
	}

	let {
		metric,
		label = undefined,
		showLabel = true,
		size = 'md',
	}: Props = $props();

	const usage = $derived($license.usage[metric]);
	const percentage = $derived(usage?.percentage ?? 0);
	const isWarning = $derived(percentage >= 80 && percentage < 100);
	const isError = $derived(percentage >= 100);
	const isUnlimited = $derived(usage?.limit === null);

	const displayLabel = $derived(label ?? formatMetricLabel(metric));

	function formatMetricLabel(m: string): string {
		const labels: Record<string, string> = {
			records: 'Records',
			storage_mb: 'Storage',
			api_calls: 'API Calls',
			workflows: 'Workflows',
			blueprints: 'Blueprints',
			emails_sent: 'Emails Sent',
			sms_sent: 'SMS Sent'
		};
		return labels[m] ?? m;
	}

	function formatUsage(used: number, limit: number | null, m: string): string {
		if (limit === null) return `${formatNumber(used)} (Unlimited)`;

		if (m === 'storage_mb') {
			return `${formatStorage(used)} / ${formatStorage(limit)}`;
		}

		return `${formatNumber(used)} / ${formatNumber(limit)}`;
	}

	function formatNumber(n: number): string {
		if (n >= 1000000) return `${(n / 1000000).toFixed(1)}M`;
		if (n >= 1000) return `${(n / 1000).toFixed(1)}K`;
		return n.toString();
	}

	function formatStorage(mb: number): string {
		if (mb >= 1024) return `${(mb / 1024).toFixed(1)} GB`;
		return `${mb} MB`;
	}

	const sizeClasses = {
		sm: 'text-xs',
		md: 'text-sm',
		lg: 'text-base'
	};
</script>

{#if usage}
	<div class={cn('space-y-1', sizeClasses[size])}>
		{#if showLabel}
			<div class="flex items-center justify-between">
				<span class="text-muted-foreground">{displayLabel}</span>
				<span class="font-medium flex items-center gap-1">
					{#if isError}
						<XCircle class="h-3 w-3 text-destructive" />
					{:else if isWarning}
						<AlertTriangle class="h-3 w-3 text-amber-500" />
					{:else if isUnlimited}
						<CheckCircle class="h-3 w-3 text-green-500" />
					{/if}
					{formatUsage(usage.used, usage.limit, metric)}
				</span>
			</div>
		{/if}

		{#if !isUnlimited}
			<Progress
				value={Math.min(percentage, 100)}
				class={cn(
					'h-2',
					isError && '[&>div]:bg-destructive',
					isWarning && '[&>div]:bg-amber-500'
				)}
			/>
		{/if}
	</div>
{/if}
