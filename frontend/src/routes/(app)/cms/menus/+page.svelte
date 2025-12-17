<script lang="ts">
	import { onMount } from 'svelte';
	import { cmsMenuApi, cmsPageApi, type CmsMenu, type MenuItem, type CmsPage } from '$lib/api/cms';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import * as Select from '$lib/components/ui/select';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import { toast } from 'svelte-sonner';
	import {
		Menu,
		Plus,
		Edit,
		Trash2,
		ChevronRight,
		ChevronDown,
		GripVertical,
		ExternalLink,
		Link,
		FileText,
		MapPin
	} from 'lucide-svelte';

	let menus = $state<CmsMenu[]>([]);
	let pages = $state<CmsPage[]>([]);
	let locations = $state<string[]>([]);
	let loading = $state(true);
	let showCreateDialog = $state(false);
	let showItemDialog = $state(false);
	let editingMenu = $state<CmsMenu | null>(null);
	let editingItem = $state<MenuItem | null>(null);
	let editingItemParentId = $state<string | null>(null);
	let deleteTarget = $state<CmsMenu | null>(null);
	let activeMenu = $state<CmsMenu | null>(null);
	let expandedIds = $state<Set<string>>(new Set());

	// Menu form state
	let formName = $state('');
	let formSlug = $state('');
	let formLocation = $state('');
	let formIsActive = $state(true);
	let formLoading = $state(false);

	// Item form state
	let itemLabel = $state('');
	let itemType = $state<'url' | 'page'>('url');
	let itemUrl = $state('');
	let itemPageId = $state<number | undefined>(undefined);
	let itemTarget = $state<'_self' | '_blank'>('_self');

	async function loadMenus() {
		loading = true;
		try {
			const [menuList, locationList, pageList] = await Promise.all([
				cmsMenuApi.list(),
				cmsMenuApi.getLocations(),
				cmsPageApi.list({ status: 'published', per_page: 100 })
			]);
			menus = menuList;
			locations = locationList;
			pages = pageList.data;
			if (menus.length > 0 && !activeMenu) {
				activeMenu = menus[0];
			}
		} catch (error) {
			toast.error('Failed to load menus');
		} finally {
			loading = false;
		}
	}

	function resetMenuForm() {
		formName = '';
		formSlug = '';
		formLocation = '';
		formIsActive = true;
		editingMenu = null;
	}

	function resetItemForm() {
		itemLabel = '';
		itemType = 'url';
		itemUrl = '';
		itemPageId = undefined;
		itemTarget = '_self';
		editingItem = null;
		editingItemParentId = null;
	}

	function openCreateDialog() {
		resetMenuForm();
		showCreateDialog = true;
	}

	function openEditDialog(menu: CmsMenu) {
		editingMenu = menu;
		formName = menu.name;
		formSlug = menu.slug;
		formLocation = menu.location || '';
		formIsActive = menu.is_active;
		showCreateDialog = true;
	}

	function openItemDialog(parentId?: string) {
		resetItemForm();
		editingItemParentId = parentId || null;
		showItemDialog = true;
	}

	function openEditItemDialog(item: MenuItem, parentId?: string) {
		editingItem = item;
		editingItemParentId = parentId || null;
		itemLabel = item.label;
		itemType = item.page_id ? 'page' : 'url';
		itemUrl = item.url || '';
		itemPageId = item.page_id;
		itemTarget = item.target || '_self';
		showItemDialog = true;
	}

	async function handleMenuSubmit() {
		if (!formName.trim()) {
			toast.error('Name is required');
			return;
		}

		formLoading = true;
		try {
			const data = {
				name: formName.trim(),
				slug: formSlug.trim() || undefined,
				location: formLocation || undefined,
				is_active: formIsActive
			};

			if (editingMenu) {
				await cmsMenuApi.update(editingMenu.id, data);
				toast.success('Menu updated');
			} else {
				const newMenu = await cmsMenuApi.create({ ...data, items: [] });
				activeMenu = newMenu;
				toast.success('Menu created');
			}

			showCreateDialog = false;
			resetMenuForm();
			await loadMenus();
		} catch (error) {
			toast.error('Failed to save menu');
		} finally {
			formLoading = false;
		}
	}

	async function handleItemSubmit() {
		if (!itemLabel.trim()) {
			toast.error('Label is required');
			return;
		}

		if (!activeMenu) return;

		const newItem: MenuItem = {
			id: editingItem?.id || `item_${Date.now()}`,
			label: itemLabel.trim(),
			url: itemType === 'url' ? itemUrl : undefined,
			page_id: itemType === 'page' ? itemPageId : undefined,
			target: itemTarget,
			children: editingItem?.children || []
		};

		let updatedItems = [...(activeMenu.items || [])];

		if (editingItem) {
			updatedItems = updateItemInTree(updatedItems, editingItem.id, newItem);
		} else if (editingItemParentId) {
			updatedItems = addItemToParent(updatedItems, editingItemParentId, newItem);
		} else {
			updatedItems.push(newItem);
		}

		try {
			await cmsMenuApi.update(activeMenu.id, { items: updatedItems });
			toast.success(editingItem ? 'Item updated' : 'Item added');
			showItemDialog = false;
			resetItemForm();
			await loadMenus();
		} catch (error) {
			toast.error('Failed to save item');
		}
	}

	function updateItemInTree(items: MenuItem[], id: string, newItem: MenuItem): MenuItem[] {
		return items.map((item) => {
			if (item.id === id) {
				return { ...newItem, children: item.children };
			}
			if (item.children && item.children.length > 0) {
				return { ...item, children: updateItemInTree(item.children, id, newItem) };
			}
			return item;
		});
	}

	function addItemToParent(items: MenuItem[], parentId: string, newItem: MenuItem): MenuItem[] {
		return items.map((item) => {
			if (item.id === parentId) {
				return { ...item, children: [...(item.children || []), newItem] };
			}
			if (item.children && item.children.length > 0) {
				return { ...item, children: addItemToParent(item.children, parentId, newItem) };
			}
			return item;
		});
	}

	async function removeItem(itemId: string) {
		if (!activeMenu) return;

		const updatedItems = removeItemFromTree(activeMenu.items || [], itemId);

		try {
			await cmsMenuApi.update(activeMenu.id, { items: updatedItems });
			toast.success('Item removed');
			await loadMenus();
		} catch (error) {
			toast.error('Failed to remove item');
		}
	}

	function removeItemFromTree(items: MenuItem[], id: string): MenuItem[] {
		return items
			.filter((item) => item.id !== id)
			.map((item) => ({
				...item,
				children: item.children ? removeItemFromTree(item.children, id) : undefined
			}));
	}

	async function handleMenuDelete() {
		if (!deleteTarget) return;

		const targetId = deleteTarget.id;
		try {
			await cmsMenuApi.delete(targetId);
			toast.success('Menu deleted');
			if (activeMenu?.id === targetId) {
				activeMenu = null;
			}
			deleteTarget = null;
			await loadMenus();
		} catch (error) {
			toast.error('Failed to delete menu');
		}
	}

	function toggleExpand(id: string) {
		const newExpanded = new Set(expandedIds);
		if (newExpanded.has(id)) {
			newExpanded.delete(id);
		} else {
			newExpanded.add(id);
		}
		expandedIds = newExpanded;
	}

	function getPageTitle(pageId: number): string {
		return pages.find((p) => p.id === pageId)?.title || `Page #${pageId}`;
	}

	onMount(() => {
		loadMenus();
	});
