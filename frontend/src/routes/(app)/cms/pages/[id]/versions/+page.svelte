<script lang="ts">
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { ArrowLeft, History, RotateCcw, Eye, User, Calendar } from 'lucide-svelte';
	import { cmsPageApi, type CmsPage, type CmsPageVersion, getPageStatusColor, getPageStatusLabel } from '$lib/api/cms';
	import { toast } from 'svelte-sonner';
	import { onMount } from 'svelte';

	const pageId = parseInt($page.params.id);

	let loading = $state(true);
	let pageData = $state<CmsPage | null>(null);
	let versions = $state<CmsPageVersion[]>([]);
	let showRestoreDialog = $state(false);
	let selectedVersion = $state<CmsPageVersion | null>(null);
	let restoring = $state(false);

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [pageRes, versionsRes] = await Promise.all([
				cmsPageApi.get(pageId),
				cmsPageApi.getVersions(pageId)
			]);
			pageData = pageRes;
			versions = versionsRes;
		} catch (error) {
			toast.error('Failed to load version history');
			goto('/cms/pages');
		} finally {
			loading = false;
		}
	}

	function openRestoreDialog(version: CmsPageVersion) {
		selectedVersion = version;
		showRestoreDialog = true;
	}

	async function handleRestore() {
		if (!selectedVersion) return;

		restoring = true;
		try {
			await cmsPageApi.restoreVersion(pageId, selectedVersion.version_number);
			toast.success(`Restored to version ${selectedVersion.version_number}`);
			showRestoreDialog = false;
			goto(`/cms/pages/${pageId}/edit`);
		} catch (error) {
			toast.error('Failed to restore version');
		} finally {
			restoring = false;
		}
	}

	function formatDate(dateString: string): string {
		const date = new Date(dateString);
		return date.toLocaleDateString('en-US', {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
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
				<Button variant="ghost" size="sm" href={`/cms/pages/${pageId}/edit`}>
					<ArrowLeft class="mr-1 h-4 w-4" />
					Back to Editor
				</Button>
				<div>
					<div class="flex items-center gap-2">
						<h1 class="text-2xl font-bold">Version History</h1>
					</div>
					<p class="text-muted-foreground">
						{pageData.title} - {versions.length} version{versions.length !== 1 ? 's' : ''}
					</p>
				</div>
			</div>
		</div>

		<Card.Root>
			<Card.Content class="p-0">
				{#if versions.length === 0}
					<div class="py-12 text-center">
						<History class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
						<p class="text-muted-foreground mb-2">No versions yet</p>
						<p class="text-muted-foreground text-sm">
							Versions are created automatically when you save changes
						</p>
					</div>
				{:else}
					<Table.Root>
						<Table.Header>
							<Table.Row>
								<Table.Head>Version</Table.Head>
								<Table.Head>Title</Table.Head>
								<Table.Head>Changes</Table.Head>
								<Table.Head>Author</Table.Head>
								<Table.Head>Date</Table.Head>
								<Table.Head class="w-24"></Table.Head>
							</Table.Row>
						</Table.Header>
						<Table.Body>
							{#each versions as version, index}
								<Table.Row>
									<Table.Cell>
										<div class="flex items-center gap-2">
											<Badge variant={index === 0 ? 'default' : 'outline'}>
												v{version.version_number}
											</Badge>
											{#if index === 0}
												<span class="text-muted-foreground text-xs">Current</span>
											{/if}
										</div>
									</Table.Cell>
									<Table.Cell>
										<span class="font-medium">{version.title}</span>
									</Table.Cell>
									<Table.Cell>
										{#if version.change_summary}
											<span class="text-muted-foreground text-sm">{version.change_summary}</span>
										{:else}
											<span class="text-muted-foreground text-sm">No summary</span>
										{/if}
									</Table.Cell>
									<Table.Cell>
										{#if version.creator}
											<div class="flex items-center gap-1">
												<User class="text-muted-foreground h-3 w-3" />
												<span class="text-sm">{version.creator.name}</span>
											</div>
										{:else}
											<span class="text-muted-foreground text-sm">Unknown</span>
										{/if}
									</Table.Cell>
									<Table.Cell>
										<div class="flex items-center gap-1 text-sm text-muted-foreground">
											<Calendar class="h-3 w-3" />
											{formatDate(version.created_at)}
										</div>
									</Table.Cell>
									<Table.Cell>
										{#if index !== 0}
											<Button
												variant="ghost"
												size="sm"
												onclick={() => openRestoreDialog(version)}
											>
												<RotateCcw class="mr-1 h-4 w-4" />
												Restore
											</Button>
										{/if}
									</Table.Cell>
								</Table.Row>
							{/each}
						</Table.Body>
					</Table.Root>
				{/if}
			</Card.Content>
		</Card.Root>
	</div>

	<!-- Restore Confirmation Dialog -->
	<Dialog.Root bind:open={showRestoreDialog}>
		<Dialog.Content>
			<Dialog.Header>
				<Dialog.Title>Restore Version</Dialog.Title>
				<Dialog.Description>
					Are you sure you want to restore to version {selectedVersion?.version_number}? This will
					create a new version with the restored content.
				</Dialog.Description>
			</Dialog.Header>
			{#if selectedVersion}
				<div class="rounded-lg border bg-muted/30 p-4">
					<div class="space-y-2 text-sm">
						<div class="flex justify-between">
							<span class="text-muted-foreground">Version</span>
							<span>v{selectedVersion.version_number}</span>
						</div>
						<div class="flex justify-between">
							<span class="text-muted-foreground">Title</span>
							<span>{selectedVersion.title}</span>
						</div>
						<div class="flex justify-between">
							<span class="text-muted-foreground">Date</span>
							<span>{formatDate(selectedVersion.created_at)}</span>
						</div>
					</div>
				</div>
			{/if}
			<Dialog.Footer>
				<Button variant="outline" onclick={() => (showRestoreDialog = false)} disabled={restoring}>
					Cancel
				</Button>
				<Button onclick={handleRestore} disabled={restoring}>
					{restoring ? 'Restoring...' : 'Restore Version'}
				</Button>
			</Dialog.Footer>
		</Dialog.Content>
	</Dialog.Root>
{/if}
