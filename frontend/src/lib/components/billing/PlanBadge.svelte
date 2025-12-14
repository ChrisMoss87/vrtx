<script lang="ts">
	import { license, isTrialing, trialDaysRemaining, isPastDue } from '$lib/stores/license';
	import { Badge } from '$lib/components/ui/badge';
	import { cn } from '$lib/utils';

	interface Props {
		showStatus?: boolean;
	}

	let { showStatus = true }: Props = $props();

	const planColors: Record<string, string> = {
		free: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100',
		starter: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
		professional: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-100',
		business: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
		enterprise: 'bg-gradient-to-r from-indigo-500 to-purple-500 text-white'
	};

	const planLabel = $derived($license.plan.charAt(0).toUpperCase() + $license.plan.slice(1));
</script>

<div class="flex items-center gap-2">
	<Badge class={cn('font-medium', planColors[$license.plan] ?? planColors.free)}>
		{planLabel}
	</Badge>

	{#if showStatus}
		{#if $isTrialing && $trialDaysRemaining !== null}
			<Badge variant="outline" class="text-amber-600 border-amber-300">
				{$trialDaysRemaining} days left in trial
			</Badge>
		{:else if $isPastDue}
			<Badge variant="destructive">
				Payment Required
			</Badge>
		{/if}
	{/if}
</div>
