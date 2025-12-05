<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import { Progress } from '$lib/components/ui/progress';
	import {
		Loader2,
		CheckCircle,
		AlertTriangle,
		XCircle,
		FileCheck,
		AlertCircle
	} from 'lucide-svelte';
	import type { Import } from '$lib/api/imports';

	interface Props {
		importData: Import | null;
		isLoading: boolean;
	}

	let { importData, isLoading }: Props = $props();

	const validationErrors = $derived(() => {
		if (!importData?.validation_errors) return [];
		return Object.entries(importData.validation_errors).map(([rowKey, errors]) => ({
			row: parseInt(rowKey.replace('row_', '')),
			errors
		}));
	});

	const hasErrors = $derived(validationErrors().length > 0);
	const errorCount = $derived(validationErrors().length);
	const validCount = $derived(
		importData ? importData.total_rows - errorCount : 0
	);
</script>

<div class="space-y-6">
	{#if isLoading || importData?.status === 'validating'}
		<!-- Validating -->
		<div class="flex flex-col items-center justify-center py-12">
			<Loader2 class="h-12 w-12 animate-spin text-primary mb-4" />
			<h4 class="text-lg font-medium mb-2">Validating your data...</h4>
			<p class="text-muted-foreground text-center">
				Checking each row against field requirements and formats
			</p>
			{#if importData}
				<div class="w-full max-w-xs mt-6">
					<Progress
						value={(importData.processed_rows / importData.total_rows) * 100}
						class="h-2"
					/>
					<p class="text-sm text-muted-foreground text-center mt-2">
						{importData.processed_rows} / {importData.total_rows} rows
					</p>
				</div>
			{/if}
		</div>
	{:else if importData?.status === 'validated'}
		<!-- Validation Complete -->
		<div class="space-y-6">
			<!-- Summary Cards -->
			<div class="grid gap-4 sm:grid-cols-3">
				<Card.Root>
					<Card.Content class="pt-6">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
								<CheckCircle class="h-5 w-5 text-green-600 dark:text-green-400" />
							</div>
							<div>
								<div class="text-2xl font-bold">{validCount.toLocaleString()}</div>
								<p class="text-xs text-muted-foreground">Valid rows</p>
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root>
					<Card.Content class="pt-6">
						<div class="flex items-center gap-3">
							<div
								class="w-10 h-10 rounded-full flex items-center justify-center"
								class:bg-red-100={hasErrors}
								class:dark:bg-red-900={hasErrors}
								class:bg-muted={!hasErrors}
							>
								{#if hasErrors}
									<XCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
								{:else}
									<FileCheck class="h-5 w-5 text-muted-foreground" />
								{/if}
							</div>
							<div>
								<div class="text-2xl font-bold">{errorCount.toLocaleString()}</div>
								<p class="text-xs text-muted-foreground">Rows with errors</p>
							</div>
						</div>
					</Card.Content>
				</Card.Root>

				<Card.Root>
					<Card.Content class="pt-6">
						<div class="flex items-center gap-3">
							<div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
								<FileCheck class="h-5 w-5 text-primary" />
							</div>
							<div>
								<div class="text-2xl font-bold">{importData.total_rows.toLocaleString()}</div>
								<p class="text-xs text-muted-foreground">Total rows</p>
							</div>
						</div>
					</Card.Content>
				</Card.Root>
			</div>

			{#if hasErrors}
				<!-- Errors List -->
				<Card.Root class="border-red-200 dark:border-red-900">
					<Card.Header class="pb-3">
						<div class="flex items-center gap-2">
							<AlertTriangle class="h-5 w-5 text-red-600" />
							<Card.Title class="text-base">Validation Errors</Card.Title>
						</div>
						<Card.Description>
							The following rows have errors and will fail to import. You can proceed anyway - these rows will be skipped.
						</Card.Description>
					</Card.Header>
					<Card.Content>
						<div class="border rounded-lg max-h-64 overflow-auto">
							<Table.Root>
								<Table.Header>
									<Table.Row>
										<Table.Head class="w-20">Row</Table.Head>
										<Table.Head>Field</Table.Head>
										<Table.Head>Error</Table.Head>
									</Table.Row>
								</Table.Header>
								<Table.Body>
									{#each validationErrors().slice(0, 20) as { row, errors }}
										{#each Object.entries(errors) as [field, message]}
											<Table.Row>
												<Table.Cell>
													<Badge variant="outline">{row}</Badge>
												</Table.Cell>
												<Table.Cell class="font-medium">{field}</Table.Cell>
												<Table.Cell class="text-red-600">{message}</Table.Cell>
											</Table.Row>
										{/each}
									{/each}
								</Table.Body>
							</Table.Root>
						</div>
						{#if validationErrors().length > 20}
							<p class="text-sm text-muted-foreground text-center mt-3">
								Showing 20 of {validationErrors().length} errors
							</p>
						{/if}
					</Card.Content>
				</Card.Root>
			{:else}
				<!-- All Valid -->
				<div class="flex flex-col items-center justify-center py-8 text-center">
					<div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mb-4">
						<CheckCircle class="h-8 w-8 text-green-600 dark:text-green-400" />
					</div>
					<h4 class="text-lg font-medium mb-2">Validation Passed</h4>
					<p class="text-muted-foreground max-w-md">
						All {importData.total_rows.toLocaleString()} rows passed validation and are ready to import.
						Click <strong>Start Import</strong> to proceed.
					</p>
				</div>
			{/if}

			{#if hasErrors}
				<div class="flex items-start gap-3 rounded-lg bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-900 p-4">
					<AlertCircle class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" />
					<div class="text-sm">
						<p class="font-medium text-yellow-800 dark:text-yellow-200">
							You can still proceed with the import
						</p>
						<p class="text-yellow-700 dark:text-yellow-300 mt-1">
							{validCount.toLocaleString()} valid rows will be imported. {errorCount.toLocaleString()} rows with errors will be skipped.
						</p>
					</div>
				</div>
			{/if}
		</div>
	{:else if importData?.status === 'failed'}
		<!-- Validation Failed -->
		<div class="flex flex-col items-center justify-center py-12 text-center">
			<div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mb-4">
				<XCircle class="h-8 w-8 text-red-600 dark:text-red-400" />
			</div>
			<h4 class="text-lg font-medium mb-2">Validation Failed</h4>
			<p class="text-muted-foreground max-w-md">
				{importData.error_message || 'An error occurred during validation'}
			</p>
		</div>
	{:else}
		<!-- Not started -->
		<div class="flex flex-col items-center justify-center py-12 text-center">
			<FileCheck class="h-12 w-12 text-muted-foreground mb-4" />
			<h4 class="text-lg font-medium mb-2">Ready to Validate</h4>
			<p class="text-muted-foreground max-w-md">
				Click <strong>Validate</strong> to check your data for errors before importing
			</p>
		</div>
	{/if}
</div>
