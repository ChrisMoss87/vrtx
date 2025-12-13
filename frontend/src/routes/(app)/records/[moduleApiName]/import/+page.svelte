<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { ArrowLeft } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { ImportWizard } from '$lib/components/import-wizard';
	import { modulesApi, type Module, type Field } from '$lib/api/modules';
	import type { Import } from '$lib/api/imports';

	const moduleApiName = $page.params.moduleApiName!;

	let module = $state<Module | null>(null);
	let fields = $state<Field[]>([]);
	let isLoading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		try {
			module = await modulesApi.getByApiName(moduleApiName);
			// Extract fields from module blocks
			fields = module.blocks?.flatMap((block) => block.fields) ?? module.fields ?? [];
		} catch (e) {
			console.error('Failed to load module:', e);
			error = 'Failed to load module data';
		} finally {
			isLoading = false;
		}
	});

	function handleComplete(importData: Import) {
		goto(`/records/${moduleApiName}?import=success&imported=${importData.successful_rows}`);
	}

	function handleCancel() {
		goto(`/records/${moduleApiName}`);
	}
</script>

<svelte:head>
	<title>Import {module?.name ?? 'Records'} | VRTX</title>
</svelte:head>

<div class="container max-w-5xl py-8">
	<div class="mb-6">
		<Button variant="ghost" href="/records/{moduleApiName}" class="mb-4">
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to {module?.name ?? 'Records'}
		</Button>
	</div>

	{#if isLoading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if error}
		<div class="text-center py-12">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" class="mt-4" onclick={() => goto(`/records/${moduleApiName}`)}>
				Go Back
			</Button>
		</div>
	{:else if module}
		<ImportWizard
			{moduleApiName}
			moduleName={module.name}
			moduleFields={fields}
			onComplete={handleComplete}
			onCancel={handleCancel}
		/>
	{/if}
</div>
