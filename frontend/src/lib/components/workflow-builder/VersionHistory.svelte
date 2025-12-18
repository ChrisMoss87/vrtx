<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Dialog from '$lib/components/ui/dialog';
	import {
		History,
		RotateCcw,
		ChevronDown,
		ChevronUp,
		Plus,
		Pencil,
		RotateCw,
		Check,
		User,
		Clock
	} from 'lucide-svelte';
	import {
		getWorkflowVersions,
		rollbackWorkflowToVersion,
		type WorkflowVersionSummary
	} from '$lib/api/workflows';
	import { toast } from 'svelte-sonner';

	interface Props {
		workflowId: number;
		onRollback?: () => void;
	}

	let { workflowId, onRollback }: Props = $props();

	let versions = $state<WorkflowVersionSummary[]>([]);
	let currentVersion = $state(1);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let expanded = $state(false);
	let rolling = $state(false);
	let confirmRollback = $state<WorkflowVersionSummary | null>(null);

	const changeTypeIcons = {
		create: Plus,
		update: Pencil,
		rollback: RotateCcw,
		restore: RotateCw
	};

	const changeTypeColors = {
		create: 'bg-green-100 text-green-800',
		update: 'bg-blue-100 text-blue-800',
		rollback: 'bg-amber-100 text-amber-800',
		restore: 'bg-purple-100 text-purple-800'
	};

	async function loadVersions() {
		loading = true;
		error = null;
		try {
			const result = await getWorkflowVersions(workflowId);
			versions = result.versions;
			currentVersion = result.current_version;
		} catch (e) {
			error = e instanceof Error ? e.message : 'Failed to load versions';
		} finally {
			loading = false;
		}
	}

	async function handleRollback() {
		if (!confirmRollback) return;

		rolling = true;
		try {
			await rollbackWorkflowToVersion(workflowId, confirmRollback.id);
			toast.success(`Restored to version ${confirmRollback.version_number}`);
			confirmRollback = null;
			await loadVersions();
			onRollback?.();
		} catch (e) {
			toast.error('Failed to rollback');
		} finally {
			rolling = false;
		}
	}

	function formatDate(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleDateString(undefined, {
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit'
		});
	}

	onMount(() => {
		loadVersions();
	});
</script>

<div class="rounded-lg border bg-card">
	<button
		class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/50"
		onclick={() => (expanded = !expanded)}
	>
		<div class="flex items-center gap-2">
			<History class="h-4 w-4 text-muted-foreground" />
			<span class="font-medium">Version History</span>
			<Badge variant="secondary" class="text-xs">v{currentVersion}</Badge>
		</div>
		{#if expanded}
			<ChevronUp class="h-4 w-4" />
		{:else}
			<ChevronDown class="h-4 w-4" />
		{/if}
	</button>

	{#if expanded}
		<div class="border-t px-4 py-3">
			{#if loading}
				<div class="space-y-3">
					{#each Array(3) as _}
						<div class="flex items-center gap-3">
							<Skeleton class="h-8 w-8 rounded-full" />
							<div class="flex-1 space-y-1">
								<Skeleton class="h-4 w-1/2" />
								<Skeleton class="h-3 w-1/3" />
							</div>
						</div>
					{/each}
				</div>
			{:else if error}
				<p class="text-sm text-destructive">{error}</p>
			{:else if versions.length === 0}
				<p class="text-sm text-muted-foreground">No version history available</p>
			{:else}
				<div class="relative space-y-4">
					<!-- Timeline line -->
					<div class="absolute left-4 top-4 bottom-4 w-0.5 bg-border"></div>

					{#each versions.slice(0, 10) as version, index (version.id)}
						{@const Icon = changeTypeIcons[version.change_type]}
						<div class="relative flex gap-4">
							<!-- Timeline dot -->
							<div
								class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full border bg-background"
							>
								<Icon class="h-4 w-4" />
							</div>

							<div class="flex-1 pb-4">
								<div class="flex items-start justify-between gap-2">
									<div>
										<div class="flex items-center gap-2">
											<span class="font-medium">v{version.version_number}</span>
											<Badge variant="outline" class="text-xs {changeTypeColors[version.change_type]}">
												{version.change_type}
											</Badge>
											{#if version.is_active}
												<Badge class="gap-1 text-xs">
													<Check class="h-3 w-3" />
													Current
												</Badge>
											{/if}
										</div>
										<p class="mt-0.5 text-sm text-muted-foreground">
											{version.change_summary || version.changes.join(', ')}
										</p>
										<div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
											<span class="flex items-center gap-1">
												<Clock class="h-3 w-3" />
												{formatDate(version.created_at)}
											</span>
											{#if version.created_by}
												<span class="flex items-center gap-1">
													<User class="h-3 w-3" />
													{version.created_by.name}
												</span>
											{/if}
											<span>{version.step_count} steps</span>
										</div>
									</div>

									{#if !version.is_active}
										<Button
											variant="ghost"
											size="sm"
											onclick={() => (confirmRollback = version)}
										>
											<RotateCcw class="mr-1 h-3 w-3" />
											Restore
										</Button>
									{/if}
								</div>
							</div>
						</div>
					{/each}

					{#if versions.length > 10}
						<p class="pl-12 text-sm text-muted-foreground">
							And {versions.length - 10} more versions...
						</p>
					{/if}
				</div>
			{/if}
		</div>
	{/if}
</div>

<!-- Rollback confirmation dialog -->
<Dialog.Root open={!!confirmRollback} onOpenChange={() => (confirmRollback = null)}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Restore Version</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to restore to version {confirmRollback?.version_number}? This will
				create a new version with the restored configuration.
			</Dialog.Description>
		</Dialog.Header>

		{#if confirmRollback}
			<div class="rounded-lg border bg-muted/50 p-3">
				<p class="font-medium">{confirmRollback.name}</p>
				<p class="mt-1 text-sm text-muted-foreground">
					{confirmRollback.change_summary || confirmRollback.changes.join(', ')}
				</p>
				<p class="mt-2 text-xs text-muted-foreground">
					Created {formatDate(confirmRollback.created_at)}
					{#if confirmRollback.created_by}
						by {confirmRollback.created_by.name}
					{/if}
				</p>
			</div>
		{/if}

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (confirmRollback = null)}>Cancel</Button>
			<Button onclick={handleRollback} disabled={rolling}>
				{#if rolling}
					Restoring...
				{:else}
					Restore Version
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
