<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Select from '$lib/components/ui/select';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Plus,
		Search,
		MoreHorizontal,
		Eye,
		Edit,
		Copy,
		Archive,
		Trash2,
		Globe,
		FileText,
		Layout,
		Newspaper,
		Clock,
		ExternalLink,
		History
	} from 'lucide-svelte';
	import {
		cmsPageApi,
		type CmsPage,
		type PageType,
		type PageStatus,
		getPageStatusColor,
		getPageStatusLabel
	} from '$lib/api/cms';
	import { toast } from 'svelte-sonner';

	let pages = $state<CmsPage[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let typeFilter = $state<PageType | ''>('');
	let statusFilter = $state<PageStatus | ''>('');

	let meta = $state({
		current_page: 1,
		last_page: 1,
		per_page: 25,
		total: 0
	});

	const pageTypes: { value: PageType; label: string; icon: typeof FileText }[] = [
		{ value: 'page', label: 'Pages', icon: FileText },
		{ value: 'blog', label: 'Blog Posts', icon: Newspaper },
		{ value: 'landing', label: 'Landing Pages', icon: Layout },
		{ value: 'article', label: 'Articles', icon: FileText }
	];

	const pageStatuses: { value: PageStatus; label: string }[] = [
		{ value: 'draft', label: 'Draft' },
		{ value: 'pending_review', label: 'Pending Review' },
		{ value: 'scheduled', label: 'Scheduled' },
		{ value: 'published', label: 'Published' },
		{ value: 'archived', label: 'Archived' }
	];

	async function loadPages() {
		loading = true;
		try {
			const response = await cmsPageApi.list({
				search: searchQuery || undefined,
				type: typeFilter || undefined,
				status: statusFilter || undefined,
				page: meta.current_page,
				per_page: meta.per_page
			});
			pages = response.data;
			meta = response.meta;
		} catch (error) {
			toast.error('Failed to load pages');
		} finally {
			loading = false;
		}
	}

	onMount(() => {
		loadPages();
	});

	async function handleDuplicate(page: CmsPage) {
		try {
			const duplicated = await cmsPageApi.duplicate(page.id);
			toast.success('Page duplicated successfully');
			goto(`/cms/pages/${duplicated.id}/edit`);
		} catch (error) {
			toast.error('Failed to duplicate page');
		}
	}

	async function handlePublish(page: CmsPage) {
		try {
			await cmsPageApi.publish(page.id);
			toast.success('Page published');
			loadPages();
		} catch (error) {
			toast.error('Failed to publish page');
		}
	}

	async function handleUnpublish(page: CmsPage) {
		try {
			await cmsPageApi.unpublish(page.id);
			toast.success('Page unpublished');
			loadPages();
		} catch (error) {
			toast.error('Failed to unpublish page');
		}
	}

	async function handleDelete(page: CmsPage) {
		if (!confirm(`Are you sure you want to delete "${page.title}"?`)) return;

		try {
			await cmsPageApi.delete(page.id);
			toast.success('Page deleted');
			loadPages();
		} catch (error) {
			toast.error('Failed to delete page');
		}
	}

	function getPageUrl(page: CmsPage): string {
		switch (page.type) {
			case 'blog':
				return `/blog/${page.slug}`;
			case 'landing':
				return `/lp/${page.slug}`;
			default:
				return `/${page.slug}`;
		}
	}

	function getTypeIcon(type: PageType) {
		switch (type) {
			case 'blog':
				return Newspaper;
			case 'landing':
				return Layout;
			default:
				return FileText;
		}
	}
</script>

<div class="container py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">CMS Pages</h1>
			<p class="text-muted-foreground">Create and manage website pages, blog posts, and landing pages</p>
		</div>
		<DropdownMenu.Root>
			<DropdownMenu.Trigger>
				<Button>
					<Plus class="mr-1 h-4 w-4" />
					New Page
				</Button>
			</DropdownMenu.Trigger>
			<DropdownMenu.Content align="end">
				{#each pageTypes as pt}
					<DropdownMenu.Item onclick={() => goto(`/cms/pages/new?type=${pt.value}`)}>
						<svelte:component this={pt.icon} class="mr-2 h-4 w-4" />
						{pt.label.replace('s', '')}
					</DropdownMenu.Item>
				{/each}
			</DropdownMenu.Content>
		</DropdownMenu.Root>
	</div>

	<!-- Filters -->
	<Card.Root class="mb-6">
		<Card.Content class="pt-6">
			<div class="flex gap-4">
				<div class="relative flex-1">
					<Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
					<Input
						placeholder="Search pages..."
						class="pl-10"
						bind:value={searchQuery}
						oninput={() => loadPages()}
					/>
				</div>
				<Select.Root
					type="single"
					value={typeFilter}
					onValueChange={(val) => { typeFilter = (val ?? '') as PageType | ''; loadPages(); }}
				>
					<Select.Trigger class="w-[160px]">
						<span>{typeFilter ? pageTypes.find(t => t.value === typeFilter)?.label : 'All types'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">All types</Select.Item>
						{#each pageTypes as pt}
							<Select.Item value={pt.value}>{pt.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
				<Select.Root
					type="single"
					value={statusFilter}
					onValueChange={(val) => { statusFilter = (val ?? '') as PageStatus | ''; loadPages(); }}
				>
					<Select.Trigger class="w-[160px]">
						<span>{statusFilter ? getPageStatusLabel(statusFilter) : 'All statuses'}</span>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">All statuses</Select.Item>
						{#each pageStatuses as ps}
							<Select.Item value={ps.value}>{ps.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Pages Table -->
	<Card.Root>
		<Card.Content class="p-0">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<div class="text-muted-foreground">Loading...</div>
				</div>
			{:else if pages.length === 0}
				<div class="py-12 text-center">
					<p class="text-muted-foreground mb-4">No pages found</p>
					<Button href="/cms/pages/new">
						<Plus class="mr-1 h-4 w-4" />
						Create your first page
					</Button>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Title</Table.Head>
							<Table.Head>Type</Table.Head>
							<Table.Head>Status</Table.Head>
							<Table.Head>Author</Table.Head>
							<Table.Head>Categories</Table.Head>
							<Table.Head>Updated</Table.Head>
							<Table.Head class="w-12"></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each pages as page}
							<Table.Row>
								<Table.Cell>
									<div class="flex items-start gap-3">
										{#if page.featured_image}
											<img
												src={page.featured_image.url}
												alt=""
												class="h-10 w-16 rounded object-cover"
											/>
										{/if}
										<div>
											<div class="font-medium">{page.title}</div>
											<div class="text-muted-foreground flex items-center gap-1 text-sm">
												<code class="text-xs">{page.slug}</code>
												{#if page.status === 'published'}
													<a
														href={getPageUrl(page)}
														target="_blank"
														rel="noopener noreferrer"
														class="text-primary hover:underline"
													>
														<ExternalLink class="h-3 w-3" />
													</a>
												{/if}
											</div>
										</div>
									</div>
								</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-1">
										<svelte:component this={getTypeIcon(page.type)} class="h-4 w-4 text-muted-foreground" />
										<span class="text-sm capitalize">{page.type}</span>
									</div>
								</Table.Cell>
								<Table.Cell>
									<Badge class={getPageStatusColor(page.status)}>
										{getPageStatusLabel(page.status)}
									</Badge>
									{#if page.scheduled_at}
										<div class="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
											<Clock class="h-3 w-3" />
											{new Date(page.scheduled_at).toLocaleDateString()}
										</div>
									{/if}
								</Table.Cell>
								<Table.Cell>
									{#if page.author}
										<span class="text-sm">{page.author.name}</span>
									{:else}
										<span class="text-muted-foreground text-sm">—</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									{#if page.categories && page.categories.length > 0}
										<div class="flex flex-wrap gap-1">
											{#each page.categories.slice(0, 2) as category}
												<Badge variant="outline" class="text-xs">{category.name}</Badge>
											{/each}
											{#if page.categories.length > 2}
												<Badge variant="outline" class="text-xs">+{page.categories.length - 2}</Badge>
											{/if}
										</div>
									{:else}
										<span class="text-muted-foreground text-sm">—</span>
									{/if}
								</Table.Cell>
								<Table.Cell>
									<span class="text-muted-foreground text-sm">
										{new Date(page.updated_at).toLocaleDateString()}
									</span>
								</Table.Cell>
								<Table.Cell>
									<DropdownMenu.Root>
										<DropdownMenu.Trigger>
											<Button variant="ghost" size="sm">
												<MoreHorizontal class="h-4 w-4" />
											</Button>
										</DropdownMenu.Trigger>
										<DropdownMenu.Content align="end">
											<DropdownMenu.Item onclick={() => goto(`/cms/pages/${page.id}/edit`)}>
												<Edit class="mr-2 h-4 w-4" />
												Edit
											</DropdownMenu.Item>
											<DropdownMenu.Item onclick={() => goto(`/cms/pages/${page.id}/versions`)}>
												<History class="mr-2 h-4 w-4" />
												Version History
											</DropdownMenu.Item>
											{#if page.status === 'published'}
												<DropdownMenu.Item onclick={() => handleUnpublish(page)}>
													<Eye class="mr-2 h-4 w-4" />
													Unpublish
												</DropdownMenu.Item>
											{:else if page.status === 'draft' || page.status === 'pending_review'}
												<DropdownMenu.Item onclick={() => handlePublish(page)}>
													<Globe class="mr-2 h-4 w-4" />
													Publish
												</DropdownMenu.Item>
											{/if}
											<DropdownMenu.Separator />
											<DropdownMenu.Item onclick={() => handleDuplicate(page)}>
												<Copy class="mr-2 h-4 w-4" />
												Duplicate
											</DropdownMenu.Item>
											{#if page.status !== 'archived'}
												<DropdownMenu.Item onclick={() => goto(`/cms/pages/${page.id}/edit?action=archive`)}>
													<Archive class="mr-2 h-4 w-4" />
													Archive
												</DropdownMenu.Item>
											{/if}
											{#if page.status !== 'published'}
												<DropdownMenu.Item
													class="text-red-600"
													onclick={() => handleDelete(page)}
												>
													<Trash2 class="mr-2 h-4 w-4" />
													Delete
												</DropdownMenu.Item>
											{/if}
										</DropdownMenu.Content>
									</DropdownMenu.Root>
								</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>

				<!-- Pagination -->
				{#if meta.last_page > 1}
					<div class="flex items-center justify-between border-t px-4 py-3">
						<div class="text-muted-foreground text-sm">
							Showing {(meta.current_page - 1) * meta.per_page + 1} to {Math.min(
								meta.current_page * meta.per_page,
								meta.total
							)} of {meta.total} pages
						</div>
						<div class="flex gap-1">
							<Button
								variant="outline"
								size="sm"
								disabled={meta.current_page === 1}
								onclick={() => {
									meta.current_page--;
									loadPages();
								}}
							>
								Previous
							</Button>
							<Button
								variant="outline"
								size="sm"
								disabled={meta.current_page === meta.last_page}
								onclick={() => {
									meta.current_page++;
									loadPages();
								}}
							>
								Next
							</Button>
						</div>
					</div>
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>
</div>
