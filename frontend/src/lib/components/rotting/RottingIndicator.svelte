<script lang="ts">
	import * as Tooltip from '$lib/components/ui/tooltip';
	import type { RotStatus, RotStatusInfo } from '$lib/api/rotting';

	interface Props {
		status: RotStatusInfo;
		size?: 'sm' | 'md' | 'lg';
		showDays?: boolean;
		showTooltip?: boolean;
	}

	let { status, size = 'md', showDays = true, showTooltip = true }: Props = $props();

	const sizeClasses: Record<typeof size, string> = {
		sm: 'h-2 w-2',
		md: 'h-3 w-3',
		lg: 'h-4 w-4'
	};

	const statusColors: Record<RotStatus, string> = {
		fresh: 'bg-green-500',
		warming: 'bg-yellow-500',
		stale: 'bg-orange-500',
		rotting: 'bg-red-500'
	};

	const statusLabels: Record<RotStatus, string> = {
		fresh: 'Fresh',
		warming: 'Warming',
		stale: 'Stale',
		rotting: 'Rotting'
	};
</script>

{#if showTooltip}
	<Tooltip.Root>
		<Tooltip.Trigger>
			<div class="flex items-center gap-1.5">
				<span class={`rounded-full ${sizeClasses[size]} ${statusColors[status.status]}`}></span>
				{#if showDays}
					<span class="text-xs text-muted-foreground">{status.days_inactive}d</span>
				{/if}
			</div>
		</Tooltip.Trigger>
		<Tooltip.Content>
			<div class="space-y-1">
				<p class="font-medium">{statusLabels[status.status]}</p>
				<p class="text-xs text-muted-foreground">
					{status.days_inactive} days inactive
					{#if status.threshold_days}
						(threshold: {status.threshold_days} days)
					{/if}
				</p>
				{#if status.message}
					<p class="text-xs">{status.message}</p>
				{/if}
			</div>
		</Tooltip.Content>
	</Tooltip.Root>
{:else}
	<div class="flex items-center gap-1.5">
		<span class={`rounded-full ${sizeClasses[size]} ${statusColors[status.status]}`}></span>
		{#if showDays}
			<span class="text-xs text-muted-foreground">{status.days_inactive}d</span>
		{/if}
	</div>
{/if}
