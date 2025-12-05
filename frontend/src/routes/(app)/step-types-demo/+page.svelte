<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, Layers } from 'lucide-svelte';
	import { goto } from '$app/navigation';

	// Step types that are available
	const stepTypes = [
		{
			name: 'Form Step',
			description: 'Standard form fields for data collection',
			example: 'Personal info, contact details, preferences',
			component: 'WizardStep'
		},
		{
			name: 'Review Step',
			description: 'Summary of all collected data before submission',
			example: 'Order summary, confirmation page',
			component: 'ReviewStep'
		},
		{
			name: 'File Upload Step',
			description: 'Document or image upload functionality',
			example: 'Profile photo, documents, attachments',
			component: 'FileUploadStep'
		},
		{
			name: 'Payment Step',
			description: 'Payment information collection',
			example: 'Credit card, billing address',
			component: 'PaymentStep'
		},
		{
			name: 'Terms Acceptance Step',
			description: 'Terms of service or legal agreements',
			example: 'ToS acceptance, GDPR consent',
			component: 'TermsAcceptanceStep'
		},
		{
			name: 'Confirmation Step',
			description: 'Success message after wizard completion',
			example: 'Thank you page, next steps',
			component: 'ConfirmationStep'
		}
	];
</script>

<div class="container mx-auto py-8">
	<div class="mb-8">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<div class="flex items-center gap-2">
					<Layers class="h-6 w-6 text-primary" />
					<h1 class="text-3xl font-bold">Step Types Demo</h1>
				</div>
				<p class="mt-1 text-muted-foreground">
					Overview of available wizard step types
				</p>
			</div>
		</div>
	</div>

	<!-- Step Types Grid -->
	<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
		{#each stepTypes as stepType}
			<div class="rounded-lg border bg-card p-6 transition-shadow hover:shadow-md">
				<h3 class="mb-2 text-lg font-semibold">{stepType.name}</h3>
				<p class="mb-3 text-sm text-muted-foreground">{stepType.description}</p>
				<div class="space-y-2">
					<div class="flex items-center gap-2">
						<span class="text-xs font-medium text-muted-foreground">Example:</span>
						<span class="text-xs">{stepType.example}</span>
					</div>
					<div class="flex items-center gap-2">
						<span class="text-xs font-medium text-muted-foreground">Component:</span>
						<code class="rounded bg-muted px-2 py-0.5 text-xs">{stepType.component}</code>
					</div>
				</div>
			</div>
		{/each}
	</div>

	<!-- Usage Example -->
	<div class="mt-8 rounded-lg border bg-card p-6">
		<h2 class="mb-4 text-xl font-semibold">Usage Example</h2>
		<pre class="overflow-x-auto rounded bg-muted p-4 text-sm"><code>{`import Wizard from '$lib/components/wizard/Wizard.svelte';
import WizardStep from '$lib/components/wizard/WizardStep.svelte';
import ReviewStep from '$lib/components/wizard/step-types/ReviewStep.svelte';
import { createWizardStore } from '$lib/hooks/useWizard.svelte';

const steps = [
  { id: 'info', title: 'Information', type: 'form' },
  { id: 'upload', title: 'Documents', type: 'file-upload' },
  { id: 'review', title: 'Review', type: 'review' }
];

const wizard = createWizardStore(steps);

<Wizard {wizard}>
  {#if wizard.currentStep?.id === 'info'}
    <WizardStep step={wizard.currentStep}>
      <!-- Form fields -->
    </WizardStep>
  {/if}
  <!-- ... other steps -->
</Wizard>`}</code></pre>
	</div>

	<!-- Navigation to Other Demos -->
	<div class="mt-8">
		<h2 class="mb-4 text-xl font-semibold">Related Demos</h2>
		<div class="flex flex-wrap gap-3">
			<Button variant="outline" onclick={() => goto('/wizard-demo')}>
				Basic Wizard Demo
			</Button>
			<Button variant="outline" onclick={() => goto('/conditional-wizard-demo')}>
				Conditional Wizard Demo
			</Button>
			<Button variant="outline" onclick={() => goto('/wizard-builder-demo')}>
				Wizard Builder Demo
			</Button>
		</div>
	</div>
</div>
