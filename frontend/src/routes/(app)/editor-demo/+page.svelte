<script lang="ts">
	import { RichTextEditor, RichTextEditorAdvanced, type MentionUser } from '$lib/components/editor';
	import { Button } from '$lib/components/ui/button';
	import {
		Card,
		CardContent,
		CardDescription,
		CardHeader,
		CardTitle
	} from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Tabs from '$lib/components/ui/tabs';

	let content = $state(`
		<h2>Welcome to the Rich Text Editor</h2>
		<p>This is a <strong>TipTap-powered</strong> rich text editor with full formatting support.</p>
		<h3>Features</h3>
		<ul>
			<li><strong>Bold</strong>, <em>italic</em>, <u>underline</u>, and <s>strikethrough</s></li>
			<li>Multiple heading levels</li>
			<li>Bullet and numbered lists</li>
			<li>Code blocks with syntax highlighting</li>
			<li>Links and images</li>
			<li>Tables with row/column management</li>
			<li>Text color and highlighting</li>
			<li>Text alignment</li>
		</ul>
		<blockquote>
			<p>This is a blockquote. Great for highlighting important information!</p>
		</blockquote>
		<p>Try out all the formatting options in the toolbar above.</p>
	`);

	let advancedContent = $state(`
		<h2>Advanced Editor Features</h2>
		<p>This editor supports <strong>@mentions</strong> and <strong>/slash commands</strong>!</p>
		<h3>How to use:</h3>
		<ul>
			<li>Type <strong>@</strong> followed by a name to mention a user</li>
			<li>Type <strong>/</strong> to open the command menu and insert blocks</li>
		</ul>
		<p>Try it out below! Start typing @ or / to see the magic.</p>
	`);

	let readonly = $state(false);
	let disabled = $state(false);
	let showToolbar = $state(true);
	let characterLimit = $state<number | undefined>(undefined);
	let useLimit = $state(false);
	let enableMentions = $state(true);
	let enableSlashCommands = $state(true);

	let editorRef:
		| { getHTML: () => string; getText: () => string; isEmpty: () => boolean }
		| undefined;

	// Demo users for mentions
	const demoUsers: MentionUser[] = [
		{ id: '1', name: 'John Smith', email: 'john.smith@example.com' },
		{ id: '2', name: 'Jane Doe', email: 'jane.doe@example.com' },
		{ id: '3', name: 'Bob Wilson', email: 'bob.wilson@example.com' },
		{ id: '4', name: 'Alice Johnson', email: 'alice.johnson@example.com' },
		{ id: '5', name: 'Charlie Brown', email: 'charlie.brown@example.com' },
		{ id: '6', name: 'Diana Prince', email: 'diana.prince@example.com' },
		{ id: '7', name: 'Edward Norton', email: 'edward.norton@example.com' }
	];

	function fetchUsers(query: string): MentionUser[] {
		return demoUsers.filter(
			(u) =>
				u.name.toLowerCase().includes(query.toLowerCase()) ||
				u.email?.toLowerCase().includes(query.toLowerCase())
		);
	}

	function handleMention(user: MentionUser) {
		console.log('User mentioned:', user);
	}

	function handleChange(html: string) {
		console.log('Content changed:', html.substring(0, 100) + '...');
	}

	function logContent() {
		if (editorRef) {
			console.log('HTML:', editorRef.getHTML());
			console.log('Text:', editorRef.getText());
			console.log('Is Empty:', editorRef.isEmpty());
		}
	}

	$effect(() => {
		characterLimit = useLimit ? 1000 : undefined;
	});
</script>

