<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import type { RotStatus, RotStatusInfo } from '$lib/api/rotting';

	interface Props {
		status: RotStatusInfo;
		showDays?: boolean;
		class?: string;
	}

	let { status, showDays = true, class: className = '' }: Props = $props();

	const badgeVariants: Record<RotStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
		fresh: 'default',
		warming: 'outline',
		stale: 'secondary',
		rotting: 'destructive'
	};

	const statusLabels: Record<RotStatus, string> = {
		fresh: 'Fresh',
		warming: 'Warming',
		stale: 'Stale',
		rotting: 'Rotting'
	};

	const statusColors: Record<RotStatus, string> = {
		fresh: 'bg-green-500/10 text-green-700 border-green-500/20',
		warming: 'bg-yellow-500/10 text-yellow-700 border-yellow-500/20',
		stale: 'bg-orange-500/10 text-orange-700 border-orange-500/20',
		rotting: 'bg-red-500/10 text-red-700 border-red-500/20'
	};
</script>

<Badge variant={badgeVariants[status.status]} class="{statusColors[status.status]} {className}">
	{statusLabels[status.status]}
	{#if showDays}
		<span class="ml-1 opacity-75">({status.days_inactive}d)</span>
	{/if}
</Badge>
