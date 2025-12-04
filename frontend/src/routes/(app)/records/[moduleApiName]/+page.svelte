<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { modulesApi, type Module, type Field } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, Plus } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import DataTable from '$lib/components/datatable/DataTable.svelte';
	import type { ColumnDef } from '$lib/components/datatable/types';

	const moduleApiName = $derived($page.params.moduleApiName as string);

	let module = $state<Module | null>(null);
	let columns = $state<ColumnDef[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		await loadModule();
	});

	async function loadModule() {
		loading = true;
		error = null;

		try {
			// Load module definition
			module = await modulesApi.getByApiName(moduleApiName);
			console.log(module);
			// Build columns from module fields
			if (module && module.blocks) {
				columns = buildColumnsFromModule(module);
			}
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load module';
		} finally {
			loading = false;
		}
	}

	function buildColumnsFromModule(mod: Module): ColumnDef[] {
		const cols: ColumnDef[] = [
			{
				id: 'id',
				header: 'ID',
				accessorKey: 'id',
				type: 'number',
				sortable: true,
				filterable: true
			}
		];

		// Add fields from all blocks
		if (mod.blocks) {
			for (const block of mod.blocks) {
				if (block.fields) {
					for (const field of block.fields) {
						const fieldOptions = field.options?.map((opt) => ({
							label: opt.label,
							value: opt.value
						}));
						cols.push({
							id: field.api_name,
							header: field.label,
							accessorKey: `data.${field.api_name}`,
							type: mapFieldTypeToColumnType(field.type),
							sortable: field.is_sortable,
							filterable: field.is_filterable,
							searchable: field.is_searchable,
							options: fieldOptions,
							filterOptions: fieldOptions // Used by Quick Filter Bar
						});
					}
				}
			}
		}

		// Add timestamps
		cols.push(
			{
				id: 'created_at',
				header: 'Created',
				accessorKey: 'created_at',
				type: 'date',
				sortable: true,
				filterable: true
			},
			{
				id: 'updated_at',
				header: 'Updated',
				accessorKey: 'updated_at',
				type: 'date',
				sortable: true,
				filterable: true
			}
		);

		return cols;
	}

	function mapFieldTypeToColumnType(fieldType: string): ColumnDef['type'] {
		const typeMap: Record<string, ColumnDef['type']> = {
			text: 'text',
			email: 'text',
			phone: 'text',
			textarea: 'text',
			number: 'number',
			currency: 'number',
			date: 'date',
			datetime: 'date',
			select: 'select',
			multiselect: 'select',
			checkbox: 'boolean',
			radio: 'select'
		};

		return typeMap[fieldType] || 'text';
	}

	function createRecord() {
		goto(`/records/${moduleApiName}/create`);
	}
</script>

<div class="container mx-auto py-8">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-center">
				<div class="mx-auto h-12 w-12 animate-spin rounded-full border-b-2 border-primary"></div>
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		</div>
	{:else if error}
		<div class="rounded-lg border border-destructive p-6">
			<p class="text-destructive" data-testid="error-message">{error}</p>
		</div>
	{:else if module && columns.length > 0}
		<div class="mb-6 flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-3xl font-bold" data-testid="module-title">{module.name}</h1>
					<p class="mt-1 text-muted-foreground">
						{module.description || `Manage ${module.name.toLowerCase()}`}
					</p>
				</div>
			</div>
			<Button onclick={createRecord} data-testid="create-record">
				<Plus class="mr-2 h-4 w-4" />
				New {module.singular_name}
			</Button>
		</div>

		<DataTable
			{columns}
			{moduleApiName}
			enableSelection={true}
			enableFilters={true}
			enableSearch={true}
			enableSorting={true}
			enablePagination={true}
			enableViews={true}
			enableExport={true}
			enableBulkActions={true}
		/>
	{/if}
</div>
