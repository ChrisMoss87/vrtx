<script lang="ts">
	import type { DocumentFolder } from '$lib/api/collaborative-documents';
	import { Folder, ChevronRight, ChevronDown, FolderOpen } from 'lucide-svelte';
	import FolderTree from './FolderTree.svelte';

	interface Props {
		folders: DocumentFolder[];
		selectedFolderId?: number | null;
		expandedFolderIds?: Set<number>;
		onSelectFolder?: (folder: DocumentFolder | null) => void;
		onToggleExpand?: (folderId: number) => void;
	}

	let {
		folders,
		selectedFolderId = null,
		expandedFolderIds = new Set(),
		onSelectFolder,
		onToggleExpand
	}: Props = $props();

	function isExpanded(folderId: number) {
		return expandedFolderIds.has(folderId);
	}

	function hasChildren(folder: DocumentFolder) {
		return folder.children && folder.children.length > 0;
	}
</script>

<div class="space-y-0.5">
	<!-- Root level -->
	<button
		class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-sm hover:bg-accent/50 transition-colors {selectedFolderId === null ? 'bg-accent text-accent-foreground' : 'text-foreground'}"
		onclick={() => onSelectFolder?.(null)}
	>
		<Folder class="h-4 w-4 text-muted-foreground" />
		<span>All Documents</span>
	</button>

	<!-- Folder tree -->
	{#each folders as folder}
		{@const expanded = isExpanded(folder.id)}
		{@const hasKids = hasChildren(folder)}
		{@const isSelected = selectedFolderId === folder.id}

		<div>
			<div class="flex items-center">
				<!-- Expand/collapse button -->
				{#if hasKids}
					<button
						class="p-1 rounded hover:bg-accent/50"
						onclick={() => onToggleExpand?.(folder.id)}
					>
						{#if expanded}
							<ChevronDown class="h-3 w-3 text-muted-foreground" />
						{:else}
							<ChevronRight class="h-3 w-3 text-muted-foreground" />
						{/if}
					</button>
				{:else}
					<div class="w-5"></div>
				{/if}

				<!-- Folder button -->
				<button
					class="flex-1 flex items-center gap-2 px-2 py-1.5 rounded-md text-sm hover:bg-accent/50 transition-colors {isSelected ? 'bg-accent text-accent-foreground' : 'text-foreground'}"
					onclick={() => onSelectFolder?.(folder)}
				>
					{#if expanded}
						<FolderOpen class="h-4 w-4" style="color: {folder.color || '#6B7280'}" />
					{:else}
						<Folder class="h-4 w-4" style="color: {folder.color || '#6B7280'}" />
					{/if}
					<span class="truncate">{folder.name}</span>
					{#if folder.document_count && folder.document_count > 0}
						<span class="text-xs text-muted-foreground ml-auto">{folder.document_count}</span>
					{/if}
				</button>
			</div>

			<!-- Children (recursive) -->
			{#if hasKids && expanded}
				<div class="ml-4 border-l border-border pl-2 mt-0.5">
					<FolderTree
						folders={folder.children || []}
						{selectedFolderId}
						{expandedFolderIds}
						{onSelectFolder}
						{onToggleExpand}
					/>
				</div>
			{/if}
		</div>
	{/each}
</div>