<div class="container mx-auto space-y-8 py-8">
	<div>
		<h1 class="text-3xl font-bold">Rich Text Editor Demo</h1>
		<p class="mt-2 text-muted-foreground">
			Phase 5: TipTap-based rich text editor with mentions, slash commands, and advanced formatting
		</p>
	</div>

	<Tabs.Root value="basic" class="w-full">
		<Tabs.List class="grid w-full grid-cols-2">
			<Tabs.Trigger value="basic">Basic Editor</Tabs.Trigger>
			<Tabs.Trigger value="advanced">Advanced Editor</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="basic" class="mt-6 space-y-6">
			<!-- Controls -->
			<Card>
				<CardHeader>
					<CardTitle>Editor Controls</CardTitle>
					<CardDescription>Toggle editor options to see different modes</CardDescription>
				</CardHeader>
				<CardContent class="flex flex-wrap gap-6">
					<div class="flex items-center gap-2">
						<Switch id="readonly" bind:checked={readonly} />
						<Label for="readonly">Read Only</Label>
					</div>
					<div class="flex items-center gap-2">
						<Switch id="disabled" bind:checked={disabled} />
						<Label for="disabled">Disabled</Label>
					</div>
					<div class="flex items-center gap-2">
						<Switch id="toolbar" bind:checked={showToolbar} />
						<Label for="toolbar">Show Toolbar</Label>
					</div>
					<div class="flex items-center gap-2">
						<Switch id="limit" bind:checked={useLimit} />
						<Label for="limit">Character Limit (1000)</Label>
					</div>
					<Button onclick={logContent} variant="outline">Log Content</Button>
				</CardContent>
			</Card>

			<!-- Basic Editor -->
			<Card>
				<CardHeader>
					<CardTitle>Basic Editor</CardTitle>
					<CardDescription>Full-featured rich text editor with toolbar</CardDescription>
				</CardHeader>
				<CardContent>
					<RichTextEditor
						bind:this={editorRef}
						bind:content
						{readonly}
						{disabled}
						{showToolbar}
						{characterLimit}
						placeholder="Start writing your content..."
						minHeight="200px"
						maxHeight="600px"
						onchange={handleChange}
					/>
				</CardContent>
			</Card>

			<!-- HTML Output -->
			<Card>
				<CardHeader>
					<CardTitle>HTML Output</CardTitle>
					<CardDescription>Raw HTML generated by the editor</CardDescription>
				</CardHeader>
				<CardContent>
					<pre
						class="max-h-96 overflow-x-auto rounded-md bg-muted p-4 text-xs whitespace-pre-wrap">{content}</pre>
				</CardContent>
			</Card>
		</Tabs.Content>

		<Tabs.Content value="advanced" class="mt-6 space-y-6">
			<!-- Advanced Controls -->
			<Card>
				<CardHeader>
					<CardTitle>Advanced Features</CardTitle>
					<CardDescription>Toggle mentions and slash commands</CardDescription>
				</CardHeader>
				<CardContent class="flex flex-wrap gap-6">
					<div class="flex items-center gap-2">
						<Switch id="mentions" bind:checked={enableMentions} />
						<Label for="mentions">Enable @mentions</Label>
					</div>
					<div class="flex items-center gap-2">
						<Switch id="slashCommands" bind:checked={enableSlashCommands} />
						<Label for="slashCommands">Enable /commands</Label>
					</div>
				</CardContent>
			</Card>

			<!-- Advanced Editor -->
			<Card>
				<CardHeader>
					<CardTitle>Advanced Editor</CardTitle>
					<CardDescription>
						Editor with @mentions and /slash commands - Type @ or / to try!
					</CardDescription>
				</CardHeader>
				<CardContent>
					<RichTextEditorAdvanced
						bind:content={advancedContent}
						{enableMentions}
						{enableSlashCommands}
						{fetchUsers}
						onMention={handleMention}
						placeholder="Type @ to mention someone, / for commands..."
						minHeight="250px"
						maxHeight="600px"
					/>
				</CardContent>
			</Card>

			<!-- Advanced HTML Output -->
			<Card>
				<CardHeader>
					<CardTitle>HTML Output</CardTitle>
					<CardDescription>Raw HTML with mention nodes</CardDescription>
				</CardHeader>
				<CardContent>
					<pre
						class="max-h-96 overflow-x-auto rounded-md bg-muted p-4 text-xs whitespace-pre-wrap">{advancedContent}</pre>
				</CardContent>
			</Card>

			<!-- Demo Users -->
			<Card>
				<CardHeader>
					<CardTitle>Available Users for Mentions</CardTitle>
					<CardDescription>Type @ followed by any of these names</CardDescription>
				</CardHeader>
				<CardContent>
					<div class="flex flex-wrap gap-2">
						{#each demoUsers as user}
							<span class="rounded-full bg-muted px-3 py-1 text-sm">
								{user.name}
							</span>
						{/each}
					</div>
				</CardContent>
			</Card>

			<!-- Slash Commands -->
			<Card>
				<CardHeader>
					<CardTitle>Available Slash Commands</CardTitle>
					<CardDescription>Type / followed by any of these commands</CardDescription>
				</CardHeader>
				<CardContent>
					<div class="grid gap-4 md:grid-cols-3">
						<div>
							<h4 class="mb-2 text-sm font-medium">Headings</h4>
							<ul class="space-y-1 text-sm text-muted-foreground">
								<li>/heading1 - Large heading</li>
								<li>/heading2 - Medium heading</li>
								<li>/heading3 - Small heading</li>
							</ul>
						</div>
						<div>
							<h4 class="mb-2 text-sm font-medium">Lists</h4>
							<ul class="space-y-1 text-sm text-muted-foreground">
								<li>/bullet - Bullet list</li>
								<li>/numbered - Numbered list</li>
							</ul>
						</div>
						<div>
							<h4 class="mb-2 text-sm font-medium">Blocks</h4>
							<ul class="space-y-1 text-sm text-muted-foreground">
								<li>/quote - Blockquote</li>
								<li>/code - Code block</li>
								<li>/divider - Horizontal rule</li>
								<li>/table - Insert table</li>
								<li>/image - Insert image</li>
							</ul>
						</div>
					</div>
				</CardContent>
			</Card>
		</Tabs.Content>
	</Tabs.Root>

	<!-- Features Summary -->
	<Card>
		<CardHeader>
			<CardTitle>Phase 5 Implementation Summary</CardTitle>
			<CardDescription>Rich Text Editor Features</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="grid gap-6 md:grid-cols-3">
				<div>
					<h4 class="mb-2 font-medium">Text Formatting</h4>
					<ul class="space-y-1 text-sm text-muted-foreground">
						<li>Bold, Italic, Underline, Strikethrough</li>
						<li>Headings (H1-H6)</li>
						<li>Text color and highlighting</li>
						<li>Text alignment</li>
						<li>Inline code</li>
					</ul>
				</div>
				<div>
					<h4 class="mb-2 font-medium">Block Elements</h4>
					<ul class="space-y-1 text-sm text-muted-foreground">
						<li>Bullet and numbered lists</li>
						<li>Blockquotes</li>
						<li>Code blocks</li>
						<li>Horizontal rules</li>
						<li>Tables with management</li>
					</ul>
				</div>
				<div>
					<h4 class="mb-2 font-medium">Advanced Features</h4>
					<ul class="space-y-1 text-sm text-muted-foreground">
						<li>@mentions with user search</li>
						<li>/slash commands</li>
						<li>Links and images</li>
						<li>Character/word count</li>
						<li>Undo/Redo</li>
					</ul>
				</div>
			</div>
		</CardContent>
	</Card>
</div>