</script>

<svelte:head>
	<title>Menus | CMS</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold flex items-center gap-2">
				<Menu class="h-6 w-6" />
				Menus
			</h1>
			<p class="text-muted-foreground">Manage navigation menus for your site</p>
		</div>
		<Button onclick={openCreateDialog}>
			<Plus class="mr-2 h-4 w-4" />
			New Menu
		</Button>
	</div>

	<div class="grid gap-6 md:grid-cols-[300px_1fr]">
		<!-- Menu List -->
		<Card.Root>
			<Card.Header class="pb-2">
				<Card.Title class="text-base">Menus</Card.Title>
			</Card.Header>
			<Card.Content class="p-0">
				{#if loading}
					<div class="p-4 text-center text-muted-foreground">Loading...</div>
				{:else if menus.length === 0}
					<div class="p-4 text-center text-muted-foreground">
						<p>No menus yet</p>
					</div>
				{:else}
					<div class="divide-y">
						{#each menus as menu}
							<button
								class="w-full flex items-center gap-3 p-3 hover:bg-muted/50 text-left {activeMenu?.id === menu.id ? 'bg-primary/10' : ''}"
								onclick={() => (activeMenu = menu)}
							>
								<div class="flex-1 min-w-0">
									<div class="flex items-center gap-2">
										<span class="font-medium">{menu.name}</span>
										{#if !menu.is_active}
											<Badge variant="secondary" class="text-xs">Inactive</Badge>
										{/if}
									</div>
									{#if menu.location}
										<p class="text-xs text-muted-foreground flex items-center gap-1">
											<MapPin class="h-3 w-3" />
											{menu.location}
										</p>
									{/if}
								</div>
								<div class="flex items-center gap-1">
									<Button
										variant="ghost"
										size="icon"
										onclick={(e) => {
											e.stopPropagation();
											openEditDialog(menu);
										}}
									>
										<Edit class="h-4 w-4" />
									</Button>
									<Button
										variant="ghost"
										size="icon"
										onclick={(e) => {
											e.stopPropagation();
											deleteTarget = menu;
										}}
									>
										<Trash2 class="h-4 w-4" />
									</Button>
								</div>
							</button>
						{/each}
					</div>
				{/if}
			</Card.Content>
		</Card.Root>

		<!-- Menu Items -->
		<Card.Root>
			<Card.Header>
				<div class="flex items-center justify-between">
					<div>
						<Card.Title>{activeMenu?.name || 'Select a menu'}</Card.Title>
						{#if activeMenu}
							<Card.Description>
								{activeMenu.items?.length || 0} items
							</Card.Description>
						{/if}
					</div>
					{#if activeMenu}
						<Button onclick={() => openItemDialog()}>
							<Plus class="mr-2 h-4 w-4" />
							Add Item
						</Button>
					{/if}
				</div>
			</Card.Header>
			<Card.Content class="p-0">
				{#if !activeMenu}
					<div class="p-8 text-center text-muted-foreground">
						<Menu class="h-12 w-12 mx-auto mb-4 opacity-50" />
						<p>Select a menu to edit its items</p>
					</div>
				{:else if !activeMenu.items || activeMenu.items.length === 0}
					<div class="p-8 text-center text-muted-foreground">
						<p>No items in this menu</p>
						<Button variant="outline" class="mt-4" onclick={() => openItemDialog()}>
							Add your first item
						</Button>
					</div>
				{:else}
					<div class="divide-y">
						{#each activeMenu.items as item}
							{@render menuItemRow(item, 0)}
						{/each}
					</div>
				{/if}
			</Card.Content>
		</Card.Root>
	</div>
</div>

{#snippet menuItemRow(item: MenuItem, depth: number, parentId?: string)}
	<div class="group">
		<div
			class="flex items-center gap-2 p-3 hover:bg-muted/50 transition-colors"
			style="padding-left: {depth * 24 + 12}px"
		>
			{#if item.children && item.children.length > 0}
				<button
					class="p-1 hover:bg-muted rounded"
					onclick={() => toggleExpand(item.id)}
				>
					{#if expandedIds.has(item.id)}
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
					<span class="font-medium">{item.label}</span>
					{#if item.target === '_blank'}
						<ExternalLink class="h-3 w-3 text-muted-foreground" />
					{/if}
				</div>
				<p class="text-xs text-muted-foreground flex items-center gap-1">
					{#if item.page_id}
						<FileText class="h-3 w-3" />
						{getPageTitle(item.page_id)}
					{:else if item.url}
						<Link class="h-3 w-3" />
						{item.url}
					{/if}
				</p>
			</div>

			<div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
				<Button variant="ghost" size="icon" onclick={() => openItemDialog(item.id)} title="Add child">
					<Plus class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="icon" onclick={() => openEditItemDialog(item, parentId)}>
					<Edit class="h-4 w-4" />
				</Button>
				<Button variant="ghost" size="icon" onclick={() => removeItem(item.id)}>
					<Trash2 class="h-4 w-4" />
				</Button>
			</div>
		</div>

		{#if item.children && item.children.length > 0 && expandedIds.has(item.id)}
			{#each item.children as child}
				{@render menuItemRow(child, depth + 1, item.id)}
			{/each}
		{/if}
	</div>
{/snippet}

<!-- Menu Dialog -->
<Dialog.Root bind:open={showCreateDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>
				{editingMenu ? 'Edit Menu' : 'New Menu'}
			</Dialog.Title>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleMenuSubmit(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Name</Label>
				<Input id="name" bind:value={formName} placeholder="Main Navigation" />
			</div>

			<div class="space-y-2">
				<Label for="slug">Slug</Label>
				<Input id="slug" bind:value={formSlug} placeholder="main-navigation" />
			</div>

			<div class="space-y-2">
				<Label>Location</Label>
				<Select.Root type="single" bind:value={formLocation}>
					<Select.Trigger>
						{formLocation || 'Select location'}
					</Select.Trigger>
					<Select.Content>
						{#each locations as loc}
							<Select.Item value={loc}>{loc}</Select.Item>
						{/each}
						<Select.Item value="">None</Select.Item>
					</Select.Content>
				</Select.Root>
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
					{formLoading ? 'Saving...' : editingMenu ? 'Update' : 'Create'}
				</Button>
			</Dialog.Footer>
		</form>
	</Dialog.Content>
</Dialog.Root>

<!-- Item Dialog -->
<Dialog.Root bind:open={showItemDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>
				{editingItem ? 'Edit Menu Item' : 'Add Menu Item'}
			</Dialog.Title>
		</Dialog.Header>

		<form onsubmit={(e) => { e.preventDefault(); handleItemSubmit(); }} class="space-y-4">
			<div class="space-y-2">
				<Label for="item_label">Label</Label>
				<Input id="item_label" bind:value={itemLabel} placeholder="Home" />
			</div>

			<div class="space-y-2">
				<Label>Link Type</Label>
				<div class="flex gap-4">
					<label class="flex items-center gap-2">
						<input type="radio" bind:group={itemType} value="url" />
						Custom URL
					</label>
					<label class="flex items-center gap-2">
						<input type="radio" bind:group={itemType} value="page" />
						Page
					</label>
				</div>
			</div>

			{#if itemType === 'url'}
				<div class="space-y-2">
					<Label for="item_url">URL</Label>
					<Input id="item_url" bind:value={itemUrl} placeholder="https://example.com" />
				</div>
			{:else}
				<div class="space-y-2">
					<Label>Page</Label>
					<Select.Root type="single" value={itemPageId?.toString() ?? ''} onValueChange={(v) => { if (v) itemPageId = parseInt(v); }}>
						<Select.Trigger>
							{itemPageId ? getPageTitle(itemPageId) : 'Select a page'}
						</Select.Trigger>
						<Select.Content>
							{#each pages as page}
								<Select.Item value={page.id.toString()}>{page.title}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{/if}

			<div class="space-y-2">
				<Label>Open in</Label>
				<div class="flex gap-4">
					<label class="flex items-center gap-2">
						<input type="radio" bind:group={itemTarget} value="_self" />
						Same window
					</label>
					<label class="flex items-center gap-2">
						<input type="radio" bind:group={itemTarget} value="_blank" />
						New tab
					</label>
				</div>
			</div>

			<Dialog.Footer>
				<Button type="button" variant="outline" onclick={() => (showItemDialog = false)}>
					Cancel
				</Button>
				<Button type="submit">
					{editingItem ? 'Update' : 'Add'}
				</Button>
			</Dialog.Footer>
		</form>
	</Dialog.Content>
</Dialog.Root>

<!-- Delete Menu Dialog -->
<AlertDialog.Root open={!!deleteTarget} onOpenChange={(open) => { if (!open) deleteTarget = null; }}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete "{deleteTarget?.name}"?</AlertDialog.Title>
			<AlertDialog.Description>
				This will permanently delete this menu and all its items.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action onclick={handleMenuDelete}>Delete</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
