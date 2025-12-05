<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as Table from '$lib/components/ui/table';
	import { Upload, FileSpreadsheet, X, Loader2, Info } from 'lucide-svelte';

	interface Props {
		moduleApiName: string;
		isLoading: boolean;
		uploadedFile: File | null;
		previewData?: {
			headers: string[];
			preview_rows: unknown[][];
			total_rows: number;
		};
		onUpload: (file: File) => void;
	}

	let {
		moduleApiName,
		isLoading,
		uploadedFile,
		previewData,
		onUpload
	}: Props = $props();

	let dragOver = $state(false);
	let fileInput: HTMLInputElement;

	function handleDrop(e: DragEvent) {
		e.preventDefault();
		dragOver = false;

		const file = e.dataTransfer?.files[0];
		if (file && isValidFile(file)) {
			onUpload(file);
		}
	}

	function handleDragOver(e: DragEvent) {
		e.preventDefault();
		dragOver = true;
	}

	function handleDragLeave() {
		dragOver = false;
	}

	function handleFileSelect(e: Event) {
		const input = e.target as HTMLInputElement;
		const file = input.files?.[0];
		if (file && isValidFile(file)) {
			onUpload(file);
		}
	}

	function isValidFile(file: File): boolean {
		const validTypes = [
			'text/csv',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		];
		const validExtensions = ['.csv', '.xls', '.xlsx'];
		const hasValidType = validTypes.includes(file.type);
		const hasValidExtension = validExtensions.some((ext) =>
			file.name.toLowerCase().endsWith(ext)
		);
		return hasValidType || hasValidExtension;
	}

	function formatFileSize(bytes: number): string {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	function clearFile() {
		// This would need to propagate up to clear the parent state
		// For now, we just show that a file is selected
	}
</script>

<div class="space-y-6">
	{#if !uploadedFile}
		<!-- File Drop Zone -->
		<div
			class="border-2 border-dashed rounded-lg p-12 text-center transition-colors {dragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/25'}"
			role="button"
			tabindex="0"
			ondrop={handleDrop}
			ondragover={handleDragOver}
			ondragleave={handleDragLeave}
			onclick={() => fileInput?.click()}
			onkeydown={(e) => e.key === 'Enter' && fileInput?.click()}
		>
			<input
				bind:this={fileInput}
				type="file"
				accept=".csv,.xls,.xlsx"
				class="hidden"
				onchange={handleFileSelect}
			/>

			<div class="flex flex-col items-center gap-4">
				<div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
					<Upload class="w-8 h-8 text-primary" />
				</div>
				<div>
					<p class="text-lg font-medium">Drop your file here</p>
					<p class="text-sm text-muted-foreground">
						or click to browse
					</p>
				</div>
				<div class="flex gap-2">
					<Badge variant="secondary">CSV</Badge>
					<Badge variant="secondary">XLS</Badge>
					<Badge variant="secondary">XLSX</Badge>
				</div>
				<p class="text-xs text-muted-foreground">
					Maximum file size: 50MB
				</p>
			</div>
		</div>

		<!-- Help Text -->
		<div class="flex items-start gap-3 rounded-lg bg-muted/50 p-4">
			<Info class="h-5 w-5 text-muted-foreground mt-0.5" />
			<div class="text-sm text-muted-foreground">
				<p class="font-medium mb-1">Tips for successful imports:</p>
				<ul class="list-disc list-inside space-y-1">
					<li>First row should contain column headers</li>
					<li>Use consistent date formats (e.g., YYYY-MM-DD)</li>
					<li>Remove any empty rows at the end of the file</li>
					<li>For large files, consider splitting into batches</li>
				</ul>
			</div>
		</div>
	{:else}
		<!-- File Preview -->
		<div class="space-y-4">
			<!-- File Info -->
			<div class="flex items-center justify-between p-4 border rounded-lg bg-muted/30">
				<div class="flex items-center gap-3">
					<div class="w-10 h-10 rounded bg-primary/10 flex items-center justify-center">
						<FileSpreadsheet class="w-5 h-5 text-primary" />
					</div>
					<div>
						<p class="font-medium">{uploadedFile.name}</p>
						<p class="text-sm text-muted-foreground">
							{formatFileSize(uploadedFile.size)}
							{#if previewData}
								&bull; {previewData.total_rows.toLocaleString()} rows
								&bull; {previewData.headers.length} columns
							{/if}
						</p>
					</div>
				</div>
				{#if isLoading}
					<Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
				{/if}
			</div>

			<!-- Data Preview -->
			{#if previewData && previewData.preview_rows.length > 0}
				<div class="space-y-2">
					<h4 class="font-medium text-sm">Data Preview</h4>
					<div class="border rounded-lg overflow-auto max-h-64">
						<Table.Root>
							<Table.Header>
								<Table.Row>
									<Table.Head class="w-12 text-center">#</Table.Head>
									{#each previewData.headers as header}
										<Table.Head class="min-w-32">{header}</Table.Head>
									{/each}
								</Table.Row>
							</Table.Header>
							<Table.Body>
								{#each previewData.preview_rows.slice(0, 5) as row, rowIndex}
									<Table.Row>
										<Table.Cell class="text-center text-muted-foreground">
											{rowIndex + 1}
										</Table.Cell>
										{#each row as cell}
											<Table.Cell class="truncate max-w-48">
												{cell ?? '-'}
											</Table.Cell>
										{/each}
									</Table.Row>
								{/each}
							</Table.Body>
						</Table.Root>
					</div>
					{#if previewData.preview_rows.length > 5}
						<p class="text-xs text-muted-foreground text-center">
							Showing 5 of {previewData.preview_rows.length} preview rows
						</p>
					{/if}
				</div>
			{/if}
		</div>
	{/if}
</div>
