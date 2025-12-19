<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { createWizardStore, type WizardStep } from '$lib/hooks/useWizard.svelte';
	import Wizard from '$lib/components/wizard/Wizard.svelte';
	import WizardDraftIndicator from '$lib/components/wizard/WizardDraftIndicator.svelte';
	import WizardDraftResume from '$lib/components/wizard/WizardDraftResume.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Save, RotateCcw } from 'lucide-svelte';

	// Define wizard steps
	const steps: WizardStep[] = [
		{
			id: 'personal-info',
			title: 'Personal Information',
			description: 'Enter your basic information'
		},
		{
			id: 'contact',
			title: 'Contact Details',
			description: 'How can we reach you?'
		},
		{
			id: 'preferences',
			title: 'Preferences',
			description: 'Set your preferences',
			canSkip: true
		},
		{
			id: 'review',
			title: 'Review',
			description: 'Review your information'
		}
	];

	// Create wizard store with localStorage draft support
	const wizard = createWizardStore(
		steps,
		{},
		{
			wizardType: 'demo_wizard',
			useApiDrafts: false, // Use localStorage for demo
			autoSaveDebounce: 1000
		}
	);

	// Local state
	let showResumeBanner = $state(false);
	let draftInfo = $state<{
		currentStepIndex: number;
		totalSteps: number;
		completionPercentage: number;
		lastSaved: Date;
	} | null>(null);

	// Form fields for each step
	let firstName = $state('');
	let lastName = $state('');
	let email = $state('');
	let phone = $state('');
	let notes = $state('');
	let newsletter = $state(false);

	// Sync form data with wizard
	$effect(() => {
		firstName = wizard.formData.firstName || '';
		lastName = wizard.formData.lastName || '';
		email = wizard.formData.email || '';
		phone = wizard.formData.phone || '';
		notes = wizard.formData.notes || '';
		newsletter = wizard.formData.newsletter || false;
	});

	// Validate current step
	$effect(() => {
		const currentId = wizard.currentStep?.id;

		if (currentId === 'personal-info') {
			wizard.setStepValid('personal-info', firstName.trim() !== '' && lastName.trim() !== '');
		} else if (currentId === 'contact') {
			wizard.setStepValid('contact', email.trim() !== '' && email.includes('@'));
		} else if (currentId === 'preferences') {
			wizard.setStepValid('preferences', true);
		} else if (currentId === 'review') {
			wizard.setStepValid('review', true);
		}
	});

	// Update form data on input
	function updateField(field: string, value: string | boolean) {
		wizard.updateFormData({ [field]: value });
	}

	// Check for existing draft on mount
	onMount(async () => {
		const hasDraft = await wizard.hasDraft();
		if (hasDraft) {
			// Get draft info from localStorage
			const localKey = 'wizard_draft_demo_wizard';
			const draftData = localStorage.getItem(localKey);
			if (draftData) {
				try {
					const draft = JSON.parse(draftData);
					const completedSteps = draft.steps.filter((s: any) => s.isComplete).length;
					draftInfo = {
						currentStepIndex: draft.currentStepIndex,
						totalSteps: draft.steps.length,
						completionPercentage: Math.round((completedSteps / draft.steps.length) * 100),
						lastSaved: new Date(draft.timestamp)
					};
					showResumeBanner = true;
				} catch (e) {
					console.error('Failed to parse draft:', e);
				}
			}
		}
	});

	function handleResume() {
		wizard.loadDraft();
		showResumeBanner = false;
	}

	function handleDiscard() {
		wizard.clearDraft();
		wizard.reset();
		showResumeBanner = false;
		draftInfo = null;
	}

	function handleDismiss() {
		showResumeBanner = false;
	}

	function handleComplete() {
		// wizard.complete() is called automatically by the Wizard component
		console.log('Wizard completed!');
	}

	onDestroy(() => {
		wizard.destroy();
	});
</script>

