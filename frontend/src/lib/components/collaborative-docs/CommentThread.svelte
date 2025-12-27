<script lang="ts">
	import type { DocumentComment } from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import { Textarea } from '$lib/components/ui/textarea';
	import { CheckCircle, Circle, Reply, Send, MoreHorizontal, Trash2, Pencil } from 'lucide-svelte';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';

	interface Props {
		comment: DocumentComment;
		replies?: DocumentComment[];
		onResolve?: (commentId: number) => void;
		onReply?: (commentId: number, content: string) => void;
		onDelete?: (commentId: number) => void;
		onEdit?: (commentId: number, content: string) => void;
	}

	let { comment, replies = [], onResolve, onReply, onDelete, onEdit }: Props = $props();

	let showReplyInput = $state(false);
	let replyContent = $state('');
	let isEditing = $state(false);
	let editContent = $state('');

	function formatDate(dateStr: string | null) {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const minutes = Math.floor(diff / (1000 * 60));
		const hours = Math.floor(diff / (1000 * 60 * 60));
		const days = Math.floor(diff / (1000 * 60 * 60 * 24));

		if (minutes < 1) return 'Just now';
		if (minutes < 60) return `${minutes}m ago`;
		if (hours < 24) return `${hours}h ago`;
		if (days < 7) return `${days}d ago`;
		return date.toLocaleDateString();
	}

	function getInitials(name: string) {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}

	function handleReply() {
		if (!replyContent.trim()) return;
		onReply?.(comment.id, replyContent);
		replyContent = '';
		showReplyInput = false;
	}

	function handleEdit() {
		if (!editContent.trim()) return;
		onEdit?.(comment.id, editContent);
		isEditing = false;
	}

	function startEdit() {
		editContent = comment.content;
		isEditing = true;
	}
</script>

<div class="p-3 rounded-lg border {comment.is_resolved ? 'opacity-60 bg-muted/30' : ''}">
	<!-- Comment Header -->
	<div class="flex items-start justify-between mb-2">
		<div class="flex items-center gap-2">
			<div class="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center text-xs font-medium text-primary">
				{getInitials(comment.user_name || 'U')}
			</div>
			<div>
				<p class="text-sm font-medium">{comment.user_name}</p>
				<p class="text-xs text-muted-foreground">{formatDate(comment.created_at)}</p>
			</div>
		</div>

		<div class="flex items-center gap-1">
			{#if !comment.is_resolved && onResolve}
				<Button
					size="sm"
					variant="ghost"
					class="h-7 w-7 p-0"
					onclick={() => onResolve(comment.id)}
					title="Mark as resolved"
				>
					<CheckCircle class="h-4 w-4" />
				</Button>
			{:else if comment.is_resolved}
				<Circle class="h-4 w-4 text-green-500" />
			{/if}

			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button variant="ghost" size="sm" class="h-7 w-7 p-0">
						<MoreHorizontal class="h-4 w-4" />
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content align="end">
					{#if onEdit}
						<DropdownMenu.Item onclick={startEdit}>
							<Pencil class="mr-2 h-4 w-4" />
							Edit
						</DropdownMenu.Item>
					{/if}
					{#if onDelete}
						<DropdownMenu.Item onclick={() => onDelete(comment.id)} class="text-destructive">
							<Trash2 class="mr-2 h-4 w-4" />
							Delete
						</DropdownMenu.Item>
					{/if}
				</DropdownMenu.Content>
			</DropdownMenu.Root>
		</div>
	</div>

	<!-- Comment Content -->
	{#if isEditing}
		<div class="space-y-2">
			<Textarea
				bind:value={editContent}
				rows={2}
				class="resize-none text-sm"
			/>
			<div class="flex gap-2">
				<Button size="sm" onclick={handleEdit}>Save</Button>
				<Button size="sm" variant="outline" onclick={() => isEditing = false}>Cancel</Button>
			</div>
		</div>
	{:else}
		<p class="text-sm whitespace-pre-wrap">{comment.content}</p>
	{/if}

	<!-- Selection indicator -->
	{#if comment.selection_range}
		<div class="mt-2 px-2 py-1 bg-yellow-100 dark:bg-yellow-900/20 rounded text-xs text-muted-foreground border-l-2 border-yellow-500">
			Selected text reference
		</div>
	{/if}

	<!-- Replies -->
	{#if replies.length > 0}
		<div class="mt-3 space-y-2 pl-4 border-l-2 border-muted">
			{#each replies as reply}
				<div class="py-2">
					<div class="flex items-center gap-2 mb-1">
						<div class="w-5 h-5 rounded-full bg-muted flex items-center justify-center text-[10px] font-medium">
							{getInitials(reply.user_name || 'U')}
						</div>
						<span class="text-xs font-medium">{reply.user_name}</span>
						<span class="text-xs text-muted-foreground">{formatDate(reply.created_at)}</span>
					</div>
					<p class="text-sm">{reply.content}</p>
				</div>
			{/each}
		</div>
	{/if}

	<!-- Reply Input -->
	{#if !comment.is_resolved}
		{#if showReplyInput}
			<div class="mt-3 flex gap-2">
				<Textarea
					placeholder="Write a reply..."
					bind:value={replyContent}
					rows={1}
					class="resize-none text-sm"
				/>
				<div class="flex flex-col gap-1">
					<Button size="sm" class="h-7" onclick={handleReply}>
						<Send class="h-3 w-3" />
					</Button>
					<Button size="sm" variant="ghost" class="h-7" onclick={() => showReplyInput = false}>
						Cancel
					</Button>
				</div>
			</div>
		{:else if onReply}
			<button
				class="mt-2 text-xs text-muted-foreground hover:text-foreground flex items-center gap-1"
				onclick={() => showReplyInput = true}
			>
				<Reply class="h-3 w-3" />
				Reply
			</button>
		{/if}
	{/if}
</div>
