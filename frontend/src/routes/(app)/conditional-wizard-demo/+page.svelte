<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Select from '$lib/components/ui/select';
	import { ArrowLeft, GitBranch } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import Wizard from '$lib/components/wizard/Wizard.svelte';
	import WizardStep from '$lib/components/wizard/WizardStep.svelte';
	import { createWizardStore, type WizardStep as WizardStepType } from '$lib/hooks/useWizard.svelte';
	import { toast } from 'svelte-sonner';

	// Define wizard steps with conditional logic
	const steps: WizardStepType[] = [
		{
			id: 'account-type',
			title: 'Account Type',
			description: 'Select your account type'
		},
		{
			id: 'personal-info',
			title: 'Personal Info',
			description: 'Your personal details',
			conditionalLogic: {
				field: 'accountType',
				operator: 'equals',
				value: 'personal'
			}
		},
		{
			id: 'business-info',
			title: 'Business Info',
			description: 'Your business details',
			conditionalLogic: {
				field: 'accountType',
				operator: 'equals',
				value: 'business'
			}
		},
		{
			id: 'review',
			title: 'Review',
			description: 'Confirm your information'
		}
	];

	// Create wizard store
	const wizard = createWizardStore(steps, {}, { wizardType: 'conditional-demo' });

	// Form data state
	let accountType = $state<string | undefined>(undefined);
	let fullName = $state('');
	let businessName = $state('');
	let taxId = $state('');

	// Validation
	$effect(() => {
		if (wizard.currentStep?.id === 'account-type') {
			wizard.setStepValid('account-type', accountType !== undefined);
		}
	});

	$effect(() => {
		if (wizard.currentStep?.id === 'personal-info') {
			wizard.setStepValid('personal-info', fullName.length > 0);
		}
	});

	$effect(() => {
		if (wizard.currentStep?.id === 'business-info') {
			wizard.setStepValid('business-info', businessName.length > 0);
		}
	});

	$effect(() => {
		if (wizard.currentStep?.id === 'review') {
			wizard.setStepValid('review', true);
		}
	});

	// Update form data
	$effect(() => {
		wizard.updateFormData({
			accountType,
			fullName,
			businessName,
			taxId
		});
	});

	async function handleSubmit() {
		toast.success('Registration complete!', {
			description: accountType === 'business' ? `Welcome, ${businessName}!` : `Welcome, ${fullName}!`
		});
	}

	function handleCancel() {
		goto('/dashboard');
	}
</script>

<div class="container mx-auto py-8">
	<div class="mb-8">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<div class="flex items-center gap-2">
					<GitBranch class="h-6 w-6 text-primary" />
					<h1 class="text-3xl font-bold">Conditional Wizard Demo</h1>
				</div>
				<p class="mt-1 text-muted-foreground">
					Wizard with conditional step visibility based on selections
				</p>
			</div>
		</div>
	</div>

	<!-- Explanation -->
	<div class="mx-auto mb-6 max-w-2xl rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
		<h3 class="font-semibold text-blue-800 dark:text-blue-200">How it works</h3>
		<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
			Select "Personal" to see personal info fields, or "Business" to see business-specific fields.
			The wizard dynamically shows/hides steps based on your selection.
		</p>
	</div>

	<Wizard
		{wizard}
		onSubmit={handleSubmit}
		onCancel={handleCancel}
		showProgress={true}
		allowClickNavigation={false}
		title="Registration"
		description="Complete the registration process"
	>
		<!-- Step 1: Account Type -->
		<WizardStep {wizard} stepId="account-type">
			<div class="space-y-4">
				<div class="space-y-2">
					<Label>Account Type *</Label>
					<Select.Root type="single" bind:value={accountType}>
						<Select.Trigger class="w-full">
							<span>
								{accountType === 'personal'
									? 'Personal Account'
									: accountType === 'business'
										? 'Business Account'
										: 'Select account type'}
							</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="personal">Personal Account</Select.Item>
							<Select.Item value="business">Business Account</Select.Item>
						</Select.Content>
					</Select.Root>
					<p class="text-sm text-muted-foreground">
						Choose Personal for individual use, or Business for company accounts
					</p>
				</div>
			</div>
		</WizardStep>

		<!-- Step 2: Personal Info (conditional) -->
		<WizardStep {wizard} stepId="personal-info">
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="fullName">Full Name *</Label>
					<Input
						id="fullName"
						bind:value={fullName}
						placeholder="Enter your full name"
					/>
				</div>
			</div>
		</WizardStep>

		<!-- Step 3: Business Info (conditional) -->
		<WizardStep {wizard} stepId="business-info">
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="businessName">Business Name *</Label>
					<Input
						id="businessName"
						bind:value={businessName}
						placeholder="Enter your business name"
					/>
				</div>
				<div class="space-y-2">
					<Label for="taxId">Tax ID (optional)</Label>
					<Input
						id="taxId"
						bind:value={taxId}
						placeholder="XX-XXXXXXX"
					/>
				</div>
			</div>
		</WizardStep>

		<!-- Step 4: Review -->
		<WizardStep {wizard} stepId="review">
			<div class="space-y-6">
				<div class="rounded-lg border bg-muted/30 p-4">
					<h3 class="mb-4 font-semibold">Review Your Information</h3>
					<dl class="space-y-3">
						<div class="flex justify-between border-b pb-2">
							<dt class="text-muted-foreground">Account Type</dt>
							<dd class="font-medium capitalize">{accountType}</dd>
						</div>
						{#if accountType === 'personal'}
							<div class="flex justify-between border-b pb-2">
								<dt class="text-muted-foreground">Name</dt>
								<dd class="font-medium">{fullName}</dd>
							</div>
						{:else if accountType === 'business'}
							<div class="flex justify-between border-b pb-2">
								<dt class="text-muted-foreground">Business Name</dt>
								<dd class="font-medium">{businessName}</dd>
							</div>
							{#if taxId}
								<div class="flex justify-between border-b pb-2">
									<dt class="text-muted-foreground">Tax ID</dt>
									<dd class="font-medium">{taxId}</dd>
								</div>
							{/if}
						{/if}
					</dl>
				</div>
			</div>
		</WizardStep>
	</Wizard>
</div>
