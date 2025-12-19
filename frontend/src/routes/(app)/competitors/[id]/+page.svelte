<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { ArrowLeft, BarChart3, FileText, MessageSquare, Settings } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Battlecard, CompetitorAnalytics, CompetitorNotes } from '$lib/components/battlecard';
	import { getCompetitor, getCompetitorNotes, addCompetitorNote, type Competitor, type CompetitorNote } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { Input } from '$lib/components/ui/input';

	const competitorId = $derived(parseInt($page.params.id ?? '0'));

	let competitor: Competitor | null = null;
	let notes: CompetitorNote[] = [];
	let loading = true;
	let activeTab = 'battlecard';

	// Note form
	let newNoteContent = '';
	let newNoteSource = '';
	let addingNote = false;

	onMount(async () => {
		await loadCompetitor();
	});

	async function loadCompetitor() {
		loading = true;
		const { data, error } = await tryCatch(getCompetitor(competitorId));
		loading = false;

		if (error) {
			toast.error('Failed to load competitor');
			goto('/competitors');
			return;
		}

		competitor = data;
		await loadNotes();
	}

	async function loadNotes() {
		const { data } = await tryCatch(getCompetitorNotes(competitorId));
		notes = data ?? [];
	}

	async function handleAddNote() {
		if (!newNoteContent.trim()) return;

		addingNote = true;
		const { data, error } = await tryCatch(
			addCompetitorNote(competitorId, newNoteContent.trim(), newNoteSource.trim() || undefined)
		);
		addingNote = false;

		if (error) {
			toast.error('Failed to add note');
			return;
		}

		notes = [data, ...notes];
		newNoteContent = '';
		newNoteSource = '';
		toast.success('Note added');
	}
</script>

<svelte:head>
	<title>{competitor?.name ?? 'Competitor'} | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if competitor}
		<!-- Header -->
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={() => goto('/competitors')}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-2xl font-bold">{competitor.name}</h1>
				<p class="text-muted-foreground">Battlecard and competitive intelligence</p>
			</div>
		</div>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List>
				<Tabs.Trigger value="battlecard" class="gap-2">
					<FileText class="h-4 w-4" />
					Battlecard
				</Tabs.Trigger>
				<Tabs.Trigger value="analytics" class="gap-2">
					<BarChart3 class="h-4 w-4" />
					Analytics
				</Tabs.Trigger>
				<Tabs.Trigger value="notes" class="gap-2">
					<MessageSquare class="h-4 w-4" />
					Team Notes ({notes.length})
				</Tabs.Trigger>
			</Tabs.List>

			<div class="mt-6">
				<Tabs.Content value="battlecard">
					<Battlecard
						{competitorId}
						onEdit={() => goto(`/competitors/${competitorId}/edit`)}
					/>
				</Tabs.Content>

				<Tabs.Content value="analytics">
					<CompetitorAnalytics {competitorId} />
				</Tabs.Content>

				<Tabs.Content value="notes">
					<div class="space-y-6">
						<!-- Add Note Form -->
						<div class="rounded-lg border p-4 space-y-3">
							<h3 class="font-semibold">Add Intel</h3>
							<div class="space-y-2">
								<textarea
									bind:value={newNoteContent}
									placeholder="Share competitive intelligence with your team..."
									class="w-full min-h-[80px] px-3 py-2 rounded-md border bg-background text-sm"
								></textarea>
								<div class="flex items-center gap-2">
									<Input
										bind:value={newNoteSource}
										placeholder="Source (optional)"
										class="max-w-xs"
									/>
									<Button
										onclick={handleAddNote}
										disabled={!newNoteContent.trim() || addingNote}
									>
										{addingNote ? 'Adding...' : 'Add Note'}
									</Button>
								</div>
							</div>
						</div>

						<!-- Notes List -->
						<CompetitorNotes {notes} />
					</div>
				</Tabs.Content>
			</div>
		</Tabs.Root>
	{/if}
</div>