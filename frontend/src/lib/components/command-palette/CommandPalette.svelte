<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { goto } from '$app/navigation';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { Badge } from '$lib/components/ui/badge';
	import { search, type SearchResult, type CommandPaletteData } from '$lib/api/search';
	import {
		Search,
		FileText,
		Users,
		Building,
		Briefcase,
		Settings,
		Plus,
		LogOut,
		User,
		Clock,
		Star,
		ArrowRight,
		Command
	} from 'lucide-svelte';

	let open = $state(false);
	let query = $state('');
	let selectedIndex = $state(0);
	let loading = $state(false);
	let mode = $state<'search' | 'commands'>('commands');

	// Results
	let searchResults = $state<SearchResult[]>([]);
	let recentSearches = $state<string[]>([]);
	let commandData = $state<CommandPaletteData | null>(null);

	// Combined items for navigation
	let allItems = $derived(getAllItems());

	function getAllItems() {
		if (mode === 'search' && query.length >= 2) {
			return searchResults.map((r) => ({
				id: `result-${r.module_api_name}-${r.id}`,
				type: 'result' as const,
				name: r.primary_value,
				description: r.secondary_value || r.module_name,
				icon: getModuleIcon(r.module_icon),
				module_api_name: r.module_api_name,
				record_id: r.id
			}));
		}

		const items: Array<{
			id: string;
			type: 'action' | 'module' | 'saved_search' | 'recent';
			name: string;
			description?: string;
			icon: typeof Search;
			shortcut?: string;
			module_api_name?: string;
			query?: string;
		}> = [];

		// Add quick actions
		if (commandData) {
			for (const action of commandData.actions) {
				items.push({
					id: action.id,
					type: 'action',
					name: action.name,
					icon: getActionIcon(action.icon),
					shortcut: action.shortcut || undefined
				});
			}

			// Add modules for navigation
			for (const module of commandData.modules) {
				items.push({
					id: module.id,
					type: 'module',
					name: module.name,
					description: `Go to ${module.name}`,
					icon: getModuleIcon(module.icon),
					module_api_name: module.api_name
				});
			}

			// Add pinned searches
			for (const savedSearch of commandData.pinned_searches) {
				items.push({
					id: savedSearch.id,
					type: 'saved_search',
					name: savedSearch.name,
					description: savedSearch.query,
					icon: Star,
					query: savedSearch.query
				});
			}
		}

		// Add recent searches
		for (const recent of recentSearches.slice(0, 3)) {
			items.push({
				id: `recent-${recent}`,
				type: 'recent',
				name: recent,
				description: 'Recent search',
				icon: Clock,
				query: recent
			});
		}

		// Filter by query if present
		if (query && query.length > 0) {
			const lowerQuery = query.toLowerCase();
			return items.filter(
				(item) =>
					item.name.toLowerCase().includes(lowerQuery) ||
					item.description?.toLowerCase().includes(lowerQuery)
			);
		}

		return items;
	}

	function getModuleIcon(iconName: string) {
		const icons: Record<string, typeof Search> = {
			users: Users,
			building: Building,
			briefcase: Briefcase,
			file: FileText,
			'file-text': FileText
		};
		return icons[iconName] || FileText;
	}

	function getActionIcon(iconName: string) {
		const icons: Record<string, typeof Search> = {
			plus: Plus,
			search: Search,
			settings: Settings,
			user: User,
			'log-out': LogOut
		};
		return icons[iconName] || Search;
	}

	async function loadCommands() {
		try {
			commandData = await search.commands();
		} catch (error) {
			console.error('Failed to load commands:', error);
		}
	}

	async function loadSuggestions() {
		try {
			const suggestions = await search.suggestions();
			recentSearches = suggestions.recent || [];
		} catch (error) {
			console.error('Failed to load suggestions:', error);
		}
	}

	async function performSearch() {
		if (query.length < 2) {
			searchResults = [];
			mode = 'commands';
			return;
		}

		mode = 'search';
		loading = true;

		try {
			const response = await search.quickSearch(query);
			searchResults = response.results;
		} catch (error) {
			console.error('Search failed:', error);
			searchResults = [];
		} finally {
			loading = false;
		}
	}

	function handleSelect(item: (typeof allItems)[0]) {
		if (!item) return;

		switch (item.type) {
			case 'result':
				goto(`/records/${item.module_api_name}/${item.record_id}`);
				break;
			case 'module':
				goto(`/records/${item.module_api_name}`);
				break;
			case 'action':
				handleAction(item.id);
				break;
			case 'saved_search':
			case 'recent':
				query = item.query || '';
				performSearch();
				return; // Don't close
		}

		close();
	}

	function handleAction(actionId: string) {
		switch (actionId) {
			case 'create-record':
				// TODO: Open create record dialog
				break;
			case 'search':
				query = '';
				mode = 'commands';
				return;
			case 'settings':
				goto('/settings');
				break;
			case 'profile':
				goto('/profile');
				break;
			case 'logout':
				goto('/logout');
				break;
		}
	}

	function handleKeydown(event: KeyboardEvent) {
		if (event.key === 'ArrowDown') {
			event.preventDefault();
			selectedIndex = Math.min(selectedIndex + 1, allItems.length - 1);
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			selectedIndex = Math.max(selectedIndex - 1, 0);
		} else if (event.key === 'Enter') {
			event.preventDefault();
			const selectedItem = allItems[selectedIndex];
			if (selectedItem) {
				handleSelect(selectedItem);
			}
		} else if (event.key === 'Escape') {
			close();
		}
	}

	function close() {
		open = false;
		query = '';
		selectedIndex = 0;
		mode = 'commands';
	}

	// Global keyboard shortcut
	function handleGlobalKeydown(event: KeyboardEvent) {
		// Cmd+K or Ctrl+K
		if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
			event.preventDefault();
			open = true;
			loadCommands();
			loadSuggestions();
		}
	}

	// Debounce search
	let searchTimeout: ReturnType<typeof setTimeout>;
	$effect(() => {
		clearTimeout(searchTimeout);
		if (query.length >= 2) {
			searchTimeout = setTimeout(performSearch, 150);
		} else {
			searchResults = [];
			mode = 'commands';
		}
	});

	// Reset selected index when items change
	$effect(() => {
		if (allItems.length > 0) {
			selectedIndex = Math.min(selectedIndex, allItems.length - 1);
		} else {
			selectedIndex = 0;
		}
	});

	onMount(() => {
		window.addEventListener('keydown', handleGlobalKeydown);
	});

	onDestroy(() => {
		window.removeEventListener('keydown', handleGlobalKeydown);
	});
