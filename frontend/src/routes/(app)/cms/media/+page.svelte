<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import {
		Upload,
		Search,
		FolderPlus,
		Grid,
		List,
		MoreHorizontal,
		Trash2,
		Download,
		Edit,
		Move,
		Image,
		FileText,
		Video,
		Music,
		File,
		Folder,
		ChevronRight,
		Home,
		X,
		Check
	} from 'lucide-svelte';
	import {
		cmsMediaApi,
		cmsMediaFolderApi,
		type CmsMedia,
		type CmsMediaFolder,
		type MediaType,
		getMediaTypeIcon
	} from '$lib/api/cms';
	import { toast } from 'svelte-sonner';

	let loading = $state(true);
	let media = $state<CmsMedia[]>([]);
	let folders = $state<CmsMediaFolder[]>([]);
	let allFolders = $state<CmsMediaFolder[]>([]);
	let currentFolderId = $state<number | null>(null);
	let breadcrumbs = $state<{ id: number | null; name: string }[]>([{ id: null, name: 'Media Library' }]);

	let searchQuery = $state('');
	let typeFilter = $state<MediaType | ''>('');
	let viewMode = $state<'grid' | 'list'>('grid');

	let selectedItems = $state<number[]>([]);
	let showUploadDialog = $state(false);
	let showFolderDialog = $state(false);
	let showEditDialog = $state(false);
	let showMoveDialog = $state(false);

	let editingMedia = $state<CmsMedia | null>(null);
	let newFolderName = $state('');
	let moveFolderId = $state<number | null>(null);

	let uploading = $state(false);
	let uploadProgress = $state(0);

	let meta = $state({
		current_page: 1,
		last_page: 1,
		per_page: 50,
		total: 0
	});

	const mediaTypes: { value: MediaType; label: string; icon: typeof Image }[] = [
		{ value: 'image', label: 'Images', icon: Image },
		{ value: 'document', label: 'Documents', icon: FileText },
		{ value: 'video', label: 'Videos', icon: Video },
		{ value: 'audio', label: 'Audio', icon: Music },
		{ value: 'other', label: 'Other', icon: File }
	];

	onMount(async () => {
		await Promise.all([loadMedia(), loadFolders()]);
	});

	async function loadMedia() {
		loading = true;
		try {
			const response = await cmsMediaApi.list({
				folder_id: currentFolderId,
				type: typeFilter || undefined,
				search: searchQuery || undefined,
				page: meta.current_page,
				per_page: meta.per_page
			});
			media = response.data;
			meta = response.meta;
		} catch (error) {
			toast.error('Failed to load media');
		} finally {
			loading = false;
		}
	}

	async function loadFolders() {
		try {
			const [foldersRes, treeRes] = await Promise.all([
				cmsMediaFolderApi.list({ parent_id: currentFolderId }),
				cmsMediaFolderApi.getTree()
			]);
			folders = foldersRes;
			allFolders = flattenFolders(treeRes);
		} catch (error) {
			console.error('Failed to load folders', error);
		}
	}

	function flattenFolders(items: CmsMediaFolder[], prefix = ''): CmsMediaFolder[] {
		let result: CmsMediaFolder[] = [];
		for (const item of items) {
			result.push({ ...item, name: prefix + item.name });
			if (item.children) {
				result = [...result, ...flattenFolders(item.children, prefix + '  ')];
			}
		}
		return result;
	}

	async function navigateToFolder(folderId: number | null) {
		currentFolderId = folderId;
		selectedItems = [];
		meta.current_page = 1;

		if (folderId === null) {
			breadcrumbs = [{ id: null, name: 'Media Library' }];
		} else {
			try {
				const response = await cmsMediaFolderApi.get(folderId);
				breadcrumbs = [
					{ id: null, name: 'Media Library' },
					...response.breadcrumbs.map((b) => ({ id: b.id, name: b.name }))
				];
			} catch (error) {
				console.error('Failed to load breadcrumbs', error);
			}
		}

		await Promise.all([loadMedia(), loadFolders()]);
	}

	async function handleUpload(event: Event) {
		const input = event.target as HTMLInputElement;
		const files = input.files;
		if (!files || files.length === 0) return;

		uploading = true;
		uploadProgress = 0;

		try {
			const totalFiles = files.length;
			let completed = 0;

			for (const file of Array.from(files)) {
				await cmsMediaApi.upload(file, {
					folder_id: currentFolderId || undefined
				});
				completed++;
				uploadProgress = Math.round((completed / totalFiles) * 100);
			}

			toast.success(`Uploaded ${totalFiles} file${totalFiles > 1 ? 's' : ''}`);
			showUploadDialog = false;
			await loadMedia();
		} catch (error) {
			toast.error('Failed to upload files');
		} finally {
			uploading = false;
			uploadProgress = 0;
		}
	}

	async function handleCreateFolder() {
		if (!newFolderName.trim()) {
			toast.error('Folder name is required');
			return;
		}

		try {
			await cmsMediaFolderApi.create({
				name: newFolderName.trim(),
				parent_id: currentFolderId || undefined
			});
			toast.success('Folder created');
			showFolderDialog = false;
			newFolderName = '';
			await loadFolders();
		} catch (error) {
			toast.error('Failed to create folder');
		}
	}

	async function handleUpdateMedia() {
		if (!editingMedia) return;

		try {
			await cmsMediaApi.update(editingMedia.id, {
				name: editingMedia.name,
				alt_text: editingMedia.alt_text || undefined,
				caption: editingMedia.caption || undefined,
				description: editingMedia.description || undefined
			});
			toast.success('Media updated');
			showEditDialog = false;
			editingMedia = null;
			await loadMedia();
		} catch (error) {
			toast.error('Failed to update media');
		}
	}

	async function handleDelete(id: number) {
		if (!confirm('Are you sure you want to delete this item?')) return;

		try {
			await cmsMediaApi.delete(id);
			toast.success('Deleted successfully');
			selectedItems = selectedItems.filter((i) => i !== id);
			await loadMedia();
		} catch (error) {
			toast.error('Failed to delete');
		}
	}

	async function handleBulkDelete() {
		if (selectedItems.length === 0) return;
		if (!confirm(`Delete ${selectedItems.length} selected items?`)) return;

		try {
			await cmsMediaApi.bulkDelete(selectedItems);
			toast.success('Items deleted');
			selectedItems = [];
			await loadMedia();
		} catch (error) {
			toast.error('Failed to delete items');
		}
	}

	async function handleBulkMove() {
		if (selectedItems.length === 0) return;

		try {
			await cmsMediaApi.bulkMove(selectedItems, moveFolderId);
			toast.success('Items moved');
			showMoveDialog = false;
			selectedItems = [];
			moveFolderId = null;
			await loadMedia();
		} catch (error) {
			toast.error('Failed to move items');
		}
	}

	function openEditDialog(item: CmsMedia) {
		editingMedia = { ...item };
		showEditDialog = true;
	}

	function toggleSelection(id: number) {
		if (selectedItems.includes(id)) {
			selectedItems = selectedItems.filter((i) => i !== id);
		} else {
			selectedItems = [...selectedItems, id];
		}
	}

	function selectAll() {
		if (selectedItems.length === media.length) {
			selectedItems = [];
		} else {
			selectedItems = media.map((m) => m.id);
		}
	}

	function getMediaIcon(type: MediaType) {
		switch (type) {
			case 'image':
				return Image;
			case 'document':
				return FileText;
			case 'video':
				return Video;
			case 'audio':
				return Music;
			default:
				return File;
		}
	}

	function formatSize(bytes: number): string {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	}
