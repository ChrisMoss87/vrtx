<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { Editor } from '@tiptap/core';
	import StarterKit from '@tiptap/starter-kit';
	import { Underline } from '@tiptap/extension-underline';
	import { Link } from '@tiptap/extension-link';
	import { Image } from '@tiptap/extension-image';
	import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table';
	import { Placeholder } from '@tiptap/extension-placeholder';
	import { CharacterCount } from '@tiptap/extension-character-count';
	import { TextAlign } from '@tiptap/extension-text-align';
	import { TextStyle } from '@tiptap/extension-text-style';
	import { Color } from '@tiptap/extension-color';
	import { Highlight } from '@tiptap/extension-highlight';
	import EditorToolbar from '$lib/components/editor/EditorToolbar.svelte';
	import { cn } from '$lib/utils';
	import {
		Bold,
		Italic,
		Underline as UnderlineIcon,
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
		Link as LinkIcon,
		Image as ImageIcon,
		Table as TableIcon,
		Undo,
		Redo,
		Minus
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Popover from '$lib/components/ui/popover';
	import { Input } from '$lib/components/ui/input';

	interface Props {
		content?: string;
		readonly?: boolean;
		onchange?: (html: string, text: string) => void;
		onsave?: () => void;
		class?: string;
	}

	let {
		content = $bindable(''),
		readonly = false,
		onchange,
		onsave,
		class: className = ''
	}: Props = $props();

	let element: HTMLDivElement;
	let editor: Editor | null = $state(null);
	let isFocused = $state(false);

	// Link popover state
	let linkUrl = $state('');
	let showLinkPopover = $state(false);

	// Image dialog state
	let imageUrl = $state('');
	let showImagePopover = $state(false);

	onMount(() => {
		editor = new Editor({
			element,
			extensions: [
				StarterKit.configure({
					heading: {
						levels: [1, 2, 3, 4, 5, 6]
					}
				}),
				Underline,
				Link.configure({
					openOnClick: false,
					HTMLAttributes: {
						class: 'text-primary underline hover:text-primary/80 cursor-pointer'
					}
				}),
				Image.configure({
					HTMLAttributes: {
						class: 'max-w-full h-auto rounded-md'
					}
				}),
				Table.configure({
					resizable: true,
					HTMLAttributes: {
						class: 'border-collapse table-auto w-full'
					}
				}),
				TableRow,
				TableCell.configure({
					HTMLAttributes: {
						class: 'border border-border p-2'
					}
				}),
				TableHeader.configure({
					HTMLAttributes: {
						class: 'border border-border p-2 bg-muted font-semibold'
					}
				}),
				Placeholder.configure({
					placeholder: 'Start typing your document...'
				}),
				TextAlign.configure({
					types: ['heading', 'paragraph']
				}),
				TextStyle,
				Color,
				Highlight.configure({
					multicolor: true
				}),
				CharacterCount
			],
			content,
			editable: !readonly,
			autofocus: 'end',
			onUpdate: ({ editor: e }) => {
				const html = e.getHTML();
				const text = e.getText();
				content = html;
				onchange?.(html, text);
			},
			onFocus: () => {
				isFocused = true;
			},
			onBlur: () => {
				isFocused = false;
			}
		});

		// Keyboard shortcuts
		const handleKeyDown = (e: KeyboardEvent) => {
			if ((e.metaKey || e.ctrlKey) && e.key === 's') {
				e.preventDefault();
				onsave?.();
			}
		};
		document.addEventListener('keydown', handleKeyDown);

		return () => {
			document.removeEventListener('keydown', handleKeyDown);
			editor?.destroy();
		};
	});

	// Update content when prop changes
	$effect(() => {
		if (editor && content !== editor.getHTML()) {
			editor.commands.setContent(content, { emitUpdate: false });
		}
	});

	// Update editable state
	$effect(() => {
		if (editor) {
			editor.setEditable(!readonly);
		}
	});

	function setLink() {
		if (linkUrl) {
			editor?.chain().focus().extendMarkRange('link').setLink({ href: linkUrl }).run();
		} else {
			editor?.chain().focus().extendMarkRange('link').unsetLink().run();
		}
		showLinkPopover = false;
		linkUrl = '';
	}

	function insertImage() {
		if (imageUrl) {
			editor?.chain().focus().setImage({ src: imageUrl }).run();
		}
		showImagePopover = false;
		imageUrl = '';
	}

	function insertTable() {
		editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
	}

	// Character/word count
	function getCharacterCount(): number {
		if (!editor) return 0;
		const storage = editor.storage as { characterCount?: { characters?: () => number } };
		return storage?.characterCount?.characters?.() ?? 0;
	}

	function getWordCount(): number {
		if (!editor) return 0;
		const storage = editor.storage as { characterCount?: { words?: () => number } };
		return storage?.characterCount?.words?.() ?? 0;
	}

	let characterCount = $derived(getCharacterCount());
	let wordCount = $derived(getWordCount());

	// Export methods
	export function getEditor() {
		return editor;
	}

	export function getHTML() {
		return editor?.getHTML() ?? '';
	}

	export function getText() {
		return editor?.getText() ?? '';
	}

	export function focus() {
		editor?.commands.focus();
	}
</script>

<div class={cn('word-editor flex flex-col h-full bg-background', className)}>
	<!-- Toolbar -->
	{#if editor && !readonly}
		<div class="border-b bg-muted/30 px-2 py-1 flex items-center gap-1 flex-wrap">
			<!-- Undo/Redo -->
			<Button
				variant="ghost"
				size="sm"
				onclick={() => editor?.chain().focus().undo().run()}
				disabled={!editor?.can().undo()}
			>
				<Undo class="h-4 w-4" />
			</Button>
			<Button
				variant="ghost"
				size="sm"
				onclick={() => editor?.chain().focus().redo().run()}
				disabled={!editor?.can().redo()}
			>
				<Redo class="h-4 w-4" />
			</Button>

			<div class="w-px h-6 bg-border mx-1"></div>

			<!-- Headings -->
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					<Button variant="ghost" size="sm" class="min-w-[100px] justify-start">
						{#if editor?.isActive('heading', { level: 1 })}
							Heading 1
						{:else if editor?.isActive('heading', { level: 2 })}
							Heading 2
						{:else if editor?.isActive('heading', { level: 3 })}
							Heading 3
						{:else}
							Normal
						{/if}
					</Button>
				</DropdownMenu.Trigger>
				<DropdownMenu.Content>
					<DropdownMenu.Item onclick={() => editor?.chain().focus().setParagraph().run()}>
						Normal text
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => editor?.chain().focus().toggleHeading({ level: 1 }).run()}>
						<Heading1 class="mr-2 h-4 w-4" /> Heading 1
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => editor?.chain().focus().toggleHeading({ level: 2 }).run()}>
						<Heading2 class="mr-2 h-4 w-4" /> Heading 2
					</DropdownMenu.Item>
					<DropdownMenu.Item onclick={() => editor?.chain().focus().toggleHeading({ level: 3 }).run()}>
						<Heading3 class="mr-2 h-4 w-4" /> Heading 3
					</DropdownMenu.Item>
				</DropdownMenu.Content>
			</DropdownMenu.Root>

			<div class="w-px h-6 bg-border mx-1"></div>

			<!-- Text formatting -->
			<Button
				variant={editor?.isActive('bold') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleBold().run()}
			>
				<Bold class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('italic') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleItalic().run()}
			>
				<Italic class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('underline') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleUnderline().run()}
			>
				<UnderlineIcon class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('strike') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleStrike().run()}
			>
				<Strikethrough class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('code') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleCode().run()}
			>
				<Code class="h-4 w-4" />
			</Button>

			<div class="w-px h-6 bg-border mx-1"></div>

			<!-- Lists -->
			<Button
				variant={editor?.isActive('bulletList') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleBulletList().run()}
			>
				<List class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('orderedList') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleOrderedList().run()}
			>
				<ListOrdered class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive('blockquote') ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().toggleBlockquote().run()}
			>
				<Quote class="h-4 w-4" />
			</Button>

			<div class="w-px h-6 bg-border mx-1"></div>

			<!-- Alignment -->
			<Button
				variant={editor?.isActive({ textAlign: 'left' }) ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().setTextAlign('left').run()}
			>
				<AlignLeft class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive({ textAlign: 'center' }) ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().setTextAlign('center').run()}
			>
				<AlignCenter class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive({ textAlign: 'right' }) ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().setTextAlign('right').run()}
			>
				<AlignRight class="h-4 w-4" />
			</Button>
			<Button
				variant={editor?.isActive({ textAlign: 'justify' }) ? 'secondary' : 'ghost'}
				size="sm"
				onclick={() => editor?.chain().focus().setTextAlign('justify').run()}
			>
				<AlignJustify class="h-4 w-4" />
			</Button>

			<div class="w-px h-6 bg-border mx-1"></div>

			<!-- Link -->
			<Popover.Root bind:open={showLinkPopover}>
				<Popover.Trigger>
					<Button
						variant={editor?.isActive('link') ? 'secondary' : 'ghost'}
						size="sm"
					>
						<LinkIcon class="h-4 w-4" />
					</Button>
				</Popover.Trigger>
				<Popover.Content class="w-80">
					<div class="flex gap-2">
						<Input
							placeholder="Enter URL..."
							bind:value={linkUrl}
							onkeydown={(e) => e.key === 'Enter' && setLink()}
						/>
						<Button onclick={setLink}>Set</Button>
					</div>
				</Popover.Content>
			</Popover.Root>

			<!-- Image -->
			<Popover.Root bind:open={showImagePopover}>
				<Popover.Trigger>
					<Button variant="ghost" size="sm">
						<ImageIcon class="h-4 w-4" />
					</Button>
				</Popover.Trigger>
				<Popover.Content class="w-80">
					<div class="flex gap-2">
						<Input
							placeholder="Enter image URL..."
							bind:value={imageUrl}
							onkeydown={(e) => e.key === 'Enter' && insertImage()}
						/>
						<Button onclick={insertImage}>Insert</Button>
					</div>
				</Popover.Content>
			</Popover.Root>

			<!-- Table -->
			<Button variant="ghost" size="sm" onclick={insertTable}>
				<TableIcon class="h-4 w-4" />
			</Button>

			<!-- Horizontal Rule -->
			<Button
				variant="ghost"
				size="sm"
				onclick={() => editor?.chain().focus().setHorizontalRule().run()}
			>
				<Minus class="h-4 w-4" />
			</Button>
		</div>
	{/if}

	<!-- Editor Content -->
	<div class="flex-1 overflow-hidden">
		<div class="h-full overflow-y-auto">
			<div class="max-w-4xl mx-auto px-8 py-6">
				<div
					bind:this={element}
					class={cn(
						'prose prose-sm dark:prose-invert max-w-none min-h-[500px] focus:outline-none',
						readonly && 'cursor-default'
					)}
				></div>
			</div>
		</div>
	</div>

	<!-- Status Bar -->
	<div class="border-t bg-muted/30 px-4 py-1.5 flex items-center justify-between text-xs text-muted-foreground">
		<span>{wordCount} words, {characterCount} characters</span>
		<span>Press Ctrl+S to save</span>
	</div>
