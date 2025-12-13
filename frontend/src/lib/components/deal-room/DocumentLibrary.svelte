<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { FileText, Upload, Eye, Trash2, Download, EyeOff } from 'lucide-svelte';
	import { uploadDocument, deleteDocument, type DealRoomDocument } from '$lib/api/deal-rooms';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';

	export let roomId: number;
	export let documents: DealRoomDocument[];
	export let onUpdate: () => void;

	let uploading = false;
	let fileInput: HTMLInputElement;

	async function handleFileSelect(e: Event) {
		const input = e.target as HTMLInputElement;
		const file = input.files?.[0];
		if (!file) return;

		uploading = true;
		const { error } = await tryCatch(uploadDocument(roomId, file));
		uploading = false;

		if (error) {
			toast.error('Failed to upload document');
			return;
		}

		toast.success('Document uploaded');
		input.value = '';
		onUpdate();
	}

	async function handleDelete(doc: DealRoomDocument) {
		if (!confirm(`Delete "${doc.name}"?`)) return;

		const { error } = await tryCatch(deleteDocument(roomId, doc.id));

		if (error) {
			toast.error('Failed to delete document');
			return;
		}

		toast.success('Document deleted');
		onUpdate();
	}

	function getFileIcon(mimeType: string | null): string {
		if (!mimeType) return 'file';
		if (mimeType.includes('pdf')) return 'pdf';
		if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) return 'spreadsheet';
		if (mimeType.includes('document') || mimeType.includes('word')) return 'doc';
		if (mimeType.includes('image')) return 'image';
		return 'file';
	}
</script>

<div class="space-y-4">
	<!-- Upload Button -->
	<div class="flex justify-between items-center">
		<h3 class="font-semibold">Documents</h3>
		<div>
			<input
				bind:this={fileInput}
				type="file"
				class="hidden"
				onchange={handleFileSelect}
				accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
			/>
			<Button onclick={() => fileInput.click()} disabled={uploading}>
				<Upload class="mr-2 h-4 w-4" />
				{uploading ? 'Uploading...' : 'Upload Document'}
			</Button>
		</div>
	</div>

	<!-- Document List -->
	{#if documents.length === 0}
		<div class="text-center py-12 border-2 border-dashed rounded-lg">
			<FileText class="mx-auto h-12 w-12 text-muted-foreground/50" />
			<h3 class="mt-4 text-sm font-medium">No documents</h3>
			<p class="text-sm text-muted-foreground mt-1">Upload documents to share with stakeholders</p>
			<Button variant="outline" class="mt-4" onclick={() => fileInput.click()}>
				<Upload class="mr-2 h-4 w-4" />
				Upload
			</Button>
		</div>
	{:else}
		<div class="space-y-2">
			{#each documents as doc}
				<div class="flex items-center gap-4 p-3 rounded-lg border hover:bg-muted/50 transition-colors">
					<div class="p-2 rounded-lg bg-muted">
						<FileText class="h-5 w-5 text-muted-foreground" />
					</div>

					<div class="flex-1 min-w-0">
						<div class="flex items-center gap-2">
							<span class="font-medium text-sm truncate">{doc.name}</span>
							{#if !doc.is_visible_to_external}
								<span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 flex items-center gap-1">
									<EyeOff class="h-3 w-3" />
									Internal
								</span>
							{/if}
						</div>
						<div class="flex items-center gap-3 text-xs text-muted-foreground mt-1">
							<span>{doc.formatted_size}</span>
							<span>v{doc.version}</span>
							<span class="flex items-center gap-1">
								<Eye class="h-3 w-3" />
								{doc.view_count} views
							</span>
							{#if doc.uploaded_by}
								<span>by {doc.uploaded_by}</span>
							{/if}
						</div>
					</div>

					<div class="flex items-center gap-1">
						<Button variant="ghost" size="icon" title="Download">
							<Download class="h-4 w-4" />
						</Button>
						<Button
							variant="ghost"
							size="icon"
							class="text-destructive hover:text-destructive"
							onclick={() => handleDelete(doc)}
							title="Delete"
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>
