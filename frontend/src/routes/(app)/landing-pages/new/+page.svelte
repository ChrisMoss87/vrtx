<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { ArrowLeft, LayoutTemplate, FileText, Wand2 } from 'lucide-svelte';
	import {
		landingPageApi,
		landingPageTemplateApi,
		type LandingPageTemplate
	} from '$lib/api/landing-pages';
	import { toast } from 'svelte-sonner';

	let templates = $state<LandingPageTemplate[]>([]);
	let loading = $state(true);
	let creating = $state(false);

	let selectedTemplateId = $state<number | null>(null);
	let name = $state('');
	let slug = $state('');
	let description = $state('');

	let step = $state<'template' | 'details'>('template');

	onMount(async () => {
		try {
			templates = await landingPageTemplateApi.list();
		} catch (error) {
			toast.error('Failed to load templates');
		} finally {
			loading = false;
		}
	});

	function generateSlug(value: string): string {
		return value
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, '');
	}

	async function handleCreate() {
		if (!name.trim()) {
			toast.error('Please enter a page name');
			return;
		}

		creating = true;
		try {
			const page = await landingPageApi.create({
				name: name.trim(),
				slug: slug || generateSlug(name),
				description: description || undefined,
				template_id: selectedTemplateId || undefined
			});
			toast.success('Landing page created');
			goto(`/landing-pages/${page.id}/edit`);
		} catch (error) {
			toast.error('Failed to create landing page');
		} finally {
			creating = false;
		}
	}

	function selectTemplate(templateId: number | null) {
		selectedTemplateId = templateId;
		step = 'details';
	}
</script>

<div class="container max-w-4xl py-6">
	<Button variant="ghost" href="/landing-pages" class="mb-4">
		<ArrowLeft class="mr-1 h-4 w-4" />
		Back to Landing Pages
	</Button>

	<h1 class="mb-6 text-2xl font-bold">Create New Landing Page</h1>

	{#if step === 'template'}
		<div class="space-y-6">
			<div>
				<h2 class="mb-4 text-lg font-semibold">Choose a template</h2>
				<p class="text-muted-foreground mb-6">
					Start with a template or create a blank page from scratch.
				</p>
			</div>

			<!-- Blank option -->
			<button
				type="button"
				class="w-full text-left rounded-lg border bg-card transition-all hover:border-primary hover:shadow-md {selectedTemplateId === null ? 'border-primary' : ''}"
				onclick={() => selectTemplate(null)}
			>
				<div class="flex items-center gap-4 p-6">
					<div class="bg-muted flex h-16 w-16 items-center justify-center rounded-lg">
						<FileText class="text-muted-foreground h-8 w-8" />
					</div>
					<div>
						<h3 class="font-semibold">Blank Page</h3>
						<p class="text-muted-foreground text-sm">Start from scratch with an empty canvas</p>
					</div>
				</div>
			</button>

			{#if loading}
				<div class="text-muted-foreground py-8 text-center">Loading templates...</div>
			{:else if templates.length > 0}
				<div>
					<h3 class="text-muted-foreground mb-3 text-sm font-medium uppercase tracking-wide">
						Templates
					</h3>
					<div class="grid gap-4 md:grid-cols-2">
						{#each templates as template}
							<button
								type="button"
								class="w-full text-left rounded-lg border bg-card p-4 transition-all hover:border-primary hover:shadow-md {selectedTemplateId === template.id ? 'border-primary' : ''}"
								onclick={() => selectTemplate(template.id)}
							>
								{#if template.thumbnail_url}
									<img
										src={template.thumbnail_url}
										alt={template.name}
										class="mb-3 h-32 w-full rounded-lg object-cover"
									/>
								{:else}
									<div
										class="bg-muted mb-3 flex h-32 items-center justify-center rounded-lg"
									>
										<LayoutTemplate class="text-muted-foreground h-8 w-8" />
									</div>
								{/if}
								<h3 class="font-semibold">{template.name}</h3>
								{#if template.description}
									<p class="text-muted-foreground mt-1 text-sm">{template.description}</p>
								{/if}
								<div class="mt-2 flex items-center gap-2">
									<span class="bg-muted rounded px-2 py-0.5 text-xs">{template.category}</span>
									<span class="text-muted-foreground text-xs">
										Used {template.usage_count} times
									</span>
								</div>
							</button>
						{/each}
					</div>
				</div>
			{/if}
		</div>
	{:else}
		<Card.Root>
			<Card.Header>
				<Card.Title>Page Details</Card.Title>
				<Card.Description>
					{#if selectedTemplateId}
						Using template: {templates.find((t) => t.id === selectedTemplateId)?.name}
					{:else}
						Creating a blank page
					{/if}
				</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				<div>
					<Label for="name">Page Name *</Label>
					<Input
						id="name"
						bind:value={name}
						placeholder="e.g., Summer Sale Landing Page"
						oninput={(e) => {
							if (!slug) slug = generateSlug(e.currentTarget.value);
						}}
					/>
				</div>

				<div>
					<Label for="slug">URL Slug</Label>
					<div class="flex items-center gap-2">
						<span class="text-muted-foreground text-sm">/p/</span>
						<Input
							id="slug"
							bind:value={slug}
							placeholder="summer-sale"
							oninput={(e) => {
								slug = generateSlug(e.currentTarget.value);
							}}
						/>
					</div>
					<p class="text-muted-foreground mt-1 text-xs">
						Leave blank to auto-generate from the page name
					</p>
				</div>

				<div>
					<Label for="description">Description</Label>
					<Textarea
						id="description"
						bind:value={description}
						placeholder="Brief description of this landing page..."
						rows={3}
					/>
				</div>
			</Card.Content>
			<Card.Footer class="flex justify-between">
				<Button variant="outline" onclick={() => (step = 'template')}>
					Back to Templates
				</Button>
				<Button onclick={handleCreate} disabled={creating || !name.trim()}>
					{#if creating}
						Creating...
					{:else}
						<Wand2 class="mr-1 h-4 w-4" />
						Create Page
					{/if}
				</Button>
			</Card.Footer>
		</Card.Root>
	{/if}
</div>
