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
	import EditorToolbar from './EditorToolbar.svelte';
	import { cn } from '$lib/utils';

	interface Props {
		content?: string;
		placeholder?: string;
		characterLimit?: number;
		readonly?: boolean;
		disabled?: boolean;
		class?: string;
		minHeight?: string;
		maxHeight?: string;
		showToolbar?: boolean;
		autofocus?: boolean;
		onchange?: (html: string) => void;
		onblur?: () => void;
		onfocus?: () => void;
	}

	let {
		content = $bindable(''),
		placeholder = 'Start writing...',
		characterLimit,
		readonly = false,
		disabled = false,
		class: className = '',
		minHeight = '150px',
		maxHeight = '500px',
		showToolbar = true,
		autofocus = false,
		onchange,
		onblur,
		onfocus
	}: Props = $props();

	let element: HTMLDivElement;
	let editor: Editor | null = $state(null);
	let isFocused = $state(false);

	// Character count tracking
	function getCharacterCount(): number {
		if (!editor) return 0;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const storage = editor.storage as any;
		return storage?.characterCount?.characters?.() ?? 0;
	}

	function getWordCount(): number {
		if (!editor) return 0;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const storage = editor.storage as any;
		return storage?.characterCount?.words?.() ?? 0;
	}

	let characterCount = $derived(getCharacterCount());
	let wordCount = $derived(getWordCount());

	onMount(() => {
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		const extensions: any[] = [
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
				placeholder
			}),
			TextAlign.configure({
				types: ['heading', 'paragraph']
			}),
			TextStyle,
			Color,
			Highlight.configure({
				multicolor: true
			})
		];

		// Add character count if limit specified
		if (characterLimit) {
			extensions.push(
				CharacterCount.configure({
					limit: characterLimit
				})
			);
		} else {
			extensions.push(CharacterCount);
		}

		editor = new Editor({
			element,
			extensions,
			content,
			editable: !readonly && !disabled,
			autofocus: autofocus ? 'end' : false,
			onUpdate: ({ editor: e }) => {
				const html = e.getHTML();
				content = html;
				onchange?.(html);
			},
			onFocus: () => {
				isFocused = true;
				onfocus?.();
			},
			onBlur: () => {
				isFocused = false;
				onblur?.();
			}
		});

		return () => {
			editor?.destroy();
		};
	});

	// Update editor content when prop changes externally
	$effect(() => {
		if (editor && content !== editor.getHTML()) {
			editor.commands.setContent(content, { emitUpdate: false });
		}
	});

	// Update editable state
	$effect(() => {
		if (editor) {
			editor.setEditable(!readonly && !disabled);
		}
	});

	// Expose editor for external use
	export function getEditor() {
		return editor;
	}

	export function focus() {
		editor?.commands.focus();
	}

	export function clear() {
		editor?.commands.clearContent();
	}

	export function insertContent(value: string) {
		editor?.commands.insertContent(value);
	}

	export function getHTML() {
		return editor?.getHTML() ?? '';
	}

	export function getText() {
		return editor?.getText() ?? '';
	}

	export function isEmpty() {
		return editor?.isEmpty ?? true;
	}
</script>

<div
	class={cn(
		'rich-text-editor rounded-md border bg-background',
		isFocused && 'ring-2 ring-ring ring-offset-2',
		disabled && 'cursor-not-allowed opacity-50',
		className
	)}
>
	{#if showToolbar && editor && !readonly}
		<EditorToolbar {editor} {disabled} />
	{/if}

	<div
		bind:this={element}
		class={cn(
			'prose prose-sm dark:prose-invert max-w-none overflow-y-auto p-4 focus:outline-none',
			readonly && 'bg-muted/30'
		)}
		style="min-height: {minHeight}; max-height: {maxHeight};"
	></div>

	{#if characterLimit || !readonly}
		<div
			class="flex items-center justify-between border-t bg-muted/30 px-4 py-2 text-xs text-muted-foreground"
		>
			<span>{wordCount} words</span>
			<span class={characterLimit && characterCount >= characterLimit ? 'text-destructive' : ''}>
				{characterCount}{characterLimit ? ` / ${characterLimit}` : ''} characters
			</span>
		</div>
	{/if}
</div>

<style>
	:global(.rich-text-editor .ProseMirror) {
		outline: none;
	}

	:global(.rich-text-editor .ProseMirror p.is-editor-empty:first-child::before) {
		content: attr(data-placeholder);
		float: left;
		color: hsl(var(--muted-foreground));
		pointer-events: none;
		height: 0;
	}

	:global(.rich-text-editor .ProseMirror h1) {
		font-size: 2em;
		font-weight: 700;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.rich-text-editor .ProseMirror h2) {
		font-size: 1.5em;
		font-weight: 600;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.rich-text-editor .ProseMirror h3) {
		font-size: 1.25em;
		font-weight: 600;
		margin-top: 0.5em;
		margin-bottom: 0.5em;
	}

	:global(.rich-text-editor .ProseMirror ul),
	:global(.rich-text-editor .ProseMirror ol) {
		padding-left: 1.5em;
	}

	:global(.rich-text-editor .ProseMirror blockquote) {
		border-left: 4px solid hsl(var(--border));
		padding-left: 1em;
		margin-left: 0;
		font-style: italic;
		color: hsl(var(--muted-foreground));
	}

	:global(.rich-text-editor .ProseMirror pre) {
		background-color: hsl(var(--muted));
		border-radius: 0.375rem;
		padding: 1em;
		overflow-x: auto;
	}

	:global(.rich-text-editor .ProseMirror code) {
		background-color: hsl(var(--muted));
		border-radius: 0.25rem;
		padding: 0.2em 0.4em;
		font-family: ui-monospace, monospace;
		font-size: 0.875em;
	}

	:global(.rich-text-editor .ProseMirror pre code) {
		background: none;
		padding: 0;
	}

	:global(.rich-text-editor .ProseMirror hr) {
		border: none;
		border-top: 1px solid hsl(var(--border));
		margin: 1em 0;
	}

	:global(.rich-text-editor .ProseMirror img) {
		max-width: 100%;
		height: auto;
	}

	:global(.rich-text-editor .ProseMirror table) {
		border-collapse: collapse;
		width: 100%;
		margin: 1em 0;
	}

	:global(.rich-text-editor .ProseMirror .selectedCell) {
		background-color: hsl(var(--accent));
	}
</style>
