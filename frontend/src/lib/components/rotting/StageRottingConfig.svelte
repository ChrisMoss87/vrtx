<script lang="ts">
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Button } from '$lib/components/ui/button';
	import { configureStageRotting, removeStageRotting } from '$lib/api/rotting';
	import { toast } from 'svelte-sonner';

	interface Stage {
		id: number;
		name: string;
		color: string;
		rotting_days: number | null;
	}

	interface Props {
		pipelineId: number;
		stages: Stage[];
		onUpdate?: () => void;
		class?: string;
	}

	let { pipelineId, stages, onUpdate, class: className = '' }: Props = $props();

	let localStages = $state<Stage[]>([]);
	let savingStageId = $state<number | null>(null);

	$effect(() => {
		localStages = stages.map((s) => ({ ...s }));
	});

	async function saveStage(stage: Stage) {
		savingStageId = stage.id;
		try {
			const rottingDays = stage.rotting_days;
			if (rottingDays && rottingDays > 0) {
				await configureStageRotting(pipelineId, stage.id, rottingDays);
				toast.success(`Rotting threshold set to ${rottingDays} days for ${stage.name}`);
			} else {
				await removeStageRotting(pipelineId, stage.id);
				toast.success(`Rotting tracking disabled for ${stage.name}`);
			}
			onUpdate?.();
		} catch (e) {
			toast.error(e instanceof Error ? e.message : 'Failed to save stage configuration');
		} finally {
			savingStageId = null;
		}
	}

	function handleDaysChange(index: number, value: string) {
		const days = value === '' ? null : parseInt(value, 10);
		localStages[index].rotting_days = days;
	}
</script>

<Card class={className}>
	<CardHeader>
		<CardTitle>Stage Rotting Thresholds</CardTitle>
		<CardDescription>
			Configure how many days a deal can stay in each stage before it's considered rotting
		</CardDescription>
	</CardHeader>
	<CardContent>
		<div class="space-y-4">
			{#each localStages as stage, index}
				<div class="flex items-center gap-4 p-3 rounded-lg border">
					<div
						class="w-3 h-3 rounded-full shrink-0"
						style="background-color: {stage.color}"
					/>
					<div class="flex-1 min-w-0">
						<Label class="font-medium">{stage.name}</Label>
					</div>
					<div class="flex items-center gap-2">
						<Input
							type="number"
							min="0"
							placeholder="Days"
							class="w-20 text-center"
							value={stage.rotting_days ?? ''}
							oninput={(e) => handleDaysChange(index, e.currentTarget.value)}
						/>
						<span class="text-sm text-muted-foreground">days</span>
						<Button
							variant="outline"
							size="sm"
							disabled={savingStageId === stage.id}
							onclick={() => saveStage(stage)}
						>
							{savingStageId === stage.id ? 'Saving...' : 'Save'}
						</Button>
					</div>
				</div>
			{/each}
		</div>

		<div class="mt-6 p-4 bg-muted/50 rounded-lg">
			<h4 class="text-sm font-medium mb-2">How rotting works</h4>
			<ul class="text-sm text-muted-foreground space-y-1">
				<li>Set the number of days a deal can stay inactive in each stage</li>
				<li>Leave blank or set to 0 to disable rotting tracking for a stage</li>
				<li>Deals will show visual indicators as they approach and exceed thresholds</li>
				<li>You'll receive alerts based on your notification settings</li>
			</ul>
		</div>
	</CardContent>
</Card>
