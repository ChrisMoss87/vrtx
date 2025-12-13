<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, Plus, Upload, Download, Kanban, Table2 } from 'lucide-svelte';
	import { goto } from '$app/navigation';
	import DataTable from '$lib/components/datatable/DataTable.svelte';
	import { ModuleKanbanView } from '$lib/components/kanban';
	import type { ColumnDef } from '$lib/components/datatable/types';

	const moduleApiName = $derived($page.params.moduleApiName as string);

	let module = $state<Module | null>(null);
	let columns = $state<ColumnDef[]>([]);
	let viewMode = $state<'table' | 'kanban'>('table');
	let loading = $state(true);
	let error = $state<string | null>(null);

	// Check if module has any select/radio fields for kanban
	const hasKanbanFields = $derived(() => {
		if (!module?.blocks) return false;
		return module.blocks.some((block) =>
			block.fields?.some((field) => field.type === 'select' || field.type === 'radio')
		);
	});

	onMount(async () => {
		await loadModule();
	});

	async function loadModule() {
		loading = true;
		error = null;

		try {
			module = await modulesApi.getByApiName(moduleApiName);

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
							filterOptions: fieldOptions, // Used by Quick Filter Bar
							meta: {
								is_mass_updatable: field.is_mass_updatable ?? true,
								isFormula: field.type === 'formula'
							}
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
			// Basic text types
			text: 'text',
			email: 'email',
			phone: 'phone',
			url: 'url',
			textarea: 'textarea',
			rich_text: 'textarea',
			// Numeric types
			number: 'number',
			decimal: 'decimal',
			currency: 'currency',
			percent: 'percent',
			// Date/time types
			date: 'date',
			datetime: 'datetime',
			time: 'time',
			// Choice types
			select: 'select',
			multiselect: 'multiselect',
			radio: 'radio',
			// Boolean types
			checkbox: 'checkbox',
			toggle: 'toggle',
			// Relationship types
			lookup: 'lookup',
			// Calculated types
			formula: 'text',
			auto_number: 'text'
		};

		return typeMap[fieldType] || 'text';
	}

	function createRecord() {
		goto(`/records/${moduleApiName}/create`);
	}

	function handleRowClick(row: { id: number }) {
		goto(`/records/${moduleApiName}/${row.id}`);
	}

	function handleKanbanRecordClick(record: { id: number }) {
		goto(`/records/${moduleApiName}/${record.id}`);
	}

	function toggleViewMode() {
		viewMode = viewMode === 'table' ? 'kanban' : 'table';
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
			<div class="flex items-center gap-2">
				{#if hasKanbanFields()}
					<Button
						variant={viewMode === 'kanban' ? 'secondary' : 'outline'}
						onclick={toggleViewMode}
					>
						{#if viewMode === 'table'}
							<Kanban class="mr-2 h-4 w-4" />
							Kanban View
						{:else}
							<Table2 class="mr-2 h-4 w-4" />
							Table View
						{/if}
					</Button>
				{/if}
				<Button variant="outline" onclick={() => goto(`/records/${moduleApiName}/import`)}>
					<Upload class="mr-2 h-4 w-4" />
					Import
				</Button>
				<Button variant="outline" onclick={() => goto(`/records/${moduleApiName}/export`)}>
					<Download class="mr-2 h-4 w-4" />
					Export
				</Button>
				<Button onclick={createRecord} data-testid="create-record">
					<Plus class="mr-2 h-4 w-4" />
					New {module.singular_name}
				</Button>
			</div>
		</div>

		{#if viewMode === 'table'}
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
				onRowClick={handleRowClick}
			/>
		{:else}
			<div class="h-[calc(100vh-16rem)]">
				<ModuleKanbanView
					{moduleApiName}
					onRecordClick={handleKanbanRecordClick}
				/>
			</div>
		{/if}
	{/if}
</div>
