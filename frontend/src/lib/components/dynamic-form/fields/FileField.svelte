<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Upload, File as FileIcon, X, Loader2, AlertCircle } from 'lucide-svelte';
	import type { FieldSettings } from '$lib/api/modules';
	import { uploadFile, deleteFile, formatFileSize, type UploadedFile } from '$lib/api/files';

	interface FileInfo {
		id?: string;
		name: string;
		size: number;
		type: string;
		url?: string;
		path?: string;
		isUploading?: boolean;
		error?: string;
	}

	interface Props {
		value: FileInfo[];
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		moduleApiName?: string;
		fieldApiName?: string;
		onchange: (value: FileInfo[]) => void;
	}

	let {
		value = $bindable([]),
		error,
		disabled = false,
		placeholder,
		required,
		settings,
		moduleApiName,
		fieldApiName,
		onchange
	}: Props = $props();

	let fileInput: HTMLInputElement;
	let uploadingCount = $state(0);

	const maxFiles = settings?.max_files || 1;
	const acceptedTypes = settings?.accepted_file_types?.join(',') || '*';
	const maxSizeMB = settings?.max_file_size || 10;

	async function handleFileSelect(event: Event) {
		const target = event.target as HTMLInputElement;
		const files = target.files;

		if (!files || files.length === 0) return;

		const filesToUpload: File[] = [];

		for (let i = 0; i < files.length && filesToUpload.length + value.length < maxFiles; i++) {
			const file = files[i];

			// Check file size
			if (file.size > maxSizeMB * 1024 * 1024) {
				alert(`File ${file.name} is too large. Maximum size is ${maxSizeMB}MB.`);
				continue;
			}

			filesToUpload.push(file);
		}

		// Reset input first
		target.value = '';

		// Upload files sequentially
		for (const file of filesToUpload) {
			await uploadSingleFile(file);
		}
	}

	async function uploadSingleFile(file: File) {
		// Add placeholder entry
		const tempId = crypto.randomUUID();
		const placeholder: FileInfo = {
			id: tempId,
			name: file.name,
			size: file.size,
			type: file.type,
			isUploading: true
		};

		value = [...value, placeholder];
		uploadingCount++;

		try {
			const uploaded = await uploadFile(file, {
				type: 'file',
				module: moduleApiName,
				field: fieldApiName
			});

			// Replace placeholder with uploaded file info
			value = value.map((f) =>
				f.id === tempId
					? {
							id: uploaded.id,
							name: uploaded.name,
							size: uploaded.size,
							type: uploaded.mime_type,
							url: uploaded.url,
							path: uploaded.path,
							isUploading: false
						}
					: f
			);
			onchange(value);
		} catch (err) {
			// Mark as error
			value = value.map((f) =>
				f.id === tempId
					? {
							...f,
							isUploading: false,
							error: err instanceof Error ? err.message : 'Upload failed'
						}
					: f
			);
		} finally {
			uploadingCount--;
		}
	}

	async function removeFile(index: number) {
		const file = value[index];

		// If file has a server path, delete from server
		if (file.path) {
			try {
				await deleteFile(file.path);
			} catch (err) {
				console.error('Failed to delete file from server:', err);
				// Continue removing from UI anyway
			}
		}

		// Revoke blob URL if exists
		if (file.url && file.url.startsWith('blob:')) {
			URL.revokeObjectURL(file.url);
		}

		value = value.filter((_, i) => i !== index);
		onchange(value);
	}

	function retryUpload(index: number) {
		const file = value[index];
		if (file.error) {
			// Remove the failed entry
			value = value.filter((_, i) => i !== index);
			// Re-upload would require the original File object, which we don't have
			// For now, user needs to select the file again
		}
	}
</script>

<div class="space-y-3">
	<input
		bind:this={fileInput}
		type="file"
		onchange={handleFileSelect}
		accept={acceptedTypes}
		multiple={maxFiles > 1}
		disabled={disabled || uploadingCount > 0}
		class="hidden"
	/>

	<Button
		type="button"
		variant="outline"
		disabled={disabled || value.length >= maxFiles || uploadingCount > 0}
		onclick={() => fileInput?.click()}
		class={`w-full ${error ? 'border-destructive' : ''} ${value.length >= maxFiles ? 'opacity-50' : ''}`}
	>
		{#if uploadingCount > 0}
			<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			Uploading...
		{:else}
			<Upload class="mr-2 h-4 w-4" />
			{value.length === 0 ? 'Upload Files' : `Upload More (${value.length}/${maxFiles})`}
		{/if}
	</Button>

	{#if value.length > 0}
		<div class="space-y-2 rounded-md border p-3">
			{#each value as file, i}
				<div
					class="flex items-center justify-between gap-2 rounded-md p-2 {file.error
						? 'bg-destructive/10'
						: 'bg-muted'}"
				>
					<div class="flex items-center gap-2 overflow-hidden">
						{#if file.isUploading}
							<Loader2 class="h-4 w-4 shrink-0 animate-spin text-muted-foreground" />
						{:else if file.error}
							<AlertCircle class="h-4 w-4 shrink-0 text-destructive" />
						{:else}
							<FileIcon class="h-4 w-4 shrink-0 text-muted-foreground" />
						{/if}
						<div class="overflow-hidden">
							<p class="truncate text-sm font-medium">{file.name}</p>
							{#if file.error}
								<p class="text-xs text-destructive">{file.error}</p>
							{:else}
								<p class="text-xs text-muted-foreground">{formatFileSize(file.size)}</p>
							{/if}
						</div>
					</div>
					<div class="flex shrink-0 gap-1">
						{#if file.url && !file.isUploading && !file.error}
							<Button
								type="button"
								variant="ghost"
								size="sm"
								onclick={() => window.open(file.url, '_blank')}
								class="h-8 px-2 text-xs"
							>
								View
							</Button>
						{/if}
						<Button
							type="button"
							variant="ghost"
							size="sm"
							disabled={disabled || file.isUploading}
							onclick={() => removeFile(i)}
							class="h-8 w-8 p-0 hover:bg-destructive/10 hover:text-destructive"
						>
							<X class="h-4 w-4" />
						</Button>
					</div>
				</div>
			{/each}
		</div>
	{/if}

	<div class="text-xs text-muted-foreground">
		Max {maxFiles} file{maxFiles !== 1 ? 's' : ''} • Up to {maxSizeMB}MB each
	</div>
</div>
