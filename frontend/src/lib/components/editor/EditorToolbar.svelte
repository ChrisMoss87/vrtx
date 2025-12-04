<script lang="ts">
	import type { Editor } from '@tiptap/core';
	import { Button } from '$lib/components/ui/button';
	import { Separator } from '$lib/components/ui/separator';
	import { Toggle } from '$lib/components/ui/toggle';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Popover from '$lib/components/ui/popover';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import {
		Bold,
		Italic,
		Underline,
		Strikethrough,
		Code,
		List,
		ListOrdered,
		Quote,
		Heading1,
		Heading2,
		Heading3,
		AlignLeft,
		AlignCenter,
		AlignRight,
		AlignJustify,
		Link,
		Unlink,
		Image,
		Table,
		Undo,
		Redo,
		RemoveFormatting,
		Highlighter,
		Palette,
		Minus,
		ChevronDown,
		Plus,
		Trash2,
		TableProperties,
		RowsIcon,
		Columns,
		Upload,
		Loader2
	} from 'lucide-svelte';
	import { cn } from '$lib/utils';
	import { uploadImage } from '$lib/api/uploads';
	import { toast } from 'svelte-sonner';

	interface Props {
		editor: Editor;
		disabled?: boolean;
		module?: string;
	}

	let { editor, disabled = false, module = 'editor' }: Props = $props();

	// Link popover state
	let linkUrl = $state('');
	let linkPopoverOpen = $state(false);

	// Image popover state
	let imageUrl = $state('');
	let imagePopoverOpen = $state(false);
	let imageUploadProgress = $state(0);
	let isUploadingImage = $state(false);
	let imageInputRef: HTMLInputElement;

	// Force reactivity by tracking editor state
	let editorState = $state(0);
	$effect(() => {
		const handler = () => {
			editorState++;
		};
		editor.on('selectionUpdate', handler);
		editor.on('transaction', handler);
		return () => {
			editor.off('selectionUpdate', handler);
			editor.off('transaction', handler);
		};
	});

	function setLink() {
		if (linkUrl) {
			editor.chain().focus().extendMarkRange('link').setLink({ href: linkUrl }).run();
		}
		linkUrl = '';
		linkPopoverOpen = false;
	}

	function removeLink() {
		editor.chain().focus().unsetLink().run();
	}

	function addImage() {
		if (imageUrl) {
			editor.chain().focus().setImage({ src: imageUrl }).run();
		}
		imageUrl = '';
		imagePopoverOpen = false;
	}

	async function handleImageUpload(event: Event) {
		const input = event.target as HTMLInputElement;
		const file = input.files?.[0];
		if (!file) return;

		// Validate image
		if (!file.type.startsWith('image/')) {
			toast.error('Please select an image file');
			return;
		}

		isUploadingImage = true;
		imageUploadProgress = 0;

		try {
			const uploaded = await uploadImage(file, {
				module,
				field: 'rich-text',
				onProgress: (progress) => {
					imageUploadProgress = progress;
				}
			});

			// Insert the uploaded image
			editor.chain().focus().setImage({ src: uploaded.url, alt: file.name }).run();
			toast.success('Image uploaded successfully');
			imagePopoverOpen = false;
		} catch (error: any) {
			console.error('Image upload error:', error);
			toast.error(error.message || 'Failed to upload image');
		} finally {
			isUploadingImage = false;
			imageUploadProgress = 0;
			// Reset input
			if (imageInputRef) {
				imageInputRef.value = '';
			}
		}
	}

	function triggerImageUpload() {
		imageInputRef?.click();
	}

	function insertTable() {
		editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
	}

	// Check if active helper
	function isActive(name: string | Record<string, unknown>, attrs?: Record<string, unknown>) {
		// Use editorState to force reactivity
		void editorState;
		if (typeof name === 'string') {
			return editor.isActive(name, attrs);
		}
		return editor.isActive(name);
	}

	const textColors = [
		{ name: 'Default', value: '' },
		{ name: 'Red', value: '#ef4444' },
		{ name: 'Orange', value: '#f97316' },
		{ name: 'Yellow', value: '#eab308' },
		{ name: 'Green', value: '#22c55e' },
		{ name: 'Blue', value: '#3b82f6' },
		{ name: 'Purple', value: '#a855f7' },
		{ name: 'Pink', value: '#ec4899' }
	];

	const highlightColors = [
		{ name: 'None', value: '' },
		{ name: 'Yellow', value: '#fef08a' },
		{ name: 'Green', value: '#bbf7d0' },
		{ name: 'Blue', value: '#bfdbfe' },
		{ name: 'Pink', value: '#fbcfe8' },
		{ name: 'Purple', value: '#e9d5ff' },
		{ name: 'Orange', value: '#fed7aa' }
	];
