<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Download,
		X,
		FileSpreadsheet,
		FileText,
		Loader2,
		GripVertical,
		ChevronUp,
		ChevronDown,
		Filter,
		SortAsc,
		Settings,
		Save
	} from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ExportFilter, ExportSort, ExportOptions, ExportFileType, Export } from '$lib/api/exports';
	import { createExport } from '$lib/api/exports';
	import { toast } from 'svelte-sonner';
	import ExportFieldSelector from './ExportFieldSelector.svelte';
	import ExportFilterBuilder from './ExportFilterBuilder.svelte';

	interface Props {
		moduleApiName: string;
		moduleName: string;
		moduleFields: Field[];
		recordCount?: number;
		onExportStarted?: (exportData: Export) => void;
		onCancel?: () => void;
	}

	let {
		moduleApiName,
		moduleName,
		moduleFields,
		recordCount = 0,
		onExportStarted,
		onCancel
	}: Props = $props();

	const dispatch = createEventDispatcher();

	// Export configuration
	let exportName = $state(`${moduleName} Export - ${new Date().toLocaleDateString()}`);
	let fileType = $state<ExportFileType>('csv');
	let selectedFields = $state<string[]>(
		moduleFields.filter((f) => !['file', 'image', 'json'].includes(f.type)).map((f) => f.api_name)
	);
	let filters = $state<ExportFilter[]>([]);
	let sorting = $state<ExportSort[]>([]);
	let options = $state<ExportOptions>({
		include_headers: true,
		date_format: 'Y-m-d'
	});

	let isExporting = $state(false);
	let activeTab = $state('fields');

	const exportableFields = $derived(
		moduleFields.filter((f) => !['file', 'image', 'json'].includes(f.type))
	);

	const estimatedRecords = $derived(recordCount); // In real app, this would be calculated based on filters

	async function handleExport() {
		if (selectedFields.length === 0) {
			toast.error('Please select at least one field to export');
			return;
		}

		isExporting = true;
		try {
			const result = await createExport(moduleApiName, {
				name: exportName,
				file_type: fileType,
				selected_fields: selectedFields,
				filters: filters.length > 0 ? filters : undefined,
				sorting: sorting.length > 0 ? sorting : undefined,
				export_options: options
			});

			toast.success('Export started! You will be notified when it\'s ready.');
			onExportStarted?.(result.export);
			dispatch('started', result.export);
		} catch (error) {
			console.error('Export failed:', error);
			toast.error('Failed to start export');
		} finally {
			isExporting = false;
		}
	}

	function handleCancel() {
		onCancel?.();
		dispatch('cancel');
	}

	function selectAllFields() {
		selectedFields = exportableFields.map((f) => f.api_name);
	}

	function clearAllFields() {
		selectedFields = [];
	}
</script>

