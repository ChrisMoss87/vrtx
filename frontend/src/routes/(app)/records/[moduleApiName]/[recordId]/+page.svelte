<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { recordsApi, type ModuleRecord } from '$lib/api/records';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { ArrowLeft, Pencil, Trash2, Loader2, Copy } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';

	const moduleApiName = $derived($page.params.moduleApiName as string);
	const recordId = $derived(parseInt($page.params.recordId as string));

	let module = $state<Module | null>(null);
	let record = $state<ModuleRecord | null>(null);
	let loading = $state(true);
	let deleting = $state(false);
	let error = $state<string | null>(null);
	let deleteDialogOpen = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		error = null;

		try {
			const [moduleData, recordData] = await Promise.all([
				modulesApi.getByApiName(moduleApiName),
				recordsApi.getById(moduleApiName, recordId)
			]);

			module = moduleData;
			record = recordData;
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load record';
		} finally {
			loading = false;
		}
	}

	async function handleDelete() {
		deleting = true;

		try {
			await recordsApi.delete(moduleApiName, recordId);
			toast.success(`${module?.singular_name || 'Record'} deleted successfully`);
			goto(`/records/${moduleApiName}`);
		} catch (err: any) {
			const message = err.response?.data?.error || err.message || 'Failed to delete record';
			toast.error(message);
		} finally {
			deleting = false;
			deleteDialogOpen = false;
		}
	}

	function handleEdit() {
		goto(`/records/${moduleApiName}/${recordId}/edit`);
	}

	function handleDuplicate() {
		// Navigate to create page with record data as query params
		const params = new URLSearchParams();
		if (record?.data) {
			params.set('duplicate', JSON.stringify(record.data));
		}
		goto(`/records/${moduleApiName}/create?${params.toString()}`);
	}

	function formatValue(value: any, fieldType: string): string {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		switch (fieldType) {
			case 'date':
				return new Date(value).toLocaleDateString();
			case 'datetime':
				return new Date(value).toLocaleString();
			case 'boolean':
			case 'checkbox':
			case 'toggle':
				return value ? 'Yes' : 'No';
			case 'currency':
				return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value);
			case 'percent':
				return `${value}%`;
			case 'multiselect':
				return Array.isArray(value) ? value.join(', ') : String(value);
			default:
				return String(value);
		}
	}

	function getFieldOption(field: any, value: string): string {
		const option = field.options?.find((o: any) => o.value === value);
		return option?.label || value;
	}
</script>

<div class="container mx-auto max-w-4xl py-8">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-center">
				<Loader2 class="mx-auto h-12 w-12 animate-spin text-primary" />
				<p class="mt-4 text-muted-foreground">Loading...</p>
			</div>
		</div>
	{:else if error}
		<div class="rounded-lg border border-destructive p-6">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" class="mt-4" onclick={() => goto(`/records/${moduleApiName}`)}>
				<ArrowLeft class="mr-2 h-4 w-4" />
				Go Back
			</Button>
		</div>
	{:else if module && record}
		<div class="space-y-6">
			<!-- Header -->
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-4">
					<Button variant="ghost" size="icon" onclick={() => goto(`/records/${moduleApiName}`)}>
						<ArrowLeft class="h-4 w-4" />
					</Button>
					<div>
						<h1 class="text-2xl font-bold">{module.singular_name} Details</h1>
						<p class="mt-1 text-muted-foreground">Record #{record.id}</p>
					</div>
				</div>
				<div class="flex items-center gap-2">
					<Button variant="outline" size="sm" onclick={handleDuplicate}>
						<Copy class="mr-2 h-4 w-4" />
						Duplicate
					</Button>
					<Button variant="outline" size="sm" onclick={handleEdit}>
						<Pencil class="mr-2 h-4 w-4" />
						Edit
					</Button>
					<Button variant="destructive" size="sm" onclick={() => (deleteDialogOpen = true)}>
						<Trash2 class="mr-2 h-4 w-4" />
						Delete
					</Button>
				</div>
			</div>

			<!-- Record metadata -->
			<div class="flex items-center gap-4 text-sm text-muted-foreground">
				<span>Created: {new Date(record.created_at).toLocaleString()}</span>
				{#if record.updated_at}
					<span>Last updated: {new Date(record.updated_at).toLocaleString()}</span>
				{/if}
			</div>

			<!-- Record data by blocks -->
			{#each module.blocks || [] as block}
				<Card.Root>
					<Card.Header>
						<Card.Title>{block.name}</Card.Title>
					</Card.Header>
					<Card.Content>
						<dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
							{#each block.fields as field}
								{@const value = record.data[field.api_name]}
								<div
									class={field.type === 'textarea' || field.type === 'rich_text'
										? 'md:col-span-2'
										: ''}
								>
									<dt class="text-sm font-medium text-muted-foreground">{field.label}</dt>
									<dd class="mt-1">
										{#if field.type === 'select' || field.type === 'radio'}
											{#if value}
												<Badge variant="secondary">{getFieldOption(field, String(value))}</Badge>
											{:else}
												<span class="text-muted-foreground">—</span>
											{/if}
										{:else if field.type === 'multiselect'}
											{#if Array.isArray(value) && value.length > 0}
												<div class="flex flex-wrap gap-1">
													{#each value as v}
														<Badge variant="secondary">{getFieldOption(field, String(v))}</Badge>
													{/each}
												</div>
											{:else}
												<span class="text-muted-foreground">—</span>
											{/if}
										{:else if field.type === 'checkbox' || field.type === 'toggle'}
											<Badge variant={value ? 'default' : 'secondary'}>{value ? 'Yes' : 'No'}</Badge
											>
										{:else if field.type === 'url' && value}
											<a
												href={String(value)}
												target="_blank"
												rel="noopener noreferrer"
												class="text-primary hover:underline"
											>
												{value}
											</a>
										{:else if field.type === 'email' && value}
											<a href="mailto:{String(value)}" class="text-primary hover:underline">
												{value}
											</a>
										{:else if field.type === 'phone' && value}
											<a href="tel:{String(value)}" class="text-primary hover:underline">
												{value}
											</a>
										{:else if field.type === 'textarea' && value}
											<p class="whitespace-pre-wrap">{value}</p>
										{:else}
											<span class={!value && value !== 0 ? 'text-muted-foreground' : ''}>
												{formatValue(value, field.type)}
											</span>
										{/if}
									</dd>
								</div>
							{/each}
						</dl>
					</Card.Content>
				</Card.Root>
			{/each}

			<!-- Actions at bottom -->
			<div class="flex justify-between border-t pt-4">
				<Button variant="outline" onclick={() => goto(`/records/${moduleApiName}`)}>
					<ArrowLeft class="mr-2 h-4 w-4" />
					Back to List
				</Button>
				<div class="flex gap-2">
					<Button variant="outline" onclick={handleDuplicate}>
						<Copy class="mr-2 h-4 w-4" />
						Duplicate
					</Button>
					<Button onclick={handleEdit}>
						<Pencil class="mr-2 h-4 w-4" />
						Edit {module.singular_name}
					</Button>
				</div>
			</div>
		</div>
	{/if}
</div>

<!-- Delete Confirmation Dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete {module?.singular_name || 'Record'}?</AlertDialog.Title>
			<AlertDialog.Description>
				This action cannot be undone. This will permanently delete this {module?.singular_name?.toLowerCase() ||
					'record'} from the database.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel disabled={deleting}>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				onclick={handleDelete}
				disabled={deleting}
				class="text-destructive-foreground bg-destructive hover:bg-destructive/90"
			>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Deleting...
				{:else}
					Delete
				{/if}
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