</script>

<Dialog.Root bind:open>
	<Dialog.Content class="max-w-lg p-0 overflow-hidden">
		<div class="flex items-center border-b px-3">
			<Search class="h-4 w-4 text-muted-foreground mr-2" />
			<input
				type="text"
				bind:value={query}
				placeholder="Search records, navigate, or type a command..."
				class="flex-1 h-12 bg-transparent border-0 outline-none text-sm placeholder:text-muted-foreground"
				onkeydown={handleKeydown}
			/>
			<kbd class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 text-xs text-muted-foreground bg-muted rounded">
				<Command class="h-3 w-3" />K
			</kbd>
		</div>

		<div class="max-h-80 overflow-y-auto p-2">
			{#if loading}
				<div class="py-8 text-center text-muted-foreground text-sm">Searching...</div>
			{:else if allItems.length === 0}
				<div class="py-8 text-center text-muted-foreground text-sm">
					{#if query.length >= 2}
						No results found for "{query}"
					{:else}
						Type to search or select a command
					{/if}
				</div>
			{:else}
				{#if mode === 'search' && searchResults.length > 0}
					<div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">Results</div>
				{:else if mode === 'commands'}
					<div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">Quick Actions</div>
				{/if}

				<div class="space-y-0.5">
					{#each allItems as item, index}
						{@const Icon = item.icon}
						<button
							class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-left transition-colors {index ===
							selectedIndex
								? 'bg-accent text-accent-foreground'
								: 'hover:bg-muted'}"
							onclick={() => handleSelect(item)}
							onmouseenter={() => (selectedIndex = index)}
						>
							<Icon class="h-4 w-4 text-muted-foreground flex-shrink-0" />
							<div class="flex-1 min-w-0">
								<div class="text-sm font-medium truncate">{item.name}</div>
								{#if item.description}
									<div class="text-xs text-muted-foreground truncate">{item.description}</div>
								{/if}
							</div>
							{#if item.type === 'action' && item.shortcut}
								<kbd class="text-xs px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
									{item.shortcut}
								</kbd>
							{/if}
							{#if item.type === 'module' || item.type === 'result'}
								<ArrowRight class="h-3 w-3 text-muted-foreground" />
							{/if}
							{#if item.type === 'saved_search'}
								<Badge variant="secondary" class="text-xs">Saved</Badge>
							{/if}
						</button>
					{/each}
				</div>
			{/if}
		</div>

		<div class="border-t px-3 py-2 text-xs text-muted-foreground flex items-center justify-between">
			<div class="flex items-center gap-3">
				<span class="flex items-center gap-1">
					<kbd class="px-1 rounded bg-muted">↑</kbd>
					<kbd class="px-1 rounded bg-muted">↓</kbd>
					to navigate
				</span>
				<span class="flex items-center gap-1">
					<kbd class="px-1.5 rounded bg-muted">↵</kbd>
					to select
				</span>
			</div>
			<span class="flex items-center gap-1">
				<kbd class="px-1.5 rounded bg-muted">esc</kbd>
				to close
			</span>
		</div>
	</Dialog.Content>
</Dialog.Root>
