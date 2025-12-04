<script lang="ts">
	import type { Snippet } from 'svelte';
	import { fade, fly } from 'svelte/transition';
	import WizardProgress from './WizardProgress.svelte';
	import WizardNavigation from './WizardNavigation.svelte';
	import type { WizardStore } from '$lib/hooks/useWizard.svelte';

	interface Props {
		wizard: WizardStore;
		children?: Snippet;
		onSubmit?: () => void | Promise<void>;
		onCancel?: () => void;
		showProgress?: boolean;
		allowClickNavigation?: boolean;
		showCancel?: boolean;
		title?: string;
		description?: string;
		class?: string;
	}

	let {
		wizard,
		children,
		onSubmit,
		onCancel,
		showProgress = true,
		allowClickNavigation = false,
		showCancel = true,
		title,
		description,
		class: className = ''
	}: Props = $props();

	let isSubmitting = $state(false);

	async function handleSubmit() {
		isSubmitting = true;
		try {
			if (onSubmit) {
				await onSubmit();
			}
			wizard.complete();
		} catch (error) {
			console.error('Wizard submission error:', error);
		} finally {
			isSubmitting = false;
		}
	}

	function handleCancel() {
		if (onCancel) {
			onCancel();
		}
	}

	function handleStepClick(stepIndex: number) {
		if (allowClickNavigation) {
			// Only allow navigation to completed steps or the next step
			if (stepIndex <= wizard.currentStepIndex + 1) {
				wizard.goToStep(stepIndex);
			}
		}
	}
</script>

<div class="wizard-container {className}">
	<!-- Wizard Header -->
	{#if title || description}
		<div class="mb-8">
			{#if title}
				<h1 class="text-3xl font-bold tracking-tight">{title}</h1>
			{/if}
			{#if description}
				<p class="mt-2 text-muted-foreground">{description}</p>
			{/if}
		</div>
	{/if}

	<!-- Progress Indicator -->
	{#if showProgress}
		<div class="mb-8">
			<WizardProgress {wizard} onClick={handleStepClick} {allowClickNavigation} />
		</div>
	{/if}

	<!-- Step Content -->
	<div class="wizard-content min-h-[400px]">
		{#if wizard.isComplete}
			<!-- Success State -->
			<div class="flex flex-col items-center justify-center py-12" in:fade={{ duration: 300 }}>
				<div class="mb-4 rounded-full bg-green-100 p-4">
					<svg
						class="h-12 w-12 text-green-600"
						fill="none"
						viewBox="0 0 24 24"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M5 13l4 4L19 7"
						/>
					</svg>
				</div>
				<h2 class="mb-2 text-2xl font-semibold">All Done!</h2>
				<p class="text-muted-foreground">Your submission has been completed successfully.</p>
			</div>
		{:else}
			<!-- Step Content with Transitions -->
			<div key={wizard.currentStepIndex}>
				{#if children}
					<div in:fly={{ x: 20, duration: 200, delay: 100 }} out:fly={{ x: -20, duration: 200 }}>
						{@render children()}
					</div>
				{/if}
			</div>
		{/if}
	</div>

	<!-- Navigation Buttons -->
	{#if !wizard.isComplete}
		<WizardNavigation
			{wizard}
			onSubmit={handleSubmit}
			onCancel={handleCancel}
			{isSubmitting}
			{showCancel}
		/>
	{/if}
</div>

<style>
	.wizard-container {
		width: 100%;
		max-width: 800px;
		margin: 0 auto;
		padding: 2rem;
	}

	.wizard-content {
		position: relative;
		overflow: hidden;
	}
</style>
