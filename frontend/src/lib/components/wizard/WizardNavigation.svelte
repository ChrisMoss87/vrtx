<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ChevronLeft, ChevronRight, Check, X, SkipForward } from 'lucide-svelte';
	import type { WizardStore } from '$lib/hooks/useWizard.svelte';

	interface Props {
		wizard: WizardStore;
		onSubmit?: () => void | Promise<void>;
		onCancel?: () => void;
		isSubmitting?: boolean;
		showCancel?: boolean;
		submitLabel?: string;
		nextLabel?: string;
		previousLabel?: string;
		cancelLabel?: string;
		skipLabel?: string;
	}

	let {
		wizard,
		onSubmit,
		onCancel,
		isSubmitting = false,
		showCancel = true,
		submitLabel = 'Submit',
		nextLabel = 'Next',
		previousLabel = 'Previous',
		cancelLabel = 'Cancel',
		skipLabel = 'Skip'
	}: Props = $props();

	async function handleSubmit() {
		if (onSubmit) {
			await onSubmit();
		}
	}

	function handleCancel() {
		if (onCancel) {
			onCancel();
		}
	}
</script>

<div class="flex items-center justify-between gap-4 border-t pt-6">
	<!-- Left side: Cancel and Skip buttons -->
	<div class="flex items-center gap-2">
		{#if showCancel}
			<Button variant="ghost" onclick={handleCancel} disabled={isSubmitting}>
				<X class="mr-2 h-4 w-4" />
				{cancelLabel}
			</Button>
		{/if}

		{#if wizard.currentStep?.canSkip && !wizard.isLastStep}
			<Button variant="outline" onclick={() => wizard.skipStep()} disabled={isSubmitting}>
				<SkipForward class="mr-2 h-4 w-4" />
				{skipLabel}
			</Button>
		{/if}
	</div>

	<!-- Right side: Navigation buttons -->
	<div class="flex items-center gap-2">
		<!-- Previous Button -->
		{#if !wizard.isFirstStep}
			<Button
				variant="outline"
				onclick={() => wizard.goPrevious()}
				disabled={isSubmitting || !wizard.canGoPrevious}
			>
				<ChevronLeft class="mr-2 h-4 w-4" />
				{previousLabel}
			</Button>
		{/if}

		<!-- Next/Submit Button -->
		{#if wizard.isLastStep}
			<Button onclick={handleSubmit} disabled={!wizard.canGoNext || isSubmitting}>
				{#if isSubmitting}
					<span class="mr-2">Submitting...</span>
				{:else}
					<Check class="mr-2 h-4 w-4" />
					{submitLabel}
				{/if}
			</Button>
		{:else}
			<Button onclick={() => wizard.goNext()} disabled={!wizard.canGoNext || isSubmitting}>
				{nextLabel}
				<ChevronRight class="ml-2 h-4 w-4" />
			</Button>
		{/if}
	</div>
</div>
