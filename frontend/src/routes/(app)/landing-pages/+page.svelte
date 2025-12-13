<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
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
		BarChart3,
		ExternalLink
	} from 'lucide-svelte';
	import {
		landingPageApi,
		type LandingPage,
		getStatusColor
	} from '$lib/api/landing-pages';
	import { toast } from 'svelte-sonner';

	let pages = $state<LandingPage[]>([]);
	let loading = $state(true);
	let searchQuery = $state('');
	let statusFilter = $state<LandingPage['status'] | ''>('');

	let meta = $state({
		current_page: 1,
		last_page: 1,
		per_page: 20,
		total: 0
	});

	async function loadPages() {
		loading = true;
		try {
			const response = await landingPageApi.list({
				search: searchQuery || undefined,
				status: statusFilter || undefined,
				page: meta.current_page,
				per_page: meta.per_page
			});
			pages = response.data;
			meta = response.meta;
		} catch (error) {
			toast.error('Failed to load landing pages');
		} finally {
			loading = false;
		}
	}

	onMount(() => {
		loadPages();
	});

	async function handleDuplicate(page: LandingPage) {
		try {
			const duplicated = await landingPageApi.duplicate(page.id);
			toast.success('Page duplicated successfully');
			goto(`/landing-pages/${duplicated.id}/edit`);
		} catch (error) {
			toast.error('Failed to duplicate page');
		}
	}

	async function handlePublish(page: LandingPage) {
		try {
			await landingPageApi.publish(page.id);
			toast.success('Page published');
			loadPages();
		} catch (error) {
			toast.error('Failed to publish page');
		}
	}

	async function handleUnpublish(page: LandingPage) {
		try {
			await landingPageApi.unpublish(page.id);
			toast.success('Page unpublished');
			loadPages();
		} catch (error) {
			toast.error('Failed to unpublish page');
		}
	}

	async function handleArchive(page: LandingPage) {
		try {
			await landingPageApi.archive(page.id);
			toast.success('Page archived');
			loadPages();
		} catch (error) {
			toast.error('Failed to archive page');
		}
	}

	async function handleDelete(page: LandingPage) {
		if (!confirm(`Are you sure you want to delete "${page.name}"?`)) return;

		try {
			await landingPageApi.delete(page.id);
			toast.success('Page deleted');
			loadPages();
		} catch (error) {
			toast.error('Failed to delete page. Make sure it is not published.');
		}
	}

	function getPageUrl(page: LandingPage): string {
		return `/p/${page.slug}`;
	}
</script>

<div class="container py-6">
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Landing Pages</h1>
			<p class="text-muted-foreground">Create and manage landing pages for your campaigns</p>
		</div>
		<Button href="/landing-pages/new">
			<Plus class="mr-1 h-4 w-4" />
			New Page
		</Button>
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
				<select
					class="rounded-md border px-3 py-2"
					bind:value={statusFilter}
					onchange={() => loadPages()}
				>
					<option value="">All statuses</option>
					<option value="draft">Draft</option>
					<option value="published">Published</option>
					<option value="archived">Archived</option>
				</select>
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
					<p class="text-muted-foreground mb-4">No landing pages found</p>
					<Button href="/landing-pages/new">
						<Plus class="mr-1 h-4 w-4" />
						Create your first page
					</Button>
				</div>
			{:else}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Page</Table.Head>
							<Table.Head>Status</Table.Head>
							<Table.Head>URL</Table.Head>
							<Table.Head>Campaign</Table.Head>
							<Table.Head>Updated</Table.Head>
							<Table.Head class="w-12"></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each pages as page}
							<Table.Row>
								<Table.Cell>
									<div>
										<div class="font-medium">{page.name}</div>
										{#if page.description}
											<div class="text-muted-foreground text-sm">{page.description}</div>
										{/if}
									</div>
								</Table.Cell>
								<Table.Cell>
									<Badge class={getStatusColor(page.status)}>
										{page.status}
									</Badge>
								</Table.Cell>
								<Table.Cell>
									<div class="flex items-center gap-1">
										<code class="text-muted-foreground text-xs">{page.slug}</code>
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
								</Table.Cell>
								<Table.Cell>
									{#if page.campaign}
										<span class="text-sm">{page.campaign.name}</span>
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
											<DropdownMenu.Item onSelect={() => goto(`/landing-pages/${page.id}/edit`)}>
												<Edit class="mr-2 h-4 w-4" />
												Edit
											</DropdownMenu.Item>
											<DropdownMenu.Item onSelect={() => goto(`/landing-pages/${page.id}`)}>
												<BarChart3 class="mr-2 h-4 w-4" />
												Analytics
											</DropdownMenu.Item>
											{#if page.status === 'published'}
												<DropdownMenu.Item onclick={() => handleUnpublish(page)}>
													<Eye class="mr-2 h-4 w-4" />
													Unpublish
												</DropdownMenu.Item>
											{:else if page.status === 'draft'}
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
												<DropdownMenu.Item onclick={() => handleArchive(page)}>
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
