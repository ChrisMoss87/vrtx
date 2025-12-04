<script lang="ts">
	import { onMount } from 'svelte';
	import { FileText, Trash2, Clock, MoreVertical, RefreshCw, Star, Calendar } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import {
		getDrafts,
		deleteDraft,
		bulkDeleteDrafts,
		makeDraftPermanent,
		extendDraftExpiration,
		type WizardDraftSummary
	} from '$lib/api/wizard-drafts';

	interface Props {
		wizardType?: string;
		referenceId?: string;
		onSelect?: (draft: WizardDraftSummary) => void;
		onCreateNew?: () => void;
	}

	let {
		wizardType = undefined,
		referenceId = undefined,
		onSelect = () => {},
		onCreateNew = () => {}
	}: Props = $props();

	let drafts = $state<WizardDraftSummary[]>([]);
	let isLoading = $state(true);
	let error = $state<string | null>(null);
	let selectedDrafts = $state<Set<number>>(new Set());
	let deleteDialogOpen = $state(false);
	let draftToDelete = $state<WizardDraftSummary | null>(null);

	onMount(async () => {
		await loadDrafts();
	});

	async function loadDrafts() {
		isLoading = true;
		error = null;

		try {
			drafts = await getDrafts({
				wizard_type: wizardType,
				reference_id: referenceId
			});
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load drafts';
		} finally {
			isLoading = false;
		}
	}

	async function handleDelete(draft: WizardDraftSummary) {
		draftToDelete = draft;
		deleteDialogOpen = true;
	}

	async function confirmDelete() {
		if (!draftToDelete) return;

		try {
			await deleteDraft(draftToDelete.id);
			drafts = drafts.filter((d) => d.id !== draftToDelete?.id);
			draftToDelete = null;
			deleteDialogOpen = false;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to delete draft';
		}
	}

	async function handleBulkDelete() {
		if (selectedDrafts.size === 0) return;

		try {
			await bulkDeleteDrafts(Array.from(selectedDrafts));
			drafts = drafts.filter((d) => !selectedDrafts.has(d.id));
			selectedDrafts = new Set();
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to delete drafts';
		}
	}

	async function handleMakePermanent(draft: WizardDraftSummary) {
		try {
			await makeDraftPermanent(draft.id);
			const index = drafts.findIndex((d) => d.id === draft.id);
			if (index !== -1) {
				drafts[index] = { ...drafts[index], expires_at: null };
			}
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to update draft';
		}
	}

	async function handleExtendExpiration(draft: WizardDraftSummary, days: number) {
		try {
			const result = await extendDraftExpiration(draft.id, days);
			const index = drafts.findIndex((d) => d.id === draft.id);
			if (index !== -1) {
				drafts[index] = { ...drafts[index], expires_at: result.expires_at };
			}
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to extend expiration';
		}
	}

	function toggleSelection(draftId: number) {
		if (selectedDrafts.has(draftId)) {
			selectedDrafts.delete(draftId);
			selectedDrafts = new Set(selectedDrafts);
		} else {
			selectedDrafts.add(draftId);
			selectedDrafts = new Set(selectedDrafts);
		}
	}

	function formatDate(dateString: string): string {
		const date = new Date(dateString);
		return date.toLocaleDateString(undefined, {
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	function formatExpiresAt(dateString: string | null): string {
		if (!dateString) return 'Never expires';
		const date = new Date(dateString);
		const now = new Date();
		const diff = date.getTime() - now.getTime();
		const days = Math.ceil(diff / (1000 * 60 * 60 * 24));

		if (days < 0) return 'Expired';
		if (days === 0) return 'Expires today';
		if (days === 1) return 'Expires tomorrow';
		if (days < 7) return `Expires in ${days} days`;
		return `Expires ${date.toLocaleDateString()}`;
	}
</script>

<div class="space-y-4">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<h3 class="text-lg font-semibold">Saved Drafts</h3>
		<div class="flex items-center gap-2">
			{#if selectedDrafts.size > 0}
				<Button variant="destructive" size="sm" onclick={handleBulkDelete}>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete ({selectedDrafts.size})
				</Button>
			{/if}
			<Button variant="outline" size="sm" onclick={loadDrafts}>
				<RefreshCw class="mr-2 h-4 w-4" />
				Refresh
			</Button>
			<Button size="sm" onclick={onCreateNew}>Start New</Button>
		</div>
	</div>

	<!-- Error message -->
	{#if error}
		<div class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
			{error}
		</div>
	{/if}

	<!-- Loading state -->
	{#if isLoading}
		<div class="flex items-center justify-center py-8">
			<RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
		</div>
	{:else if drafts.length === 0}
		<!-- Empty state -->
		<div class="flex flex-col items-center justify-center py-12 text-center">
			<FileText class="mb-4 h-12 w-12 text-muted-foreground/50" />
			<p class="text-muted-foreground">No saved drafts</p>
			<Button variant="link" onclick={onCreateNew}>Start a new wizard</Button>
		</div>
	{:else}
		<!-- Drafts list -->
		<div class="space-y-2">
			{#each drafts as draft (draft.id)}
				<div
					class="group flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
				>
					<!-- Checkbox -->
					<input
						type="checkbox"
						checked={selectedDrafts.has(draft.id)}
						onchange={() => toggleSelection(draft.id)}
						class="h-4 w-4 rounded border-gray-300"
					/>

					<!-- Draft info -->
					<button
						type="button"
						class="flex flex-1 cursor-pointer items-start gap-3 text-left"
						onclick={() => onSelect(draft)}
					>
						<div class="min-w-0 flex-1">
							<div class="flex items-center gap-2">
								<span class="truncate font-medium">{draft.name}</span>
								{#if draft.expires_at === null}
									<span title="Permanent draft">
										<Star class="h-3 w-3 text-yellow-500" />
									</span>
								{/if}
							</div>
							<div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
								<span class="flex items-center gap-1">
									<Clock class="h-3 w-3" />
									{formatDate(draft.updated_at)}
								</span>
								<span class="flex items-center gap-1">
									<Calendar class="h-3 w-3" />
									{formatExpiresAt(draft.expires_at)}
								</span>
							</div>
						</div>

						<!-- Progress -->
						<div class="flex flex-col items-end gap-1">
							<span class="text-sm font-medium">{draft.completion_percentage}%</span>
							<div class="h-1.5 w-20 rounded-full bg-muted">
								<div
									class="h-full rounded-full bg-primary transition-all"
									style="width: {draft.completion_percentage}%"
								></div>
							</div>
						</div>
					</button>

					<!-- Actions menu -->
					<DropdownMenu.Root>
						<DropdownMenu.Trigger>
							{#snippet child({ props })}
								<Button
									variant="ghost"
									size="icon"
									class="h-8 w-8 opacity-0 group-hover:opacity-100"
									{...props}
								>
									<MoreVertical class="h-4 w-4" />
								</Button>
							{/snippet}
						</DropdownMenu.Trigger>
						<DropdownMenu.Content align="end">
							<DropdownMenu.Item onclick={() => onSelect(draft)}>
								Continue editing
							</DropdownMenu.Item>
							{#if draft.expires_at}
								<DropdownMenu.Item onclick={() => handleMakePermanent(draft)}>
									<Star class="mr-2 h-4 w-4" />
									Make permanent
								</DropdownMenu.Item>
								<DropdownMenu.Item onclick={() => handleExtendExpiration(draft, 30)}>
									<Calendar class="mr-2 h-4 w-4" />
									Extend 30 days
								</DropdownMenu.Item>
							{/if}
							<DropdownMenu.Separator />
							<DropdownMenu.Item class="text-destructive" onclick={() => handleDelete(draft)}>
								<Trash2 class="mr-2 h-4 w-4" />
								Delete
							</DropdownMenu.Item>
						</DropdownMenu.Content>
					</DropdownMenu.Root>
				</div>
			{/each}
		</div>
	{/if}
</div>

<!-- Delete confirmation dialog -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Draft</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{draftToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				onclick={confirmDelete}
				class="text-destructive-foreground bg-destructive"
			>
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
