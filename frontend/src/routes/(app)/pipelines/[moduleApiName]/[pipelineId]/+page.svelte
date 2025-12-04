<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { ArrowLeft, Settings, Search, RefreshCw, Table } from 'lucide-svelte';
	import { KanbanBoard } from '$lib/components/kanban';
	import type { KanbanColumn } from '$lib/api/pipelines';

	const moduleApiName = $derived($page.params.moduleApiName as string);
	const pipelineId = $derived(Number($page.params.pipelineId));

	let kanbanBoard = $state<{ refresh: () => void } | null>(null);
	let searchQuery = $state('');

	function handleRecordClick(record: KanbanColumn['records'][0]) {
		goto(`/records/${moduleApiName}/${record.id}`);
	}

	function goBack() {
		goto(`/pipelines/${moduleApiName}`);
	}

	function goToSettings() {
		goto(`/pipelines/${moduleApiName}/${pipelineId}/settings`);
	}

	function goToTableView() {
		goto(`/records/${moduleApiName}`);
	}

	function refresh() {
		kanbanBoard?.refresh();
	}
</script>

<div class="flex h-[calc(100vh-64px)] flex-col">
	<!-- Header -->
	<div class="border-b bg-background px-6 py-4">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={goBack}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-xl font-semibold">Pipeline</h1>
					<p class="text-muted-foreground text-sm capitalize">{moduleApiName}</p>
				</div>
			</div>
			<div class="flex items-center gap-2">
				<div class="relative">
					<Search class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
					<Input
						type="search"
						placeholder="Search records..."
						class="w-64 pl-9"
						bind:value={searchQuery}
					/>
				</div>
				<Button variant="outline" size="icon" onclick={refresh}>
					<RefreshCw class="h-4 w-4" />
				</Button>
				<Button variant="outline" onclick={goToTableView}>
					<Table class="mr-2 h-4 w-4" />
					Table
				</Button>
				<Button variant="outline" size="icon" onclick={goToSettings}>
					<Settings class="h-4 w-4" />
				</Button>
			</div>
		</div>
	</div>

	<!-- Kanban Board -->
	<div class="flex-1 overflow-hidden">
		<KanbanBoard
			{pipelineId}
			valueField="value"
			titleField="name"
			subtitleField="company"
			search={searchQuery}
			onRecordClick={handleRecordClick}
			bind:this={kanbanBoard}
			class="h-full p-4"
		/>
	</div>
</div>
