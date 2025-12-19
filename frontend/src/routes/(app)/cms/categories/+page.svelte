<script lang="ts">
	import { onMount } from 'svelte';
	import { cmsCategoryApi, type CmsCategory } from '$lib/api/cms';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import {
		FolderTree,
		Plus,
		Edit,
		Trash2,
		ChevronRight,
		ChevronDown,
		GripVertical,
		FileText
	} from 'lucide-svelte';

	let categories = $state<CmsCategory[]>([]);
	let loading = $state(true);
	let showCreateDialog = $state(false);
	let editingCategory = $state<CmsCategory | null>(null);
	let deleteTarget = $state<CmsCategory | null>(null);
	let expandedIds = $state<Set<number>>(new Set());

	// Form state
	let formName = $state('');
	let formSlug = $state('');
	let formDescription = $state('');
	let formParentId = $state<number | undefined>(undefined);
	let formIsActive = $state(true);
	let formLoading = $state(false);

	async function loadCategories() {
		loading = true;
		try {
			categories = await cmsCategoryApi.getTree();
		} catch (error) {
			toast.error('Failed to load categories');
		} finally {
			loading = false;
		}
	}

	function resetForm() {
		formName = '';
		formSlug = '';
		formDescription = '';
		formParentId = undefined;
		formIsActive = true;
		editingCategory = null;
	}

	function openCreateDialog(parentId?: number) {
		resetForm();
		formParentId = parentId;
		showCreateDialog = true;
	}

	function openEditDialog(category: CmsCategory) {
		editingCategory = category;
		formName = category.name;
		formSlug = category.slug;
		formDescription = category.description || '';
		formParentId = category.parent_id || undefined;
		formIsActive = category.is_active;
		showCreateDialog = true;
	}

	async function handleSubmit() {
		if (!formName.trim()) {
			toast.error('Name is required');
			return;
		}

		formLoading = true;
		try {
			const data = {
				name: formName.trim(),
				slug: formSlug.trim() || undefined,
				description: formDescription.trim() || undefined,
				parent_id: formParentId,
				is_active: formIsActive
			};

			if (editingCategory) {
				await cmsCategoryApi.update(editingCategory.id, data);
				toast.success('Category updated');
			} else {
				await cmsCategoryApi.create(data);
				toast.success('Category created');
			}

			showCreateDialog = false;
			resetForm();
			await loadCategories();
		} catch (error) {
			toast.error('Failed to save category');
		} finally {
			formLoading = false;
		}
	}

	async function handleDelete() {
		if (!deleteTarget) return;

		try {
			await cmsCategoryApi.delete(deleteTarget.id);
			toast.success('Category deleted');
			deleteTarget = null;
			await loadCategories();
		} catch (error) {
			toast.error('Failed to delete category');
		}
	}

	function toggleExpand(id: number) {
		const newExpanded = new Set(expandedIds);
		if (newExpanded.has(id)) {
			newExpanded.delete(id);
		} else {
			newExpanded.add(id);
		}
		expandedIds = newExpanded;
	}

	function generateSlug(name: string): string {
		return name
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, '');
	}

	onMount(() => {
		loadCategories();
	});
</script>

<svelte:head>
	<title>Categories | CMS</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<FolderTree class="h-6 w-6" />
				Categories
			</h1>
			<p class="text-muted-foreground">Organize your content with categories</p>
		</div>
		<Button onclick={() => openCreateDialog()}>
			<Plus class="mr-2 h-4 w-4" />
			New Category
		</Button>
	</div>

	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
				</div>
			{:else if categories.length === 0}
				<div class="text-center py-12 text-muted-foreground">
					<FolderTree class="h-12 w-12 mx-auto mb-4 opacity-50" />
					<p>No categories yet</p>
					<Button variant="outline" class="mt-4" onclick={() => openCreateDialog()}>
						Create your first category
					</Button>
				</div>
			{:else}
				<div class="divide-y">
					{#each categories as category}
						{@render categoryRow(category, 0)}
					{/each}
				</div>
			{/if}
		</Card.Content>
	</Card.Root>
</div>

{#snippet categoryRow(category: CmsCategory, depth: number)}
	<div class="group">
		<div
			class="flex items-center gap-2 p-3 hover:bg-muted/50 transition-colors"
			style="padding-left: {depth * 24 + 12}px"
		>
			{#if category.children && category.children.length > 0}
				<button
					class="p-1 hover:bg-muted rounded"
					onclick={() => toggleExpand(category.id)}
				>
					{#if expandedIds.has(category.id)}
						<ChevronDown class="h-4 w-4" />
					{:else}
						<ChevronRight class="h-4 w-4" />
					{/if}
				</button>
			{:else}
				<div class="w-6"></div>
			{/if}

			<GripVertical class="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 cursor-grab" />

			<div class="flex-1 min-w-0">
				<div class="flex items-center gap-2">
					<span class="font-medium">{category.name}</span>
					{#if !category.is_active}
						<Badge variant="secondary">Inactive</Badge>
					{/if}
					{#if category.pages_count}
						<span class="text-xs text-muted-foreground flex items-center gap-1">
							<FileText class="h-3 w-3" />
							{category.pages_count}
						</span>
					{/if}
				</div>
				{#if category.description}
					<p class="text-sm text-muted-foreground truncate">{category.description}</p>
				{/if}
			</div>

			<div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
				<Button variant="ghost" size="icon" onclick={() => openCreateDialog(category.id)} title="Add child">
					<Plus class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="icon" onclick={() => openEditDialog(category)}>
					<Edit class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="icon" onclick={() => (deleteTarget = category)}>
					<Trash2 class="h-4 w-4" />
				</Button>
			</div>
		</div>

		{#if category.children && category.children.length > 0 && expandedIds.has(category.id)}
			{#each category.children as child}
				{@render categoryRow(child, depth + 1)}
			{/each}
		{/if}
	</div>
{/snippet}

<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>
				{editingCategory ? 'Edit Category' : 'New Category'}
			</Dialog.Title>
			<Dialog.Description>
				{editingCategory ? 'Update category details' : 'Create a new category to organize content'}
			</Dialog.Description>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Name</Label>
				<Input
					id="name"
					bind:value={formName}
					placeholder="Category name"
					oninput={() => {
						if (!editingCategory && !formSlug) {
							formSlug = generateSlug(formName);
						}
					}}
				/>
			</div>

			<div class="space-y-2">
				<Label for="slug">Slug</Label>
				<Input id="slug" bind:value={formSlug} placeholder="category-slug" />
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={formDescription}
					placeholder="Optional description..."
					rows={3}
				/>
			</div>

			<div class="flex items-center gap-2">
				<Switch id="is_active" bind:checked={formIsActive} />
				<Label for="is_active">Active</Label>
			</div>

			<Dialog.Footer>
				<Button type="button" variant="outline" onclick={() => (showCreateDialog = false)}>
					Cancel
				</Button>
				<Button type="submit" disabled={formLoading}>
					{formLoading ? 'Saving...' : editingCategory ? 'Update' : 'Create'}
				</Button>
			</Dialog.Footer>
		</form>
	</Dialog.Content>
</Dialog.Root>

<AlertDialog.Root open={!!deleteTarget} onOpenChange={(open) => { if (!open) deleteTarget = null; }}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete "{deleteTarget?.name}"?</AlertDialog.Title>
			<AlertDialog.Description>
				This will permanently delete this category. Pages in this category will not be deleted.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
