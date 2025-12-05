<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import { Progress } from '$lib/components/ui/progress';
	import {
		Loader2,
		CheckCircle,
		XCircle,
		SkipForward,
		Clock,
		FileCheck,
		PartyPopper
	} from 'lucide-svelte';
	import type { Import } from '$lib/api/imports';

	interface Props {
		importData: Import | null;
	}

	let { importData }: Props = $props();

	const progress = $derived(() => {
		if (!importData || importData.total_rows === 0) return 0;
		return Math.round((importData.processed_rows / importData.total_rows) * 100);
	});

	const isComplete = $derived(importData?.status === 'completed');
	const isFailed = $derived(importData?.status === 'failed');
	const isRunning = $derived(importData?.status === 'importing');

	function formatDuration(startedAt: string | null, completedAt: string | null): string {
		if (!startedAt) return '-';
		const start = new Date(startedAt);
		const end = completedAt ? new Date(completedAt) : new Date();
		const diff = Math.floor((end.getTime() - start.getTime()) / 1000);

		if (diff < 60) return `${diff}s`;
		if (diff < 3600) return `${Math.floor(diff / 60)}m ${diff % 60}s`;
		return `${Math.floor(diff / 3600)}h ${Math.floor((diff % 3600) / 60)}m`;
	}
</script>

<div class="space-y-6">
	{#if isComplete}
		<!-- Import Complete -->
		<div class="flex flex-col items-center justify-center py-8 text-center">
			<div class="w-20 h-20 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mb-6">
				<PartyPopper class="h-10 w-10 text-green-600 dark:text-green-400" />
			</div>
			<h4 class="text-xl font-semibold mb-2">Import Complete!</h4>
			<p class="text-muted-foreground max-w-md mb-6">
				Your records have been successfully imported into the system.
			</p>
		</div>
	{:else if isFailed}
		<!-- Import Failed -->
		<div class="flex flex-col items-center justify-center py-8 text-center">
			<div class="w-20 h-20 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mb-6">
				<XCircle class="h-10 w-10 text-red-600 dark:text-red-400" />
			</div>
			<h4 class="text-xl font-semibold mb-2">Import Failed</h4>
			<p class="text-muted-foreground max-w-md">
				{importData?.error_message || 'An error occurred during the import process.'}
			</p>
		</div>
	{:else if isRunning}
		<!-- Import In Progress -->
		<div class="flex flex-col items-center justify-center py-8 text-center">
			<Loader2 class="h-12 w-12 animate-spin text-primary mb-6" />
			<h4 class="text-xl font-semibold mb-2">Importing Records...</h4>
			<p class="text-muted-foreground max-w-md mb-6">
				Please wait while we import your data. This may take a few minutes for large files.
			</p>

			<div class="w-full max-w-md space-y-2">
				<Progress value={progress()} class="h-3" />
				<p class="text-sm text-muted-foreground">
					{importData?.processed_rows?.toLocaleString() || 0} / {importData?.total_rows?.toLocaleString() || 0} rows processed ({progress()}%)
				</p>
			</div>
		</div>
	{:else}
		<!-- Waiting -->
		<div class="flex flex-col items-center justify-center py-8 text-center">
			<Clock class="h-12 w-12 text-muted-foreground mb-4" />
			<h4 class="text-lg font-medium mb-2">Ready to Import</h4>
			<p class="text-muted-foreground">Waiting for import to start...</p>
		</div>
	{/if}

	{#if importData}
		<!-- Stats Cards -->
		<div class="grid gap-4 sm:grid-cols-4">
			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-3">
						<FileCheck class="h-5 w-5 text-muted-foreground" />
						<div>
							<div class="text-xl font-bold">{importData.total_rows.toLocaleString()}</div>
							<p class="text-xs text-muted-foreground">Total rows</p>
						</div>
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-3">
						<CheckCircle class="h-5 w-5 text-green-600" />
						<div>
							<div class="text-xl font-bold text-green-600">
								{importData.successful_rows.toLocaleString()}
							</div>
							<p class="text-xs text-muted-foreground">Successful</p>
						</div>
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-3">
						<XCircle class="h-5 w-5 text-red-600" />
						<div>
							<div class="text-xl font-bold text-red-600">
								{importData.failed_rows.toLocaleString()}
							</div>
							<p class="text-xs text-muted-foreground">Failed</p>
						</div>
					</div>
				</Card.Content>
			</Card.Root>

			<Card.Root>
				<Card.Content class="pt-6">
					<div class="flex items-center gap-3">
						<SkipForward class="h-5 w-5 text-yellow-600" />
						<div>
							<div class="text-xl font-bold text-yellow-600">
								{importData.skipped_rows.toLocaleString()}
							</div>
							<p class="text-xs text-muted-foreground">Skipped</p>
						</div>
					</div>
				</Card.Content>
			</Card.Root>
		</div>

		<!-- Duration (if started) -->
		{#if importData.started_at}
			<div class="flex justify-center">
				<Badge variant="secondary" class="text-sm">
					<Clock class="h-3 w-3 mr-1" />
					Duration: {formatDuration(importData.started_at, importData.completed_at)}
				</Badge>
			</div>
		{/if}
	{/if}
</div>
