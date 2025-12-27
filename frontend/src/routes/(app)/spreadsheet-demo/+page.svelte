<script lang="ts">
	import { apiClient } from '$lib/api/client';
	import { Button } from '$lib/components/ui/button';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { ArrowLeft, Download, Upload, FileSpreadsheet, Zap, CheckCircle, Clock } from 'lucide-svelte';
	import { goto } from '$app/navigation';

	interface SampleData {
		id: number;
		name: string;
		email: string;
		company: string;
		revenue: number;
		status: string;
		created_at: string;
	}

	interface BenchmarkResult {
		time_ms: number;
		file_size?: number;
		rows_read?: number;
	}

	interface ParseResult {
		filename: string;
		file_type: string;
		headers: string[];
		preview_rows: (string | number | null)[][];
		total_rows: number;
	}

	let sampleData = $state<SampleData[]>([]);
	let loading = $state(false);
	let benchmarkResults = $state<Record<string, BenchmarkResult> | null>(null);
	let benchmarkLoading = $state(false);
	let roundTripResult = $state<{ success: boolean; original_count: number; read_back_count: number; headers_match: boolean } | null>(null);
	let parseResult = $state<ParseResult | null>(null);
	let parseLoading = $state(false);

	async function loadSampleData() {
		loading = true;
		try {
			const response = await apiClient.get<{ data: SampleData[] }>('/spreadsheet-demo/sample-data');
			sampleData = response.data;
		} catch (error) {
			console.error('Failed to load sample data:', error);
		} finally {
			loading = false;
		}
	}

	async function exportCsv() {
		const token = localStorage.getItem('auth_token');
		const response = await fetch(`${window.location.origin}/api/v1/spreadsheet-demo/export-csv`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${token}`
			},
			body: JSON.stringify({ rows: sampleData })
		});

		if (response.ok) {
			const blob = await response.blob();
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = `export-${new Date().toISOString().slice(0, 10)}.csv`;
			a.click();
			URL.revokeObjectURL(url);
		}
	}

	async function exportExcel() {
		const token = localStorage.getItem('auth_token');
		const response = await fetch(`${window.location.origin}/api/v1/spreadsheet-demo/export-excel`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${token}`
			},
			body: JSON.stringify({ rows: sampleData })
		});

		if (response.ok) {
			const blob = await response.blob();
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = `export-${new Date().toISOString().slice(0, 10)}.xlsx`;
			a.click();
			URL.revokeObjectURL(url);
		}
	}

	async function runBenchmark() {
		benchmarkLoading = true;
		benchmarkResults = null;
		try {
			const response = await apiClient.get<{ results: Record<string, BenchmarkResult> }>('/spreadsheet-demo/benchmark', {
				params: { rows: 5000 }
			});
			benchmarkResults = response.results;
		} catch (error) {
			console.error('Benchmark failed:', error);
		} finally {
			benchmarkLoading = false;
		}
	}

	async function testRoundTrip() {
		try {
			const response = await apiClient.get<{ success: boolean; original_count: number; read_back_count: number; headers_match: boolean }>('/spreadsheet-demo/test-round-trip');
			roundTripResult = response;
		} catch (error) {
			console.error('Round trip test failed:', error);
		}
	}

	async function handleFileUpload(event: Event) {
		const target = event.target as HTMLInputElement;
		const file = target.files?.[0];
		if (!file) return;

		parseLoading = true;
		parseResult = null;

		try {
			const formData = new FormData();
			formData.append('file', file);

			const response = await apiClient.upload<ParseResult>('/spreadsheet-demo/parse-file', formData);
			parseResult = response;
		} catch (error) {
			console.error('File parsing failed:', error);
		} finally {
			parseLoading = false;
			target.value = '';
		}
	}

	function formatBytes(bytes: number): string {
		if (bytes < 1024) return `${bytes} B`;
		if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
		return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
	}

	// Load sample data on mount
	$effect(() => {
		loadSampleData();
	});
</script>

