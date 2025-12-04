<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Upload, X, FileIcon, Loader2 } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface UploadedFile {
		name: string;
		size: number;
		url?: string;
		path?: string;
	}

	interface Props {
		label?: string;
		name: string;
		value?: string | string[];
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		accept?: string;
		multiple?: boolean;
		maxSize?: number; // in MB
		maxFiles?: number;
		onchange?: (value: string | string[] | undefined) => void;
		onUpload?: (files: File[]) => Promise<UploadedFile[]>;
	}

	let {
		label,
		name,
		value = $bindable(),
		description,
		error,
		required = false,
		disabled = false,
		width = 100,
		class: className,
		accept,
		multiple = false,
		maxSize = 10, // 10MB default
		maxFiles = 5,
		onchange,
		onUpload
	}: Props = $props();

	let fileInput: HTMLInputElement;
	let uploadedFiles = $state<UploadedFile[]>([]);
	let uploading = $state(false);
	let dragOver = $state(false);

	// Initialize uploaded files from value
	$effect(() => {
		if (value) {
			const paths = Array.isArray(value) ? value : [value];
			uploadedFiles = paths.map((path) => ({
				name: path.split('/').pop() || 'file',
				size: 0,
				path
			}));
		}
	});

	async function handleFileSelect(event: Event) {
		const input = event.target as HTMLInputElement;
		const files = Array.from(input.files || []);
		await handleFiles(files);
	}

	async function handleDrop(event: DragEvent) {
		event.preventDefault();
		dragOver = false;

		if (disabled) return;

		const files = Array.from(event.dataTransfer?.files || []);
		await handleFiles(files);
	}

	async function handleFiles(files: File[]) {
		if (disabled || files.length === 0) return;

		// Validate file count
		if (!multiple && files.length > 1) {
			files = [files[0]];
		}

		if (maxFiles && uploadedFiles.length + files.length > maxFiles) {
			alert(`Maximum ${maxFiles} files allowed`);
			return;
		}

		// Validate file sizes
		const oversizedFiles = files.filter((f) => f.size > maxSize * 1024 * 1024);
		if (oversizedFiles.length > 0) {
			alert(`File size must be less than ${maxSize}MB`);
			return;
		}

		// Upload files
		uploading = true;
		try {
			if (onUpload) {
				const uploaded = await onUpload(files);
				uploadedFiles = [...uploadedFiles, ...uploaded];

				// Update value
				const paths = uploadedFiles.map((f) => f.path || '');
				value = multiple ? paths : paths[0];
				onchange?.(value);
			} else {
				// No upload handler - just show file names
				const fileData: UploadedFile[] = files.map((f) => ({
					name: f.name,
					size: f.size
				}));
				uploadedFiles = [...uploadedFiles, ...fileData];
			}
		} catch (err) {
			console.error('File upload error:', err);
			alert('Failed to upload file');
		} finally {
			uploading = false;
		}

		// Reset input
		if (fileInput) {
			fileInput.value = '';
		}
	}

	function removeFile(index: number) {
		uploadedFiles = uploadedFiles.filter((_, i) => i !== index);

		// Update value
		const paths = uploadedFiles.map((f) => f.path || '');
		value = multiple ? paths : paths.length > 0 ? paths[0] : undefined;
		onchange?.(value);
	}

	function formatFileSize(bytes: number): string {
		if (bytes === 0) return '0 Bytes';
		const k = 1024;
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(k));
		return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
	}

	function handleDragOver(event: DragEvent) {
		event.preventDefault();
		if (!disabled) {
			dragOver = true;
		}
	}

	function handleDragLeave() {
		dragOver = false;
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} {width} class={className}>
	{#snippet children(props)}
		<div class="w-full space-y-2">
			<!-- Upload Area -->
			<div
				role="button"
				tabindex="0"
				class={cn(
					'cursor-pointer rounded-lg border-2 border-dashed p-6 text-center transition-colors',
					dragOver && 'border-primary bg-primary/5',
					!dragOver && 'border-muted-foreground/25 hover:border-muted-foreground/50',
					disabled && 'cursor-not-allowed opacity-50',
					error && 'border-destructive'
				)}
				ondragover={handleDragOver}
				ondragleave={handleDragLeave}
				ondrop={handleDrop}
				onclick={() => !disabled && fileInput.click()}
				onkeydown={(e) => {
					if ((e.key === 'Enter' || e.key === ' ') && !disabled) {
						e.preventDefault();
						fileInput.click();
					}
				}}
			>
				{#if uploading}
					<Loader2 class="mx-auto mb-2 h-8 w-8 animate-spin text-muted-foreground" />
					<p class="text-sm text-muted-foreground">Uploading...</p>
				{:else}
					<Upload class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
					<p class="mb-1 text-sm text-muted-foreground">Click to upload or drag and drop</p>
					<p class="text-xs text-muted-foreground">
						{#if accept}
							{accept.replace(/,/g, ', ')}
						{:else}
							Any file type
						{/if}
						(max {maxSize}MB)
					</p>
				{/if}
			</div>

			<!-- Hidden file input -->
			<Input
				{...props}
				bind:this={fileInput}
				type="file"
				{accept}
				{multiple}
				{disabled}
				onchange={handleFileSelect}
				class="hidden"
			/>

			<!-- Uploaded Files List -->
			{#if uploadedFiles.length > 0}
				<div class="space-y-2">
					{#each uploadedFiles as file, index (index)}
						<div class="flex items-center justify-between rounded-lg border bg-muted/50 p-3">
							<div class="flex min-w-0 flex-1 items-center gap-3">
								<FileIcon class="h-5 w-5 shrink-0 text-muted-foreground" />
								<div class="min-w-0 flex-1">
									<p class="truncate text-sm font-medium">{file.name}</p>
									{#if file.size > 0}
										<p class="text-xs text-muted-foreground">
											{formatFileSize(file.size)}
										</p>
									{/if}
								</div>
							</div>
							{#if !disabled}
								<Button
									type="button"
									variant="ghost"
									size="sm"
									onclick={() => removeFile(index)}
									class="h-8 w-8 shrink-0 p-0"
								>
									<X class="h-4 w-4" />
								</Button>
							{/if}
						</div>
					{/each}
				</div>
			{/if}
		</div>
	{/snippet}
</FieldBase>
