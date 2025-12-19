<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, Blocks } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import WizardBuilder from '$lib/components/wizard-builder/WizardBuilder.svelte';
	import { toast } from 'svelte-sonner';

	// Initial wizard configuration for the builder demo
	const initialConfig = {
		name: 'Demo Wizard',
		description: 'A sample wizard created with the builder',
		steps: [
			{
				id: 'step-1',
				title: 'Getting Started',
				description: 'Welcome to the wizard',
				type: 'form' as const,
				fields: [] as string[],
				canSkip: false,
				order: 0
			},
			{
				id: 'step-2',
				title: 'Details',
				description: 'Provide more information',
				type: 'form' as const,
				fields: [] as string[],
				canSkip: false,
				order: 1
			},
			{
				id: 'step-3',
				title: 'Review',
				description: 'Review and submit',
				type: 'review' as const,
				fields: [] as string[],
				canSkip: false,
				order: 2
			}
		],
		settings: {
			showProgress: true,
			allowClickNavigation: false,
			saveAsDraft: true
		}
	};

	function handleSave(config: unknown) {
		console.log('Wizard configuration saved:', config);
		toast.success('Wizard configuration saved!');
	}

	function handlePreview(config: unknown) {
		console.log('Preview wizard:', config);
		toast.info('Preview mode - configuration logged to console');
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
					<Blocks class="h-6 w-6 text-primary" />
					<h1 class="text-3xl font-bold">Wizard Builder Demo</h1>
				</div>
				<p class="mt-1 text-muted-foreground">
					Visual wizard builder for creating multi-step forms
				</p>
			</div>
		</div>
	</div>

	<WizardBuilder
		{initialConfig}
		onSave={handleSave}
		onPreview={handlePreview}
	/>
</div>
