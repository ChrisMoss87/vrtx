<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { documentEditorStore } from '$lib/stores/documents.svelte';
	import {
		documentsApi,
		documentVersionsApi,
		documentCommentsApi,
		type DocumentVersion,
		type DocumentComment
	} from '$lib/api/collaborative-documents';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Sheet from '$lib/components/ui/sheet';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Tooltip from '$lib/components/ui/tooltip';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Label } from '$lib/components/ui/label';
	import { WordEditor } from '$lib/components/collaborative-docs';
	import {
		ArrowLeft,
		Save,
		Share2,
		MoreHorizontal,
		History,
		MessageSquare,
		Users,
		FileText,
		Table2,
		Presentation,
		Check,
		Copy,
		Download,
		Trash2,
		Star,
		Clock,
		User,
		Send,
		X,
		CheckCircle,
		Circle
	} from 'lucide-svelte';

	// Get document ID from URL
	const documentId = $derived(parseInt($page.params.id ?? '0'));

	// Local state
	let showShareSheet = $state(false);
	let showVersionsSheet = $state(false);
	let showCommentsSheet = $state(false);
	let versions = $state<DocumentVersion[]>([]);
	let comments = $state<DocumentComment[]>([]);
	let newVersionLabel = $state('');
	let newComment = $state('');
	let loadingVersions = $state(false);
	let loadingComments = $state(false);
	let isEditingTitle = $state(false);
	let editedTitle = $state('');
	let shareUrl = $state('');

	// Editor state
	let editorContent = $state('');
	let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;
	let hasUnsavedChanges = $state(false);

	onMount(async () => {
		await loadDocument();
		await joinSession();
		// Initialize editor content from document
		editorContent = documentEditorStore.document?.html_snapshot || '';
	});

	onDestroy(async () => {
		// Save any pending changes before leaving
		if (hasUnsavedChanges) {
			await saveDocument();
		}
		if (autoSaveTimer) {
			clearTimeout(autoSaveTimer);
		}
		await leaveSession();
	});

	// Handle editor content changes
	function handleEditorChange(html: string, text: string) {
		hasUnsavedChanges = true;

		// Clear existing timer
		if (autoSaveTimer) {
			clearTimeout(autoSaveTimer);
		}

		// Auto-save after 3 seconds of inactivity
		autoSaveTimer = setTimeout(() => {
			saveDocument();
		}, 3000);
	}

	async function saveDocument() {
		if (!hasUnsavedChanges) return;

		try {
			await documentEditorStore.saveContent(documentId, editorContent);
			hasUnsavedChanges = false;
		} catch (err) {
			console.error('Failed to save document:', err);
		}
	}

	async function loadDocument() {
		try {
			await documentEditorStore.loadDocument(documentId);
			await documentEditorStore.loadShareSettings(documentId);
			await documentEditorStore.loadCollaborators(documentId);
		} catch (err) {
			console.error('Failed to load document:', err);
		}
	}

	async function joinSession() {
		try {
			await documentEditorStore.joinSession(documentId);
		} catch (err) {
			console.error('Failed to join session:', err);
		}
	}

	async function leaveSession() {
		try {
			await documentEditorStore.leaveSession(documentId);
			documentEditorStore.clearDocument();
		} catch (err) {
			console.error('Failed to leave session:', err);
		}
	}

	async function loadVersions() {
		loadingVersions = true;
		try {
			const response = await documentVersionsApi.list(documentId);
			versions = response.data;
		} catch (err) {
			console.error('Failed to load versions:', err);
		} finally {
			loadingVersions = false;
		}
	}

	async function loadComments() {
		loadingComments = true;
		try {
			const response = await documentCommentsApi.list(documentId);
			comments = response.data;
		} catch (err) {
			console.error('Failed to load comments:', err);
		} finally {
			loadingComments = false;
		}
	}

	async function createVersion() {
		if (!newVersionLabel.trim()) return;

		try {
			await documentVersionsApi.create(documentId, newVersionLabel);
			newVersionLabel = '';
			await loadVersions();
		} catch (err) {
			console.error('Failed to create version:', err);
		}
	}

	async function restoreVersion(versionNumber: number) {
		if (confirm('Are you sure you want to restore this version? Current changes will be preserved as an auto-save.')) {
			try {
				await documentVersionsApi.restore(documentId, versionNumber);
				await loadDocument();
			} catch (err) {
				console.error('Failed to restore version:', err);
			}
		}
	}

	async function addComment() {
		if (!newComment.trim()) return;

		try {
			await documentCommentsApi.create(documentId, { content: newComment });
			newComment = '';
			await loadComments();
		} catch (err) {
			console.error('Failed to add comment:', err);
		}
	}

	async function resolveComment(commentId: number) {
		try {
			await documentCommentsApi.resolve(documentId, commentId);
			await loadComments();
		} catch (err) {
			console.error('Failed to resolve comment:', err);
		}
	}

	async function updateTitle() {
		if (!editedTitle.trim() || editedTitle === documentEditorStore.document?.title) {
			isEditingTitle = false;
			return;
		}

		try {
			await documentsApi.update(documentId, { title: editedTitle });
			await loadDocument();
			isEditingTitle = false;
		} catch (err) {
			console.error('Failed to update title:', err);
		}
	}

	async function copyShareUrl() {
		if (documentEditorStore.shareSettings?.share_url) {
			await navigator.clipboard.writeText(documentEditorStore.shareSettings.share_url);
			// TODO: Show toast
		}
	}

	async function enableSharing() {
		try {
			const result = await documentEditorStore.enableLinkSharing(documentId, 'view');
			shareUrl = result.share_url || '';
		} catch (err) {
			console.error('Failed to enable sharing:', err);
		}
	}

	async function disableSharing() {
		try {
			await documentEditorStore.disableLinkSharing(documentId);
			shareUrl = '';
		} catch (err) {
			console.error('Failed to disable sharing:', err);
		}
	}

	function getDocumentIcon(type: string | undefined) {
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

	function formatDate(dateStr: string | null) {
		if (!dateStr) return '';
		return new Date(dateStr).toLocaleString();
	}

	function getUserInitials(name: string) {
		return name
			.split(' ')
			.map((n) => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}
</script>

<svelte:head>
	<title>{documentEditorStore.document?.title || 'Document'} | VRTX</title>
</svelte:head>

{#if documentEditorStore.loading}
	<div class="flex items-center justify-center h-screen">
		<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
	</div>
{:else if documentEditorStore.error}
	<div class="flex flex-col items-center justify-center h-screen gap-4">
		<p class="text-destructive">{documentEditorStore.error}</p>
		<Button onclick={() => goto('/documents')}>Back to Documents</Button>
	</div>
{:else if documentEditorStore.document}
	{@const doc = documentEditorStore.document}
	{@const DocIcon = getDocumentIcon(doc.type)}

	<div class="flex flex-col h-screen">
		<!-- Header -->
		<header class="flex items-center justify-between px-4 py-2 border-b bg-background">
			<div class="flex items-center gap-3">
				<Button variant="ghost" size="sm" onclick={() => goto('/documents')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>

				<DocIcon class="h-5 w-5 text-primary" />

				{#if isEditingTitle}
					<Input
						bind:value={editedTitle}
						class="w-64 h-8"
						onblur={updateTitle}
						onkeydown={(e) => {
							if (e.key === 'Enter') updateTitle();
							if (e.key === 'Escape') isEditingTitle = false;
						}}
						autofocus
					/>
				{:else}
					<button
						class="text-lg font-medium hover:bg-accent/50 px-2 py-1 rounded"
						onclick={() => {
							editedTitle = doc.title;
							isEditingTitle = true;
						}}
					>
						{doc.title}
					</button>
				{/if}

				{#if documentEditorStore.saving}
					<span class="text-xs text-muted-foreground flex items-center gap-1">
						<div class="animate-spin rounded-full h-3 w-3 border-b border-muted-foreground"></div>
						Saving...
					</span>
				{:else if documentEditorStore.lastSavedAt}
					<span class="text-xs text-muted-foreground flex items-center gap-1">
						<Check class="h-3 w-3" />
						Saved
					</span>
				{/if}
			</div>

			<div class="flex items-center gap-2">
				<!-- Active Collaborators -->
				{#if documentEditorStore.activeCollaborators.length > 0}
					<div class="flex items-center -space-x-2 mr-2">
						{#each documentEditorStore.activeCollaborators.slice(0, 5) as collaborator}
							<Tooltip.Root>
								<Tooltip.Trigger>
									<div
										class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium text-white border-2 border-background"
										style="background-color: {collaborator.color}"
									>
										{getUserInitials(collaborator.name)}
									</div>
								</Tooltip.Trigger>
								<Tooltip.Content>
									{collaborator.name}
								</Tooltip.Content>
							</Tooltip.Root>
						{/each}
						{#if documentEditorStore.activeCollaborators.length > 5}
							<div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-medium bg-muted border-2 border-background">
								+{documentEditorStore.activeCollaborators.length - 5}
							</div>
						{/if}
					</div>
				{/if}

				<!-- Comments -->
				<Button
					variant="ghost"
					size="sm"
					onclick={() => {
						showCommentsSheet = true;
						loadComments();
					}}
				>
					<MessageSquare class="h-4 w-4 mr-1" />
					{doc.comment_count || 0}
				</Button>

				<!-- History -->
				<Button
					variant="ghost"
					size="sm"
					onclick={() => {
						showVersionsSheet = true;
						loadVersions();
					}}
				>
					<History class="h-4 w-4" />
				</Button>

				<!-- Share -->
				<Button
					variant="default"
					size="sm"
					onclick={() => showShareSheet = true}
				>
					<Share2 class="h-4 w-4 mr-1" />
					Share
				</Button>

				<!-- More Options -->
				<DropdownMenu.Root>
					<DropdownMenu.Trigger>
						<Button variant="ghost" size="sm">
							<MoreHorizontal class="h-4 w-4" />
						</Button>
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end">
						<DropdownMenu.Item>
							<Download class="mr-2 h-4 w-4" />
							Download
						</DropdownMenu.Item>
						<DropdownMenu.Item>
							<Copy class="mr-2 h-4 w-4" />
							Make a copy
						</DropdownMenu.Item>
						<DropdownMenu.Item>
							<Star class="mr-2 h-4 w-4" />
							Save as template
						</DropdownMenu.Item>
						<DropdownMenu.Separator />
						<DropdownMenu.Item class="text-destructive">
							<Trash2 class="mr-2 h-4 w-4" />
							Delete
						</DropdownMenu.Item>
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			</div>
		</header>

		<!-- Editor Area -->
		<main class="flex-1 overflow-hidden">
			{#if doc.type === 'word'}
				<WordEditor
					bind:content={editorContent}
					onchange={handleEditorChange}
					onsave={saveDocument}
					readonly={doc.user_permission === 'view'}
					class="h-full"
				/>
			{:else if doc.type === 'spreadsheet'}
				<div class="h-full flex items-center justify-center bg-muted/30">
					<div class="text-center text-muted-foreground">
						<Table2 class="mx-auto h-16 w-16 mb-4 opacity-50" />
						<h3 class="text-lg font-medium">Spreadsheet Editor</h3>
						<p class="text-sm mt-2">Spreadsheet editing coming soon</p>
					</div>
				</div>
			{:else if doc.type === 'presentation'}
				<div class="h-full flex items-center justify-center bg-muted/30">
					<div class="text-center text-muted-foreground">
						<Presentation class="mx-auto h-16 w-16 mb-4 opacity-50" />
						<h3 class="text-lg font-medium">Presentation Editor</h3>
						<p class="text-sm mt-2">Presentation editing coming soon</p>
					</div>
				</div>
			{:else}
				<div class="h-full flex items-center justify-center bg-muted/30">
					<div class="text-center text-muted-foreground">
						<DocIcon class="mx-auto h-16 w-16 mb-4 opacity-50" />
						<h3 class="text-lg font-medium">Unknown document type</h3>
					</div>
				</div>
			{/if}
		</main>
	</div>

	<!-- Share Sheet -->
	<Sheet.Root bind:open={showShareSheet}>
		<Sheet.Content side="right" class="w-96">
			<Sheet.Header>
				<Sheet.Title>Share "{doc.title}"</Sheet.Title>
				<Sheet.Description>
					Add people or create a link to share this document.
				</Sheet.Description>
			</Sheet.Header>
			<div class="py-4 space-y-6">
				<!-- People with access -->
				<div>
					<h4 class="text-sm font-medium mb-3">People with access</h4>
					<div class="space-y-2">
						<!-- Owner -->
						<div class="flex items-center justify-between py-2">
							<div class="flex items-center gap-3">
								<div class="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-xs text-primary-foreground">
									{getUserInitials(doc.owner?.name || 'O')}
								</div>
								<div>
									<p class="text-sm font-medium">{doc.owner?.name || 'Unknown'}</p>
									<p class="text-xs text-muted-foreground">{doc.owner?.email || ''}</p>
								</div>
							</div>
							<span class="text-xs text-muted-foreground">Owner</span>
						</div>

						<!-- Collaborators -->
						{#each documentEditorStore.collaborators as collab}
							<div class="flex items-center justify-between py-2">
								<div class="flex items-center gap-3">
									<div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center text-xs">
										{getUserInitials(collab.user_name || 'U')}
									</div>
									<div>
										<p class="text-sm font-medium">{collab.user_name || 'Unknown'}</p>
										<p class="text-xs text-muted-foreground">{collab.user_email || ''}</p>
									</div>
								</div>
								<select
									class="text-xs border rounded px-2 py-1"
									value={collab.permission}
									onchange={(e) => documentEditorStore.updateCollaboratorPermission(documentId, collab.user_id, e.currentTarget.value as 'view' | 'comment' | 'edit')}
								>
									<option value="view">Viewer</option>
									<option value="comment">Commenter</option>
									<option value="edit">Editor</option>
								</select>
							</div>
						{/each}
					</div>
				</div>

				<!-- Link sharing -->
				<div>
					<h4 class="text-sm font-medium mb-3">Get link</h4>
					{#if documentEditorStore.shareSettings?.enabled}
						<div class="space-y-3">
							<div class="flex items-center gap-2">
								<Input
									value={documentEditorStore.shareSettings.share_url || ''}
									readonly
									class="text-sm"
								/>
								<Button size="sm" variant="outline" onclick={copyShareUrl}>
									<Copy class="h-4 w-4" />
								</Button>
							</div>
							<div class="flex items-center justify-between text-sm">
								<span class="text-muted-foreground">
									Anyone with the link can {documentEditorStore.shareSettings.permission}
								</span>
								<Button size="sm" variant="ghost" class="text-destructive" onclick={disableSharing}>
									Remove link
								</Button>
							</div>
						</div>
					{:else}
						<Button variant="outline" class="w-full" onclick={enableSharing}>
							<Share2 class="mr-2 h-4 w-4" />
							Create shareable link
						</Button>
					{/if}
				</div>
			</div>
		</Sheet.Content>
	</Sheet.Root>

	<!-- Versions Sheet -->
	<Sheet.Root bind:open={showVersionsSheet}>
		<Sheet.Content side="right" class="w-96">
			<Sheet.Header>
				<Sheet.Title>Version History</Sheet.Title>
				<Sheet.Description>
					View and restore previous versions of this document.
				</Sheet.Description>
			</Sheet.Header>
			<div class="py-4 space-y-4">
				<!-- Create new version -->
				<div class="flex gap-2">
					<Input
						placeholder="Version label..."
						bind:value={newVersionLabel}
						onkeydown={(e) => e.key === 'Enter' && createVersion()}
					/>
					<Button onclick={createVersion} disabled={!newVersionLabel.trim()}>Save</Button>
				</div>

				<!-- Version list -->
				{#if loadingVersions}
					<div class="flex justify-center py-4">
						<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
					</div>
				{:else}
					<div class="space-y-2">
						{#each versions as version}
							<div class="flex items-center justify-between p-3 rounded-lg border hover:bg-accent/50">
								<div>
									<p class="text-sm font-medium">
										{version.label || `Version ${version.version_number}`}
										{#if version.is_auto_save}
											<span class="text-xs text-muted-foreground ml-1">(Auto-save)</span>
										{/if}
									</p>
									<p class="text-xs text-muted-foreground">
										{formatDate(version.created_at)}
									</p>
								</div>
								<Button
									size="sm"
									variant="ghost"
									onclick={() => restoreVersion(version.version_number)}
								>
									Restore
								</Button>
							</div>
						{/each}

						{#if versions.length === 0}
							<div class="text-center py-8 text-muted-foreground">
								<History class="mx-auto h-8 w-8 opacity-50 mb-2" />
								<p class="text-sm">No versions yet</p>
							</div>
						{/if}
					</div>
				{/if}
			</div>
		</Sheet.Content>
	</Sheet.Root>

	<!-- Comments Sheet -->
	<Sheet.Root bind:open={showCommentsSheet}>
		<Sheet.Content side="right" class="w-96">
			<Sheet.Header>
				<Sheet.Title>Comments</Sheet.Title>
				<Sheet.Description>
					View and add comments to this document.
				</Sheet.Description>
			</Sheet.Header>
			<div class="py-4 flex flex-col h-full">
				<!-- Add comment -->
				<div class="flex gap-2 mb-4">
					<Textarea
						placeholder="Add a comment..."
						bind:value={newComment}
						rows={2}
						class="resize-none"
					/>
					<Button onclick={addComment} disabled={!newComment.trim()} size="icon">
						<Send class="h-4 w-4" />
					</Button>
				</div>

				<!-- Comments list -->
				{#if loadingComments}
					<div class="flex justify-center py-4">
						<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-primary"></div>
					</div>
				{:else}
					<div class="space-y-3 flex-1 overflow-y-auto">
						{#each comments as comment}
							<div class="p-3 rounded-lg border {comment.is_resolved ? 'opacity-60' : ''}">
								<div class="flex items-start justify-between mb-2">
									<div class="flex items-center gap-2">
										<div class="w-6 h-6 rounded-full bg-muted flex items-center justify-center text-xs">
											{getUserInitials(comment.user_name || 'U')}
										</div>
										<div>
											<p class="text-sm font-medium">{comment.user_name}</p>
											<p class="text-xs text-muted-foreground">{formatDate(comment.created_at)}</p>
										</div>
									</div>
									{#if !comment.is_resolved}
										<Button
											size="sm"
											variant="ghost"
											onclick={() => resolveComment(comment.id)}
										>
											<CheckCircle class="h-4 w-4" />
										</Button>
									{:else}
										<Circle class="h-4 w-4 text-muted-foreground" />
									{/if}
								</div>
								<p class="text-sm">{comment.content}</p>
								{#if comment.reply_count && comment.reply_count > 0}
									<p class="text-xs text-muted-foreground mt-2">
										{comment.reply_count} {comment.reply_count === 1 ? 'reply' : 'replies'}
									</p>
								{/if}
							</div>
						{/each}

						{#if comments.length === 0}
							<div class="text-center py-8 text-muted-foreground">
								<MessageSquare class="mx-auto h-8 w-8 opacity-50 mb-2" />
								<p class="text-sm">No comments yet</p>
							</div>
						{/if}
					</div>
				{/if}
			</div>
		</Sheet.Content>
	</Sheet.Root>
{/if}
