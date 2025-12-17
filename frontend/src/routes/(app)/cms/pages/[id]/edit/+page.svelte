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
	import * as Dialog from '$lib/components/ui/dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import {
		ArrowLeft,
		Save,
		Eye,
		FileText,
		Settings,
		Search as SearchIcon,
		Globe,
		Clock,
		History,
		MoreHorizontal,
		Plus,
		Trash2,
		GripVertical,
		Copy,
		Type,
		Image,
		Video,
		Square,
		Minus,
		Columns,
		Layout,
		Megaphone,
		HelpCircle,
		DollarSign,
		MessageSquare,
		Grid,
		Code,
		Table
	} from 'lucide-svelte';
	import {
		cmsPageApi,
		cmsTemplateApi,
		cmsCategoryApi,
		type CmsPage,
		type PageType,
		type CmsTemplate,
		type CmsCategory,
		type ContentBlock,
		type BlockType,
		getPageStatusColor,
		getPageStatusLabel,
		createDefaultBlock
	} from '$lib/api/cms';
	import { toast } from 'svelte-sonner';
	import { onMount } from 'svelte';

	const pageId = parseInt($page.params.id);

	let loading = $state(true);
	let saving = $state(false);
	let pageData = $state<CmsPage | null>(null);
	let templates = $state<CmsTemplate[]>([]);
	let categories = $state<CmsCategory[]>([]);

	// Form state
	let title = $state('');
	let slug = $state('');
	let type = $state<PageType>('page');
	let excerpt = $state('');
	let content = $state<ContentBlock[]>([]);
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
	let showScheduleDialog = $state(false);
	let scheduleDate = $state('');
	let scheduleTime = $state('');
	let showBlockPicker = $state(false);
	let selectedBlockIndex = $state<number | null>(null);

	const pageTypes: { value: PageType; label: string }[] = [
		{ value: 'page', label: 'Page' },
		{ value: 'blog', label: 'Blog Post' },
		{ value: 'landing', label: 'Landing Page' },
		{ value: 'article', label: 'Article' }
	];

	const blockTypes: { type: BlockType; label: string; icon: typeof Type; category: string }[] = [
		{ type: 'heading', label: 'Heading', icon: Type, category: 'Text' },
		{ type: 'paragraph', label: 'Paragraph', icon: FileText, category: 'Text' },
		{ type: 'image', label: 'Image', icon: Image, category: 'Media' },
		{ type: 'video', label: 'Video', icon: Video, category: 'Media' },
		{ type: 'button', label: 'Button', icon: Square, category: 'Basic' },
		{ type: 'divider', label: 'Divider', icon: Minus, category: 'Basic' },
		{ type: 'columns', label: 'Columns', icon: Columns, category: 'Layout' },
		{ type: 'section', label: 'Section', icon: Layout, category: 'Layout' },
		{ type: 'hero', label: 'Hero', icon: Layout, category: 'Components' },
		{ type: 'cta', label: 'Call to Action', icon: Megaphone, category: 'Components' },
		{ type: 'faq', label: 'FAQ', icon: HelpCircle, category: 'Components' },
		{ type: 'pricing', label: 'Pricing', icon: DollarSign, category: 'Components' },
		{ type: 'testimonials', label: 'Testimonials', icon: MessageSquare, category: 'Components' },
		{ type: 'gallery', label: 'Gallery', icon: Grid, category: 'Media' },
		{ type: 'embed', label: 'Embed', icon: Code, category: 'Advanced' },
		{ type: 'html', label: 'Custom HTML', icon: Code, category: 'Advanced' },
		{ type: 'table', label: 'Table', icon: Table, category: 'Basic' }
	];

	onMount(async () => {
		await loadPage();
	});

	async function loadPage() {
		loading = true;
		try {
			const [pageRes, templatesRes, categoriesRes] = await Promise.all([
				cmsPageApi.get(pageId),
				cmsTemplateApi.list({ is_active: true }),
				cmsCategoryApi.getTree()
			]);

			pageData = pageRes;
			templates = templatesRes.data;
			categories = categoriesRes;

			// Populate form state
			title = pageRes.title;
			slug = pageRes.slug;
			type = pageRes.type;
			excerpt = pageRes.excerpt || '';
			content = pageRes.content || [];
			templateId = pageRes.template_id;
			metaTitle = pageRes.meta_title || '';
			metaDescription = pageRes.meta_description || '';
			metaKeywords = pageRes.meta_keywords || '';
			canonicalUrl = pageRes.canonical_url || '';
			noindex = pageRes.noindex;
			nofollow = pageRes.nofollow;
			selectedCategoryIds = pageRes.categories?.map((c) => c.id) || [];
			tagNames = pageRes.tags?.map((t) => t.name) || [];
		} catch (error) {
			toast.error('Failed to load page');
			goto('/cms/pages');
		} finally {
			loading = false;
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

	function addBlock(blockType: BlockType, index?: number) {
		const newBlock = createDefaultBlock(blockType);
		if (index !== undefined) {
			content = [...content.slice(0, index + 1), newBlock, ...content.slice(index + 1)];
		} else {
			content = [...content, newBlock];
		}
		showBlockPicker = false;
		selectedBlockIndex = null;
	}

	function removeBlock(index: number) {
		content = content.filter((_, i) => i !== index);
	}

	function duplicateBlock(index: number) {
		const block = content[index];
		const newBlock = { ...block, id: `block_${Math.random().toString(36).substring(2, 11)}` };
		content = [...content.slice(0, index + 1), newBlock, ...content.slice(index + 1)];
	}

	function moveBlock(from: number, to: number) {
		const newContent = [...content];
		const [moved] = newContent.splice(from, 1);
		newContent.splice(to, 0, moved);
		content = newContent;
	}

	function updateBlockProps(index: number, props: Record<string, unknown>) {
		content = content.map((block, i) => (i === index ? { ...block, props: { ...block.props, ...props } } : block));
	}

	async function handleSave(publish = false) {
		if (!title.trim()) {
			toast.error('Title is required');
			return;
		}

		saving = true;
		try {
			await cmsPageApi.update(pageId, {
				title: title.trim(),
				slug: slug.trim() || undefined,
				type,
				excerpt: excerpt.trim() || undefined,
				content,
				template_id: templateId || undefined,
				meta_title: metaTitle.trim() || undefined,
				meta_description: metaDescription.trim() || undefined,
				meta_keywords: metaKeywords.trim() || undefined,
				canonical_url: canonicalUrl.trim() || undefined,
				noindex,
				nofollow,
				category_ids: selectedCategoryIds.length > 0 ? selectedCategoryIds : undefined,
				tag_names: tagNames.length > 0 ? tagNames : undefined,
				create_version: true
			});

			if (publish) {
				await cmsPageApi.publish(pageId);
				toast.success('Page published');
			} else {
				toast.success('Page saved');
			}

			await loadPage();
		} catch (error) {
			toast.error(publish ? 'Failed to publish page' : 'Failed to save page');
		} finally {
			saving = false;
		}
	}

	async function handlePublish() {
		await handleSave(true);
	}

	async function handleUnpublish() {
		try {
			await cmsPageApi.unpublish(pageId);
			toast.success('Page unpublished');
			await loadPage();
		} catch (error) {
			toast.error('Failed to unpublish page');
		}
	}

	async function handleSchedule() {
		if (!scheduleDate || !scheduleTime) {
			toast.error('Please select date and time');
			return;
		}

		try {
			await handleSave();
			await cmsPageApi.schedule(pageId, `${scheduleDate}T${scheduleTime}:00`);
			toast.success('Page scheduled');
			showScheduleDialog = false;
			await loadPage();
		} catch (error) {
			toast.error('Failed to schedule page');
		}
	}

	function getBlockIcon(blockType: BlockType) {
		return blockTypes.find((b) => b.type === blockType)?.icon || Square;
	}

	function getBlockLabel(blockType: BlockType) {
		return blockTypes.find((b) => b.type === blockType)?.label || blockType;
	}
</script>

{#if loading}
	<div class="flex h-[50vh] items-center justify-center">
		<div class="text-muted-foreground">Loading...</div>
	</div>
{:else if pageData}
	<div class="container py-6">
		<!-- Header -->
		<div class="mb-6 flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="sm" href="/cms/pages">
					<ArrowLeft class="mr-1 h-4 w-4" />
					Back
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<h1 class="text-2xl font-bold">{title || 'Untitled'}</h1>
						<Badge class={getPageStatusColor(pageData.status)}>
							{getPageStatusLabel(pageData.status)}
						</Badge>
					</div>
					<p class="text-muted-foreground text-sm">/{slug}</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<Button variant="outline" href={`/cms/pages/${pageId}/versions`}>
					<History class="mr-1 h-4 w-4" />
					Versions
				</Button>
				<Button variant="outline" disabled={saving}>
					<Eye class="mr-1 h-4 w-4" />
					Preview
				</Button>
				<Button variant="outline" onclick={() => handleSave()} disabled={saving}>
					<Save class="mr-1 h-4 w-4" />
					{saving ? 'Saving...' : 'Save'}
				</Button>
				{#if pageData.status === 'published'}
					<Button variant="destructive" onclick={handleUnpublish} disabled={saving}>
						Unpublish
					</Button>
				{:else}
					<DropdownMenu.Root>
						<DropdownMenu.Trigger>
							<Button disabled={saving}>
								<Globe class="mr-1 h-4 w-4" />
								Publish
							</Button>
						</DropdownMenu.Trigger>
						<DropdownMenu.Content align="end">
							<DropdownMenu.Item onclick={handlePublish}>
								<Globe class="mr-2 h-4 w-4" />
								Publish Now
							</DropdownMenu.Item>
							<DropdownMenu.Item onclick={() => (showScheduleDialog = true)}>
								<Clock class="mr-2 h-4 w-4" />
								Schedule
							</DropdownMenu.Item>
						</DropdownMenu.Content>
					</DropdownMenu.Root>
				{/if}
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
										<Input id="slug" placeholder="page-url-slug" bind:value={slug} />
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
											onValueChange={(val) => {
												templateId = val ? parseInt(val) : null;
											}}
										>
											<Select.Trigger>
												<span
													>{templateId
														? templates.find((t) => t.id === templateId)?.name
														: 'Select a template'}</span
												>
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

								<!-- Content Blocks -->
								<div class="space-y-2">
									<Label>Content Blocks</Label>
									<div class="space-y-2">
										{#if content.length === 0}
											<div class="rounded-lg border border-dashed bg-muted/30 p-8 text-center">
												<p class="text-muted-foreground mb-3">No content blocks yet</p>
												<Button
													variant="outline"
													onclick={() => {
														selectedBlockIndex = null;
														showBlockPicker = true;
													}}
												>
													<Plus class="mr-1 h-4 w-4" />
													Add Block
												</Button>
											</div>
										{:else}
											{#each content as block, index}
												<div class="group relative rounded-lg border bg-card p-4">
													<div class="flex items-start gap-3">
														<button
															type="button"
															class="mt-1 cursor-grab text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
														>
															<GripVertical class="h-4 w-4" />
														</button>
														<div class="flex-1">
															<div class="mb-2 flex items-center gap-2">
																<svelte:component
																	this={getBlockIcon(block.type)}
																	class="h-4 w-4 text-muted-foreground"
																/>
																<span class="text-sm font-medium">{getBlockLabel(block.type)}</span>
															</div>

															<!-- Block-specific editing -->
															{#if block.type === 'heading'}
																<Input
																	placeholder="Heading text..."
																	value={block.props.content as string}
																	oninput={(e) =>
																		updateBlockProps(index, {
																			content: (e.target as HTMLInputElement).value
																		})}
																/>
															{:else if block.type === 'paragraph'}
																<Textarea
																	placeholder="Paragraph text..."
																	value={block.props.content as string}
																	rows={3}
																	oninput={(e) =>
																		updateBlockProps(index, {
																			content: (e.target as HTMLTextAreaElement).value
																		})}
																/>
															{:else if block.type === 'image'}
																<Input
																	placeholder="Image URL..."
																	value={block.props.src as string}
																	oninput={(e) =>
																		updateBlockProps(index, {
																			src: (e.target as HTMLInputElement).value
																		})}
																/>
															{:else if block.type === 'button'}
																<div class="grid grid-cols-2 gap-2">
																	<Input
																		placeholder="Button text..."
																		value={block.props.label as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				label: (e.target as HTMLInputElement).value
																			})}
																	/>
																	<Input
																		placeholder="Button URL..."
																		value={block.props.url as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				url: (e.target as HTMLInputElement).value
																			})}
																	/>
																</div>
															{:else if block.type === 'hero'}
																<div class="space-y-2">
																	<Input
																		placeholder="Headline..."
																		value={block.props.title as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				title: (e.target as HTMLInputElement).value
																			})}
																	/>
																	<Input
																		placeholder="Subheadline..."
																		value={block.props.subtitle as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				subtitle: (e.target as HTMLInputElement).value
																			})}
																	/>
																</div>
															{:else if block.type === 'cta'}
																<div class="space-y-2">
																	<Input
																		placeholder="CTA title..."
																		value={block.props.title as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				title: (e.target as HTMLInputElement).value
																			})}
																	/>
																	<Input
																		placeholder="Button text..."
																		value={block.props.buttonText as string}
																		oninput={(e) =>
																			updateBlockProps(index, {
																				buttonText: (e.target as HTMLInputElement).value
																			})}
																	/>
																</div>
															{:else if block.type === 'html' || block.type === 'embed'}
																<Textarea
																	placeholder="HTML or embed code..."
																	value={block.props.code as string}
																	rows={5}
																	class="font-mono text-sm"
																	oninput={(e) =>
																		updateBlockProps(index, {
																			code: (e.target as HTMLTextAreaElement).value
																		})}
																/>
															{:else}
																<p class="text-muted-foreground text-sm">
																	Block type: {block.type}
																</p>
															{/if}
														</div>
														<div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
															<Button
																variant="ghost"
																size="sm"
																onclick={() => {
																	selectedBlockIndex = index;
																	showBlockPicker = true;
																}}
															>
																<Plus class="h-4 w-4" />
															</Button>
															<Button variant="ghost" size="sm" onclick={() => duplicateBlock(index)}>
																<Copy class="h-4 w-4" />
															</Button>
															<Button variant="ghost" size="sm" onclick={() => removeBlock(index)}>
																<Trash2 class="h-4 w-4" />
															</Button>
														</div>
													</div>
												</div>
											{/each}
											<Button
												variant="outline"
												class="w-full"
												onclick={() => {
													selectedBlockIndex = content.length - 1;
													showBlockPicker = true;
												}}
											>
												<Plus class="mr-1 h-4 w-4" />
												Add Block
											</Button>
										{/if}
									</div>
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
								onValueChange={(val) => {
									if (val) type = val as PageType;
								}}
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

						<div class="space-y-1 text-sm">
							<div class="flex justify-between">
								<span class="text-muted-foreground">Views</span>
								<span>{pageData.view_count.toLocaleString()}</span>
							</div>
							<div class="flex justify-between">
								<span class="text-muted-foreground">Created</span>
								<span>{new Date(pageData.created_at).toLocaleDateString()}</span>
							</div>
							<div class="flex justify-between">
								<span class="text-muted-foreground">Updated</span>
								<span>{new Date(pageData.updated_at).toLocaleDateString()}</span>
							</div>
							{#if pageData.published_at}
								<div class="flex justify-between">
									<span class="text-muted-foreground">Published</span>
									<span>{new Date(pageData.published_at).toLocaleDateString()}</span>
								</div>
							{/if}
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
								onkeydown={(e) => {
									if (e.key === 'Enter') {
										e.preventDefault();
										addTag();
									}
								}}
							/>
							<Button variant="outline" size="sm" onclick={addTag}>Add</Button>
						</div>
						{#if tagNames.length > 0}
							<div class="flex flex-wrap gap-1">
								{#each tagNames as tag}
									<span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs">
										{tag}
										<button type="button" onclick={() => removeTag(tag)} class="hover:text-destructive">
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

	<!-- Block Picker Dialog -->
	<Dialog.Root bind:open={showBlockPicker}>
		<Dialog.Content class="max-w-2xl">
			<Dialog.Header>
				<Dialog.Title>Add Content Block</Dialog.Title>
				<Dialog.Description>Choose a block type to add to your page</Dialog.Description>
			</Dialog.Header>
			<div class="grid grid-cols-3 gap-2 py-4">
				{#each ['Text', 'Media', 'Basic', 'Layout', 'Components', 'Advanced'] as category}
					<div class="col-span-3">
						<h4 class="text-muted-foreground mb-2 text-sm font-medium">{category}</h4>
						<div class="grid grid-cols-3 gap-2">
							{#each blockTypes.filter((b) => b.category === category) as bt}
								<button
									type="button"
									class="flex items-center gap-2 rounded-lg border p-3 text-left transition-colors hover:bg-muted"
									onclick={() => addBlock(bt.type, selectedBlockIndex ?? undefined)}
								>
									<svelte:component this={bt.icon} class="h-4 w-4 text-muted-foreground" />
									<span class="text-sm">{bt.label}</span>
								</button>
							{/each}
						</div>
					</div>
				{/each}
			</div>
		</Dialog.Content>
	</Dialog.Root>

	<!-- Schedule Dialog -->
	<Dialog.Root bind:open={showScheduleDialog}>
		<Dialog.Content>
			<Dialog.Header>
				<Dialog.Title>Schedule Publication</Dialog.Title>
				<Dialog.Description>Choose when to publish this page</Dialog.Description>
			</Dialog.Header>
			<div class="grid gap-4 py-4">
				<div class="space-y-2">
					<Label for="scheduleDate">Date</Label>
					<Input id="scheduleDate" type="date" bind:value={scheduleDate} />
				</div>
				<div class="space-y-2">
					<Label for="scheduleTime">Time</Label>
					<Input id="scheduleTime" type="time" bind:value={scheduleTime} />
				</div>
			</div>
			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showScheduleDialog = false)}>Cancel</Button>
				<Button onclick={handleSchedule}>Schedule</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>
{/if}
