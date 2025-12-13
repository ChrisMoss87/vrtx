<script lang="ts">
	import { onMount } from 'svelte';
	import { Plus, Search, Building2, MoreVertical, Trash2, ExternalLink } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { getCompetitors, deleteCompetitor, type Competitor } from '$lib/api/competitors';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import CreateCompetitorModal from './CreateCompetitorModal.svelte';

	let competitors = $state<Competitor[]>([]);
	let loading = $state(true);
	let search = $state('');
	let showCreateModal = $state(false);
	let searchTimeout: ReturnType<typeof setTimeout> | null = null;

	onMount(async () => {
		await loadCompetitors();
	});

	async function loadCompetitors() {
		loading = true;
		const { data, error } = await tryCatch(getCompetitors(search || undefined));
		loading = false;

		if (error) {
			toast.error('Failed to load competitors');
			return;
		}

		competitors = data ?? [];
	}

	async function handleDelete(id: number) {
		if (!confirm('Are you sure you want to delete this competitor?')) return;

		const { error } = await tryCatch(deleteCompetitor(id));

		if (error) {
			toast.error('Failed to delete competitor');
			return;
		}

		toast.success('Competitor deleted');
		competitors = competitors.filter((c) => c.id !== id);
	}

	function handleCreated(competitor: Competitor) {
		competitors = [competitor, ...competitors];
		showCreateModal = false;
	}

	function getWinRateColor(rate: number | null): string {
		if (rate === null) return 'text-muted-foreground';
		if (rate >= 60) return 'text-green-600 dark:text-green-400';
		if (rate >= 40) return 'text-amber-600 dark:text-amber-400';
		return 'text-red-600 dark:text-red-400';
	}

	function handleSearchInput(e: Event) {
		const target = e.target as HTMLInputElement;
		search = target.value;

		if (searchTimeout) {
			clearTimeout(searchTimeout);
		}
		searchTimeout = setTimeout(loadCompetitors, 300);
	}
</script>

<div class="space-y-4">
	<!-- Search and Actions -->
	<div class="flex items-center gap-4">
		<div class="relative flex-1 max-w-sm">
			<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
			<Input
				value={search}
				oninput={handleSearchInput}
				placeholder="Search competitors..."
				class="pl-9"
			/>
		</div>
		<Button onclick={() => (showCreateModal = true)}>
			<Plus class="h-4 w-4 mr-2" />
			Add Competitor
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
		</div>
	{:else if competitors.length === 0}
		<div class="text-center py-12 text-muted-foreground">
			<Building2 class="h-12 w-12 mx-auto mb-3 opacity-50" />
			<p>No competitors found</p>
			<p class="text-sm mt-1">Add competitors to track your competitive landscape</p>
		</div>
	{:else}
		<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
			{#each competitors as competitor (competitor.id)}
				<div class="rounded-lg border bg-card p-4 hover:shadow-md transition-shadow">
					<div class="flex items-start gap-3">
						<!-- Logo/Avatar -->
						<div class="w-12 h-12 rounded-lg bg-muted flex items-center justify-center flex-shrink-0">
							{#if competitor.logo_url}
								<img
									src={competitor.logo_url}
									alt={competitor.name}
									class="w-10 h-10 object-contain"
								/>
							{:else}
								<Building2 class="h-6 w-6 text-muted-foreground" />
							{/if}
						</div>

						<!-- Content -->
						<div class="flex-1 min-w-0">
							<div class="flex items-center justify-between">
								<button
									class="font-semibold truncate hover:text-primary text-left"
									onclick={() => goto(`/competitors/${competitor.id}`)}
								>
									{competitor.name}
								</button>
								<DropdownMenu.Root>
									<DropdownMenu.Trigger>
										{#snippet child({ props })}
											<button {...props} class="inline-flex items-center justify-center h-8 w-8 -mr-2 rounded-md hover:bg-accent hover:text-accent-foreground">
												<MoreVertical class="h-4 w-4" />
											</button>
										{/snippet}
									</DropdownMenu.Trigger>
									<DropdownMenu.Content align="end">
										<DropdownMenu.Item onclick={() => goto(`/competitors/${competitor.id}`)}>
											View Battlecard
										</DropdownMenu.Item>
										{#if competitor.website}
											<DropdownMenu.Item onclick={() => window.open(competitor.website ?? '', '_blank')}>
												<ExternalLink class="h-4 w-4 mr-2" />
												Visit Website
											</DropdownMenu.Item>
										{/if}
										<DropdownMenu.Separator />
										<DropdownMenu.Item onclick={() => handleDelete(competitor.id)} class="text-destructive">
											<Trash2 class="h-4 w-4 mr-2" />
											Delete
										</DropdownMenu.Item>
									</DropdownMenu.Content>
								</DropdownMenu.Root>
							</div>

							{#if competitor.description}
								<p class="text-sm text-muted-foreground line-clamp-2 mt-1">
									{competitor.description}
								</p>
							{/if}

							<!-- Stats -->
							<div class="flex items-center gap-4 mt-3 text-sm">
								<div>
									<span class="font-medium {getWinRateColor(competitor.win_rate)}">
										{competitor.win_rate !== null ? `${competitor.win_rate}%` : '-'}
									</span>
									<span class="text-muted-foreground"> win rate</span>
								</div>
								<div class="text-muted-foreground">
									{competitor.total_deals} deal{competitor.total_deals !== 1 ? 's' : ''}
								</div>
							</div>
						</div>
					</div>
				</div>
			{/each}
		</div>
	{/if}
</div>

{#if showCreateModal}
	<CreateCompetitorModal
		onClose={() => (showCreateModal = false)}
		onCreated={handleCreated}
	/>
{/if}
