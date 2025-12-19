<script lang="ts">
	import * as Dialog from '$lib/components/ui/dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import Wizard from '$lib/components/wizard/Wizard.svelte';
	import WizardStep from '$lib/components/wizard/WizardStep.svelte';
	import { createWizardStore } from '$lib/hooks/useWizard.svelte';
	import type { Field, FieldOption } from '$lib/types/modules';
	import { X } from 'lucide-svelte';

	interface WizardStepConfig {
		id: string;
		title: string;
		description: string;
		type: 'form' | 'review' | 'confirmation';
		fields: string[];
		canSkip: boolean;
		order: number;
	}

	interface WizardConfig {
		name: string;
		description: string;
		steps: WizardStepConfig[];
		settings: {
			showProgress: boolean;
			allowClickNavigation: boolean;
			saveAsDraft: boolean;
		};
	}

	interface Props {
		config: WizardConfig;
		moduleFields: Field[];
		onClose: () => void;
	}

	let { config, moduleFields, onClose }: Props = $props();

	const wizard = createWizardStore(
		config.steps.map((s) => ({
			id: s.id,
			title: s.title,
			description: s.description,
			canSkip: s.canSkip
		})),
		{}
	);

	let formData = $state<Record<string, any>>({});

	$effect(() => {
		wizard.updateFormData(formData);
	});

	function getStepFields(stepConfig: WizardStepConfig) {
		return stepConfig.fields
			.map((fieldId) => moduleFields.find((f) => f.id?.toString() === fieldId))
			.filter((f): f is Field => f !== undefined);
	}

	function validateFormStep(stepConfig: WizardStepConfig) {
		const fields = getStepFields(stepConfig);
		// Check if all required fields have values
		return fields.every((field) => !field.is_required || formData[field.api_name]);
	}

	function handleSubmit() {
		console.log('Preview form submitted:', formData);
		onClose();
	}
</script>

<Dialog.Root open={true} onOpenChange={onClose}>
	<Dialog.Content class="max-h-[90vh] max-w-4xl overflow-y-auto">
		<Dialog.Header>
			<div class="flex items-center justify-between">
				<div>
					<Dialog.Title>Wizard Preview</Dialog.Title>
					<Dialog.Description>Preview how your wizard will appear to users</Dialog.Description>
				</div>
				<Button variant="ghost" size="sm" onclick={onClose}>
					<X class="h-4 w-4" />
				</Button>
			</div>
		</Dialog.Header>

		<div class="py-4">
			<Wizard
				{wizard}
				title={config.name}
				description={config.description}
				showProgress={config.settings.showProgress}
				allowClickNavigation={config.settings.allowClickNavigation}
				onSubmit={handleSubmit}
				onCancel={onClose}
			>
				{#each config.steps as stepConfig}
					<WizardStep
						{wizard}
						stepId={stepConfig.id}
						title={stepConfig.title}
						description={stepConfig.description}
						onValidate={() => validateFormStep(stepConfig)}
					>
						{#if stepConfig.type === 'form'}
							<!-- Form Step -->
							<div class="space-y-4">
								{#each getStepFields(stepConfig) as field}
									<div class="space-y-2">
										<Label for={field.api_name}>
											{field.label}
											{#if field.is_required}
												<span class="text-destructive">*</span>
											{/if}
										</Label>

										{#if field.type === 'text' || field.type === 'email' || field.type === 'phone'}
											<Input
												id={field.api_name}
												type={field.type === 'email'
													? 'email'
													: field.type === 'phone'
														? 'tel'
														: 'text'}
												bind:value={formData[field.api_name]}
												placeholder={field.help_text || ''}
												required={field.is_required}
											/>
										{:else if field.type === 'number' || field.type === 'currency' || field.type === 'percent' || field.type === 'decimal'}
											<Input
												id={field.api_name}
												type="number"
												bind:value={formData[field.api_name]}
												placeholder={field.help_text || ''}
												required={field.is_required}
											/>
										{:else if field.type === 'textarea'}
											<textarea
												id={field.api_name}
												bind:value={formData[field.api_name]}
												placeholder={field.help_text || ''}
												required={field.is_required}
												rows={3}
												class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
											></textarea>
										{:else if field.type === 'select' || field.type === 'radio'}
											<select
												id={field.api_name}
												bind:value={formData[field.api_name]}
												required={field.is_required}
												class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
											>
												<option value="">Select an option</option>
												{#if field.options}
													{#each field.options as option}
														<option value={option.value}>{option.label}</option>
													{/each}
												{/if}
											</select>
										{:else if field.type === 'checkbox'}
											<div class="flex items-center gap-2">
												<input
													id={field.api_name}
													type="checkbox"
													bind:checked={formData[field.api_name]}
													class="h-4 w-4"
												/>
												<Label for={field.api_name} class="cursor-pointer font-normal">
													{field.help_text || field.label}
												</Label>
											</div>
										{:else if field.type === 'date'}
											<Input
												id={field.api_name}
												type="date"
												bind:value={formData[field.api_name]}
												required={field.is_required}
											/>
										{:else}
											<Input
												id={field.api_name}
												bind:value={formData[field.api_name]}
												placeholder={field.help_text || ''}
												required={field.is_required}
											/>
										{/if}

										{#if field.help_text && field.type !== 'checkbox'}
											<p class="text-xs text-muted-foreground">{field.help_text}</p>
										{/if}
									</div>
								{/each}
							</div>
						{:else if stepConfig.type === 'review'}
							<!-- Review Step -->
							<div class="space-y-4">
								<div class="mb-4 text-sm text-muted-foreground">
									Please review your information before submitting
								</div>

								{#each config.steps.filter((s) => s.type === 'form') as formStep}
									<div class="rounded-lg border p-4">
										<h3 class="mb-3 font-semibold">{formStep.title}</h3>
										<dl class="space-y-2 text-sm">
											{#each getStepFields(formStep) as field}
												{#if formData[field.api_name]}
													<div class="flex justify-between">
														<dt class="text-muted-foreground">{field.label}:</dt>
														<dd class="font-medium">
															{#if field.type === 'checkbox'}
																{formData[field.api_name] ? 'Yes' : 'No'}
															{:else if field.type === 'select' || field.type === 'radio'}
																{field.options?.find(
																	(o: FieldOption) => o.value === formData[field.api_name]
																)?.label || formData[field.api_name]}
															{:else}
																{formData[field.api_name]}
															{/if}
														</dd>
													</div>
												{/if}
											{/each}
										</dl>
									</div>
								{/each}
							</div>
						{:else if stepConfig.type === 'confirmation'}
							<!-- Confirmation Step -->
							<div class="py-8 text-center">
								<div
									class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 p-4"
								>
									<svg
										class="h-8 w-8 text-green-600"
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
								<h3 class="mb-2 text-xl font-semibold">Thank You!</h3>
								<p class="text-muted-foreground">
									Your information has been submitted successfully.
								</p>
							</div>
						{/if}
					</WizardStep>
				{/each}
			</Wizard>
		</div>
	</Dialog.Content>
</Dialog.Root>
