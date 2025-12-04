<script lang="ts">
	import type { StageInput } from '$lib/api/pipelines';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Slider } from '$lib/components/ui/slider';
	import { cn } from '$lib/utils';

	interface Props {
		stage: StageInput | null;
		open: boolean;
		onSave: (stage: StageInput) => void;
		onClose: () => void;
	}

	let { stage, open, onSave, onClose }: Props = $props();

	// Default colors for stages
	const defaultColors = [
		'#6b7280', // gray
		'#3b82f6', // blue
		'#10b981', // green
		'#f59e0b', // amber
		'#ef4444', // red
		'#8b5cf6', // violet
		'#ec4899', // pink
		'#14b8a6', // teal
		'#f97316', // orange
		'#6366f1' // indigo
	];

	// Form state
	let name = $state(stage?.name || '');
	let color = $state(stage?.color || '#6b7280');
	let probability = $state([stage?.probability || 0]);
	let isWonStage = $state(stage?.is_won_stage || false);
	let isLostStage = $state(stage?.is_lost_stage || false);

	// Reset form when stage changes
	$effect(() => {
		if (stage) {
			name = stage.name || '';
			color = stage.color || '#6b7280';
			probability = [stage.probability || 0];
			isWonStage = stage.is_won_stage || false;
			isLostStage = stage.is_lost_stage || false;
		}
	});

	function handleSave() {
		if (!name.trim()) return;

		const updatedStage: StageInput & { _index?: number } = {
			...stage,
			name: name.trim(),
			color,
			probability: probability[0],
			is_won_stage: isWonStage,
			is_lost_stage: isLostStage
		};

		onSave(updatedStage);
	}

	function handleWonChange(checked: boolean) {
		isWonStage = checked;
		if (checked) {
			isLostStage = false;
			probability = [100];
		}
	}

	function handleLostChange(checked: boolean) {
		isLostStage = checked;
		if (checked) {
			isWonStage = false;
			probability = [0];
		}
	}
</script>

<Dialog.Root bind:open onOpenChange={(o) => !o && onClose()}>
	<Dialog.Content class="sm:max-w-[425px]">
		<Dialog.Header>
			<Dialog.Title>{stage?.id ? 'Edit Stage' : 'Add Stage'}</Dialog.Title>
			<Dialog.Description>
				Configure the stage properties. Stages represent steps in your pipeline.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<!-- Stage Name -->
			<div class="space-y-2">
				<Label for="stage-name">Stage Name *</Label>
				<Input
					id="stage-name"
					bind:value={name}
					placeholder="e.g., Qualification, Proposal, Negotiation"
				/>
			</div>

			<!-- Color Picker -->
			<div class="space-y-2">
				<Label>Color</Label>
				<div class="flex flex-wrap gap-2">
					{#each defaultColors as c}
						<button
							type="button"
							class={cn(
								'h-8 w-8 rounded-full border-2 transition-transform hover:scale-110',
								color === c ? 'border-primary ring-2 ring-primary/20' : 'border-transparent'
							)}
							style="background-color: {c}"
							onclick={() => (color = c)}
						>
							<span class="sr-only">Select color {c}</span>
						</button>
					{/each}
				</div>
				<div class="mt-2 flex items-center gap-2">
					<Label for="custom-color" class="text-xs text-muted-foreground">Custom:</Label>
					<Input
						id="custom-color"
						type="color"
						bind:value={color}
						class="h-8 w-16 cursor-pointer p-0"
					/>
					<Input bind:value={color} placeholder="#000000" class="w-24 font-mono text-xs" />
				</div>
			</div>

			<!-- Probability -->
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>Win Probability</Label>
					<span class="text-sm text-muted-foreground">{probability[0]}%</span>
				</div>
				<Slider
					type="multiple"
					bind:value={probability}
					min={0}
					max={100}
					step={5}
					disabled={isWonStage || isLostStage}
				/>
				<p class="text-xs text-muted-foreground">Used for weighted pipeline value calculations</p>
			</div>

			<!-- Won/Lost Stage -->
			<div class="space-y-3 rounded-lg border p-3">
				<div class="flex items-center justify-between">
					<div>
						<Label for="won-stage">Won Stage</Label>
						<p class="text-xs text-muted-foreground">Mark this as the winning stage</p>
					</div>
					<Switch id="won-stage" checked={isWonStage} onCheckedChange={handleWonChange} />
				</div>

				<div class="flex items-center justify-between">
					<div>
						<Label for="lost-stage">Lost Stage</Label>
						<p class="text-xs text-muted-foreground">Mark this as the losing stage</p>
					</div>
					<Switch id="lost-stage" checked={isLostStage} onCheckedChange={handleLostChange} />
				</div>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={onClose}>Cancel</Button>
			<Button onclick={handleSave} disabled={!name.trim()}>
				{stage?.id ? 'Update Stage' : 'Add Stage'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
