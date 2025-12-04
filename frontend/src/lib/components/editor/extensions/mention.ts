import { Mention } from '@tiptap/extension-mention';
import { type SuggestionOptions } from '@tiptap/suggestion';

export interface MentionUser {
	id: string;
	name: string;
	email?: string;
	avatar?: string;
}

export interface MentionSuggestionProps {
	query: string;
	editor: unknown;
	range: { from: number; to: number };
	clientRect: (() => DOMRect | null) | null;
	command: (props: { id: string; label: string }) => void;
	decorationNode: Element | null;
	items: MentionUser[];
}

export type MentionFetchFunction = (query: string) => Promise<MentionUser[]> | MentionUser[];

/**
 * Creates a configured Mention extension with custom suggestion handling
 */
export function createMentionExtension(options: {
	fetchUsers: MentionFetchFunction;
	onMentionSelect?: (user: MentionUser) => void;
	renderDropdown: (props: MentionSuggestionProps) => {
		onStart: (props: MentionSuggestionProps) => void;
		onUpdate: (props: MentionSuggestionProps) => void;
		onKeyDown: (props: { event: KeyboardEvent }) => boolean;
		onExit: () => void;
	};
}) {
	const suggestion: Partial<SuggestionOptions<MentionUser>> = {
		char: '@',
		allowSpaces: false,
		allowedPrefixes: [' ', '\n'],

		items: async ({ query }) => {
			const users = await options.fetchUsers(query);
			return users.slice(0, 5); // Limit to 5 results
		},

		render: () => {
			let currentProps: MentionSuggestionProps | null = null;
			let handlers: ReturnType<typeof options.renderDropdown> | null = null;

			return {
				onStart: (props) => {
					currentProps = props as MentionSuggestionProps;
					handlers = options.renderDropdown(currentProps);
					handlers.onStart(currentProps);
				},

				onUpdate: (props) => {
					currentProps = props as MentionSuggestionProps;
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
			// Insert the mention
			editor
				.chain()
				.focus()
				.insertContentAt(range, [
					{
						type: 'mention',
						attrs: {
							id: props.id,
							label: props.name
						}
					},
					{
						type: 'text',
						text: ' '
					}
				])
				.run();

			// Callback after selection
			if (options.onMentionSelect) {
				options.onMentionSelect(props as MentionUser);
			}
		}
	};

	return Mention.configure({
		HTMLAttributes: {
			class: 'mention bg-primary/10 text-primary rounded px-1 py-0.5 font-medium'
		},
		suggestion: suggestion as SuggestionOptions<MentionUser>
	});
}

export { Mention };
