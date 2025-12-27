<script lang="ts">
	import type { DocumentFolder } from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Folder, MoreHorizontal, Pencil, Trash2 } from 'lucide-svelte';

	interface Props {
		folder: DocumentFolder;
		viewMode?: 'grid' | 'list';
		onOpen?: (folder: DocumentFolder) => void;
		onDelete?: (folder: DocumentFolder) => void;
		onRename?: (folder: DocumentFolder) => void;
	}

	let { folder, viewMode = 'grid', onOpen, onDelete, onRename }: Props = $props();

	function handleOpen() {
		onOpen?.(folder);
	}

	function formatDate(dateStr: string | null) {
		if (!dateStr) return '';
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

{#if viewMode === 'grid'}
	<div
		class="group relative flex flex-col items-center p-4 rounded-lg border bg-card hover:bg-accent/50 cursor-pointer transition-colors"
		ondblclick={handleOpen}
		role="button"
		tabindex="0"
		onkeydown={(e) => e.key === 'Enter' && handleOpen()}
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
					<DropdownMenu.Item onclick={handleOpen}>
						<Folder class="mr-2 h-4 w-4" />
						Open
					</DropdownMenu.Item>
					{#if onRename}
						<DropdownMenu.Item onclick={() => onRename(folder)}>
							<Pencil class="mr-2 h-4 w-4" />
							Rename
						</DropdownMenu.Item>
					{/if}
					{#if onDelete}
						<DropdownMenu.Separator />
						<DropdownMenu.Item onclick={() => onDelete(folder)} class="text-destructive">
							<Trash2 class="mr-2 h-4 w-4" />
							Delete
						</DropdownMenu.Item>
					{/if}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>
{:else}
	<!-- List View Row -->
	<tr
		class="border-b hover:bg-accent/50 cursor-pointer"
		ondblclick={handleOpen}
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
					<DropdownMenu.Item onclick={handleOpen}>
						<Folder class="mr-2 h-4 w-4" />
						Open
					</DropdownMenu.Item>
					{#if onRename}
						<DropdownMenu.Item onclick={() => onRename(folder)}>
							<Pencil class="mr-2 h-4 w-4" />
							Rename
						</DropdownMenu.Item>
					{/if}
					{#if onDelete}
						<DropdownMenu.Separator />
						<DropdownMenu.Item onclick={() => onDelete(folder)} class="text-destructive">
							<Trash2 class="mr-2 h-4 w-4" />
							Delete
						</DropdownMenu.Item>
					{/if}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</td>
	</tr>
{/if}
