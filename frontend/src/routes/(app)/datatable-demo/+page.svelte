<script lang="ts">
	import DataTable from '$lib/components/datatable/DataTable.svelte';
	import type { ColumnDef } from '$lib/components/datatable/types';
	import { Button } from '$lib/components/ui/button';
	import { ArrowLeft, RefreshCw, Table2 } from 'lucide-svelte';
	import { goto } from '$app/navigation';

	// Demo column definitions showing different field types
	const columns: ColumnDef[] = [
		{
			id: 'id',
			header: 'ID',
			accessorKey: 'id',
			type: 'number',
			sortable: true,
			filterable: true,
			width: 80
		},
		{
			id: 'name',
			header: 'Name',
			accessorKey: 'data.name',
			type: 'text',
			sortable: true,
			filterable: true,
			searchable: true
		},
		{
			id: 'email',
			header: 'Email',
			accessorKey: 'data.email',
			type: 'email',
			sortable: true,
			filterable: true,
			searchable: true
		},
		{
			id: 'status',
			header: 'Status',
			accessorKey: 'data.status',
			type: 'select',
			sortable: true,
			filterable: true,
			options: [
				{ label: 'Active', value: 'active' },
				{ label: 'Inactive', value: 'inactive' },
				{ label: 'Pending', value: 'pending' }
			],
			filterOptions: [
				{ label: 'Active', value: 'active' },
				{ label: 'Inactive', value: 'inactive' },
				{ label: 'Pending', value: 'pending' }
			]
		},
		{
			id: 'amount',
			header: 'Amount',
			accessorKey: 'data.amount',
			type: 'currency',
			sortable: true,
			filterable: true
		},
		{
			id: 'created_at',
			header: 'Created',
			accessorKey: 'created_at',
			type: 'date',
			sortable: true,
			filterable: true
		}
	];

	// Note: This demo page shows the DataTable component structure
	// In a real implementation, you would connect it to a moduleApiName
	// to fetch data from your API
</script>

<div class="container mx-auto py-8">
	<div class="mb-8">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-3xl font-bold">DataTable Demo</h1>
					<p class="mt-1 text-muted-foreground">
						Demonstrates the DataTable component with various features
					</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Table2 class="h-8 w-8 text-primary" />
			</div>
		</div>
	</div>

	<!-- Feature Overview -->
	<div class="mb-8 grid gap-4 md:grid-cols-3">
		<div class="rounded-lg border bg-card p-4">
			<h3 class="font-semibold">Filtering & Search</h3>
			<p class="mt-1 text-sm text-muted-foreground">
				Advanced filtering with multiple operators, quick filters, and global search
			</p>
		</div>
		<div class="rounded-lg border bg-card p-4">
			<h3 class="font-semibold">Sorting & Pagination</h3>
			<p class="mt-1 text-sm text-muted-foreground">
				Multi-column sorting, customizable page sizes, and server-side pagination
			</p>
		</div>
		<div class="rounded-lg border bg-card p-4">
			<h3 class="font-semibold">Bulk Actions</h3>
			<p class="mt-1 text-sm text-muted-foreground">
				Row selection, mass updates, bulk delete, and export functionality
			</p>
		</div>
	</div>

	<!-- Info Banner -->
	<div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
		<h3 class="font-semibold text-blue-800 dark:text-blue-200">Demo Mode</h3>
		<p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
			To see a fully functional DataTable, navigate to any module's records page (e.g., /records/contacts).
			This demo shows the component structure and available column types.
		</p>
	</div>

	<!-- Column Types Reference -->
	<div class="rounded-lg border bg-card p-6">
		<h2 class="mb-4 text-xl font-semibold">Available Column Types</h2>
		<div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
			{#each ['text', 'number', 'email', 'phone', 'url', 'date', 'datetime', 'boolean', 'select', 'multiselect', 'currency', 'percent', 'textarea'] as type}
				<div class="rounded border bg-muted/30 px-3 py-2 text-sm">
					<code class="font-mono">{type}</code>
				</div>
			{/each}
		</div>
	</div>

	<!-- Usage Example -->
	<div class="mt-6 rounded-lg border bg-card p-6">
		<h2 class="mb-4 text-xl font-semibold">Usage Example</h2>
		<pre class="overflow-x-auto rounded bg-muted p-4 text-sm"><code>{`<DataTable
  columns={columns}
  moduleApiName="contacts"
  enableSelection={true}
  enableFilters={true}
  enableSearch={true}
  enableSorting={true}
  enablePagination={true}
  enableViews={true}
  enableExport={true}
  enableBulkActions={true}
/>`}</code></pre>
	</div>
</div>
