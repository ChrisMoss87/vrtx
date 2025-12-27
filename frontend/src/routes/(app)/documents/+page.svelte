<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { documentBrowserStore } from '$lib/stores/documents.svelte';
	import {
		type CollaborativeDocument,
		type DocumentFolder,
		type DocumentType
	} from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Label } from '$lib/components/ui/label';
	import {
		Plus,
		Search,
		Grid,
		List,
		FileText,
		Table2,
		Presentation,
		Folder,
		FolderPlus,
		MoreHorizontal,
		Trash2,
		Copy,
		Pencil,
		Star,
		ChevronRight,
		Home
	} from 'lucide-svelte';

	// Local state
	let searchQuery = $state('');
	let showNewDocDialog = $state(false);
	let showNewFolderDialog = $state(false);
	let newDocTitle = $state('');
	let newDocType = $state<DocumentType>('word');
	let newFolderName = $state('');
	let newFolderColor = $state('#4F46E5');

	// Get folder ID from URL
	const folderId = $derived($page.url.searchParams.get('folder'));

	onMount(() => {
		documentBrowserStore.initialize();
		loadContent();
		documentBrowserStore.loadFolderTree();
		documentBrowserStore.loadStatistics();
	});

	// Reload when folder changes
	$effect(() => {
		const id = folderId ? parseInt(folderId) : undefined;
		documentBrowserStore.loadFolderContents(id);
	});

	async function loadContent() {
		const id = folderId ? parseInt(folderId) : undefined;
		await documentBrowserStore.loadFolderContents(id);
	}

	function handleSearch() {
		if (searchQuery.trim()) {
			documentBrowserStore.searchDocuments(searchQuery);
		} else {
			loadContent();
		}
	}

	function navigateToFolder(folder: DocumentFolder | null) {
		if (folder) {
			goto(`/documents?folder=${folder.id}`);
		} else {
			goto('/documents');
		}
	}

	function openDocument(doc: CollaborativeDocument) {
		goto(`/documents/${doc.id}`);
	}

	async function createDocument() {
		if (!newDocTitle.trim()) return;

		try {
			const doc = await documentBrowserStore.createDocument(newDocTitle, newDocType);
			showNewDocDialog = false;
			newDocTitle = '';
			goto(`/documents/${doc.id}`);
		} catch (err) {
			console.error('Failed to create document:', err);
		}
	}

	async function createFolder() {
		if (!newFolderName.trim()) return;

		try {
			await documentBrowserStore.createFolder(newFolderName, newFolderColor);
			showNewFolderDialog = false;
			newFolderName = '';
		} catch (err) {
			console.error('Failed to create folder:', err);
		}
	}

	async function deleteDocument(doc: CollaborativeDocument) {
		if (confirm(`Are you sure you want to delete "${doc.title}"?`)) {
			try {
				await documentBrowserStore.deleteDocument(doc.id);
			} catch (err) {
				console.error('Failed to delete document:', err);
			}
		}
	}

	async function duplicateDocument(doc: CollaborativeDocument) {
		try {
			const newDoc = await documentBrowserStore.duplicateDocument(doc.id);
			goto(`/documents/${newDoc.id}`);
		} catch (err) {
			console.error('Failed to duplicate document:', err);
		}
	}

	async function deleteFolder(folder: DocumentFolder) {
		if (confirm(`Are you sure you want to delete "${folder.name}"? Contents will be moved to parent folder.`)) {
			try {
				await documentBrowserStore.deleteFolder(folder.id);
			} catch (err) {
				console.error('Failed to delete folder:', err);
			}
		}
	}

	function getDocumentIcon(type: DocumentType) {
		switch (type) {
			case 'word':
				return FileText;
			case 'spreadsheet':
				return Table2;
			case 'presentation':
				return Presentation;
			default:
				return FileText;
		}
	}

	function getDocumentTypeLabel(type: DocumentType) {
		switch (type) {
			case 'word':
				return 'Document';
			case 'spreadsheet':
				return 'Spreadsheet';
			case 'presentation':
				return 'Presentation';
			default:
				return 'Document';
		}
	}

	function formatDate(dateStr: string | null) {
		if (!dateStr) return 'Never';
		const date = new Date(dateStr);
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const days = Math.floor(diff / (1000 * 60 * 60 * 24));

		if (days === 0) return 'Today';
		if (days === 1) return 'Yesterday';
		if (days < 7) return `${days} days ago`;
		return date.toLocaleDateString();
	}
