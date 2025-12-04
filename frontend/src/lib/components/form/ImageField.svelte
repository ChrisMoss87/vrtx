<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Upload, X, Loader2, Image as ImageIcon } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface UploadedImage {
		name: string;
		size: number;
		url?: string;
		path?: string;
		preview?: string; // Data URL for preview
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
		multiple?: boolean;
		maxSize?: number; // in MB
		maxImages?: number;
		onchange?: (value: string | string[] | undefined) => void;
		onUpload?: (files: File[]) => Promise<UploadedImage[]>;
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
		multiple = false,
		maxSize = 5, // 5MB default for images
		maxImages = 10,
		onchange,
		onUpload
	}: Props = $props();

	let fileInput: HTMLInputElement;
	let uploadedImages = $state<UploadedImage[]>([]);
	let uploading = $state(false);
	let dragOver = $state(false);

	// Initialize uploaded images from value
	$effect(() => {
		if (value) {
			const paths = Array.isArray(value) ? value : [value];
			uploadedImages = paths.map((path) => ({
				name: path.split('/').pop() || 'image',
				size: 0,
				path,
				url: path // Assume path is accessible URL
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

		if (maxImages && uploadedImages.length + files.length > maxImages) {
			alert(`Maximum ${maxImages} images allowed`);
			return;
		}

		// Validate file types (images only)
		const invalidFiles = files.filter((f) => !f.type.startsWith('image/'));
		if (invalidFiles.length > 0) {
			alert('Only image files are allowed');
			return;
		}

		// Validate file sizes
		const oversizedFiles = files.filter((f) => f.size > maxSize * 1024 * 1024);
		if (oversizedFiles.length > 0) {
			alert(`Image size must be less than ${maxSize}MB`);
			return;
		}

		// Create preview URLs
		const previews = await Promise.all(
			files.map((file) => {
				return new Promise<UploadedImage>((resolve) => {
					const reader = new FileReader();
					reader.onload = (e) => {
						resolve({
							name: file.name,
							size: file.size,
							preview: e.target?.result as string
						});
					};
					reader.readAsDataURL(file);
				});
			})
		);

		// Upload files
		uploading = true;
		try {
			if (onUpload) {
				const uploaded = await onUpload(files);
				uploadedImages = [...uploadedImages, ...uploaded];

				// Update value
				const paths = uploadedImages.map((img) => img.path || img.url || '');
				value = multiple ? paths : paths[0];
				onchange?.(value);
			} else {
				// No upload handler - just show previews
				uploadedImages = [...uploadedImages, ...previews];
			}
		} catch (err) {
			console.error('Image upload error:', err);
			alert('Failed to upload image');
		} finally {
			uploading = false;
		}

		// Reset input
		if (fileInput) {
			fileInput.value = '';
		}
	}

	function removeImage(index: number) {
		uploadedImages = uploadedImages.filter((_, i) => i !== index);

		// Update value
		const paths = uploadedImages.map((img) => img.path || img.url || '');
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
		<div class="w-full space-y-3">
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
					<ImageIcon class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
					<p class="mb-1 text-sm text-muted-foreground">Click to upload or drag and drop</p>
					<p class="text-xs text-muted-foreground">
						PNG, JPG, GIF, WebP (max {maxSize}MB)
					</p>
				{/if}
			</div>

			<!-- Hidden file input -->
			<Input
				{...props}
				bind:this={fileInput}
				type="file"
				accept="image/*"
				{multiple}
				{disabled}
				onchange={handleFileSelect}
				class="hidden"
			/>

			<!-- Uploaded Images Grid -->
			{#if uploadedImages.length > 0}
				<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
					{#each uploadedImages as image, index (index)}
						<div class="group relative aspect-square overflow-hidden rounded-lg border bg-muted">
							<!-- Image Preview -->
							<img
								src={image.preview || image.url || image.path}
								alt={image.name}
								class="h-full w-full object-cover"
							/>

							<!-- Overlay with info and remove button -->
							<div
								class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 p-2 opacity-0 transition-opacity group-hover:opacity-100"
							>
								<p class="mb-1 w-full truncate text-center text-xs font-medium text-white">
									{image.name}
								</p>
								{#if image.size > 0}
									<p class="mb-2 text-xs text-white/70">
										{formatFileSize(image.size)}
									</p>
								{/if}
								{#if !disabled}
									<Button
										type="button"
										variant="destructive"
										size="sm"
										onclick={() => removeImage(index)}
										class="h-7 px-2"
									>
										<X class="mr-1 h-3 w-3" />
										Remove
									</Button>
								{/if}
							</div>
						</div>
					{/each}
				</div>
			{/if}
		</div>
	{/snippet}
</FieldBase>
