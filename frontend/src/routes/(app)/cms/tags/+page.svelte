<script lang="ts">
	import { onMount } from 'svelte';
	import { cmsTagApi, type CmsTag } from '$lib/api/cms';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import { Tags, Plus, Edit, Trash2, Merge, Search, FileText } from 'lucide-svelte';

	let tags = $state<CmsTag[]>([]);
	let popularTags = $state<CmsTag[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let showCreateDialog = $state(false);
	let showMergeDialog = $state(false);
	let editingTag = $state<CmsTag | null>(null);
	let deleteTarget = $state<CmsTag | null>(null);

	// Form state
	let formName = $state('');
	let formLoading = $state(false);

	// Merge state
	let mergeSource = $state<CmsTag | null>(null);
	let mergeTarget = $state<CmsTag | null>(null);
	let mergeLoading = $state(false);

	const filteredTags = $derived(
		searchQuery
			? tags.filter((tag) =>
					tag.name.toLowerCase().includes(searchQuery.toLowerCase())
				)
			: tags
	);

	async function loadTags() {
		loading = true;
		try {
			const [allTags, popular] = await Promise.all([
				cmsTagApi.list(),
				cmsTagApi.getPopular(10)
			]);
			tags = allTags;
			popularTags = popular;
		} catch (error) {
			toast.error('Failed to load tags');
		} finally {
			loading = false;
		}
	}

	function resetForm() {
		formName = '';
		editingTag = null;
	}

	function openCreateDialog() {
		resetForm();
		showCreateDialog = true;
	}

	function openEditDialog(tag: CmsTag) {
		editingTag = tag;
		formName = tag.name;
		showCreateDialog = true;
	}

	function openMergeDialog(tag: CmsTag) {
		mergeSource = tag;
		mergeTarget = null;
		showMergeDialog = true;
	}

	async function handleSubmit() {
		if (!formName.trim()) {
			toast.error('Name is required');
			return;
		}

		formLoading = true;
		try {
			if (editingTag) {
				await cmsTagApi.update(editingTag.id, formName.trim());
				toast.success('Tag updated');
			} else {
				await cmsTagApi.create(formName.trim());
				toast.success('Tag created');
			}

			showCreateDialog = false;
			resetForm();
			await loadTags();
		} catch (error) {
			toast.error('Failed to save tag');
		} finally {
			formLoading = false;
		}
	}

	async function handleDelete() {
		if (!deleteTarget) return;

		try {
			await cmsTagApi.delete(deleteTarget.id);
			toast.success('Tag deleted');
			deleteTarget = null;
			await loadTags();
		} catch (error) {
			toast.error('Failed to delete tag');
		}
	}

	async function handleMerge() {
		if (!mergeSource || !mergeTarget) return;

		mergeLoading = true;
		try {
			await cmsTagApi.merge(mergeSource.id, mergeTarget.id);
			toast.success(`Merged "${mergeSource.name}" into "${mergeTarget.name}"`);
			showMergeDialog = false;
			mergeSource = null;
			mergeTarget = null;
			await loadTags();
		} catch (error) {
			toast.error('Failed to merge tags');
		} finally {
			mergeLoading = false;
		}
	}

	onMount(() => {
		loadTags();
	});
</script>

<svelte:head>
	<title>Tags | CMS</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<Tags class="h-6 w-6" />
				Tags
			</h1>
			<p class="text-muted-foreground">Manage tags for your content</p>
		</div>
		<Button onclick={openCreateDialog}>
			<Plus class="mr-2 h-4 w-4" />
			New Tag
		</Button>
	</div>

	{#if popularTags.length > 0}
		<Card.Root>
			<Card.Header class="pb-2">
				<Card.Title class="text-base">Popular Tags</Card.Title>
			</Card.Header>
			<Card.Content>
				<div class="flex flex-wrap gap-2">
					{#each popularTags as tag}
						<Badge variant="secondary" class="text-sm cursor-pointer hover:bg-muted">
							{tag.name}
							{#if tag.pages_count}
								<span class="ml-1 text-xs opacity-60">({tag.pages_count})</span>
							{/if}
						</Badge>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>
	{/if}

	<Card.Root>
		<Card.Header class="pb-2">
			<div class="flex items-center justify-between">
				<Card.Title class="text-base">All Tags ({tags.length})</Card.Title>
				<div class="relative w-64">
					<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
					<Input
						class="pl-9"
						placeholder="Search tags..."
						bind:value={searchQuery}
					/>
				</div>
			</div>
		</Card.Header>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
				</div>
			{:else if filteredTags.length === 0}
				<div class="text-center py-12 text-muted-foreground">
					{#if searchQuery}
						<p>No tags match "{searchQuery}"</p>
					{:else}
						<Tags class="h-12 w-12 mx-auto mb-4 opacity-50" />
						<p>No tags yet</p>
						<Button variant="outline" class="mt-4" onclick={openCreateDialog}>
							Create your first tag
						</Button>
					{/if}
				</div>
			{:else}
				<div class="divide-y">
					{#each filteredTags as tag}
						<div class="group flex items-center gap-4 p-3 hover:bg-muted/50 transition-colors">
							<div class="flex-1 min-w-0">
								<div class="flex items-center gap-2">
									<span class="font-medium">{tag.name}</span>
									{#if tag.pages_count}
										<span class="text-xs text-muted-foreground flex items-center gap-1">
											<FileText class="h-3 w-3" />
											{tag.pages_count} pages
										</span>
									{/if}
								</div>
								<p class="text-xs text-muted-foreground">
									/{tag.slug}
								</p>
							</div>

							<div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
								<Button
									variant="ghost"
									size="icon"
									onclick={() => openMergeDialog(tag)}
									title="Merge into another tag"
								>
									<Merge class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" onclick={() => openEditDialog(tag)}>
									<Edit class="h-4 w-4" />
								</Button>
								<Button variant="ghost" size="icon" onclick={() => (deleteTarget = tag)}>
									<Trash2 class="h-4 w-4" />
								</Button>
							</div>
						</div>
					{/each}
				</div>
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>
				{editingTag ? 'Edit Tag' : 'New Tag'}
			</Dialog.Title>
			<Dialog.Description>
				{editingTag ? 'Update tag name' : 'Create a new tag'}
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Name</Label>
				<Input id="name" bind:value={formName} placeholder="Tag name" />
			</div>

			<Dialog.Footer>
				<Button type="button" variant="outline" onclick={() => (showCreateDialog = false)}>
					Cancel
				</Button>
				<Button type="submit" disabled={formLoading}>
					{formLoading ? 'Saving...' : editingTag ? 'Update' : 'Create'}
				</Button>
			</Dialog.Footer>
		</form>
	</Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={showMergeDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Merge Tag</Dialog.Title>
			<Dialog.Description>
				Merge "{mergeSource?.name}" into another tag. All pages with this tag will be updated.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4">
			<div class="space-y-2">
				<Label>Target Tag</Label>
				<div class="max-h-48 overflow-y-auto border rounded-md">
					{#each tags.filter((t) => t.id !== mergeSource?.id) as tag}
						<button
							type="button"
							class="w-full flex items-center gap-2 p-2 hover:bg-muted text-left {mergeTarget?.id === tag.id ? 'bg-primary/10' : ''}"
							onclick={() => (mergeTarget = tag)}
						>
							<span class="flex-1">{tag.name}</span>
							{#if tag.pages_count}
								<span class="text-xs text-muted-foreground">{tag.pages_count} pages</span>
							{/if}
						</button>
					{/each}
				</div>
			</div>

			{#if mergeTarget}
				<p class="text-sm text-muted-foreground">
					This will move all pages from "{mergeSource?.name}" to "{mergeTarget.name}" and delete "{mergeSource?.name}".
				</p>
			{/if}
		</div>

		<Dialog.Footer>
			<Button type="button" variant="outline" onclick={() => (showMergeDialog = false)}>
				Cancel
			</Button>
			<Button onclick={handleMerge} disabled={!mergeTarget || mergeLoading}>
				{mergeLoading ? 'Merging...' : 'Merge'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<AlertDialog.Root open={!!deleteTarget} onOpenChange={(open) => { if (!open) deleteTarget = null; }}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete "{deleteTarget?.name}"?</AlertDialog.Title>
			<AlertDialog.Description>
				This will permanently delete this tag. Pages with this tag will not be deleted.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