</script>

<svelte:head>
	<title>Documents | VRTX</title>
</svelte:head>

<div class="container py-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Documents</h1>
			<p class="text-muted-foreground">Create and collaborate on documents, spreadsheets, and presentations</p>
		</div>
		<div class="flex items-center gap-2">
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button variant="default">
						<Plus class="mr-2 h-4 w-4" />
						New
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					<DropdownMenu.Item onclick={() => { newDocType = 'word'; showNewDocDialog = true; }}>
						<FileText class="mr-2 h-4 w-4 text-blue-500" />
						Document
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => { newDocType = 'spreadsheet'; showNewDocDialog = true; }}>
						<Table2 class="mr-2 h-4 w-4 text-green-500" />
						Spreadsheet
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => { newDocType = 'presentation'; showNewDocDialog = true; }}>
						<Presentation class="mr-2 h-4 w-4 text-orange-500" />
						Presentation
					</DropdownMenu.Item>
					<DropdownMenu.Separator />
					<DropdownMenu.Item onclick={() => showNewFolderDialog = true}>
						<FolderPlus class="mr-2 h-4 w-4" />
						New Folder
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>

	<!-- Toolbar -->
	<div class="mb-4 flex items-center justify-between gap-4">
		<div class="flex items-center gap-2">
			<!-- Search -->
			<div class="relative w-64">
				<Search class="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
				<Input
					type="text"
					placeholder="Search documents..."
					class="pl-8"
					bind:value={searchQuery}
					onkeydown={(e) => e.key === 'Enter' && handleSearch()}
				/>
			</div>
		</div>

		<div class="flex items-center gap-2">
			<!-- View Toggle -->
			<div class="flex rounded-md border">
				<Button
					variant={documentBrowserStore.viewMode === 'grid' ? 'secondary' : 'ghost'}
					size="sm"
					class="rounded-r-none"
					onclick={() => documentBrowserStore.setViewMode('grid')}
				>
					<Grid class="h-4 w-4" />
				</Button>
				<Button
					variant={documentBrowserStore.viewMode === 'list' ? 'secondary' : 'ghost'}
					size="sm"
					class="rounded-l-none"
					onclick={() => documentBrowserStore.setViewMode('list')}
				>
					<List class="h-4 w-4" />
				</Button>
			</div>
		</div>
	</div>

	<!-- Breadcrumb -->
	<div class="mb-4 flex items-center gap-1 text-sm">
		<Button
			variant="ghost"
			size="sm"
			class="h-7 px-2"
			onclick={() => navigateToFolder(null)}
		>
			<Home class="h-4 w-4" />
		</Button>
		{#each documentBrowserStore.folderPath as folder, i}
			<ChevronRight class="h-4 w-4 text-muted-foreground" />
			<Button
				variant="ghost"
				size="sm"
				class="h-7 px-2"
				onclick={() => navigateToFolder(folder)}
			>
				{folder.name}
			</Button>
		{/each}
		{#if documentBrowserStore.currentFolder}
			<ChevronRight class="h-4 w-4 text-muted-foreground" />
			<span class="px-2 font-medium">{documentBrowserStore.currentFolder.name}</span>
		{/if}
	</div>

	<!-- Loading State -->
	{#if documentBrowserStore.loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if documentBrowserStore.error}
		<div class="text-center py-12 text-destructive">
			{documentBrowserStore.error}
		</div>
	{:else}
		<!-- Grid View -->
		{#if documentBrowserStore.viewMode === 'grid'}
			<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
				<!-- Folders -->
				{#each documentBrowserStore.folders as folder}
					<div
						class="group relative flex flex-col items-center p-4 rounded-lg border bg-card hover:bg-accent/50 cursor-pointer transition-colors"
						ondblclick={() => navigateToFolder(folder)}
						role="button"
						tabindex="0"
						onkeydown={(e) => e.key === 'Enter' && navigateToFolder(folder)}
					>
						<Folder class="h-12 w-12 mb-2" style="color: {folder.color || '#6B7280'}" />
						<span class="text-sm font-medium text-center truncate w-full">{folder.name}</span>
						<div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									<Button variant="ghost" size="sm" class="h-7 w-7 p-0" onclick={(e) => e.stopPropagation()}>
										<MoreHorizontal class="h-4 w-4" />
									</Button>
								</DropdownMenu.Trigger>
								<DropdownMenu.Content>
									<DropdownMenu.Item onclick={() => deleteFolder(folder)}>
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</div>
				{/each}

				<!-- Documents -->
				{#each documentBrowserStore.documents as doc}
					{@const DocIcon = getDocumentIcon(doc.type)}
					<div
						class="group relative flex flex-col items-center p-4 rounded-lg border bg-card hover:bg-accent/50 cursor-pointer transition-colors"
						ondblclick={() => openDocument(doc)}
						role="button"
						tabindex="0"
						onkeydown={(e) => e.key === 'Enter' && openDocument(doc)}
					>
						<DocIcon class="h-12 w-12 mb-2 text-primary" />
						<span class="text-sm font-medium text-center truncate w-full">{doc.title}</span>
						<span class="text-xs text-muted-foreground">{formatDate(doc.updated_at)}</span>
						{#if doc.is_template}
							<span class="absolute top-2 left-2 px-1.5 py-0.5 text-xs bg-primary/10 text-primary rounded">
								Template
							</span>
						{/if}
						<div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
							<DropdownMenu.Root>
								<DropdownMenu.Trigger>
									<Button variant="ghost" size="sm" class="h-7 w-7 p-0" onclick={(e) => e.stopPropagation()}>
										<MoreHorizontal class="h-4 w-4" />
									</Button>
								</DropdownMenu.Trigger>
								<DropdownMenu.Content>
									<DropdownMenu.Item onclick={() => openDocument(doc)}>
										<Pencil class="mr-2 h-4 w-4" />
										Open
									</DropdownMenu.Item>
									<DropdownMenu.Item onclick={() => duplicateDocument(doc)}>
										<Copy class="mr-2 h-4 w-4" />
										Duplicate
									</DropdownMenu.Item>
									<DropdownMenu.Separator />
									<DropdownMenu.Item onclick={() => deleteDocument(doc)} class="text-destructive">
										<Trash2 class="mr-2 h-4 w-4" />
										Delete
									</DropdownMenu.Item>
								</DropdownMenu.Content>
							</DropdownMenu.Root>
						</div>
					</div>
				{/each}

				<!-- Empty State -->
				{#if documentBrowserStore.folders.length === 0 && documentBrowserStore.documents.length === 0}
					<div class="col-span-full text-center py-12">
						<FileText class="mx-auto h-12 w-12 text-muted-foreground/50" />
						<h3 class="mt-4 text-lg font-medium">No documents yet</h3>
						<p class="mt-2 text-sm text-muted-foreground">
							Create your first document to get started
						</p>
						<Button class="mt-4" onclick={() => showNewDocDialog = true}>
							<Plus class="mr-2 h-4 w-4" />
							Create Document
						</Button>
					</div>
				{/if}
			</div>
		{:else}
			<!-- List View -->
			<div class="rounded-md border">
				<table class="w-full">
					<thead class="border-b bg-muted/50">
						<tr>
							<th class="px-4 py-3 text-left text-sm font-medium">Name</th>
							<th class="px-4 py-3 text-left text-sm font-medium">Type</th>
							<th class="px-4 py-3 text-left text-sm font-medium">Modified</th>
							<th class="px-4 py-3 text-left text-sm font-medium">Owner</th>
							<th class="px-4 py-3 text-right text-sm font-medium"></th>
						</tr>
					</thead>
					<tbody>
						<!-- Folders -->
						{#each documentBrowserStore.folders as folder}
							<tr
								class="border-b hover:bg-accent/50 cursor-pointer"
								ondblclick={() => navigateToFolder(folder)}
							>
								<td class="px-4 py-3">
									<div class="flex items-center gap-3">
										<Folder class="h-5 w-5" style="color: {folder.color || '#6B7280'}" />
										<span class="font-medium">{folder.name}</span>
									</div>
								</td>
								<td class="px-4 py-3 text-muted-foreground">Folder</td>
								<td class="px-4 py-3 text-muted-foreground">{formatDate(folder.updated_at)}</td>
								<td class="px-4 py-3 text-muted-foreground">—</td>
								<td class="px-4 py-3 text-right">
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											<Button variant="ghost" size="sm" onclick={(e) => e.stopPropagation()}>
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										</DropdownMenu.Trigger>
										<DropdownMenu.Content>
											<DropdownMenu.Item onclick={() => deleteFolder(folder)}>
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</td>
							</tr>
						{/each}

						<!-- Documents -->
						{#each documentBrowserStore.documents as doc}
							{@const DocIcon = getDocumentIcon(doc.type)}
							<tr
								class="border-b hover:bg-accent/50 cursor-pointer"
								ondblclick={() => openDocument(doc)}
							>
								<td class="px-4 py-3">
									<div class="flex items-center gap-3">
										<DocIcon class="h-5 w-5 text-primary" />
										<span class="font-medium">{doc.title}</span>
										{#if doc.is_template}
											<span class="px-1.5 py-0.5 text-xs bg-primary/10 text-primary rounded">
												Template
											</span>
										{/if}
									</div>
								</td>
								<td class="px-4 py-3 text-muted-foreground">{getDocumentTypeLabel(doc.type)}</td>
								<td class="px-4 py-3 text-muted-foreground">{formatDate(doc.updated_at)}</td>
								<td class="px-4 py-3 text-muted-foreground">{doc.owner?.name || '—'}</td>
								<td class="px-4 py-3 text-right">
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											<Button variant="ghost" size="sm" onclick={(e) => e.stopPropagation()}>
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										</DropdownMenu.Trigger>
										<DropdownMenu.Content>
											<DropdownMenu.Item onclick={() => openDocument(doc)}>
												<Pencil class="mr-2 h-4 w-4" />
												Open
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => duplicateDocument(doc)}>
												<Copy class="mr-2 h-4 w-4" />
												Duplicate
											</DropdownMenu.Item>
											<DropdownMenu.Separator />
											<DropdownMenu.Item onclick={() => deleteDocument(doc)} class="text-destructive">
												<Trash2 class="mr-2 h-4 w-4" />
												Delete
											</DropdownMenu.Item>
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</td>
							</tr>
						{/each}
					</tbody>
				</table>

				<!-- Empty State -->
				{#if documentBrowserStore.folders.length === 0 && documentBrowserStore.documents.length === 0}
					<div class="text-center py-12">
						<FileText class="mx-auto h-12 w-12 text-muted-foreground/50" />
						<h3 class="mt-4 text-lg font-medium">No documents yet</h3>
						<p class="mt-2 text-sm text-muted-foreground">
							Create your first document to get started
						</p>
						<Button class="mt-4" onclick={() => showNewDocDialog = true}>
							<Plus class="mr-2 h-4 w-4" />
							Create Document
						</Button>
					</div>
				{/if}
			</div>
		{/if}
	{/if}
</div>

<!-- New Document Dialog -->
<Dialog.Root bind:open={showNewDocDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Create New {getDocumentTypeLabel(newDocType)}</Dialog.Title>
			<Dialog.Description>
				Enter a name for your new {newDocType === 'word' ? 'document' : newDocType}.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="doc-title">Title</Label>
				<Input
					id="doc-title"
					placeholder="Untitled"
					bind:value={newDocTitle}
					onkeydown={(e) => e.key === 'Enter' && createDocument()}
				/>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => showNewDocDialog = false}>Cancel</Button>
			<Button onclick={createDocument} disabled={!newDocTitle.trim()}>Create</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>

<!-- New Folder Dialog -->
<Dialog.Root bind:open={showNewFolderDialog}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Create New Folder</Dialog.Title>
			<Dialog.Description>
				Enter a name for your new folder.
			</Dialog.Description>
		</Dialog.Header>
		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="folder-name">Name</Label>
				<Input
					id="folder-name"
					placeholder="New Folder"
					bind:value={newFolderName}
					onkeydown={(e) => e.key === 'Enter' && createFolder()}
				/>
			</div>
			<div class="space-y-2">
				<Label for="folder-color">Color</Label>
				<div class="flex items-center gap-2">
					<input
						type="color"
						id="folder-color"
						bind:value={newFolderColor}
						class="w-10 h-10 rounded cursor-pointer"
					/>
					<Input value={newFolderColor} class="w-28" readonly />
				</div>
			</div>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => showNewFolderDialog = false}>Cancel</Button>
			<Button onclick={createFolder} disabled={!newFolderName.trim()}>Create</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
