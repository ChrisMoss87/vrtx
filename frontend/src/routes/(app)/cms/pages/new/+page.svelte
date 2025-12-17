<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Switch } from '$lib/components/ui/switch';
	import { ArrowLeft, Save, Eye, FileText, Settings, Search as SearchIcon } from 'lucide-svelte';
	import { cmsPageApi, cmsTemplateApi, cmsCategoryApi, type PageType, type CmsTemplate, type CmsCategory } from '$lib/api/cms';
	import { toast } from 'svelte-sonner';

	const urlParams = $page.url.searchParams;
	const initialType = (urlParams.get('type') as PageType) || 'page';

	let saving = $state(false);
	let templates = $state<CmsTemplate[]>([]);
	let categories = $state<CmsCategory[]>([]);

	// Form state
	let title = $state('');
	let slug = $state('');
	let type = $state<PageType>(initialType);
	let excerpt = $state('');
	let templateId = $state<number | null>(null);
	let metaTitle = $state('');
	let metaDescription = $state('');
	let metaKeywords = $state('');
	let canonicalUrl = $state('');
	let noindex = $state(false);
	let nofollow = $state(false);
	let selectedCategoryIds = $state<number[]>([]);
	let tagNames = $state<string[]>([]);
	let tagInput = $state('');

	let activeTab = $state('content');

	const pageTypes: { value: PageType; label: string }[] = [
		{ value: 'page', label: 'Page' },
		{ value: 'blog', label: 'Blog Post' },
		{ value: 'landing', label: 'Landing Page' },
		{ value: 'article', label: 'Article' }
	];

	$effect(() => {
		loadData();
	});

	async function loadData() {
		try {
			const [templatesRes, categoriesRes] = await Promise.all([
				cmsTemplateApi.list({ type: type, is_active: true }),
				cmsCategoryApi.getTree()
			]);
			templates = templatesRes.data;
			categories = categoriesRes;
		} catch (error) {
			console.error('Failed to load data', error);
		}
	}

	function generateSlug(text: string): string {
		return text
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/(^-|-$)/g, '');
	}

	function handleTitleChange() {
		if (!slug || slug === generateSlug(title.slice(0, -1))) {
			slug = generateSlug(title);
		}
	}

	function addTag() {
		const tag = tagInput.trim();
		if (tag && !tagNames.includes(tag)) {
			tagNames = [...tagNames, tag];
		}
		tagInput = '';
	}

	function removeTag(tag: string) {
		tagNames = tagNames.filter((t) => t !== tag);
	}

	function toggleCategory(categoryId: number) {
		if (selectedCategoryIds.includes(categoryId)) {
			selectedCategoryIds = selectedCategoryIds.filter((id) => id !== categoryId);
		} else {
			selectedCategoryIds = [...selectedCategoryIds, categoryId];
		}
	}

	async function handleSave() {
		if (!title.trim()) {
			toast.error('Title is required');
			return;
		}

		saving = true;
		try {
			const newPage = await cmsPageApi.create({
				title: title.trim(),
				slug: slug.trim() || undefined,
				type,
				excerpt: excerpt.trim() || undefined,
				template_id: templateId || undefined,
				meta_title: metaTitle.trim() || undefined,
				meta_description: metaDescription.trim() || undefined,
				meta_keywords: metaKeywords.trim() || undefined,
				canonical_url: canonicalUrl.trim() || undefined,
				noindex,
				nofollow,
				category_ids: selectedCategoryIds.length > 0 ? selectedCategoryIds : undefined,
				tag_names: tagNames.length > 0 ? tagNames : undefined
			});

			toast.success('Page created successfully');
			goto(`/cms/pages/${newPage.id}/edit`);
		} catch (error) {
			toast.error('Failed to create page');
		} finally {
			saving = false;
		}
	}
</script>

