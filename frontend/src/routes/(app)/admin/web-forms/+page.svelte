<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Pencil,
		Trash2,
		Copy,
		Eye,
		Link,
		Code,
		BarChart3,
		FileText,
		Loader2
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWebForms,
		deleteWebForm,
		duplicateWebForm,
		toggleWebFormActive,
		getWebFormEmbedCode,
		type WebForm
	} from '$lib/api/web-forms';

	let forms = $state<WebForm[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let activeFilter = $state<'all' | 'active' | 'inactive'>('all');

	// Delete confirmation dialog
	let deleteDialogOpen = $state(false);
	let formToDelete = $state<WebForm | null>(null);
	let deleting = $state(false);

	// Embed code dialog
	let embedDialogOpen = $state(false);
	let embedForm = $state<WebForm | null>(null);
	let embedCode = $state<{ iframe: string; javascript: string; public_url: string } | null>(null);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const params: { is_active?: boolean; search?: string } = {};
			if (activeFilter === 'active') params.is_active = true;
			if (activeFilter === 'inactive') params.is_active = false;
			if (searchQuery) params.search = searchQuery;

			forms = await getWebForms(params);
		} catch (error) {
			console.error('Failed to load forms:', error);
			toast.error('Failed to load web forms');
		} finally {
			loading = false;
		}
	}

	function confirmDelete(form: WebForm) {
		formToDelete = form;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!formToDelete) return;

		deleting = true;
		try {
			await deleteWebForm(formToDelete.id);
			toast.success('Form deleted');
			deleteDialogOpen = false;
			formToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete form:', error);
			toast.error('Failed to delete form');
		} finally {
			deleting = false;
		}
	}

	async function handleDuplicate(form: WebForm) {
		try {
			await duplicateWebForm(form.id);
			toast.success('Form duplicated');
			await loadData();
		} catch (error) {
			console.error('Failed to duplicate form:', error);
			toast.error('Failed to duplicate form');
		}
	}

	async function handleToggleActive(form: WebForm) {
		try {
			await toggleWebFormActive(form.id);
			toast.success(form.is_active ? 'Form deactivated' : 'Form activated');
			await loadData();
		} catch (error) {
			console.error('Failed to toggle form status:', error);
			toast.error('Failed to update form status');
		}
	}

	async function openEmbedDialog(form: WebForm) {
		embedForm = form;
		try {
			embedCode = await getWebFormEmbedCode(form.id);
			embedDialogOpen = true;
		} catch (error) {
			console.error('Failed to get embed code:', error);
			toast.error('Failed to get embed code');
		}
	}

	function copyToClipboard(text: string) {
		navigator.clipboard.writeText(text);
		toast.success('Copied to clipboard');
	}

	async function handleSearch() {
		await loadData();
	}

	async function handleFilterChange(value: string) {
		activeFilter = value as 'all' | 'active' | 'inactive';
		await loadData();
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}
</script>