<div class="container mx-auto py-8 max-w-6xl">
	<div class="mb-8">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/dashboard')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div>
					<h1 class="text-3xl font-bold">Native Spreadsheet Demo</h1>
					<p class="mt-1 text-muted-foreground">
						CSV and Excel handling using native PHP (no PhpSpreadsheet)
					</p>
				</div>
			</div>
			<FileSpreadsheet class="h-10 w-10 text-primary" />
		</div>
	</div>

	<!-- Feature Cards -->
	<div class="grid gap-4 md:grid-cols-3 mb-8">
		<Card>
			<CardHeader class="pb-2">
				<CardTitle class="text-lg flex items-center gap-2">
					<Zap class="h-5 w-5 text-yellow-500" />
					Zero Dependencies
				</CardTitle>
			</CardHeader>
			<CardContent>
				<p class="text-sm text-muted-foreground">
					Uses native PHP extensions (ZipArchive, XMLReader) instead of PhpSpreadsheet's ~15MB vendor footprint.
				</p>
			</CardContent>
		</Card>

		<Card>
			<CardHeader class="pb-2">
				<CardTitle class="text-lg flex items-center gap-2">
					<Clock class="h-5 w-5 text-blue-500" />
					Streaming Support
				</CardTitle>
			</CardHeader>
			<CardContent>
				<p class="text-sm text-muted-foreground">
					Generator-based reading for memory-efficient processing of large files with minimal RAM usage.
				</p>
			</CardContent>
		</Card>

		<Card>
			<CardHeader class="pb-2">
				<CardTitle class="text-lg flex items-center gap-2">
					<CheckCircle class="h-5 w-5 text-green-500" />
					Full Control
				</CardTitle>
			</CardHeader>
			<CardContent>
				<p class="text-sm text-muted-foreground">
					Custom implementation means full control over behavior, formatting, and performance optimizations.
				</p>
			</CardContent>
		</Card>
	</div>

	<!-- Export Section -->
	<Card class="mb-8">
		<CardHeader>
			<CardTitle>Export Demo</CardTitle>
			<CardDescription>
				Generate CSV and Excel files from sample data using native PHP services
			</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="flex items-center gap-4 mb-6">
				<Button onclick={exportCsv} disabled={sampleData.length === 0}>
					<Download class="h-4 w-4 mr-2" />
					Export CSV
				</Button>
				<Button onclick={exportExcel} disabled={sampleData.length === 0} variant="outline">
					<Download class="h-4 w-4 mr-2" />
					Export Excel (.xlsx)
				</Button>
				<Badge variant="secondary">{sampleData.length} records</Badge>
			</div>

			{#if sampleData.length > 0}
				<div class="border rounded-lg overflow-hidden">
					<div class="overflow-x-auto">
						<table class="w-full text-sm">
							<thead class="bg-muted">
								<tr>
									<th class="px-4 py-2 text-left font-medium">ID</th>
									<th class="px-4 py-2 text-left font-medium">Name</th>
									<th class="px-4 py-2 text-left font-medium">Email</th>
									<th class="px-4 py-2 text-left font-medium">Company</th>
									<th class="px-4 py-2 text-right font-medium">Revenue</th>
									<th class="px-4 py-2 text-left font-medium">Status</th>
								</tr>
							</thead>
							<tbody>
								{#each sampleData.slice(0, 10) as row}
									<tr class="border-t">
										<td class="px-4 py-2">{row.id}</td>
										<td class="px-4 py-2">{row.name}</td>
										<td class="px-4 py-2 text-muted-foreground">{row.email}</td>
										<td class="px-4 py-2">{row.company}</td>
										<td class="px-4 py-2 text-right font-mono">${row.revenue.toLocaleString()}</td>
										<td class="px-4 py-2">
											<Badge variant={row.status === 'Active' ? 'default' : 'secondary'}>
												{row.status}
											</Badge>
										</td>
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
					{#if sampleData.length > 10}
						<div class="bg-muted px-4 py-2 text-sm text-muted-foreground">
							Showing 10 of {sampleData.length} records
						</div>
					{/if}
				</div>
			{/if}
		</CardContent>
	</Card>

	<!-- Import Section -->
	<Card class="mb-8">
		<CardHeader>
			<CardTitle>Import Demo</CardTitle>
			<CardDescription>
				Upload CSV or Excel files to parse and preview using native PHP services
			</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="flex items-center gap-4 mb-6">
				<label class="cursor-pointer">
					<input
						type="file"
						accept=".csv,.xlsx,.xls"
						class="hidden"
						onchange={handleFileUpload}
					/>
					<Button variant="outline" class="pointer-events-none" disabled={parseLoading}>
						<Upload class="h-4 w-4 mr-2" />
						{parseLoading ? 'Parsing...' : 'Upload File'}
					</Button>
				</label>
				<span class="text-sm text-muted-foreground">Supports CSV, XLSX, XLS</span>
			</div>

			{#if parseResult}
				<div class="space-y-4">
					<div class="flex items-center gap-4">
						<Badge>{parseResult.file_type.toUpperCase()}</Badge>
						<span class="font-medium">{parseResult.filename}</span>
						<span class="text-muted-foreground">({parseResult.total_rows} rows)</span>
					</div>

					<div class="border rounded-lg overflow-hidden">
						<div class="overflow-x-auto">
							<table class="w-full text-sm">
								<thead class="bg-muted">
									<tr>
										{#each parseResult.headers as header}
											<th class="px-4 py-2 text-left font-medium">{header}</th>
										{/each}
									</tr>
								</thead>
								<tbody>
									{#each parseResult.preview_rows as row}
										<tr class="border-t">
											{#each row as cell}
												<td class="px-4 py-2">{cell ?? ''}</td>
											{/each}
										</tr>
									{/each}
								</tbody>
							</table>
						</div>
					</div>
				</div>
			{/if}
		</CardContent>
	</Card>

	<!-- Benchmark Section -->
	<Card class="mb-8">
		<CardHeader>
			<CardTitle>Performance Benchmark</CardTitle>
			<CardDescription>
				Compare CSV and Excel read/write performance with 5,000 rows
			</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="flex items-center gap-4 mb-6">
				<Button onclick={runBenchmark} disabled={benchmarkLoading}>
					<Zap class="h-4 w-4 mr-2" />
					{benchmarkLoading ? 'Running...' : 'Run Benchmark'}
				</Button>
				<Button onclick={testRoundTrip} variant="outline">
					<CheckCircle class="h-4 w-4 mr-2" />
					Test Round-Trip
				</Button>
			</div>

			{#if benchmarkResults}
				<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
					<div class="border rounded-lg p-4">
						<div class="text-sm text-muted-foreground mb-1">CSV Write</div>
						<div class="text-2xl font-bold">{benchmarkResults.csv_write?.time_ms}ms</div>
						<div class="text-sm text-muted-foreground">
							{formatBytes(benchmarkResults.csv_write?.file_size ?? 0)}
						</div>
					</div>
					<div class="border rounded-lg p-4">
						<div class="text-sm text-muted-foreground mb-1">CSV Read</div>
						<div class="text-2xl font-bold">{benchmarkResults.csv_read?.time_ms}ms</div>
						<div class="text-sm text-muted-foreground">
							{benchmarkResults.csv_read?.rows_read} rows
						</div>
					</div>
					<div class="border rounded-lg p-4">
						<div class="text-sm text-muted-foreground mb-1">Excel Write</div>
						<div class="text-2xl font-bold">{benchmarkResults.xlsx_write?.time_ms}ms</div>
						<div class="text-sm text-muted-foreground">
							{formatBytes(benchmarkResults.xlsx_write?.file_size ?? 0)}
						</div>
					</div>
					<div class="border rounded-lg p-4">
						<div class="text-sm text-muted-foreground mb-1">Excel Read</div>
						<div class="text-2xl font-bold">{benchmarkResults.xlsx_read?.time_ms}ms</div>
						<div class="text-sm text-muted-foreground">
							{benchmarkResults.xlsx_read?.rows_read} rows
						</div>
					</div>
				</div>

				{#if benchmarkResults.memory}
					<div class="mt-4 p-4 bg-muted rounded-lg">
						<span class="text-sm">
							Peak Memory: <strong>{benchmarkResults.memory.peak_mb} MB</strong> |
							Current: <strong>{benchmarkResults.memory.current_mb} MB</strong>
						</span>
					</div>
				{/if}
			{/if}

			{#if roundTripResult}
				<div class="mt-4 p-4 border rounded-lg bg-green-50 dark:bg-green-950">
					<div class="flex items-center gap-2 text-green-700 dark:text-green-300">
						<CheckCircle class="h-5 w-5" />
						<span class="font-medium">Round-Trip Test Passed</span>
					</div>
					<div class="mt-2 text-sm text-green-600 dark:text-green-400">
						Wrote {roundTripResult.original_count} rows to Excel, read back {roundTripResult.read_back_count} rows.
						Headers match: {roundTripResult.headers_match ? 'Yes' : 'No'}
					</div>
				</div>
			{/if}
		</CardContent>
	</Card>

	<!-- Implementation Details -->
	<Card>
		<CardHeader>
			<CardTitle>Implementation Details</CardTitle>
			<CardDescription>Native PHP services used in this demo</CardDescription>
		</CardHeader>
		<CardContent>
			<div class="grid gap-4 md:grid-cols-3">
				<div>
					<h4 class="font-medium mb-2">CsvService</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li>Native fgetcsv/fputcsv</li>
						<li>Auto delimiter detection</li>
						<li>Generator-based streaming</li>
						<li>Memory-efficient chunking</li>
					</ul>
				</div>
				<div>
					<h4 class="font-medium mb-2">XlsxReader</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li>ZipArchive for .xlsx files</li>
						<li>XMLReader for streaming</li>
						<li>Shared strings support</li>
						<li>Multiple sheet support</li>
					</ul>
				</div>
				<div>
					<h4 class="font-medium mb-2">XlsxWriter</h4>
					<ul class="text-sm text-muted-foreground space-y-1">
						<li>Creates valid .xlsx files</li>
						<li>Multiple worksheets</li>
						<li>Header styling</li>
						<li>Shared strings optimization</li>
					</ul>
				</div>
			</div>
		</CardContent>
	</Card>
</div>
