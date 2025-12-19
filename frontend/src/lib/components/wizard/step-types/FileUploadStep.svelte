<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Upload, File as FileIcon, X, CheckCircle, AlertCircle, Loader2 } from 'lucide-svelte';
	import { generateId } from '$lib/utils/id';

	interface UploadedFile {
		id: string;
		name: string;
		size: number;
		type: string;
		status: 'pending' | 'uploading' | 'success' | 'error';
		progress?: number;
		url?: string;
		error?: string;
	}

	interface Props {
		files?: UploadedFile[];
		onUpload?: (files: File[]) => Promise<void>;
		onRemove?: (fileId: string) => void;
		maxFiles?: number;
		maxFileSize?: number; // in MB
		acceptedTypes?: string[];
		multiple?: boolean;
		required?: boolean;
		title?: string;
		description?: string;
		showFileList?: boolean;
	}

	let {
		files = $bindable([]),
		onUpload,
		onRemove,
		maxFiles = 5,
		maxFileSize = 10,
		acceptedTypes = [],
		multiple = true,
		required = false,
		title = 'Upload Files',
		description = 'Drag and drop files here or click to browse',
		showFileList = true
	}: Props = $props();

	let isDragging = $state(false);
	let fileInput: HTMLInputElement;

	const canUploadMore = $derived(files.length < maxFiles);
	const acceptString = $derived(acceptedTypes.length > 0 ? acceptedTypes.join(',') : '*/*');

	function formatFileSize(bytes: number): string {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
	}

	function getFileIcon(type: string) {
		if (type.startsWith('image/')) return '🖼️';
		if (type.startsWith('video/')) return '🎥';
		if (type.startsWith('audio/')) return '🎵';
		if (type.includes('pdf')) return '📄';
		if (type.includes('doc')) return '📝';
		if (type.includes('sheet') || type.includes('excel')) return '📊';
		return '📎';
	}

	function validateFile(file: File): string | null {
		// Check file size
		if (file.size > maxFileSize * 1024 * 1024) {
			return `File size exceeds ${maxFileSize}MB`;
		}

		// Check file type if specified
		if (acceptedTypes.length > 0) {
			const isAccepted = acceptedTypes.some((type) => {
				if (type.endsWith('/*')) {
					return file.type.startsWith(type.replace('/*', ''));
				}
				return file.type === type;
			});
			if (!isAccepted) {
				return 'File type not accepted';
			}
		}

		return null;
	}

	async function handleFiles(fileList: FileList | null) {
		if (!fileList) return;

		const filesToUpload = Array.from(fileList);

		// Check max files limit
		if (files.length + filesToUpload.length > maxFiles) {
			alert(`You can only upload up to ${maxFiles} files`);
			return;
		}

		// Validate each file
		const validatedFiles: File[] = [];
		for (const file of filesToUpload) {
			const error = validateFile(file);
			if (error) {
				// Add file with error status
				files = [
					...files,
					{
						id: generateId(),
						name: file.name,
						size: file.size,
						type: file.type,
						status: 'error',
						error
					}
				];
			} else {
				validatedFiles.push(file);
			}
		}

		// Upload valid files
		if (validatedFiles.length > 0 && onUpload) {
			// Add files with pending status
			const newFiles: UploadedFile[] = validatedFiles.map((file) => ({
				id: generateId(),
				name: file.name,
				size: file.size,
				type: file.type,
				status: 'uploading',
				progress: 0
			}));
			files = [...files, ...newFiles];

			try {
				await onUpload(validatedFiles);
				// Update status to success
				files = files.map((f) =>
					newFiles.find((nf) => nf.id === f.id)
						? { ...f, status: 'success' as const, progress: 100 }
						: f
				);
			} catch (error) {
				// Update status to error
				files = files.map((f) =>
					newFiles.find((nf) => nf.id === f.id)
						? { ...f, status: 'error' as const, error: 'Upload failed' }
						: f
				);
			}
		}
	}

	function handleDragOver(e: DragEvent) {
		e.preventDefault();
		isDragging = true;
	}

	function handleDragLeave(e: DragEvent) {
		e.preventDefault();
		isDragging = false;
	}

	function handleDrop(e: DragEvent) {
		e.preventDefault();
		isDragging = false;
		handleFiles(e.dataTransfer?.files || null);
	}

	function handleRemove(fileId: string) {
		if (onRemove) {
			onRemove(fileId);
		}
		files = files.filter((f) => f.id !== fileId);
	}

	function triggerFileInput() {
		fileInput?.click();
	}
