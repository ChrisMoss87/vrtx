<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { WizardStore } from '$lib/hooks/useWizard.svelte';

	interface Props {
		wizard: WizardStore;
		stepId: string;
		title?: string;
		description?: string;
		children?: Snippet;
		onValidate?: (formData: Record<string, any>) => boolean | Promise<boolean>;
	}

	let { wizard, stepId, title, description, children, onValidate }: Props = $props();

	const isActive = $derived(wizard.currentStep?.id === stepId);
	const step = $derived(wizard.steps.find((s) => s.id === stepId));

	// Validate step when form data changes
	$effect(() => {
		if (isActive && onValidate) {
			const validateStep = async () => {
				const isValid = await onValidate(wizard.formData);
				wizard.setStepValid(stepId, isValid);
			};
			validateStep();
		}
	});
</script>

{#if isActive}
	<div class="wizard-step" data-step-id={stepId}>
		{#if title || description}
			<div class="mb-6">
				{#if title}
					<h2 class="text-2xl font-semibold tracking-tight">{title}</h2>
				{/if}
				{#if description}
					<p class="mt-2 text-muted-foreground">{description}</p>
				{/if}
			</div>
		{/if}

		<div class="wizard-step-content">
			{#if children}
				{@render children()}
			{/if}
		</div>

		{#if step?.isComplete && !isActive}
			<div class="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
				<svg class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="2"
						d="M5 13l4 4L19 7"
					/>
				</svg>
				Step completed
			</div>
		{/if}
	</div>
{/if}
