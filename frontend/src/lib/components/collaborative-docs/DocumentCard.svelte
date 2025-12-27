<script lang="ts">
	import { goto } from '$app/navigation';
	import type { CollaborativeDocument } from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { MoreHorizontal, Pencil, Copy, Trash2, Star } from 'lucide-svelte';
	import DocumentIcon from './DocumentIcon.svelte';

	interface Props {
		document: CollaborativeDocument;
		viewMode?: 'grid' | 'list';
		onDelete?: (doc: CollaborativeDocument) => void;
		onDuplicate?: (doc: CollaborativeDocument) => void;
		onToggleStar?: (doc: CollaborativeDocument) => void;
	}

	let { document: doc, viewMode = 'grid', onDelete, onDuplicate, onToggleStar }: Props = $props();

	function openDocument() {
		goto(`/documents/${doc.id}`);
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

	function getDocumentTypeLabel(type: string) {
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
</script>

{#if viewMode === 'grid'}
	<div
		class="group relative flex flex-col items-center p-4 rounded-lg border bg-card hover:bg-accent/50 cursor-pointer transition-colors"
		ondblclick={openDocument}
		role="button"
		tabindex="0"
		onkeydown={(e) => e.key === 'Enter' && openDocument()}
	>
		<DocumentIcon type={doc.type} class="h-12 w-12 mb-2" />
		<span class="text-sm font-medium text-center truncate w-full">{doc.title}</span>
		<span class="text-xs text-muted-foreground">{formatDate(doc.updated_at)}</span>

		{#if doc.is_template}
			<span class="absolute top-2 left-2 px-1.5 py-0.5 text-xs bg-primary/10 text-primary rounded">
				Template
			</span>
		{/if}

		{#if doc.is_starred}
			<Star class="absolute top-2 left-2 h-4 w-4 text-yellow-500 fill-yellow-500" />
		{/if}

		<div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button variant="ghost" size="sm" class="h-7 w-7 p-0" onclick={(e) => e.stopPropagation()}>
						<MoreHorizontal class="h-4 w-4" />
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content>
					<DropdownMenu.Item onclick={openDocument}>
						<Pencil class="mr-2 h-4 w-4" />
						Open
					</DropdownMenu.Item>
					{#if onDuplicate}
						<DropdownMenu.Item onclick={() => onDuplicate(doc)}>
							<Copy class="mr-2 h-4 w-4" />
							Duplicate
						</DropdownMenu.Item>
					{/if}
					{#if onToggleStar}
						<DropdownMenu.Item onclick={() => onToggleStar(doc)}>
							<Star class="mr-2 h-4 w-4" />
							{doc.is_starred ? 'Unstar' : 'Star'}
						</DropdownMenu.Item>
					{/if}
					{#if onDelete}
						<DropdownMenu.Separator />
						<DropdownMenu.Item onclick={() => onDelete(doc)} class="text-destructive">
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
		ondblclick={openDocument}
	>
		<td class="px-4 py-3">
			<div class="flex items-center gap-3">
				<DocumentIcon type={doc.type} class="h-5 w-5" />
				<span class="font-medium">{doc.title}</span>
				{#if doc.is_template}
					<span class="px-1.5 py-0.5 text-xs bg-primary/10 text-primary rounded">
						Template
					</span>
				{/if}
				{#if doc.is_starred}
					<Star class="h-4 w-4 text-yellow-500 fill-yellow-500" />
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
					<DropdownMenu.Item onclick={openDocument}>
						<Pencil class="mr-2 h-4 w-4" />
						Open
					</DropdownMenu.Item>
					{#if onDuplicate}
						<DropdownMenu.Item onclick={() => onDuplicate(doc)}>
							<Copy class="mr-2 h-4 w-4" />
							Duplicate
						</DropdownMenu.Item>
					{/if}
					{#if onToggleStar}
						<DropdownMenu.Item onclick={() => onToggleStar(doc)}>
							<Star class="mr-2 h-4 w-4" />
							{doc.is_starred ? 'Unstar' : 'Star'}
						</DropdownMenu.Item>
					{/if}
					{#if onDelete}
						<DropdownMenu.Separator />
						<DropdownMenu.Item onclick={() => onDelete(doc)} class="text-destructive">
							<Trash2 class="mr-2 h-4 w-4" />
							Delete
						</DropdownMenu.Item>
					{/if}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</td>
	</tr>
{/if}