<div class="container mx-auto max-w-4xl py-8">
	<div class="mb-6">
		<h1 class="text-3xl font-bold">Draft Management Demo</h1>
		<p class="mt-2 text-muted-foreground">
			This demo shows how wizard drafts are automatically saved and can be resumed later. Try
			filling out some fields and refreshing the page!
		</p>
	</div>

	<!-- Resume banner -->
	{#if showResumeBanner && draftInfo}
		<div class="mb-6">
			<WizardDraftResume
				draft={draftInfo}
				onResume={handleResume}
				onDiscard={handleDiscard}
				onDismiss={handleDismiss}
			/>
		</div>
	{/if}

	<!-- Draft indicator -->
	<div class="mb-4 flex items-center justify-between">
		<WizardDraftIndicator
			isSaving={wizard.isSaving}
			lastSaved={wizard.lastSaved}
			saveError={wizard.saveError}
		/>
		<div class="flex items-center gap-2">
			<Button variant="outline" size="sm" onclick={() => wizard.saveDraft()}>
				<Save class="mr-2 h-4 w-4" />
				Save Draft
			</Button>
			<Button
				variant="outline"
				size="sm"
				onclick={() => {
					wizard.clearDraft();
					wizard.reset();
				}}
			>
				<RotateCcw class="mr-2 h-4 w-4" />
				Reset
			</Button>
		</div>
	</div>

	<!-- Wizard -->
	<Wizard {wizard} onSubmit={handleComplete}>
		{#if wizard.currentStep?.id === 'personal-info'}
			<div class="space-y-4">
				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label for="firstName">First Name *</Label>
						<Input
							id="firstName"
							value={firstName}
							oninput={(e) => updateField('firstName', e.currentTarget.value)}
							placeholder="Enter your first name"
						/>
					</div>
					<div class="space-y-2">
						<Label for="lastName">Last Name *</Label>
						<Input
							id="lastName"
							value={lastName}
							oninput={(e) => updateField('lastName', e.currentTarget.value)}
							placeholder="Enter your last name"
						/>
					</div>
				</div>
				<p class="text-sm text-muted-foreground">* Required fields</p>
			</div>
		{:else if wizard.currentStep?.id === 'contact'}
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="email">Email Address *</Label>
					<Input
						id="email"
						type="email"
						value={email}
						oninput={(e) => updateField('email', e.currentTarget.value)}
						placeholder="your@email.com"
					/>
				</div>
				<div class="space-y-2">
					<Label for="phone">Phone Number (optional)</Label>
					<Input
						id="phone"
						type="tel"
						value={phone}
						oninput={(e) => updateField('phone', e.currentTarget.value)}
						placeholder="+1 (555) 000-0000"
					/>
				</div>
			</div>
		{:else if wizard.currentStep?.id === 'preferences'}
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="notes">Additional Notes</Label>
					<Textarea
						id="notes"
						value={notes}
						oninput={(e) => updateField('notes', e.currentTarget.value)}
						placeholder="Any additional information..."
						rows={4}
					/>
				</div>
				<div class="flex items-center gap-2">
					<input
						type="checkbox"
						id="newsletter"
						checked={newsletter}
						onchange={(e) => updateField('newsletter', e.currentTarget.checked)}
						class="h-4 w-4 rounded border-gray-300"
					/>
					<Label for="newsletter" class="font-normal">Subscribe to our newsletter</Label>
				</div>
				<p class="text-sm text-muted-foreground">This step is optional - you can skip it.</p>
			</div>
		{:else if wizard.currentStep?.id === 'review'}
			<div class="space-y-4">
				<h3 class="text-lg font-medium">Review Your Information</h3>
				<div class="space-y-3 rounded-lg border p-4">
					<div class="grid grid-cols-2 gap-2">
						<span class="text-muted-foreground">Name:</span>
						<span class="font-medium">{firstName} {lastName}</span>
					</div>
					<div class="grid grid-cols-2 gap-2">
						<span class="text-muted-foreground">Email:</span>
						<span class="font-medium">{email}</span>
					</div>
					{#if phone}
						<div class="grid grid-cols-2 gap-2">
							<span class="text-muted-foreground">Phone:</span>
							<span class="font-medium">{phone}</span>
						</div>
					{/if}
					{#if notes}
						<div class="grid grid-cols-2 gap-2">
							<span class="text-muted-foreground">Notes:</span>
							<span class="font-medium">{notes}</span>
						</div>
					{/if}
					<div class="grid grid-cols-2 gap-2">
						<span class="text-muted-foreground">Newsletter:</span>
						<span class="font-medium">{newsletter ? 'Yes' : 'No'}</span>
					</div>
				</div>
				<p class="text-sm text-muted-foreground">Click "Complete" to finish the wizard.</p>
			</div>
		{/if}
	</Wizard>

	<!-- Debug info -->
	<div class="mt-8 rounded-lg border p-4">
		<h3 class="mb-2 font-medium">Debug Info</h3>
		<pre class="overflow-auto text-xs text-muted-foreground">
{JSON.stringify(
				{
					currentStep: wizard.currentStep?.id,
					currentStepIndex: wizard.currentStepIndex,
					progress: wizard.progress,
					isComplete: wizard.isComplete,
					formData: wizard.formData,
					lastSaved: wizard.lastSaved?.toISOString()
				},
				null,
				2
			)}
		</pre>
	</div>
</div>
