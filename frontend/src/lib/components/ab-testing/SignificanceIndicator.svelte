<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import * as Tooltip from '$lib/components/ui/tooltip';
	import { CheckCircle2, AlertCircle, Clock, HelpCircle } from 'lucide-svelte';

	interface Props {
		isSignificant: boolean;
		confidenceLevel: number;
		sampleProgress: number;
		improvement?: number;
	}

	let { isSignificant, confidenceLevel, sampleProgress, improvement = 0 }: Props = $props();

	const status = $derived.by(() => {
		if (isSignificant && improvement > 0) {
			return {
				label: 'Significant Winner',
				variant: 'default' as const,
				class: 'bg-green-100 text-green-800 border-green-200',
				icon: CheckCircle2,
				description: `Results are statistically significant at ${confidenceLevel}% confidence level`
			};
		}
		if (isSignificant && improvement < 0) {
			return {
				label: 'Significant Loser',
				variant: 'destructive' as const,
				class: 'bg-red-100 text-red-800 border-red-200',
				icon: AlertCircle,
				description: `Variant is performing worse than control with ${confidenceLevel}% confidence`
			};
		}
		if (sampleProgress < 100) {
			return {
				label: 'Collecting Data',
				variant: 'secondary' as const,
				class: 'bg-blue-100 text-blue-800 border-blue-200',
				icon: Clock,
				description: `${Math.round(sampleProgress)}% of minimum sample size collected`
			};
		}
		return {
			label: 'Inconclusive',
			variant: 'outline' as const,
			class: 'bg-yellow-100 text-yellow-800 border-yellow-200',
			icon: HelpCircle,
			description: 'Not enough evidence to determine a winner'
		};
	});
</script>

<Tooltip.Root>
	<Tooltip.Trigger>
		<Badge variant={status.variant} class={status.class}>
			{@const Icon = status.icon}
			<Icon class="mr-1 h-3 w-3" />
			{status.label}
		</Badge>
	</Tooltip.Trigger>
	<Tooltip.Content>
		<p class="max-w-xs text-sm">{status.description}</p>
	</Tooltip.Content>
</Tooltip.Root>
