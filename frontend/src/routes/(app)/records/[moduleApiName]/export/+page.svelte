<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { ArrowLeft } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { ExportBuilder } from '$lib/components/export-builder';
	import { modulesApi, type Module, type Field } from '$lib/api/modules';
	import { recordsApi } from '$lib/api/records';
	import type { Export } from '$lib/api/exports';

	const moduleApiName = $page.params.moduleApiName!;

	let module = $state<Module | null>(null);
	let fields = $state<Field[]>([]);
	let recordCount = $state(0);
	let isLoading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		try {
			module = await modulesApi.getByApiName(moduleApiName);
			// Extract fields from module blocks
			fields = module.blocks?.flatMap((block) => block.fields) ?? module.fields ?? [];

			// Get record count
			const records = await recordsApi.getAll(moduleApiName, { per_page: 1 });
			recordCount = records.meta.total;
		} catch (e) {
			console.error('Failed to load module:', e);
			error = 'Failed to load module data';
		} finally {
			isLoading = false;
		}
	});

	function handleExportStarted(exportData: Export) {
		goto(`/records/${moduleApiName}?export=started&exportId=${exportData.id}`);
	}

	function handleCancel() {
		goto(`/records/${moduleApiName}`);
	}
</script>

<svelte:head>
	<title>Export {module?.name ?? 'Records'} | VRTX</title>
</svelte:head>

<div class="container max-w-4xl py-8">
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
		<ExportBuilder
			{moduleApiName}
			moduleName={module.name}
			moduleFields={fields}
			{recordCount}
			onExportStarted={handleExportStarted}
			onCancel={handleCancel}
		/>
	{/if}
</div>