<div class="container py-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="sm" href="/cms/pages">
				<ArrowLeft class="mr-1 h-4 w-4" />
				Back
			</Button>
			<div>
				<h1 class="text-2xl font-bold">New {pageTypes.find((p) => p.value === type)?.label}</h1>
				<p class="text-muted-foreground">Create a new {type}</p>
			</div>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" disabled={saving}>
				<Eye class="mr-1 h-4 w-4" />
				Preview
			</Button>
			<Button onclick={handleSave} disabled={saving}>
				<Save class="mr-1 h-4 w-4" />
				{saving ? 'Saving...' : 'Save Draft'}
			</Button>
		</div>
	</div>

	<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
		<!-- Main Content -->
		<div class="lg:col-span-2">
			<Tabs.Root bind:value={activeTab}>
				<Tabs.List class="mb-4">
					<Tabs.Trigger value="content">
						<FileText class="mr-1 h-4 w-4" />
						Content
					</Tabs.Trigger>
					<Tabs.Trigger value="seo">
						<SearchIcon class="mr-1 h-4 w-4" />
						SEO
					</Tabs.Trigger>
				</Tabs.List>

				<Tabs.Content value="content">
					<Card.Root>
						<Card.Content class="space-y-4 pt-6">
							<div class="space-y-2">
								<Label for="title">Title</Label>
								<Input
									id="title"
									placeholder="Enter page title"
									bind:value={title}
									oninput={handleTitleChange}
								/>
							</div>

							<div class="space-y-2">
								<Label for="slug">Slug</Label>
								<div class="flex items-center gap-2">
									<span class="text-muted-foreground text-sm">/</span>
									<Input
										id="slug"
										placeholder="page-url-slug"
										bind:value={slug}
									/>
								</div>
							</div>

							<div class="space-y-2">
								<Label for="excerpt">Excerpt</Label>
								<Textarea
									id="excerpt"
									placeholder="Brief description of the page..."
									bind:value={excerpt}
									rows={3}
								/>
							</div>

							{#if templates.length > 0}
								<div class="space-y-2">
									<Label>Template</Label>
									<Select.Root
										type="single"
										value={templateId?.toString() ?? ''}
										onValueChange={(val) => { templateId = val ? parseInt(val) : null; }}
									>
										<Select.Trigger>
											<span>{templateId ? templates.find((t) => t.id === templateId)?.name : 'Select a template'}</span>
										</Select.Trigger>
										<Select.Content>
											<Select.Item value="">No template</Select.Item>
											{#each templates as template}
												<Select.Item value={template.id.toString()}>{template.name}</Select.Item>
											{/each}
										</Select.Content>
									</Select.Root>
								</div>
							{/if}

							<div class="rounded-lg border bg-muted/30 p-8 text-center">
								<p class="text-muted-foreground mb-2">Page content editor</p>
								<p class="text-muted-foreground text-sm">
									Save this page first, then you can add content blocks in the editor.
								</p>
							</div>
						</Card.Content>
					</Card.Root>
				</Tabs.Content>

				<Tabs.Content value="seo">
					<Card.Root>
						<Card.Content class="space-y-4 pt-6">
							<div class="space-y-2">
								<Label for="metaTitle">Meta Title</Label>
								<Input
									id="metaTitle"
									placeholder="SEO title (defaults to page title)"
									bind:value={metaTitle}
								/>
								<p class="text-muted-foreground text-xs">
									{metaTitle.length}/60 characters
								</p>
							</div>

							<div class="space-y-2">
								<Label for="metaDescription">Meta Description</Label>
								<Textarea
									id="metaDescription"
									placeholder="SEO description for search engines..."
									bind:value={metaDescription}
									rows={3}
								/>
								<p class="text-muted-foreground text-xs">
									{metaDescription.length}/160 characters
								</p>
							</div>

							<div class="space-y-2">
								<Label for="metaKeywords">Meta Keywords</Label>
								<Input
									id="metaKeywords"
									placeholder="keyword1, keyword2, keyword3"
									bind:value={metaKeywords}
								/>
							</div>

							<div class="space-y-2">
								<Label for="canonicalUrl">Canonical URL</Label>
								<Input
									id="canonicalUrl"
									type="url"
									placeholder="https://example.com/original-page"
									bind:value={canonicalUrl}
								/>
							</div>

							<div class="flex items-center justify-between rounded-lg border p-4">
								<div>
									<Label>Noindex</Label>
									<p class="text-muted-foreground text-sm">
										Prevent search engines from indexing this page
									</p>
								</div>
								<Switch bind:checked={noindex} />
							</div>

							<div class="flex items-center justify-between rounded-lg border p-4">
								<div>
									<Label>Nofollow</Label>
									<p class="text-muted-foreground text-sm">
										Prevent search engines from following links on this page
									</p>
								</div>
								<Switch bind:checked={nofollow} />
							</div>
						</Card.Content>
					</Card.Root>
				</Tabs.Content>
			</Tabs.Root>
		</div>

		<!-- Sidebar -->
		<div class="space-y-6">
			<!-- Settings -->
			<Card.Root>
				<Card.Header>
					<Card.Title class="flex items-center gap-2">
						<Settings class="h-4 w-4" />
						Settings
					</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="space-y-2">
						<Label>Type</Label>
						<Select.Root
							type="single"
							value={type}
							onValueChange={(val) => { if (val) type = val as PageType; }}
						>
							<Select.Trigger>
								<span>{pageTypes.find((p) => p.value === type)?.label}</span>
							</Select.Trigger>
							<Select.Content>
								{#each pageTypes as pt}
									<Select.Item value={pt.value}>{pt.label}</Select.Item>
								{/each}
							</Select.Content>
						</Select.Root>
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Categories -->
			{#if categories.length > 0}
				<Card.Root>
					<Card.Header>
						<Card.Title>Categories</Card.Title>
					</Card.Header>
					<Card.Content>
						<div class="max-h-48 space-y-2 overflow-y-auto">
							{#each categories as category}
								<label class="flex cursor-pointer items-center gap-2">
									<input
										type="checkbox"
										checked={selectedCategoryIds.includes(category.id)}
										onchange={() => toggleCategory(category.id)}
										class="h-4 w-4 rounded border-gray-300"
									/>
									<span class="text-sm">{category.name}</span>
								</label>
								{#if category.children && category.children.length > 0}
									{#each category.children as child}
										<label class="ml-6 flex cursor-pointer items-center gap-2">
											<input
												type="checkbox"
												checked={selectedCategoryIds.includes(child.id)}
												onchange={() => toggleCategory(child.id)}
												class="h-4 w-4 rounded border-gray-300"
											/>
											<span class="text-sm">{child.name}</span>
										</label>
									{/each}
								{/if}
							{/each}
						</div>
					</Card.Content>
				</Card.Root>
			{/if}

			<!-- Tags -->
			<Card.Root>
				<Card.Header>
					<Card.Title>Tags</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-3">
					<div class="flex gap-2">
						<Input
							placeholder="Add tag..."
							bind:value={tagInput}
							onkeydown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addTag(); } }}
						/>
						<Button variant="outline" size="sm" onclick={addTag}>Add</Button>
					</div>
					{#if tagNames.length > 0}
						<div class="flex flex-wrap gap-1">
							{#each tagNames as tag}
								<span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs">
									{tag}
									<button
										type="button"
										onclick={() => removeTag(tag)}
										class="hover:text-destructive"
									>
										&times;
									</button>
								</span>
							{/each}
						</div>
					{/if}
				</Card.Content>
			</Card.Root>
		</div>
	</div>
</div>
