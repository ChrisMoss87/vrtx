import { Extension } from '@tiptap/core';
import { type SuggestionOptions } from '@tiptap/suggestion';
import Suggestion from '@tiptap/suggestion';

export interface SlashCommand {
	id: string;
	title: string;
	description: string;
	icon?: string;
	category?: string;
	action: (editor: unknown) => void;
}

export interface SlashCommandProps {
	query: string;
	editor: unknown;
	range: { from: number; to: number };
	clientRect: (() => DOMRect | null) | null;
	command: (props: SlashCommand) => void;
	decorationNode: Element | null;
	items: SlashCommand[];
}

/**
 * Default slash commands for the editor
 */
export const defaultSlashCommands: SlashCommand[] = [
	{
		id: 'heading1',
		title: 'Heading 1',
		description: 'Large section heading',
		icon: 'heading-1',
		category: 'Headings',
		action: (editor: any) => {
			editor.chain().focus().toggleHeading({ level: 1 }).run();
		}
	},
	{
		id: 'heading2',
		title: 'Heading 2',
		description: 'Medium section heading',
		icon: 'heading-2',
		category: 'Headings',
		action: (editor: any) => {
			editor.chain().focus().toggleHeading({ level: 2 }).run();
		}
	},
	{
		id: 'heading3',
		title: 'Heading 3',
		description: 'Small section heading',
		icon: 'heading-3',
		category: 'Headings',
		action: (editor: any) => {
			editor.chain().focus().toggleHeading({ level: 3 }).run();
		}
	},
	{
		id: 'bulletList',
		title: 'Bullet List',
		description: 'Create a simple bullet list',
		icon: 'list',
		category: 'Lists',
		action: (editor: any) => {
			editor.chain().focus().toggleBulletList().run();
		}
	},
	{
		id: 'orderedList',
		title: 'Numbered List',
		description: 'Create a numbered list',
		icon: 'list-ordered',
		category: 'Lists',
		action: (editor: any) => {
			editor.chain().focus().toggleOrderedList().run();
		}
	},
	{
		id: 'blockquote',
		title: 'Quote',
		description: 'Capture a quote',
		icon: 'quote',
		category: 'Blocks',
		action: (editor: any) => {
			editor.chain().focus().toggleBlockquote().run();
		}
	},
	{
		id: 'codeBlock',
		title: 'Code Block',
		description: 'Add a code snippet',
		icon: 'code',
		category: 'Blocks',
		action: (editor: any) => {
			editor.chain().focus().toggleCodeBlock().run();
		}
	},
	{
		id: 'horizontalRule',
		title: 'Divider',
		description: 'Visual divider line',
		icon: 'minus',
		category: 'Blocks',
		action: (editor: any) => {
			editor.chain().focus().setHorizontalRule().run();
		}
	},
	{
		id: 'table',
		title: 'Table',
		description: 'Insert a table',
		icon: 'table',
		category: 'Blocks',
		action: (editor: any) => {
			editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
		}
	},
	{
		id: 'image',
		title: 'Image',
		description: 'Upload or embed an image',
		icon: 'image',
		category: 'Media',
		action: (editor: any) => {
			const url = window.prompt('Enter image URL');
			if (url) {
				editor.chain().focus().setImage({ src: url }).run();
			}
		}
	}
];

/**
 * Creates a Slash Commands extension with custom rendering
 */
export function createSlashCommandsExtension(options: {
	commands?: SlashCommand[];
	renderDropdown: (props: SlashCommandProps) => {
		onStart: (props: SlashCommandProps) => void;
		onUpdate: (props: SlashCommandProps) => void;
		onKeyDown: (props: { event: KeyboardEvent }) => boolean;
		onExit: () => void;
	};
}) {
	const commands = options.commands ?? defaultSlashCommands;

	const suggestion: Partial<SuggestionOptions<SlashCommand>> = {
		char: '/',
		allowSpaces: false,
		startOfLine: false,

		items: ({ query }) => {
			const filtered = commands.filter(
				(cmd) =>
					cmd.title.toLowerCase().includes(query.toLowerCase()) ||
					cmd.description.toLowerCase().includes(query.toLowerCase())
			);
			return filtered.slice(0, 10);
		},

		render: () => {
			let currentProps: SlashCommandProps | null = null;
			let handlers: ReturnType<typeof options.renderDropdown> | null = null;

			return {
				onStart: (props) => {
					currentProps = props as SlashCommandProps;
					handlers = options.renderDropdown(currentProps);
					handlers.onStart(currentProps);
				},

				onUpdate: (props) => {
					currentProps = props as SlashCommandProps;
					handlers?.onUpdate(currentProps);
				},

				onKeyDown: (props) => {
					if (props.event.key === 'Escape') {
						handlers?.onExit();
						return true;
					}
					return handlers?.onKeyDown(props) ?? false;
				},

				onExit: () => {
					handlers?.onExit();
					currentProps = null;
					handlers = null;
				}
			};
		},

		command: ({ editor, range, props }) => {
			// Delete the slash command text
			editor.chain().focus().deleteRange(range).run();
			// Execute the command action
			props.action(editor);
		}
	};

	return Extension.create({
		name: 'slashCommands',

		addOptions() {
			return {
				suggestion
			};
		},

		addProseMirrorPlugins() {
			return [
				Suggestion({
					editor: this.editor,
					...this.options.suggestion
				})
			];
		}
	});
}
