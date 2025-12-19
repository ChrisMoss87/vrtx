<script lang="ts">
	import { Alert, AlertDescription, AlertTitle } from '$lib/components/ui/alert';
	import { Button } from '$lib/components/ui/button';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { AlertCircle, AlertTriangle, CheckCircle2, X } from 'lucide-svelte';
	import type { ValidationResult } from '$lib/lib/module-validation';

	interface Props {
		result: ValidationResult;
		onClose?: () => void;
		onFixError?: (error: any) => void;
	}

	let { result, onClose, onFixError }: Props = $props();

	const hasIssues = $derived(result.errors.length > 0 || result.warnings.length > 0);
</script>

{#if hasIssues}
	<div class="rounded-lg border bg-card">
		<div class="flex items-center justify-between border-b p-4">
			<div class="flex items-center gap-2">
				{#if result.errors.length > 0}
					<AlertCircle class="h-5 w-5 text-destructive" />
					<h3 class="font-semibold">Validation Issues</h3>
				{:else}
					<AlertTriangle class="h-5 w-5 text-yellow-600" />
					<h3 class="font-semibold">Validation Warnings</h3>
				{/if}
			</div>
			{#if onClose}
				<Button variant="ghost" size="icon" onclick={onClose}>
					<X class="h-4 w-4" />
				</Button>
			{/if}
		</div>

		<ScrollArea class="h-[300px]">
			<div class="space-y-3 p-4">
				<!-- Errors -->
				{#if result.errors.length > 0}
					<div class="space-y-2">
						<div class="flex items-center gap-2 text-sm font-medium text-destructive">
							<AlertCircle class="h-4 w-4" />
							<span>{result.errors.length} Error{result.errors.length !== 1 ? 's' : ''}</span>
						</div>
						{#each result.errors as error, index}
							<Alert variant="destructive" class="py-3">
								<AlertDescription class="flex items-start justify-between gap-2">
									<div class="flex-1">
										{#if error.blockIndex !== undefined && error.fieldIndex !== undefined}
											<span class="font-medium">
												Block {error.blockIndex + 1}, Field {error.fieldIndex + 1}
											</span>
											{#if error.field}
												<span class="text-muted-foreground"> ({error.field})</span>
											{/if}
											<span>: </span>
										{:else if error.blockIndex !== undefined}
											<span class="font-medium">Block {error.blockIndex + 1}: </span>
										{:else if error.field}
											<span class="font-medium">{error.field}: </span>
										{/if}
										<span>{error.message}</span>
									</div>
									{#if onFixError}
										<Button
											variant="ghost"
											size="sm"
											onclick={() => onFixError?.(error)}
											class="shrink-0"
										>
											Fix
										</Button>
									{/if}
								</AlertDescription>
							</Alert>
						{/each}
					</div>
				{/if}

				<!-- Warnings -->
				{#if result.warnings.length > 0}
					<div class="space-y-2">
						<div class="flex items-center gap-2 text-sm font-medium text-yellow-600">
							<AlertTriangle class="h-4 w-4" />
							<span>{result.warnings.length} Warning{result.warnings.length !== 1 ? 's' : ''}</span>
						</div>
						{#each result.warnings as warning}
							<Alert
								class="border-yellow-200 bg-yellow-50 py-3 dark:border-yellow-900 dark:bg-yellow-950/30"
							>
								<AlertDescription class="text-yellow-900 dark:text-yellow-100">
									{#if warning.blockIndex !== undefined && warning.fieldIndex !== undefined}
										<span class="font-medium">
											Block {warning.blockIndex + 1}, Field {warning.fieldIndex + 1}
										</span>
										{#if warning.field}
											<span class="text-yellow-700 dark:text-yellow-300"> ({warning.field})</span>
										{/if}
										<span>: </span>
									{:else if warning.blockIndex !== undefined}
										<span class="font-medium">Block {warning.blockIndex + 1}: </span>
									{:else if warning.field}
										<span class="font-medium">{warning.field}: </span>
									{/if}
									<span>{warning.message}</span>
								</AlertDescription>
							</Alert>
						{/each}
					</div>
				{/if}
			</div>
		</ScrollArea>
	</div>
{:else}
	<Alert class="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/30">
		<CheckCircle2 class="h-4 w-4 text-green-600" />
		<AlertTitle class="text-green-900 dark:text-green-100">All Checks Passed</AlertTitle>
		<AlertDescription class="text-green-800 dark:text-green-200">
			This module is valid and ready to be published.
		</AlertDescription>
	</Alert>
{/if}
