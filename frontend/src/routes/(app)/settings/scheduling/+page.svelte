<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		Plus,
		MoreHorizontal,
		Pencil,
		Trash2,
		Eye,
		Link,
		Calendar,
		Clock,
		Loader2,
		CalendarDays,
		Settings,
		Copy
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getSchedulingPages,
		deleteSchedulingPage,
		updateSchedulingPage,
		type SchedulingPage
	} from '$lib/api/scheduling';

	let pages = $state<SchedulingPage[]>([]);
	let loading = $state(true);
	let activeTab = $state('pages');

	// Delete confirmation dialog
	let deleteDialogOpen = $state(false);
	let pageToDelete = $state<SchedulingPage | null>(null);
	let deleting = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const result = await getSchedulingPages();
			pages = result || [];
		} catch (error) {
			console.error('Failed to load scheduling pages:', error);
			toast.error('Failed to load scheduling pages');
			pages = [];
		} finally {
			loading = false;
		}
	}

	function confirmDelete(page: SchedulingPage) {
		pageToDelete = page;
		deleteDialogOpen = true;
	}

	async function handleDelete() {
		if (!pageToDelete) return;

		deleting = true;
		try {
			await deleteSchedulingPage(pageToDelete.id);
			toast.success('Scheduling page deleted');
			deleteDialogOpen = false;
			pageToDelete = null;
			await loadData();
		} catch (error) {
			console.error('Failed to delete page:', error);
			toast.error('Failed to delete scheduling page');
		} finally {
			deleting = false;
		}
	}

	async function handleToggleActive(page: SchedulingPage) {
		try {
			await updateSchedulingPage(page.id, { is_active: !page.is_active });
			toast.success(page.is_active ? 'Page deactivated' : 'Page activated');
			await loadData();
		} catch (error) {
			console.error('Failed to toggle page status:', error);
			toast.error('Failed to update page status');
		}
	}

	function copyPublicUrl(page: SchedulingPage) {
		const url = `${window.location.origin}/schedule/${page.slug}`;
		navigator.clipboard.writeText(url);
		toast.success('Public URL copied to clipboard');
	}

	function formatDate(dateString: string): string {
		return new Date(dateString).toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric'
		});
	}
</script>

<svelte:head>
	<title>Meeting Scheduler | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Meeting Scheduler</h1>
			<p class="text-muted-foreground">Create booking pages and manage your availability</p>
		</div>
		<Button onclick={() => goto('/settings/scheduling/pages/create')}>
			<Plus class="mr-2 h-4 w-4" />
			New Scheduling Page
		</Button>
	</div>

	<!-- Tabs -->
	<Tabs.Root bind:value={activeTab} class="space-y-4">
		<Tabs.List>
			<Tabs.Trigger value="pages">
				<CalendarDays class="mr-2 h-4 w-4" />
				Scheduling Pages
			</Tabs.Trigger>
			<Tabs.Trigger value="availability" onclick={() => goto('/settings/scheduling/availability')}>
				<Clock class="mr-2 h-4 w-4" />
				Availability
			</Tabs.Trigger>
			<Tabs.Trigger value="meetings" onclick={() => goto('/settings/scheduling/meetings')}>
				<Calendar class="mr-2 h-4 w-4" />
				Meetings
			</Tabs.Trigger>
		</Tabs.List>

		<Tabs.Content value="pages">
			<Card.Root>
				<Card.Content class="p-0">
					{#if loading}
						<div class="flex items-center justify-center py-12">
							<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
						</div>
					{:else if pages.length === 0}
						<div class="flex flex-col items-center justify-center py-12">
							<CalendarDays class="mb-4 h-12 w-12 text-muted-foreground" />
							<h3 class="mb-2 text-lg font-medium">No scheduling pages yet</h3>
							<p class="mb-4 text-center text-muted-foreground">
								Create your first scheduling page to let others book meetings with you
							</p>
							<Button onclick={() => goto('/settings/scheduling/pages/create')}>
								<Plus class="mr-2 h-4 w-4" />
								Create Scheduling Page
							</Button>
						</div>
					{:else}
						<Table.Root>
							<Table.Header>
								<Table.Row>
									<Table.Head>Name</Table.Head>
									<Table.Head>URL</Table.Head>
									<Table.Head>Meeting Types</Table.Head>
									<Table.Head>Status</Table.Head>
									<Table.Head>Updated</Table.Head>
									<Table.Head class="w-[100px]"></Table.Head>
								</Table.Row>
							</Table.Header>
							<Table.Body>
								{#each pages as page}
									<Table.Row>
										<Table.Cell>
											<div>
												<p class="font-medium">{page.name}</p>
												{#if page.description}
													<p class="text-sm text-muted-foreground line-clamp-1">
														{page.description}
													</p>
												{/if}
											</div>
										</Table.Cell>
										<Table.Cell>
											<div class="flex items-center gap-2">
												<code class="text-sm text-muted-foreground">/{page.slug}</code>
												<Button
													variant="ghost"
													size="icon"
													class="h-6 w-6"
													onclick={() => copyPublicUrl(page)}
												>
													<Copy class="h-3 w-3" />
												</Button>
											</div>
										</Table.Cell>
										<Table.Cell>
											<Badge variant="outline">
												{page.meeting_types_count || 0} types
											</Badge>
										</Table.Cell>
										<Table.Cell>
											<Switch
												checked={page.is_active}
												onCheckedChange={() => handleToggleActive(page)}
											/>
										</Table.Cell>
										<Table.Cell>
											<span class="text-sm text-muted-foreground">
												{formatDate(page.updated_at)}
											</span>
										</Table.Cell>
										<Table.Cell>
											<DropdownMenu.Root>
												<DropdownMenu.Trigger>
													{#snippet child({ props })}
														<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
															<MoreHorizontal class="h-4 w-4" />
														</Button>
													{/snippet}
												</DropdownMenu.Trigger>
												<DropdownMenu.Content align="end">
													<DropdownMenu.Item
														onclick={() => window.open(`/schedule/${page.slug}`, '_blank')}
													>
														<Eye class="mr-2 h-4 w-4" />
														Preview
													</DropdownMenu.Item>
													<DropdownMenu.Item
														onclick={() => goto(`/settings/scheduling/pages/${page.id}`)}
													>
														<Pencil class="mr-2 h-4 w-4" />
														Edit
													</DropdownMenu.Item>
													<DropdownMenu.Item onclick={() => copyPublicUrl(page)}>
														<Link class="mr-2 h-4 w-4" />
														Copy Link
													</DropdownMenu.Item>
													<DropdownMenu.Separator />
													<DropdownMenu.Item
														class="text-destructive focus:text-destructive"
														onclick={() => confirmDelete(page)}
													>
														<Trash2 class="mr-2 h-4 w-4" />
														Delete
													</DropdownMenu.Item>
												</DropdownMenu.Content>
											</DropdownMenu.Root>
										</Table.Cell>
									</Table.Row>
								{/each}
							</Table.Body>
						</Table.Root>
					{/if}
				</Card.Content>
			</Card.Root>
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Delete Confirmation Dialog -->
<Dialog.Root bind:open={deleteDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Delete Scheduling Page</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to delete "{pageToDelete?.name}"? All meeting types and scheduled
				meetings will also be deleted. This action cannot be undone.
			</Dialog.Description>
		</Dialog.Header>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (deleteDialogOpen = false)} disabled={deleting}>
				Cancel
			</Button>
			<Button variant="destructive" onclick={handleDelete} disabled={deleting}>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