<svelte:head>
	<title>Web Forms | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Web Forms</h1>
			<p class="text-muted-foreground">Create embeddable forms to capture leads and data</p>
		</div>
		<Button onclick={() => goto('/admin/web-forms/create')}>
			<Plus class="mr-2 h-4 w-4" />
			New Form
		</Button>
	</div>

	<!-- Filters -->
	<Card.Root class="mb-6">
		<Card.Content class="pt-6">
			<div class="flex flex-wrap gap-4">
				<div class="flex flex-1 items-center gap-2">
					<Search class="h-4 w-4 text-muted-foreground" />
					<Input
						type="search"
						placeholder="Search forms..."
						class="max-w-sm"
						bind:value={searchQuery}
						onkeydown={(e) => e.key === 'Enter' && handleSearch()}
					/>
					<Button variant="secondary" onclick={handleSearch}>Search</Button>
				</div>
				<Select.Root type="single" value={activeFilter} onValueChange={handleFilterChange}>
					<Select.Trigger class="w-[150px]">
						{activeFilter === 'all' ? 'All Forms' : activeFilter === 'active' ? 'Active' : 'Inactive'}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="all">All Forms</Select.Item>
						<Select.Item value="active">Active</Select.Item>
						<Select.Item value="inactive">Inactive</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Forms Table -->
	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if forms.length === 0}
				<div class="flex flex-col items-center justify-center py-12">
					<FileText class="mb-4 h-12 w-12 text-muted-foreground" />
					<h3 class="mb-2 text-lg font-medium">No forms found</h3>
					<p class="mb-4 text-muted-foreground">
						{searchQuery || activeFilter !== 'all'
							? 'Try adjusting your search or filters'
							: 'Create your first web form to capture leads'}
					</p>
					{#if !searchQuery && activeFilter === 'all'}
						<Button onclick={() => goto('/admin/web-forms/create')}>
							<Plus class="mr-2 h-4 w-4" />
							Create Form
						</Button>
					{/if}
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Form</Table.Head>
							<Table.Head>Module</Table.Head>
							<Table.Head>Submissions</Table.Head>
							<Table.Head>Status</Table.Head>
							<Table.Head>Updated</Table.Head>
							<Table.Head class="w-[100px]"></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each forms as form}
							<Table.Row>
								<Table.Cell>
									<div>
										<p class="font-medium">{form.name}</p>
										<p class="text-sm text-muted-foreground">/{form.slug}</p>
									</div>
								</Table.Cell>
								<Table.Cell>
									{#if form.module}
										<Badge variant="outline">{form.module.name}</Badge>
									{:else}
										<span class="text-muted-foreground">-</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<span class="font-medium">{form.submission_count}</span>
								</Table.Cell>
								<Table.Cell>
									<Switch
										checked={form.is_active}
										onCheckedChange={() => handleToggleActive(form)}
									/>
								</Table.Cell>
								<Table.Cell>
									<span class="text-sm text-muted-foreground">
										{formatDate(form.updated_at)}
									</span>
								</Table.Cell>
								<Table.Cell>
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											{#snippet child({ props })}
												<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
													<MoreHorizontal class="h-4 w-4" />
												</Button>
											{/snippet}
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end">
											<DropdownMenu.Item onclick={() => window.open(form.public_url, '_blank')}>
												<Eye class="mr-2 h-4 w-4" />
												Preview
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => goto(`/admin/web-forms/${form.id}/edit`)}>
												<Pencil class="mr-2 h-4 w-4" />
												Edit
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => openEmbedDialog(form)}>
												<Code class="mr-2 h-4 w-4" />
												Embed Code
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => handleDuplicate(form)}>
												<Copy class="mr-2 h-4 w-4" />
												Duplicate
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item
												class="text-destructive focus:text-destructive"
												onclick={() => confirmDelete(form)}
											>
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<!-- Delete Confirmation Dialog -->
<Dialog.Root bind:open={deleteDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Form</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete "{formToDelete?.name}"? All submissions will also be deleted.
				This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (deleteDialogOpen = false)} disabled={deleting}>
				Cancel
			</Button>
			<Button variant="destructive" onclick={handleDelete} disabled={deleting}>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Embed Code Dialog -->
<Dialog.Root bind:open={embedDialogOpen}>
	<Dialog.Content class="max-w-2xl">
		<Dialog.Header>
			<Dialog.Title>Embed Code: {embedForm?.name}</Dialog.Title>
			<Dialog.Description>
				Copy the code below to embed this form on your website
			</Dialog.Description>
		</Dialog.Header>
		{#if embedCode}
			<div class="space-y-4">
				<div class="space-y-2">
					<div class="flex items-center justify-between">
						<label class="text-sm font-medium">Public URL</label>
						<Button
							variant="ghost"
							size="sm"
							onclick={() => copyToClipboard(embedCode?.public_url ?? '')}
						>
							<Link class="mr-2 h-4 w-4" />
							Copy
						</Button>
					</div>
					<Input value={embedCode.public_url} readonly />
				</div>

				<div class="space-y-2">
					<div class="flex items-center justify-between">
						<label class="text-sm font-medium">Iframe Embed</label>
						<Button
							variant="ghost"
							size="sm"
							onclick={() => copyToClipboard(embedCode?.iframe ?? '')}
						>
							<Copy class="mr-2 h-4 w-4" />
							Copy
						</Button>
					</div>
					<pre
						class="rounded-md border bg-muted p-3 text-xs overflow-x-auto">{embedCode.iframe}</pre>
				</div>

				<div class="space-y-2">
					<div class="flex items-center justify-between">
						<label class="text-sm font-medium">JavaScript Embed</label>
						<Button
							variant="ghost"
							size="sm"
							onclick={() => copyToClipboard(embedCode?.javascript ?? '')}
						>
							<Copy class="mr-2 h-4 w-4" />
							Copy
						</Button>
					</div>
					<pre
						class="rounded-md border bg-muted p-3 text-xs overflow-x-auto">{embedCode.javascript}</pre>
				</div>
			</div>
		{/if}
		<Dialog.Footer>
			<Button onclick={() => (embedDialogOpen = false)}>Close</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
