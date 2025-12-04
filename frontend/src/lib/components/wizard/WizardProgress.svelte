<script lang="ts">
	import { Check } from 'lucide-svelte';
	import type { WizardStore } from '$lib/hooks/useWizard.svelte';

	interface Props {
		wizard: WizardStore;
		onClick?: (stepIndex: number) => void;
		allowClickNavigation?: boolean;
	}

	let { wizard, onClick, allowClickNavigation = false }: Props = $props();

	function handleStepClick(index: number) {
		if (allowClickNavigation && onClick) {
			onClick(index);
		}
	}

	function getStepStatus(index: number) {
		const step = wizard.steps[index];
		if (step.isComplete) return 'complete';
		if (index === wizard.currentStepIndex) return 'current';
		if (index < wizard.currentStepIndex) return 'complete';
		return 'upcoming';
	}
</script>

<div class="w-full">
	<!-- Progress Bar -->
	<div class="mb-8">
		<div class="h-2 w-full overflow-hidden rounded-full bg-muted">
			<div
				class="h-full bg-primary transition-all duration-300 ease-in-out"
				style="width: {wizard.progress}%"
			></div>
		</div>
		<div class="mt-2 text-center text-sm text-muted-foreground">
			Step {wizard.currentStepIndex + 1} of {wizard.totalSteps}
		</div>
	</div>

	<!-- Step Indicators -->
	<div class="flex items-center justify-between">
		{#each wizard.steps as step, index}
			<div class="flex flex-1 flex-col items-center">
				<!-- Step Circle -->
				<button
					type="button"
					onclick={() => handleStepClick(index)}
					disabled={!allowClickNavigation}
					class="relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all duration-200
						{getStepStatus(index) === 'complete'
						? 'border-primary bg-primary text-primary-foreground'
						: getStepStatus(index) === 'current'
							? 'border-primary bg-background text-primary'
							: 'border-muted bg-background text-muted-foreground'}
						{allowClickNavigation && 'cursor-pointer hover:scale-110'}
						{!allowClickNavigation && 'cursor-default'}
					"
					aria-current={index === wizard.currentStepIndex ? 'step' : undefined}
				>
					{#if step.isComplete}
						<Check class="h-5 w-5" />
					{:else if step.isSkipped}
						<span class="text-xs">Skip</span>
					{:else}
						<span class="font-medium">{index + 1}</span>
					{/if}
				</button>

				<!-- Step Label -->
				<div class="mt-2 max-w-[120px] text-center">
					<div
						class="text-sm font-medium {index === wizard.currentStepIndex
							? 'text-foreground'
							: 'text-muted-foreground'}"
					>
						{step.title}
					</div>
					{#if step.description}
						<div class="mt-0.5 hidden text-xs text-muted-foreground sm:block">
							{step.description}
						</div>
					{/if}
				</div>
			</div>

			<!-- Connector Line -->
			{#if index < wizard.steps.length - 1}
				<div
					class="mx-2 mt-[-40px] h-0.5 flex-1 {index < wizard.currentStepIndex
						? 'bg-primary'
						: 'bg-muted'}"
				></div>
			{/if}
		{/each}
	</div>
</div>