</script>

<div class="file-upload-step space-y-6">
	<!-- Upload Area -->
	<Card.Root>
		<Card.Header>
			<Card.Title>{title}</Card.Title>
			{#if description}
				<Card.Description>{description}</Card.Description>
			{/if}
		</Card.Header>
		<Card.Content>
			<!-- Drag & Drop Zone -->
			<div
				class="rounded-lg border-2 border-dashed p-8 text-center transition-colors {isDragging
					? 'border-primary bg-primary/5'
					: 'border-border hover:border-primary/50'} {canUploadMore
					? 'cursor-pointer'
					: 'cursor-not-allowed opacity-50'}"
				ondragover={canUploadMore ? handleDragOver : undefined}
				ondragleave={canUploadMore ? handleDragLeave : undefined}
				ondrop={canUploadMore ? handleDrop : undefined}
				onclick={canUploadMore ? triggerFileInput : undefined}
				role="button"
				tabindex={canUploadMore ? 0 : -1}
			>
				<Upload class="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
				<div class="space-y-2">
					<p class="text-lg font-medium">
						{#if canUploadMore}
							Drop files here or click to browse
						{:else}
							Maximum number of files reached
						{/if}
					</p>
					<p class="text-sm text-muted-foreground">
						{#if acceptedTypes.length > 0}
							Accepted: {acceptedTypes.join(', ')}
						{/if}
						{#if maxFileSize}
							· Max size: {maxFileSize}MB
						{/if}
						· Max files: {maxFiles}
					</p>
				</div>
			</div>

			<!-- Hidden File Input -->
			<input
				bind:this={fileInput}
				type="file"
				{multiple}
				accept={acceptString}
				onchange={(e) => handleFiles(e.currentTarget.files)}
				class="hidden"
			/>

			<!-- File Count -->
			{#if files.length > 0}
				<div class="mt-4 text-center text-sm text-muted-foreground">
					{files.length} of {maxFiles} files uploaded
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Uploaded Files List -->
	{#if showFileList && files.length > 0}
		<Card.Root>
			<Card.Header>
				<Card.Title>Uploaded Files</Card.Title>
			</Card.Header>
			<Card.Content>
				<div class="space-y-2">
					{#each files as file}
						<div
							class="flex items-center justify-between rounded-lg border p-3 {file.status ===
							'error'
								? 'border-destructive bg-destructive/5'
								: 'border-border bg-card'}"
						>
							<div class="flex min-w-0 flex-1 items-center gap-3">
								<!-- Icon -->
								<div class="text-2xl">{getFileIcon(file.type)}</div>

								<!-- File Info -->
								<div class="min-w-0 flex-1">
									<div class="truncate font-medium">{file.name}</div>
									<div class="text-xs text-muted-foreground">
										{formatFileSize(file.size)}
										{#if file.status === 'uploading' && file.progress !== undefined}
											· {file.progress}%
										{/if}
									</div>
									{#if file.error}
										<div class="mt-1 text-xs text-destructive">{file.error}</div>
									{/if}
								</div>

								<!-- Status Icon -->
								<div>
									{#if file.status === 'uploading'}
										<Loader2 class="h-5 w-5 animate-spin text-primary" />
									{:else if file.status === 'success'}
										<CheckCircle class="h-5 w-5 text-green-600" />
									{:else if file.status === 'error'}
										<AlertCircle class="h-5 w-5 text-destructive" />
									{/if}
								</div>
							</div>

							<!-- Remove Button -->
							<Button
								variant="ghost"
								size="sm"
								onclick={() => handleRemove(file.id)}
								disabled={file.status === 'uploading'}
								class="ml-2"
							>
								<X class="h-4 w-4" />
							</Button>
						</div>
					{/each}
				</div>
			</Card.Content>
		</Card.Root>
	{/if}
</div>