</script>

<div class="container py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Media Library</h1>
			<p class="text-muted-foreground">Upload and manage your media files</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={() => (showFolderDialog = true)}>
				<FolderPlus class="mr-1 h-4 w-4" />
				New Folder
			</Button>
			<Button onclick={() => (showUploadDialog = true)}>
				<Upload class="mr-1 h-4 w-4" />
				Upload
			</Button>
		</div>
	</div>

	<!-- Breadcrumbs -->
	<div class="mb-4 flex items-center gap-1 text-sm">
		{#each breadcrumbs as crumb, index}
			{#if index > 0}
				<ChevronRight class="text-muted-foreground h-4 w-4" />
			{/if}
			<button
				type="button"
				class="hover:text-primary flex items-center gap-1 {index === breadcrumbs.length - 1
					? 'font-medium'
					: 'text-muted-foreground'}"
				onclick={() => navigateToFolder(crumb.id)}
			>
				{#if index === 0}
					<Home class="h-4 w-4" />
				{/if}
				{crumb.name}
			</button>
		{/each}
	</div>

	<!-- Toolbar -->
	<Card.Root class="mb-6">
		<Card.Content class="pt-6">
			<div class="flex items-center justify-between gap-4">
				<div class="flex flex-1 items-center gap-4">
					<div class="relative flex-1 max-w-md">
						<Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
						<Input
							placeholder="Search media..."
							class="pl-10"
							bind:value={searchQuery}
							oninput={() => loadMedia()}
						/>
					</div>
					<Select.Root
						type="single"
						value={typeFilter}
						onValueChange={(val) => {
							typeFilter = (val ?? '') as MediaType | '';
							loadMedia();
						}}
					>
						<Select.Trigger class="w-[150px]">
							<span>{typeFilter ? mediaTypes.find((t) => t.value === typeFilter)?.label : 'All types'}</span>
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="">All types</Select.Item>
							{#each mediaTypes as mt}
								<Select.Item value={mt.value}>{mt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="flex items-center gap-2">
					{#if selectedItems.length > 0}
						<Badge variant="secondary">{selectedItems.length} selected</Badge>
						<Button variant="outline" size="sm" onclick={() => (showMoveDialog = true)}>
							<Move class="mr-1 h-4 w-4" />
							Move
						</Button>
						<Button variant="destructive" size="sm" onclick={handleBulkDelete}>
							<Trash2 class="mr-1 h-4 w-4" />
							Delete
						</Button>
					{/if}
					<div class="flex rounded-lg border">
						<Button
							variant={viewMode === 'grid' ? 'secondary' : 'ghost'}
							size="sm"
							class="rounded-r-none"
							onclick={() => (viewMode = 'grid')}
						>
							<Grid class="h-4 w-4" />
						</Button>
						<Button
							variant={viewMode === 'list' ? 'secondary' : 'ghost'}
							size="sm"
							class="rounded-l-none"
							onclick={() => (viewMode = 'list')}
						>
							<List class="h-4 w-4" />
						</Button>
					</div>
				</div>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Content -->
	<Card.Root>
		<Card.Content class="p-6">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="text-muted-foreground">Loading...</div>
				</div>
			{:else}
				<!-- Folders -->
				{#if folders.length > 0}
					<div class="mb-6">
						<h3 class="text-muted-foreground mb-3 text-sm font-medium">Folders</h3>
						<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6">
							{#each folders as folder}
								<button
									type="button"
									class="group flex flex-col items-center gap-2 rounded-lg border p-4 text-center transition-colors hover:bg-muted"
									onclick={() => navigateToFolder(folder.id)}
								>
									<Folder class="h-10 w-10 text-yellow-500" />
									<span class="text-sm font-medium truncate w-full">{folder.name}</span>
									{#if folder.media_count !== undefined}
										<span class="text-muted-foreground text-xs">{folder.media_count} items</span>
									{/if}
								</button>
							{/each}
						</div>
					</div>
				{/if}

				<!-- Media -->
				{#if media.length === 0 && folders.length === 0}
					<div class="py-12 text-center">
						<Upload class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
						<p class="text-muted-foreground mb-4">No media files yet</p>
						<Button onclick={() => (showUploadDialog = true)}>
							<Upload class="mr-1 h-4 w-4" />
							Upload Files
						</Button>
					</div>
				{:else if media.length > 0}
					{#if viewMode === 'grid'}
						<div class="grid grid-cols-2 gap-4 sm:grid-cols-4 md:grid-cols-6">
							{#each media as item}
								<div
									class="group relative cursor-pointer rounded-lg border transition-all {selectedItems.includes(
										item.id
									)
										? 'ring-2 ring-primary'
										: 'hover:border-primary'}"
								>
									<div class="absolute left-2 top-2 z-10">
										<Checkbox
											checked={selectedItems.includes(item.id)}
											onCheckedChange={() => toggleSelection(item.id)}
										/>
									</div>
									<div class="aspect-square overflow-hidden rounded-t-lg bg-muted">
										{#if item.type === 'image' && item.thumbnail_url}
											<img
												src={item.thumbnail_url}
												alt={item.alt_text || item.name}
												class="h-full w-full object-cover"
											/>
										{:else}
											<div class="flex h-full items-center justify-center">
												<svelte:component
													this={getMediaIcon(item.type)}
													class="h-12 w-12 text-muted-foreground"
												/>
											</div>
										{/if}
									</div>
									<div class="p-2">
										<p class="truncate text-sm font-medium">{item.name}</p>
										<p class="text-muted-foreground text-xs">{formatSize(item.size)}</p>
									</div>
									<div class="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100">
										<DropdownMenu.Root>
											<DropdownMenu.Trigger>
												<Button variant="secondary" size="sm">
													<MoreHorizontal class="h-4 w-4" />
												</Button>
											</DropdownMenu.Trigger>
											<DropdownMenu.Content align="end">
												<DropdownMenu.Item onclick={() => openEditDialog(item)}>
													<Edit class="mr-2 h-4 w-4" />
													Edit Details
												</DropdownMenu.Item>
												<DropdownMenu.Item>
													<Download class="mr-2 h-4 w-4" />
													<a href={item.url} download={item.filename}>Download</a>
												</DropdownMenu.Item>
												<DropdownMenu.Separator />
												<DropdownMenu.Item class="text-red-600" onclick={() => handleDelete(item.id)}>
													<Trash2 class="mr-2 h-4 w-4" />
													Delete
												</DropdownMenu.Item>
											</DropdownMenu.Content>
										</DropdownMenu.Root>
									</div>
								</div>
							{/each}
						</div>
					{:else}
						<div class="space-y-2">
							{#each media as item}
								<div
									class="group flex items-center gap-4 rounded-lg border p-3 transition-all {selectedItems.includes(
										item.id
									)
										? 'ring-2 ring-primary'
										: 'hover:border-primary'}"
								>
									<Checkbox
										checked={selectedItems.includes(item.id)}
										onCheckedChange={() => toggleSelection(item.id)}
									/>
									<div class="h-12 w-12 flex-shrink-0 overflow-hidden rounded bg-muted">
										{#if item.type === 'image' && item.thumbnail_url}
											<img
												src={item.thumbnail_url}
												alt={item.alt_text || item.name}
												class="h-full w-full object-cover"
											/>
										{:else}
											<div class="flex h-full items-center justify-center">
												<svelte:component
													this={getMediaIcon(item.type)}
													class="h-6 w-6 text-muted-foreground"
												/>
											</div>
										{/if}
									</div>
									<div class="flex-1 min-w-0">
										<p class="truncate font-medium">{item.name}</p>
										<p class="text-muted-foreground text-sm">
											{item.mime_type} - {formatSize(item.size)}
										</p>
									</div>
									<div class="text-muted-foreground text-sm">
										{new Date(item.created_at).toLocaleDateString()}
									</div>
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											<Button variant="ghost" size="sm">
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end">
											<DropdownMenu.Item onclick={() => openEditDialog(item)}>
												<Edit class="mr-2 h-4 w-4" />
												Edit Details
											</DropdownMenu.Item>
											<DropdownMenu.Item>
												<Download class="mr-2 h-4 w-4" />
												<a href={item.url} download={item.filename}>Download</a>
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item class="text-red-600" onclick={() => handleDelete(item.id)}>
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</div>
							{/each}
						</div>
					{/if}

					<!-- Pagination -->
					{#if meta.last_page > 1}
						<div class="mt-6 flex items-center justify-between">
							<div class="text-muted-foreground text-sm">
								Showing {(meta.current_page - 1) * meta.per_page + 1} to {Math.min(
									meta.current_page * meta.per_page,
									meta.total
								)} of {meta.total} items
							</div>
							<div class="flex gap-1">
								<Button
									variant="outline"
									size="sm"
									disabled={meta.current_page === 1}
									onclick={() => {
										meta.current_page--;
										loadMedia();
									}}
								>
									Previous
								</Button>
								<Button
									variant="outline"
									size="sm"
									disabled={meta.current_page === meta.last_page}
									onclick={() => {
										meta.current_page++;
										loadMedia();
									}}
								>
									Next
								</Button>
							</div>
						</div>
					{/if}
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>
</div>

<!-- Upload Dialog -->
<Dialog.Root bind:open={showUploadDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Upload Files</Dialog.Title>
			<Dialog.Description>Upload images, documents, and other media files</Dialog.Description>
		</Dialog.Header>
		<div class="py-4">
			{#if uploading}
				<div class="space-y-4">
					<div class="h-2 rounded-full bg-muted overflow-hidden">
						<div
							class="h-full bg-primary transition-all"
							style="width: {uploadProgress}%"
						></div>
					</div>
					<p class="text-center text-muted-foreground">Uploading... {uploadProgress}%</p>
				</div>
			{:else}
				<label
					class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 transition-colors hover:border-primary hover:bg-muted/50"
				>
					<Upload class="text-muted-foreground mb-4 h-12 w-12" />
					<p class="text-muted-foreground mb-2">Click to upload or drag and drop</p>
					<p class="text-muted-foreground text-xs">PNG, JPG, PDF, DOC up to 10MB</p>
					<input
						type="file"
						class="hidden"
						multiple
						accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.mp3"
						onchange={handleUpload}
					/>
				</label>
			{/if}
		</div>
	</Dialog.Content>
</Dialog.Root>

<!-- New Folder Dialog -->
<Dialog.Root bind:open={showFolderDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Create Folder</Dialog.Title>
			<Dialog.Description>Create a new folder to organize your media</Dialog.Description>
		</Dialog.Header>
		<div class="py-4">
			<div class="space-y-2">
				<Label for="folderName">Folder Name</Label>
				<Input
					id="folderName"
					placeholder="Enter folder name"
					bind:value={newFolderName}
					onkeydown={(e) => {
						if (e.key === 'Enter') handleCreateFolder();
					}}
				/>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showFolderDialog = false)}>Cancel</Button>
			<Button onclick={handleCreateFolder}>Create Folder</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Edit Media Dialog -->
<Dialog.Root bind:open={showEditDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Edit Media Details</Dialog.Title>
		</Dialog.Header>
		{#if editingMedia}
			<div class="space-y-4 py-4">
				<div class="space-y-2">
					<Label for="mediaName">Name</Label>
					<Input id="mediaName" bind:value={editingMedia.name} />
				</div>
				<div class="space-y-2">
					<Label for="altText">Alt Text</Label>
					<Input
						id="altText"
						placeholder="Describe the image for accessibility"
						value={editingMedia.alt_text || ''}
						oninput={(e) => {
							if (editingMedia) editingMedia.alt_text = (e.target as HTMLInputElement).value;
						}}
					/>
				</div>
				<div class="space-y-2">
					<Label for="caption">Caption</Label>
					<Input
						id="caption"
						placeholder="Optional caption"
						value={editingMedia.caption || ''}
						oninput={(e) => {
							if (editingMedia) editingMedia.caption = (e.target as HTMLInputElement).value;
						}}
					/>
				</div>
				<div class="space-y-2">
					<Label for="description">Description</Label>
					<Textarea
						id="description"
						placeholder="Optional description"
						rows={3}
						value={editingMedia.description || ''}
						oninput={(e) => {
							if (editingMedia) editingMedia.description = (e.target as HTMLTextAreaElement).value;
						}}
					/>
				</div>
			</div>
		{/if}
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showEditDialog = false)}>Cancel</Button>
			<Button onclick={handleUpdateMedia}>Save Changes</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- Move Dialog -->
<Dialog.Root bind:open={showMoveDialog}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Move Items</Dialog.Title>
			<Dialog.Description>Select a destination folder for {selectedItems.length} item(s)</Dialog.Description>
		</Dialog.Header>
		<div class="py-4">
			<Select.Root
				type="single"
				value={moveFolderId?.toString() ?? ''}
				onValueChange={(val) => {
					moveFolderId = val ? parseInt(val) : null;
				}}
			>
				<Select.Trigger>
					<span>{moveFolderId ? allFolders.find((f) => f.id === moveFolderId)?.name : 'Root folder'}</span>
				</Select.Trigger>
				<Select.Content>
					<Select.Item value="">Root folder</Select.Item>
					{#each allFolders as folder}
						<Select.Item value={folder.id.toString()}>{folder.name}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showMoveDialog = false)}>Cancel</Button>
			<Button onclick={handleBulkMove}>Move</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
