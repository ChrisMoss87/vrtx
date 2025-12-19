<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import { chatCannedResponsesApi, type ChatCannedResponse } from '$lib/api/live-chat';
	import { Plus, Pencil, Trash2, Search, Loader2, MessageSquare } from 'lucide-svelte';

	let loading = $state(true);
	let responses = $state<ChatCannedResponse[]>([]);
	let searchQuery = $state('');
	let showDialog = $state(false);
	let editingResponse = $state<ChatCannedResponse | null>(null);
	let saving = $state(false);

	// Form state
	let shortcut = $state('');
	let title = $state('');
	let content = $state('');
	let category = $state('');
	let isGlobal = $state(true);

	async function loadResponses() {
		loading = true;
		try {
			responses = await chatCannedResponsesApi.list();
		} catch (err) {
			console.error('Failed to load canned responses:', err);
		}
		loading = false;
	}

	function openCreateDialog() {
		editingResponse = null;
		shortcut = '';
		title = '';
		content = '';
		category = '';
		isGlobal = true;
		showDialog = true;
	}

	function openEditDialog(response: ChatCannedResponse) {
		editingResponse = response;
		shortcut = response.shortcut;
		title = response.title;
		content = response.content;
		category = response.category || '';
		isGlobal = response.is_global;
		showDialog = true;
	}

	async function handleSave() {
		if (!shortcut.trim() || !title.trim() || !content.trim()) return;

		saving = true;
		try {
			const data = {
				shortcut: shortcut.trim().replace(/^\//, ''),
				title: title.trim(),
				content: content.trim(),
				category: category.trim() || undefined,
				is_global: isGlobal
			};

			if (editingResponse) {
				await chatCannedResponsesApi.update(editingResponse.id, data);
			} else {
				await chatCannedResponsesApi.create(data);
			}

			showDialog = false;
			loadResponses();
		} catch (err) {
			console.error('Failed to save canned response:', err);
		}
		saving = false;
	}

	async function handleDelete(id: number) {
		if (!confirm('Are you sure you want to delete this canned response?')) return;

		try {
			await chatCannedResponsesApi.delete(id);
			loadResponses();
		} catch (err) {
			console.error('Failed to delete canned response:', err);
		}
	}

	const filteredResponses = $derived(
		responses.filter((r) => {
			if (!searchQuery) return true;
			const query = searchQuery.toLowerCase();
			return (
				r.shortcut.toLowerCase().includes(query) ||
				r.title.toLowerCase().includes(query) ||
				r.content.toLowerCase().includes(query) ||
				r.category?.toLowerCase().includes(query)
			);
		})
	);

	const groupedResponses = $derived(() => {
		const groups: Record<string, ChatCannedResponse[]> = {};
		filteredResponses.forEach((r) => {
			const cat = r.category || 'Uncategorized';
			if (!groups[cat]) groups[cat] = [];
			groups[cat].push(r);
		});
		return groups;
	});

	onMount(loadResponses);
</script>

<Card>
	<CardHeader>
		<div class="flex items-center justify-between">
			<div>
				<CardTitle>Canned Responses</CardTitle>
				<CardDescription>
					Pre-written responses for common questions. Use /{'{shortcut}'} to quickly insert.
				</CardDescription>
			</div>
			<Button onclick={openCreateDialog}>
				<Plus class="h-4 w-4 mr-2" />
				Add Response
			</Button>
		</div>
	</CardHeader>
	<CardContent>
		<div class="mb-4">
			<div class="relative">
				<Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
				<Input
					type="search"
					placeholder="Search responses..."
					class="pl-8"
					bind:value={searchQuery}
				/>
			</div>
		</div>

		{#if loading}
			<div class="flex items-center justify-center h-32">
				<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
			</div>
		{:else if filteredResponses.length === 0}
			<div class="flex flex-col items-center justify-center h-32 text-muted-foreground">
				<MessageSquare class="h-8 w-8 mb-2 opacity-50" />
				<p class="text-sm">No canned responses found</p>
			</div>
		{:else}
			<div class="space-y-6">
				{#each Object.entries(groupedResponses()) as [category, items]}
					<div>
						<h4 class="text-sm font-medium text-muted-foreground mb-2">{category}</h4>
						<div class="space-y-2">
							{#each items as response}
								<div
									class="flex items-start justify-between p-3 rounded-lg border hover:bg-muted/50"
								>
									<div class="flex-1 min-w-0">
										<div class="flex items-center gap-2">
											<code class="text-sm font-medium bg-muted px-1.5 py-0.5 rounded">
												/{response.shortcut}
											</code>
											<span class="text-sm">{response.title}</span>
											{#if response.is_global}
												<span class="text-xs text-muted-foreground">(Global)</span>
											{/if}
										</div>
										<p class="text-sm text-muted-foreground mt-1 line-clamp-2">
											{response.content}
										</p>
										<p class="text-xs text-muted-foreground mt-1">
											Used {response.usage_count} times
										</p>
									</div>
									<div class="flex items-center gap-1 ml-4">
										<Button
											variant="ghost"
											size="icon"
											class="h-8 w-8"
											onclick={() => openEditDialog(response)}
										>
											<Pencil class="h-4 w-4" />
										</Button>
										<Button
											variant="ghost"
											size="icon"
											class="h-8 w-8 text-destructive"
											onclick={() => handleDelete(response.id)}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							{/each}
						</div>
					</div>
				{/each}
			</div>
		{/if}
	</CardContent>
</Card>

<Dialog.Root bind:open={showDialog}>
	<Dialog.Content class="max-w-lg">
		<Dialog.Header>
			<Dialog.Title>
				{editingResponse ? 'Edit Canned Response' : 'Create Canned Response'}
			</Dialog.Title>
		</Dialog.Header>

		<form
			onsubmit={(e) => {
				e.preventDefault();
				handleSave();
			}}
			class="space-y-4"
		>
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="shortcut">Shortcut *</Label>
					<div class="flex">
						<span class="inline-flex items-center px-3 rounded-l-md border border-r-0 bg-muted text-muted-foreground text-sm">
							/
						</span>
						<Input
							id="shortcut"
							bind:value={shortcut}
							placeholder="hello"
							class="rounded-l-none"
						/>
					</div>
				</div>

				<div class="space-y-2">
					<Label for="category">Category</Label>
					<Input id="category" bind:value={category} placeholder="General" />
				</div>
			</div>

			<div class="space-y-2">
				<Label for="title">Title *</Label>
				<Input id="title" bind:value={title} placeholder="Greeting message" />
			</div>

			<div class="space-y-2">
				<Label for="content">Content *</Label>
				<Textarea
					id="content"
					bind:value={content}
					placeholder="Hi! Thanks for reaching out. How can I help you today?"
					rows={5}
				/>
				<p class="text-xs text-muted-foreground">
					Use {'{'}visitor_name{'}'} and {'{'}agent_name{'}'} as placeholders.
				</p>
			</div>

			<div class="flex items-center justify-between">
				<div>
					<Label>Global Response</Label>
					<p class="text-sm text-muted-foreground">Available to all agents</p>
				</div>
				<Switch bind:checked={isGlobal} />
			</div>
		</form>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (showDialog = false)}>Cancel</Button>
			<Button onclick={handleSave} disabled={saving || !shortcut.trim() || !title.trim() || !content.trim()}>
				{#if saving}
					<Loader2 class="h-4 w-4 mr-2 animate-spin" />
				{/if}
				{editingResponse ? 'Update' : 'Create'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
