<script lang="ts">
	import { onMount } from 'svelte';
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
	import MentionDropdown from './MentionDropdown.svelte';
	import CommandMenu from './CommandMenu.svelte';
	import {
		createMentionExtension,
		type MentionUser,
		type MentionSuggestionProps
	} from './extensions/mention';
	import {
		createSlashCommandsExtension,
		defaultSlashCommands,
		type SlashCommand,
		type SlashCommandProps
	} from './extensions/slashCommands';
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
		enableMentions?: boolean;
		enableSlashCommands?: boolean;
		fetchUsers?: (query: string) => Promise<MentionUser[]> | MentionUser[];
		slashCommands?: SlashCommand[];
		onchange?: (html: string) => void;
		onblur?: () => void;
		onfocus?: () => void;
		onMention?: (user: MentionUser) => void;
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
		enableMentions = false,
		enableSlashCommands = false,
		fetchUsers = defaultFetchUsers,
		slashCommands = defaultSlashCommands,
		onchange,
		onblur,
		onfocus,
		onMention
	}: Props = $props();

	let element: HTMLDivElement;
	let editor: Editor | null = $state(null);
	let isFocused = $state(false);

	// Mention dropdown state
	let mentionDropdownOpen = $state(false);
	let mentionDropdownPosition = $state({ top: 0, left: 0 });
	let mentionItems = $state<MentionUser[]>([]);
	let mentionSelectedIndex = $state(0);
	let mentionCommand: ((props: { id: string; label: string }) => void) | null = $state(null);

	// Command menu state
	let commandMenuOpen = $state(false);
	let commandMenuPosition = $state({ top: 0, left: 0 });
	let commandItems = $state<SlashCommand[]>([]);
	let commandSelectedIndex = $state(0);
	let commandHandler: ((props: SlashCommand) => void) | null = $state(null);

	// Default user fetch (demo data)
	function defaultFetchUsers(query: string): MentionUser[] {
		const demoUsers: MentionUser[] = [
			{ id: '1', name: 'John Smith', email: 'john@example.com' },
			{ id: '2', name: 'Jane Doe', email: 'jane@example.com' },
			{ id: '3', name: 'Bob Wilson', email: 'bob@example.com' },
			{ id: '4', name: 'Alice Johnson', email: 'alice@example.com' },
			{ id: '5', name: 'Charlie Brown', email: 'charlie@example.com' }
		];
		return demoUsers.filter(
			(u) =>
				u.name.toLowerCase().includes(query.toLowerCase()) ||
				u.email?.toLowerCase().includes(query.toLowerCase())
		);
	}

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

		// Add character count
		if (characterLimit) {
			extensions.push(
				CharacterCount.configure({
					limit: characterLimit
				})
			);
		} else {
			extensions.push(CharacterCount);
		}

		// Add mentions if enabled
		if (enableMentions) {
			extensions.push(
				createMentionExtension({
					fetchUsers,
					onMentionSelect: onMention,
					renderDropdown: createMentionDropdownRenderer()
				})
			);
		}

		// Add slash commands if enabled
		if (enableSlashCommands) {
			extensions.push(
				createSlashCommandsExtension({
					commands: slashCommands,
					renderDropdown: createCommandMenuRenderer()
				})
			);
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

	// Create mention dropdown renderer
	function createMentionDropdownRenderer() {
		return (props: MentionSuggestionProps) => ({
			onStart: (p: MentionSuggestionProps) => {
				mentionItems = p.items;
				mentionSelectedIndex = 0;
				mentionCommand = p.command;

				if (p.clientRect) {
					const rect = p.clientRect();
					if (rect) {
						mentionDropdownPosition = {
							top: rect.bottom + window.scrollY + 8,
							left: rect.left + window.scrollX
						};
					}
				}
				mentionDropdownOpen = true;
			},
			onUpdate: (p: MentionSuggestionProps) => {
				mentionItems = p.items;
				mentionCommand = p.command;

				if (p.clientRect) {
					const rect = p.clientRect();
					if (rect) {
						mentionDropdownPosition = {
							top: rect.bottom + window.scrollY + 8,
							left: rect.left + window.scrollX
						};
					}
				}
			},
			onKeyDown: ({ event }: { event: KeyboardEvent }) => {
				if (event.key === 'ArrowUp') {
					mentionSelectedIndex = Math.max(0, mentionSelectedIndex - 1);
					return true;
				}
				if (event.key === 'ArrowDown') {
					mentionSelectedIndex = Math.min(mentionItems.length - 1, mentionSelectedIndex + 1);
					return true;
				}
				if (event.key === 'Enter') {
					const item = mentionItems[mentionSelectedIndex];
					if (item && mentionCommand) {
						mentionCommand({ id: item.id, label: item.name });
					}
					return true;
				}
				return false;
			},
			onExit: () => {
				mentionDropdownOpen = false;
				mentionItems = [];
				mentionSelectedIndex = 0;
				mentionCommand = null;
			}
		});
	}

	// Create command menu renderer
	function createCommandMenuRenderer() {
		return (props: SlashCommandProps) => ({
			onStart: (p: SlashCommandProps) => {
				commandItems = p.items;
				commandSelectedIndex = 0;
				commandHandler = p.command;

				if (p.clientRect) {
					const rect = p.clientRect();
					if (rect) {
						commandMenuPosition = {
							top: rect.bottom + window.scrollY + 8,
							left: rect.left + window.scrollX
						};
					}
				}
				commandMenuOpen = true;
			},
			onUpdate: (p: SlashCommandProps) => {
				commandItems = p.items;
				commandHandler = p.command;

				if (p.clientRect) {
					const rect = p.clientRect();
					if (rect) {
						commandMenuPosition = {
							top: rect.bottom + window.scrollY + 8,
							left: rect.left + window.scrollX
						};
					}
				}
			},
			onKeyDown: ({ event }: { event: KeyboardEvent }) => {
				if (event.key === 'ArrowUp') {
					commandSelectedIndex = Math.max(0, commandSelectedIndex - 1);
					return true;
				}
				if (event.key === 'ArrowDown') {
					commandSelectedIndex = Math.min(commandItems.length - 1, commandSelectedIndex + 1);
					return true;
				}
				if (event.key === 'Enter') {
					const item = commandItems[commandSelectedIndex];
					if (item && commandHandler) {
						commandHandler(item);
					}
					return true;
				}
				return false;
			},
			onExit: () => {
				commandMenuOpen = false;
				commandItems = [];
				commandSelectedIndex = 0;
				commandHandler = null;
			}
		});
	}

	// Handle mention selection from dropdown
	function handleMentionSelect(item: MentionUser) {
		if (mentionCommand) {
			mentionCommand({ id: item.id, label: item.name });
		}
	}

	// Handle command selection from menu
	function handleCommandSelect(item: SlashCommand) {
		if (commandHandler) {
			commandHandler(item);
		}
	}

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
		'rich-text-editor relative rounded-md border bg-background',
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

<!-- Mention Dropdown Portal -->
{#if mentionDropdownOpen}
	<div
		class="fixed z-50"
		style="top: {mentionDropdownPosition.top}px; left: {mentionDropdownPosition.left}px;"
	>
		<MentionDropdown
			items={mentionItems}
			selectedIndex={mentionSelectedIndex}
			onSelect={handleMentionSelect}
		/>
	</div>
{/if}

<!-- Command Menu Portal -->
{#if commandMenuOpen}
	<div
		class="fixed z-50"
		style="top: {commandMenuPosition.top}px; left: {commandMenuPosition.left}px;"
	>
		<CommandMenu
			items={commandItems}
			selectedIndex={commandSelectedIndex}
			onSelect={handleCommandSelect}
		/>
	</div>
{/if}

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

	:global(.rich-text-editor .ProseMirror .mention) {
		background-color: hsl(var(--primary) / 0.1);
		color: hsl(var(--primary));
		border-radius: 0.25rem;
		padding: 0.125rem 0.375rem;
		font-weight: 500;
	}
</style>