</script>

<div
	class={cn(
		'flex flex-wrap items-center gap-0.5 border-b bg-muted/30 p-2',
		disabled && 'pointer-events-none opacity-50'
	)}
>
	<!-- Undo/Redo -->
	<Button
		variant="ghost"
		size="icon"
		class="h-8 w-8"
		onclick={() => editor.chain().focus().undo().run()}
		disabled={!editor.can().undo()}
		title="Undo"
	>
		<Undo class="h-4 w-4" />
	</Button>
	<Button
		variant="ghost"
		size="icon"
		class="h-8 w-8"
		onclick={() => editor.chain().focus().redo().run()}
		disabled={!editor.can().redo()}
		title="Redo"
	>
		<Redo class="h-4 w-4" />
	</Button>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Headings Dropdown -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			<Button variant="ghost" size="sm" class="h-8 gap-1">
				<span class="text-xs">
					{#if isActive('heading', { level: 1 })}
						H1
					{:else if isActive('heading', { level: 2 })}
						H2
					{:else if isActive('heading', { level: 3 })}
						H3
					{:else}
						Normal
					{/if}
				</span>
				<ChevronDown class="h-3 w-3" />
			</Button>
		</DropdownMenu.Trigger>
		<DropdownMenu.Content>
			<DropdownMenu.Item onclick={() => editor.chain().focus().setParagraph().run()}>
				Normal text
			</DropdownMenu.Item>
			<DropdownMenu.Item onclick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}>
				<Heading1 class="mr-2 h-4 w-4" />
				Heading 1
			</DropdownMenu.Item>
			<DropdownMenu.Item onclick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>
				<Heading2 class="mr-2 h-4 w-4" />
				Heading 2
			</DropdownMenu.Item>
			<DropdownMenu.Item onclick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}>
				<Heading3 class="mr-2 h-4 w-4" />
				Heading 3
			</DropdownMenu.Item>
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Text Formatting -->
	<Toggle
		pressed={isActive('bold')}
		onPressedChange={() => editor.chain().focus().toggleBold().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Bold"
	>
		<Bold class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive('italic')}
		onPressedChange={() => editor.chain().focus().toggleItalic().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Italic"
	>
		<Italic class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive('underline')}
		onPressedChange={() => editor.chain().focus().toggleUnderline().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Underline"
	>
		<Underline class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive('strike')}
		onPressedChange={() => editor.chain().focus().toggleStrike().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Strikethrough"
	>
		<Strikethrough class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive('code')}
		onPressedChange={() => editor.chain().focus().toggleCode().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Inline Code"
	>
		<Code class="h-4 w-4" />
	</Toggle>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Text Color -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			<Button variant="ghost" size="icon" class="h-8 w-8" title="Text Color">
				<Palette class="h-4 w-4" />
			</Button>
		</DropdownMenu.Trigger>
		<DropdownMenu.Content>
			{#each textColors as color}
				<DropdownMenu.Item
					onclick={() => {
						if (color.value) {
							editor.chain().focus().setColor(color.value).run();
						} else {
							editor.chain().focus().unsetColor().run();
						}
					}}
				>
					<span
						class="mr-2 h-4 w-4 rounded border"
						style="background-color: {color.value || 'transparent'}"
					></span>
					{color.name}
				</DropdownMenu.Item>
			{/each}
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	<!-- Highlight -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			<Button variant="ghost" size="icon" class="h-8 w-8" title="Highlight">
				<Highlighter class="h-4 w-4" />
			</Button>
		</DropdownMenu.Trigger>
		<DropdownMenu.Content>
			{#each highlightColors as color}
				<DropdownMenu.Item
					onclick={() => {
						if (color.value) {
							editor.chain().focus().toggleHighlight({ color: color.value }).run();
						} else {
							editor.chain().focus().unsetHighlight().run();
						}
					}}
				>
					<span
						class="mr-2 h-4 w-4 rounded border"
						style="background-color: {color.value || 'transparent'}"
					></span>
					{color.name}
				</DropdownMenu.Item>
			{/each}
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Alignment -->
	<Toggle
		pressed={isActive({ textAlign: 'left' })}
		onPressedChange={() => editor.chain().focus().setTextAlign('left').run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Align Left"
	>
		<AlignLeft class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive({ textAlign: 'center' })}
		onPressedChange={() => editor.chain().focus().setTextAlign('center').run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Align Center"
	>
		<AlignCenter class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive({ textAlign: 'right' })}
		onPressedChange={() => editor.chain().focus().setTextAlign('right').run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Align Right"
	>
		<AlignRight class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive({ textAlign: 'justify' })}
		onPressedChange={() => editor.chain().focus().setTextAlign('justify').run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Justify"
	>
		<AlignJustify class="h-4 w-4" />
	</Toggle>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Lists -->
	<Toggle
		pressed={isActive('bulletList')}
		onPressedChange={() => editor.chain().focus().toggleBulletList().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Bullet List"
	>
		<List class="h-4 w-4" />
	</Toggle>
	<Toggle
		pressed={isActive('orderedList')}
		onPressedChange={() => editor.chain().focus().toggleOrderedList().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Ordered List"
	>
		<ListOrdered class="h-4 w-4" />
	</Toggle>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Blockquote -->
	<Toggle
		pressed={isActive('blockquote')}
		onPressedChange={() => editor.chain().focus().toggleBlockquote().run()}
		size="sm"
		class="h-8 w-8"
		aria-label="Blockquote"
	>
		<Quote class="h-4 w-4" />
	</Toggle>

	<!-- Horizontal Rule -->
	<Button
		variant="ghost"
		size="icon"
		class="h-8 w-8"
		onclick={() => editor.chain().focus().setHorizontalRule().run()}
		title="Horizontal Rule"
	>
		<Minus class="h-4 w-4" />
	</Button>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Link -->
	<Popover.Root bind:open={linkPopoverOpen}>
		<Popover.Trigger>
			<Toggle pressed={isActive('link')} size="sm" class="h-8 w-8" aria-label="Link">
				<Link class="h-4 w-4" />
			</Toggle>
		</Popover.Trigger>
		<Popover.Content class="w-80">
			<div class="grid gap-4">
				<div class="space-y-2">
					<h4 class="leading-none font-medium">Insert Link</h4>
					<p class="text-sm text-muted-foreground">Enter the URL for the link</p>
				</div>
				<div class="grid gap-2">
					<Label for="link-url">URL</Label>
					<Input
						id="link-url"
						bind:value={linkUrl}
						placeholder="https://example.com"
						onkeydown={(e) => e.key === 'Enter' && setLink()}
					/>
				</div>
				<div class="flex justify-end gap-2">
					{#if isActive('link')}
						<Button variant="destructive" size="sm" onclick={removeLink}>
							<Unlink class="mr-1 h-4 w-4" />
							Remove
						</Button>
					{/if}
					<Button size="sm" onclick={setLink}>Insert</Button>
				</div>
			</div>
		</Popover.Content>
	</Popover.Root>

	<!-- Image -->
	<Popover.Root bind:open={imagePopoverOpen}>
		<Popover.Trigger>
			<Button variant="ghost" size="icon" class="h-8 w-8" title="Insert Image">
				<Image class="h-4 w-4" />
			</Button>
		</Popover.Trigger>
		<Popover.Content class="w-80">
			<div class="grid gap-4">
				<div class="space-y-2">
					<h4 class="leading-none font-medium">Insert Image</h4>
					<p class="text-sm text-muted-foreground">Upload an image or enter a URL</p>
				</div>

				<!-- Hidden file input -->
				<input
					bind:this={imageInputRef}
					type="file"
					accept="image/jpeg,image/png,image/gif,image/webp"
					class="hidden"
					onchange={handleImageUpload}
				/>

				<!-- Upload button -->
				<Button
					variant="outline"
					class="w-full"
					onclick={triggerImageUpload}
					disabled={isUploadingImage}
				>
					{#if isUploadingImage}
						<Loader2 class="mr-2 h-4 w-4 animate-spin" />
						Uploading... {imageUploadProgress}%
					{:else}
						<Upload class="mr-2 h-4 w-4" />
						Upload Image
					{/if}
				</Button>

				<div class="relative">
					<div class="absolute inset-0 flex items-center">
						<span class="w-full border-t" />
					</div>
					<div class="relative flex justify-center text-xs uppercase">
						<span class="bg-popover px-2 text-muted-foreground">Or</span>
					</div>
				</div>

				<div class="grid gap-2">
					<Label for="image-url">Image URL</Label>
					<Input
						id="image-url"
						bind:value={imageUrl}
						placeholder="https://example.com/image.jpg"
						onkeydown={(e) => e.key === 'Enter' && addImage()}
						disabled={isUploadingImage}
					/>
				</div>
				<div class="flex justify-end">
					<Button size="sm" onclick={addImage} disabled={!imageUrl || isUploadingImage}>
						Insert from URL
					</Button>
				</div>
			</div>
		</Popover.Content>
	</Popover.Root>

	<!-- Table -->
	<DropdownMenu.Root>
		<DropdownMenu.Trigger>
			<Button variant="ghost" size="icon" class="h-8 w-8" title="Table">
				<Table class="h-4 w-4" />
			</Button>
		</DropdownMenu.Trigger>
		<DropdownMenu.Content>
			<DropdownMenu.Item onclick={insertTable}>
				<Plus class="mr-2 h-4 w-4" />
				Insert Table
			</DropdownMenu.Item>
			{#if isActive('table')}
				<DropdownMenu.Separator />
				<DropdownMenu.Item onclick={() => editor.chain().focus().addColumnBefore().run()}>
					<Columns class="mr-2 h-4 w-4" />
					Add Column Before
				</DropdownMenu.Item>
				<DropdownMenu.Item onclick={() => editor.chain().focus().addColumnAfter().run()}>
					<Columns class="mr-2 h-4 w-4" />
					Add Column After
				</DropdownMenu.Item>
				<DropdownMenu.Item onclick={() => editor.chain().focus().deleteColumn().run()}>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete Column
				</DropdownMenu.Item>
				<DropdownMenu.Separator />
				<DropdownMenu.Item onclick={() => editor.chain().focus().addRowBefore().run()}>
					<RowsIcon class="mr-2 h-4 w-4" />
					Add Row Before
				</DropdownMenu.Item>
				<DropdownMenu.Item onclick={() => editor.chain().focus().addRowAfter().run()}>
					<RowsIcon class="mr-2 h-4 w-4" />
					Add Row After
				</DropdownMenu.Item>
				<DropdownMenu.Item onclick={() => editor.chain().focus().deleteRow().run()}>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete Row
				</DropdownMenu.Item>
				<DropdownMenu.Separator />
				<DropdownMenu.Item onclick={() => editor.chain().focus().toggleHeaderRow().run()}>
					<TableProperties class="mr-2 h-4 w-4" />
					Toggle Header Row
				</DropdownMenu.Item>
				<DropdownMenu.Item
					onclick={() => editor.chain().focus().deleteTable().run()}
					class="text-destructive"
				>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete Table
				</DropdownMenu.Item>
			{/if}
		</DropdownMenu.Content>
	</DropdownMenu.Root>

	<Separator orientation="vertical" class="mx-1 h-6" />

	<!-- Clear Formatting -->
	<Button
		variant="ghost"
		size="icon"
		class="h-8 w-8"
		onclick={() => editor.chain().focus().clearNodes().unsetAllMarks().run()}
		title="Clear Formatting"
	>
		<RemoveFormatting class="h-4 w-4" />
	</Button>
</div>