<Card.Root class="w-full max-w-3xl mx-auto">
	<Card.Header>
		<div class="flex items-center justify-between">
			<div>
				<Card.Title class="text-xl">Export {moduleName}</Card.Title>
				<Card.Description>
					Export records to CSV or Excel format
				</Card.Description>
			</div>
			<Button variant="ghost" size="icon" onclick={handleCancel}>
				<X class="h-4 w-4" />
			</Button>
		</div>
	</Card.Header>

	<Card.Content class="space-y-6">
		<!-- Export Name & Format -->
		<div class="grid gap-4 sm:grid-cols-2">
			<div class="space-y-2">
				<Label>Export Name</Label>
				<Input bind:value={exportName} placeholder="Enter export name" />
			</div>
			<div class="space-y-2">
				<Label>File Format</Label>
				<Select.Root
					type="single"
					value={fileType}
					onValueChange={(v) => { if (v) fileType = v as ExportFileType; }}
				>
					<Select.Trigger>
						<div class="flex items-center gap-2">
							{#if fileType === 'csv'}
								<FileText class="h-4 w-4" />
								<span>CSV (.csv)</span>
							{:else}
								<FileSpreadsheet class="h-4 w-4" />
								<span>Excel (.xlsx)</span>
							{/if}
						</div>
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="csv">
							<div class="flex items-center gap-2">
								<FileText class="h-4 w-4" />
								<span>CSV (.csv)</span>
							</div>
						</Select.Item>
						<Select.Item value="xlsx">
							<div class="flex items-center gap-2">
								<FileSpreadsheet class="h-4 w-4" />
								<span>Excel (.xlsx)</span>
							</div>
						</Select.Item>
					</Select.Content>
				</Select.Root>
			</div>
		</div>

		<!-- Configuration Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List class="grid grid-cols-3">
				<Tabs.Trigger value="fields" class="gap-2">
					<GripVertical class="h-4 w-4" />
					Fields
					<Badge variant="secondary" class="text-xs">
						{selectedFields.length}
					</Badge>
				</Tabs.Trigger>
				<Tabs.Trigger value="filters" class="gap-2">
					<Filter class="h-4 w-4" />
					Filters
					{#if filters.length > 0}
						<Badge variant="secondary" class="text-xs">
							{filters.length}
						</Badge>
					{/if}
				</Tabs.Trigger>
				<Tabs.Trigger value="options" class="gap-2">
					<Settings class="h-4 w-4" />
					Options
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="fields" class="mt-4">
				<ExportFieldSelector
					fields={exportableFields}
					bind:selectedFields
					onselectall={selectAllFields}
					onclearall={clearAllFields}
				/>
			</Tabs.Content>

			<Tabs.Content value="filters" class="mt-4">
				<ExportFilterBuilder
					fields={moduleFields}
					bind:filters
				/>
			</Tabs.Content>

			<Tabs.Content value="options" class="mt-4">
				<div class="space-y-4">
					<div class="flex items-center justify-between">
						<div class="space-y-0.5">
							<Label>Include Headers</Label>
							<p class="text-sm text-muted-foreground">
								Add column headers as the first row
							</p>
						</div>
						<Checkbox
							checked={options.include_headers}
							onCheckedChange={(checked) => {
								options = { ...options, include_headers: !!checked };
							}}
						/>
					</div>

					<div class="space-y-2">
						<Label>Date Format</Label>
						<Select.Root
							type="single"
							value={options.date_format || 'Y-m-d'}
							onValueChange={(v) => {
								if (v) options = { ...options, date_format: v };
							}}
						>
							<Select.Trigger>
								{options.date_format === 'Y-m-d'
									? '2024-12-05 (ISO)'
									: options.date_format === 'm/d/Y'
										? '12/05/2024 (US)'
										: options.date_format === 'd/m/Y'
											? '05/12/2024 (EU)'
											: options.date_format}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="Y-m-d">2024-12-05 (ISO)</Select.Item>
								<Select.Item value="m/d/Y">12/05/2024 (US)</Select.Item>
								<Select.Item value="d/m/Y">05/12/2024 (EU)</Select.Item>
								<Select.Item value="d M Y">05 Dec 2024</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>
				</div>
			</Tabs.Content>
		</Tabs.Root>

		<!-- Summary -->
		<div class="flex items-center justify-between p-4 bg-muted/50 rounded-lg">
			<div class="text-sm">
				<span class="text-muted-foreground">Exporting </span>
				<span class="font-medium">{selectedFields.length} fields</span>
				<span class="text-muted-foreground"> from </span>
				<span class="font-medium">{estimatedRecords.toLocaleString()} records</span>
				{#if filters.length > 0}
					<span class="text-muted-foreground"> with </span>
					<span class="font-medium">{filters.length} filter(s)</span>
				{/if}
			</div>
			<Badge variant="outline">
				{fileType.toUpperCase()}
			</Badge>
		</div>
	</Card.Content>

	<Card.Footer class="flex justify-between">
		<Button variant="ghost" onclick={handleCancel}>
			Cancel
		</Button>
		<Button
			onclick={handleExport}
			disabled={isExporting || selectedFields.length === 0}
		>
			{#if isExporting}
				<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				Starting Export...
			{:else}
				<Download class="mr-2 h-4 w-4" />
				Export Records
			{/if}
		</Button>
	</Card.Footer>
</Card.Root>