</div>

<style>
	:global(.word-editor .ProseMirror) {
		outline: none;
		min-height: 100%;
	}

	:global(.word-editor .ProseMirror p.is-editor-empty:first-child::before) {
		content: attr(data-placeholder);
		float: left;
		color: hsl(var(--muted-foreground));
		pointer-events: none;
		height: 0;
	}

	:global(.word-editor .ProseMirror h1) {
		font-size: 2em;
		font-weight: 700;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.word-editor .ProseMirror h2) {
		font-size: 1.5em;
		font-weight: 600;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.word-editor .ProseMirror h3) {
		font-size: 1.25em;
		font-weight: 600;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.word-editor .ProseMirror ul),
	:global(.word-editor .ProseMirror ol) {
		padding-left: 1.5em;
	}

	:global(.word-editor .ProseMirror blockquote) {
		border-left: 4px solid hsl(var(--border));
		padding-left: 1em;
		margin-left: 0;
		font-style: italic;
		color: hsl(var(--muted-foreground));
	}

	:global(.word-editor .ProseMirror pre) {
		background-color: hsl(var(--muted));
		border-radius: 0.375rem;
		padding: 1em;
		overflow-x: auto;
	}

	:global(.word-editor .ProseMirror code) {
		background-color: hsl(var(--muted));
		border-radius: 0.25rem;
		padding: 0.2em 0.4em;
		font-family: ui-monospace, monospace;
		font-size: 0.875em;
	}

	:global(.word-editor .ProseMirror pre code) {
		background: none;
		padding: 0;
	}

	:global(.word-editor .ProseMirror hr) {
		border: none;
		border-top: 1px solid hsl(var(--border));
		margin: 1em 0;
	}

	:global(.word-editor .ProseMirror img) {
		max-width: 100%;
		height: auto;
	}

	:global(.word-editor .ProseMirror table) {
		border-collapse: collapse;
		width: 100%;
		margin: 1em 0;
	}

	:global(.word-editor .ProseMirror .selectedCell) {
		background-color: hsl(var(--accent));
	}
</style>
