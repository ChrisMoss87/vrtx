<script lang="ts">
	import { page } from '$app/stores';
	import { onMount } from 'svelte';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { recordsApi, type ModuleRecord } from '$lib/api/records';
	import { Button } from '$lib/components/ui/button';
	import { Card, CardContent } from '$lib/components/ui/card';
	import { ArrowLeft, Plus } from 'lucide-svelte';
	import { goto } from '$app/navigation';

	const moduleApiName = $derived($page.params.moduleApiName);

	let module = $state<Module | null>(null);
	let records = $state<ModuleRecord[]>([]);
	let totalRecords = $state(0);
	let currentPage = $state(1);
	let perPage = $state(15);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		await loadModuleAndRecords();
	});

	async function loadModuleAndRecords() {
		loading = true;
		error = null;

		try {
			// Load module definition
			module = await modulesApi.getByApiName(moduleApiName);

			// Load records
			const response = await recordsApi.getAll(moduleApiName, {
				page: currentPage,
				per_page: perPage
			});

			records = response.records;
			totalRecords = response.meta.total;
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load data';
		} finally {
			loading = false;
		}
	}

	function createRecord() {
		goto(`/records/${moduleApiName}/create`);
	}

	function editRecord(recordId: number) {
		goto(`/records/${moduleApiName}/${recordId}/edit`);
	}

	async function deleteRecord(recordId: number) {
		if (!confirm('Are you sure you want to delete this record?')) {
			return;
		}

		try {
			await recordsApi.delete(moduleApiName, recordId);
			await loadModuleAndRecords();
		} catch (err) {
			alert(err instanceof Error ? err.message : 'Failed to delete record');
		}
	}
</script>

<div class="container mx-auto py-8">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-center">
				<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto"></div>
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		</div>
	{:else if error}
		<Card class="border-destructive">
			<CardContent class="pt-6">
				<p class="text-destructive" data-testid="error-message">{error}</p>
			</CardContent>
		</Card>
	{:else if module}
		<div class="flex items-center justify-between mb-8">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/modules')}>
					<ArrowLeft class="w-4 h-4" />
				</Button>
				<div>
					<h1 class="text-3xl font-bold" data-testid="module-title">{module.name}</h1>
					<p class="text-muted-foreground mt-2">{module.description || `Manage ${module.name.toLowerCase()}`}</p>
				</div>
			</div>
			<Button onclick={createRecord} data-testid="create-record">
				<Plus class="w-4 h-4 mr-2" />
				New {module.singular_name}
			</Button>
		</div>

		{#if records.length === 0}
			<Card>
				<CardContent class="pt-6 text-center py-12">
					<h3 class="text-lg font-semibold mb-2">No records yet</h3>
					<p class="text-muted-foreground mb-4">
						Create your first {module.singular_name.toLowerCase()} to get started
					</p>
					<Button onclick={createRecord}>
						<Plus class="w-4 h-4 mr-2" />
						Create {module.singular_name}
					</Button>
				</CardContent>
			</Card>
		{:else}
			<Card>
				<CardContent class="pt-6">
					<div class="overflow-x-auto">
						<table class="w-full" data-testid="records-table">
							<thead>
								<tr class="border-b">
									<th class="text-left p-2 font-medium">ID</th>
									{#if module.blocks}
										{#each module.blocks as block}
											{#each block.fields as field}
												<th class="text-left p-2 font-medium">{field.label}</th>
											{/each}
										{/each}
									{/if}
									<th class="text-left p-2 font-medium">Actions</th>
								</tr>
							</thead>
							<tbody>
								{#each records as record (record.id)}
									<tr class="border-b hover:bg-muted/50" data-testid="record-row-{record.id}">
										<td class="p-2">{record.id}</td>
										{#if module.blocks}
											{#each module.blocks as block}
												{#each block.fields as field}
													<td class="p-2">
														{record.data[field.api_name] ?? '-'}
													</td>
												{/each}
											{/each}
										{/if}
										<td class="p-2">
											<div class="flex gap-2">
												<Button
													variant="outline"
													size="sm"
													onclick={() => editRecord(record.id)}
													data-testid="edit-record-{record.id}"
												>
													Edit
												</Button>
												<Button
													variant="outline"
													size="sm"
													onclick={() => deleteRecord(record.id)}
													data-testid="delete-record-{record.id}"
												>
													Delete
												</Button>
											</div>
										</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>

					{#if totalRecords > perPage}
						<div class="flex items-center justify-between mt-4 pt-4 border-t">
							<p class="text-sm text-muted-foreground">
								Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, totalRecords)} of {totalRecords} records
							</p>
							<div class="flex gap-2">
								<Button
									variant="outline"
									size="sm"
									disabled={currentPage === 1}
									onclick={() => { currentPage--; loadModuleAndRecords(); }}
								>
									Previous
								</Button>
								<Button
									variant="outline"
									size="sm"
									disabled={currentPage * perPage >= totalRecords}
									onclick={() => { currentPage++; loadModuleAndRecords(); }}
								>
									Next
								</Button>
							</div>
						</div>
					{/if}
				</CardContent>
			</Card>
		{/if}
	{/if}
</div>
