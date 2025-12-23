<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Avatar, AvatarFallback } from '$lib/components/ui/avatar';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import * as Sheet from '$lib/components/ui/sheet';
	import {
		MessageSquare,
		Send,
		Reply,
		Check,
		RotateCcw,
		Loader2,
		Trash2
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { apiClient } from '$lib/api/client';

	interface Comment {
		id: number;
		dashboard_id: number;
		widget_id: number | null;
		user_id: number;
		parent_id: number | null;
		content: string;
		resolved: boolean;
		created_at: string;
		user?: { id: number; name: string; email: string };
		replies?: Comment[];
	}

	interface Props {
		dashboardId: number;
		widgetId?: number | null;
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
	}

	let { dashboardId, widgetId = null, open = $bindable(false), onOpenChange }: Props = $props();

	let comments = $state<Comment[]>([]);
	let loading = $state(false);
	let newComment = $state('');
	let replyingTo = $state<number | null>(null);
	let replyContent = $state('');
	let submitting = $state(false);

	$effect(() => {
		if (open) {
			loadComments();
		}
	});

	async function loadComments() {
		loading = true;
		try {
			const params = widgetId ? `?widget_id=${widgetId}` : '';
			const response = await apiClient.get<{ data: Comment[] }>(
				`/dashboards/${dashboardId}/comments${params}`
			);
			comments = response.data;
		} catch (error) {
			console.error('Failed to load comments:', error);
			toast.error('Failed to load comments');
		} finally {
			loading = false;
		}
	}

	async function submitComment() {
		if (!newComment.trim()) return;

		submitting = true;
		try {
			const response = await apiClient.post<{ data: Comment }>(
				`/dashboards/${dashboardId}/comments`,
				{
					widget_id: widgetId,
					content: newComment.trim()
				}
			);
			comments = [...comments, response.data];
			newComment = '';
			toast.success('Comment added');
		} catch (error) {
			console.error('Failed to add comment:', error);
			toast.error('Failed to add comment');
		} finally {
			submitting = false;
		}
	}

	async function submitReply(parentId: number) {
		if (!replyContent.trim()) return;

		submitting = true;
		try {
			const response = await apiClient.post<{ data: Comment }>(
				`/dashboards/${dashboardId}/comments`,
				{
					parent_id: parentId,
					content: replyContent.trim()
				}
			);
			// Add reply to parent comment
			comments = comments.map((c) => {
				if (c.id === parentId) {
					return { ...c, replies: [...(c.replies || []), response.data] };
				}
				return c;
			});
			replyContent = '';
			replyingTo = null;
			toast.success('Reply added');
		} catch (error) {
			console.error('Failed to add reply:', error);
			toast.error('Failed to add reply');
		} finally {
			submitting = false;
		}
	}

	async function toggleResolved(comment: Comment) {
		try {
			await apiClient.post(`/dashboards/${dashboardId}/comments/${comment.id}/toggle-resolved`);
			comments = comments.map((c) =>
				c.id === comment.id ? { ...c, resolved: !c.resolved } : c
			);
			toast.success(comment.resolved ? 'Comment reopened' : 'Comment resolved');
		} catch (error) {
			console.error('Failed to toggle resolved:', error);
			toast.error('Failed to update comment');
		}
	}

	async function deleteComment(comment: Comment) {
		try {
			await apiClient.delete(`/dashboards/${dashboardId}/comments/${comment.id}`);
			comments = comments.filter((c) => c.id !== comment.id);
			toast.success('Comment deleted');
		} catch (error) {
			console.error('Failed to delete comment:', error);
			toast.error('Failed to delete comment');
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		if (!value) {
			replyingTo = null;
			replyContent = '';
		}
		onOpenChange?.(value);
	}

	function getInitials(name: string): string {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}

	function formatDate(dateString: string): string {
		const date = new Date(dateString);
		return date.toLocaleString();
	}

	const unresolvedCount = $derived(comments.filter((c) => !c.resolved).length);
</script>

<Sheet.Root bind:open onOpenChange={handleOpenChange}>
	<Sheet.Content side="right" class="w-96 flex flex-col">
		<Sheet.Header>
			<Sheet.Title class="flex items-center gap-2">
				<MessageSquare class="h-5 w-5" />
				Comments
				{#if unresolvedCount > 0}
					<Badge variant="secondary">{unresolvedCount} open</Badge>
				{/if}
			</Sheet.Title>
			<Sheet.Description>
				{widgetId ? 'Comments on this widget' : 'Dashboard comments'}
			</Sheet.Description>
		</Sheet.Header>

		<div class="flex-1 overflow-hidden flex flex-col py-4">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else}
				<ScrollArea class="flex-1">
					<div class="space-y-4 pr-4">
						{#if comments.length === 0}
							<div class="flex flex-col items-center justify-center py-12 text-center">
								<MessageSquare class="h-12 w-12 text-muted-foreground mb-4" />
								<h3 class="font-medium">No comments yet</h3>
								<p class="text-sm text-muted-foreground mt-1">
									Start a discussion below
								</p>
							</div>
						{:else}
							{#each comments as comment}
								<div
									class="rounded-lg border p-3 {comment.resolved
										? 'bg-muted/30 opacity-75'
										: ''}"
								>
									<div class="flex items-start gap-3">
										<Avatar class="h-8 w-8">
											<AvatarFallback class="text-xs">
												{getInitials(comment.user?.name || 'User')}
											</AvatarFallback>
										</Avatar>
										<div class="flex-1 min-w-0">
											<div class="flex items-center justify-between gap-2">
												<span class="font-medium text-sm">
													{comment.user?.name || 'Unknown'}
												</span>
												{#if comment.resolved}
													<Badge variant="outline" class="text-xs">Resolved</Badge>
												{/if}
											</div>
											<p class="text-sm mt-1 whitespace-pre-wrap">{comment.content}</p>
											<p class="text-xs text-muted-foreground mt-2">
												{formatDate(comment.created_at)}
											</p>

											<!-- Actions -->
											<div class="flex items-center gap-2 mt-2">
												<Button
													variant="ghost"
													size="sm"
													class="h-7 text-xs"
													onclick={() => {
														replyingTo = replyingTo === comment.id ? null : comment.id;
													}}
												>
													<Reply class="mr-1 h-3 w-3" />
													Reply
												</Button>
												<Button
													variant="ghost"
													size="sm"
													class="h-7 text-xs"
													onclick={() => toggleResolved(comment)}
												>
													{#if comment.resolved}
														<RotateCcw class="mr-1 h-3 w-3" />
														Reopen
													{:else}
														<Check class="mr-1 h-3 w-3" />
														Resolve
													{/if}
												</Button>
												<Button
													variant="ghost"
													size="sm"
													class="h-7 text-xs text-destructive"
													onclick={() => deleteComment(comment)}
												>
													<Trash2 class="h-3 w-3" />
												</Button>
											</div>

											<!-- Reply form -->
											{#if replyingTo === comment.id}
												<div class="mt-3 space-y-2">
													<Textarea
														bind:value={replyContent}
														placeholder="Write a reply..."
														rows={2}
														class="text-sm"
													/>
													<div class="flex gap-2">
														<Button
															size="sm"
															class="h-7"
															onclick={() => submitReply(comment.id)}
															disabled={submitting}
														>
															{#if submitting}
																<Loader2 class="mr-1 h-3 w-3 animate-spin" />
															{:else}
																<Send class="mr-1 h-3 w-3" />
															{/if}
															Reply
														</Button>
														<Button
															variant="ghost"
															size="sm"
															class="h-7"
															onclick={() => {
																replyingTo = null;
																replyContent = '';
															}}
														>
															Cancel
														</Button>
													</div>
												</div>
											{/if}

											<!-- Replies -->
											{#if comment.replies && comment.replies.length > 0}
												<div class="mt-3 ml-4 space-y-3 border-l-2 pl-3">
													{#each comment.replies as reply}
														<div class="flex items-start gap-2">
															<Avatar class="h-6 w-6">
																<AvatarFallback class="text-xs">
																	{getInitials(reply.user?.name || 'User')}
																</AvatarFallback>
															</Avatar>
															<div class="flex-1 min-w-0">
																<span class="text-xs font-medium">
																	{reply.user?.name || 'Unknown'}
																</span>
																<p class="text-sm whitespace-pre-wrap">{reply.content}</p>
																<p class="text-xs text-muted-foreground">
																	{formatDate(reply.created_at)}
																</p>
															</div>
														</div>
													{/each}
												</div>
											{/if}
										</div>
									</div>
								</div>
							{/each}
						{/if}
					</div>
				</ScrollArea>

				<!-- New comment form -->
				<div class="mt-4 space-y-2 border-t pt-4">
					<Textarea
						bind:value={newComment}
						placeholder="Add a comment..."
						rows={3}
						class="text-sm"
					/>
					<Button
						class="w-full"
						onclick={submitComment}
						disabled={submitting || !newComment.trim()}
					>
						{#if submitting}
							<Loader2 class="mr-2 h-4 w-4 animate-spin" />
						{:else}
							<Send class="mr-2 h-4 w-4" />
						{/if}
						Post Comment
					</Button>
				</div>
			{/if}
		</div>
	</Sheet.Content>
</Sheet.Root>
