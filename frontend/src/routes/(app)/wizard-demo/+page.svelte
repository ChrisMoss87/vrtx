<script lang="ts">
	import { onMount, untrack } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import { ArrowLeft, Wand2 } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import Wizard from '$lib/components/wizard/Wizard.svelte';
	import WizardStep from '$lib/components/wizard/WizardStep.svelte';
	import { createWizardStore, type WizardStep as WizardStepType } from '$lib/hooks/useWizard.svelte';
	import { toast } from 'svelte-sonner';

	// Define wizard steps
	const steps: WizardStepType[] = [
		{
			id: 'personal',
			title: 'Personal Info',
			description: 'Basic information about yourself'
		},
		{
			id: 'contact',
			title: 'Contact Details',
			description: 'How can we reach you?'
		},
		{
			id: 'preferences',
			title: 'Preferences',
			description: 'Customize your experience'
		},
		{
			id: 'review',
			title: 'Review',
			description: 'Confirm your information'
		}
	];

	// Create wizard store
	const wizard = createWizardStore(steps, {}, { wizardType: 'demo-wizard' });

	// Form data state
	let firstName = $state('');
	let lastName = $state('');
	let email = $state('');
	let phone = $state('');
	let notifications = $state<string | undefined>(undefined);
	let bio = $state('');

	// Validation for each step - validate ALL steps whenever their values change
	// This ensures canGoNext is correctly computed
	$effect(() => {
		// Personal step validation
		wizard.setStepValid('personal', firstName.length > 0 && lastName.length > 0);
	});

	$effect(() => {
		// Contact step validation
		const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		wizard.setStepValid('contact', emailValid);
	});

	$effect(() => {
		// Preferences step validation
		wizard.setStepValid('preferences', notifications !== undefined);
	});

	$effect(() => {
		// Review step is always valid
		wizard.setStepValid('review', true);
	});

	// Update form data in wizard store - use untrack to prevent infinite loops
	// The updateFormData call triggers scheduleDraftSave which modifies state
	$effect(() => {
		// Read the values we want to track
		const data = {
			firstName,
			lastName,
			email,
			phone,
			notifications,
			bio
		};
		// Use untrack to prevent the effect from re-running when updateFormData modifies state
		untrack(() => {
			wizard.updateFormData(data);
		});
	});

	async function handleSubmit() {
		toast.success('Form submitted successfully!', {
			description: `Thank you, ${firstName}!`
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
					<Wand2 class="h-6 w-6 text-primary" />
					<h1 class="text-3xl font-bold">Wizard Demo</h1>
				</div>
				<p class="mt-1 text-muted-foreground">
					Multi-step form wizard with validation and progress tracking
				</p>
			</div>
		</div>
	</div>

	<Wizard
		{wizard}
		onSubmit={handleSubmit}
		onCancel={handleCancel}
		showProgress={true}
		allowClickNavigation={true}
		title="Registration Wizard"
		description="Complete all steps to finish your registration"
	>
		<!-- Step 1: Personal Info -->
		<WizardStep {wizard} stepId="personal">
			<div class="space-y-4">
				<div class="grid gap-4 md:grid-cols-2">
					<div class="space-y-2">
						<Label for="firstName">First Name *</Label>
						<Input
							id="firstName"
							bind:value={firstName}
							placeholder="Enter your first name"
						/>
					</div>
					<div class="space-y-2">
						<Label for="lastName">Last Name *</Label>
						<Input
							id="lastName"
							bind:value={lastName}
							placeholder="Enter your last name"
						/>
					</div>
				</div>
				<div class="space-y-2">
					<Label for="bio">Bio (optional)</Label>
					<Textarea
						id="bio"
						bind:value={bio}
						placeholder="Tell us about yourself"
						rows={3}
					/>
				</div>
			</div>
		</WizardStep>

		<!-- Step 2: Contact Details -->
		<WizardStep {wizard} stepId="contact">
			<div class="space-y-4">
				<div class="space-y-2">
					<Label for="email">Email Address *</Label>
					<Input
						id="email"
						type="email"
						bind:value={email}
						placeholder="you@example.com"
					/>
					{#if email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)}
						<p class="text-sm text-destructive">Please enter a valid email address</p>
					{/if}
				</div>
				<div class="space-y-2">
					<Label for="phone">Phone (optional)</Label>
					<Input
						id="phone"
						type="tel"
						bind:value={phone}
						placeholder="+1 (555) 000-0000"
					/>
				</div>
			</div>
		</WizardStep>

		<!-- Step 3: Preferences -->
		<WizardStep {wizard} stepId="preferences">
			<div class="space-y-4">
				<div class="space-y-2">
					<Label>Notification Preferences *</Label>
					<Select.Root type="single" bind:value={notifications}>
						<Select.Trigger>
							<span>
								{notifications === 'all'
									? 'All Notifications'
									: notifications === 'important'
										? 'Important Only'
										: notifications === 'none'
											? 'No Notifications'
											: 'Select preference'}
							</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="all">All Notifications</Select.Item>
							<Select.Item value="important">Important Only</Select.Item>
							<Select.Item value="none">No Notifications</Select.Item>
						</Select.Content>
					</Select.Root>
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
							<dt class="text-muted-foreground">Name</dt>
							<dd class="font-medium">{firstName} {lastName}</dd>
						</div>
						<div class="flex justify-between border-b pb-2">
							<dt class="text-muted-foreground">Email</dt>
							<dd class="font-medium">{email}</dd>
						</div>
						{#if phone}
							<div class="flex justify-between border-b pb-2">
								<dt class="text-muted-foreground">Phone</dt>
								<dd class="font-medium">{phone}</dd>
							</div>
						{/if}
						<div class="flex justify-between border-b pb-2">
							<dt class="text-muted-foreground">Notifications</dt>
							<dd class="font-medium capitalize">{notifications || 'Not set'}</dd>
						</div>
						{#if bio}
							<div class="pt-2">
								<dt class="mb-1 text-muted-foreground">Bio</dt>
								<dd class="font-medium">{bio}</dd>
							</div>
						{/if}
					</dl>
				</div>
			</div>
		</WizardStep>
	</Wizard>
</div>
