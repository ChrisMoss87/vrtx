<script lang="ts">
	import type {
		KanbanData,
		KanbanRecord,
		KanbanField,
		KanbanConfig,
		KanbanColumn,
		KanbanColumnSettings
	} from '$lib/api/views';
	import type { KanbanCardConfig, CardStyle } from '$lib/types/kanban-card-config';
	import { mergeCardStyles } from '$lib/types/kanban-card-config';
	import { getKanbanData, moveKanbanRecord, getKanbanFields } from '$lib/api/views';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import { Spinner } from '$lib/components/ui/spinner';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import * as Card from '$lib/components/ui/card';
	import { toast } from 'svelte-sonner';
	import { cn } from '$lib/utils';
	import { formatFieldValue, truncateText } from '$lib/utils/field-formatters';
	import {
		Search,
		RefreshCw,
		LayoutGrid,
		Settings2,
		Eye,
		EyeOff,
		ChevronDown,
		ChevronRight,
		Palette,
		GripVertical,
		AlertCircle
	} from 'lucide-svelte';

	// Minimal view props needed for kanban
	interface KanbanViewConfig {
		id?: number;
		name?: string;
		view_type?: 'kanban' | 'table';
		kanban_config?: KanbanConfig;
	}

	interface Props {
		moduleApiName: string;
		view?: KanbanViewConfig;
		moduleSettings?: Record<string, unknown>;
		onRecordClick?: (record: KanbanRecord) => void;
		onFieldChange?: (fieldApiName: string) => void;
		onConfigChange?: (config: KanbanConfig) => void;
		class?: string;
	}

	let {
		moduleApiName,
		view,
		moduleSettings,
		onRecordClick,
		onFieldChange,
		onConfigChange,
		class: className
	}: Props = $props();

	let loading = $state(false);
	let loadingFields = $state(true);
	let kanbanData = $state<KanbanData | null>(null);
	let kanbanFields = $state<KanbanField[]>([]);
	let selectedField = $state<string | null>(null);
	let searchTerm = $state('');
	let searchTimeout: ReturnType<typeof setTimeout> | null = null;

	// Local config state (extends view config)
	let localConfig = $state<Partial<KanbanConfig>>({});

	// Drag and drop state
	let draggedRecord = $state<KanbanRecord | null>(null);
	let draggedFromColumnId = $state<string | null>(null);
	let dragOverColumnId = $state<string | null>(null);

	// Settings popover state
	let settingsOpen = $state(false);

	// Use selected field or config field
	const groupByField = $derived(selectedField || view?.kanban_config?.group_by_field);
	const valueField = $derived(view?.kanban_config?.value_field);
	const titleField = $derived(view?.kanban_config?.title_field ?? 'name');
	const subtitleField = $derived(view?.kanban_config?.subtitle_field);

	// Card fields: use view config first, then fall back to module settings
	const cardFields = $derived.by(() => {
		if (view?.kanban_config?.card_fields && view.kanban_config.card_fields.length > 0) {
			return view.kanban_config.card_fields;
		}
		// Fall back to module settings
		if (moduleSettings?.kanban_card_fields && Array.isArray(moduleSettings.kanban_card_fields)) {
			return moduleSettings.kanban_card_fields as string[];
		}
		return [];
	});

	// Card config from module settings
	const cardConfig = $derived.by(() => {
		if (moduleSettings?.kanban_card_config) {
			return moduleSettings.kanban_card_config as KanbanCardConfig;
		}
		return null;
	});

	// Get card style for a specific record based on its field value
	function getCardStyle(record: KanbanRecord, columnId: string, columnColor?: string): CardStyle {
		const defaultFallback: CardStyle = {
			backgroundColor: '#ffffff',
			borderColor: '#e5e7eb',
			accentColor: columnColor || '#3b82f6', // Use column color as default accent
			accentWidth: 3,
			titleColor: '#111827',
			subtitleColor: '#6b7280',
			textColor: '#374151'
		};

		if (!cardConfig) {
			return defaultFallback;
		}

		const defaultStyle = cardConfig.default;
		const override = cardConfig.fieldOverrides?.[columnId];

		// If there's an override, use it. Otherwise use default style with column color as accent fallback
		if (override) {
			return mergeCardStyles(defaultStyle, override);
		}

		// Use default style but apply column color as accent if no specific accent is set
		return {
			...defaultStyle,
			accentColor: defaultStyle.accentColor || columnColor || '#3b82f6'
		};
	}

	// Get field value for display
	function getCardFieldValue(record: KanbanRecord, field: any): string {
		const fieldApiName = typeof field === 'string' ? field : field.fieldApiName;
		const value = record.data[fieldApiName];
		if (value === undefined || value === null || value === '') return '';
		return formatFieldValue(value, 'text');
	}

	// Merge view config with local config
	const effectiveConfig = $derived<Partial<KanbanConfig>>({
		...view?.kanban_config,
		...localConfig,
		column_settings: {
			...(view?.kanban_config?.column_settings || {}),
			...(localConfig.column_settings || {})
		}
	});

	// Get hidden columns from config
	const hiddenColumns = $derived<Set<string>>(
		new Set(effectiveConfig.hidden_columns || [])
	);

	// Get collapsed columns from config
	const collapsedColumns = $derived<Set<string>>(
		new Set(effectiveConfig.collapsed_columns || [])
	);

	// Process and filter columns
	const processedColumns = $derived.by(() => {
		if (!kanbanData) return [] as KanbanColumn[];

		// Apply column order if specified
		let columns = [...kanbanData.columns];
		const columnOrder = effectiveConfig.column_order;

		if (columnOrder && columnOrder.length > 0) {
			columns.sort((a, b) => {
				const aIndex = columnOrder.indexOf(a.id);
				const bIndex = columnOrder.indexOf(b.id);
				// Put items not in order at the end
				if (aIndex === -1 && bIndex === -1) return a.display_order - b.display_order;
				if (aIndex === -1) return 1;
				if (bIndex === -1) return -1;
				return aIndex - bIndex;
			});
		}

		// Apply column settings (colors, wip limits)
		return columns.map((col): KanbanColumn => {
			const settings = effectiveConfig.column_settings?.[col.id];
			return {
				...col,
				color: settings?.color || col.color,
				hidden: hiddenColumns.has(col.id),
				collapsed: collapsedColumns.has(col.id),
				wip_limit: settings?.wip_limit
			};
		});
	});

	// Visible columns (not hidden)
	const visibleColumns = $derived(
		processedColumns.filter((col) => !col.hidden)
	);

	// All columns for settings (including hidden)
	const allColumns = $derived(processedColumns);

	// Color palette for columns
	const colorPalette = [
		'#6b7280', // gray
		'#ef4444', // red
		'#f97316', // orange
		'#f59e0b', // amber
		'#eab308', // yellow
		'#84cc16', // lime
		'#22c55e', // green
		'#10b981', // emerald
		'#14b8a6', // teal
		'#06b6d4', // cyan
		'#0ea5e9', // sky
		'#3b82f6', // blue
		'#6366f1', // indigo
		'#8b5cf6', // violet
		'#a855f7', // purple
		'#d946ef', // fuchsia
		'#ec4899', // pink
		'#f43f5e' // rose
	];

	async function loadData(field?: string | null) {
		const fieldToUse = field ?? groupByField;
		// Ensure we have a valid field before making API call
		if (!fieldToUse || fieldToUse.trim() === '') {
			return;
		}

		loading = true;
		try {
			const viewId = view?.id || 0;
			kanbanData = await getKanbanData(moduleApiName, viewId, searchTerm || undefined, fieldToUse);
		} catch (error) {
			console.error('Failed to load kanban data:', error);
			toast.error('Failed to load kanban data');
		} finally {
			loading = false;
		}
	}

	async function loadKanbanFields() {
		loadingFields = true;
		try {
			const fields = await getKanbanFields(moduleApiName);
			// Deduplicate options by label (keep first occurrence)
			kanbanFields = fields.map((field) => {
				const seenLabels = new Set<string>();
				const uniqueOptions = field.options.filter((opt) => {
					const key = opt.label.toLowerCase();
					if (seenLabels.has(key)) return false;
					seenLabels.add(key);
					return true;
				});
				return { ...field, options: uniqueOptions };
			});

			// Auto-select first field if none selected
			// The $effect will automatically trigger loadData when selectedField changes
			if (kanbanFields.length > 0 && !selectedField && !view?.kanban_config?.group_by_field) {
				selectedField = kanbanFields[0].api_name;
			}
		} catch (error) {
			console.error('Failed to load kanban fields:', error);
		} finally {
			loadingFields = false;
		}
	}

	function handleSearch(e: Event) {
		const target = e.target as HTMLInputElement;
		if (searchTimeout) clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			searchTerm = target.value;
			loadData();
		}, 300);
	}

	function handleFieldChange(value: string | undefined) {
		if (value && value !== groupByField) {
			selectedField = value;
			// Reset local config when field changes
			localConfig = {};
			onFieldChange?.(value);
		}
	}

	function toggleColumnVisibility(columnId: string) {
		const currentHidden = new Set(effectiveConfig.hidden_columns || []);
		if (currentHidden.has(columnId)) {
			currentHidden.delete(columnId);
		} else {
			currentHidden.add(columnId);
		}
		localConfig = {
			...localConfig,
			hidden_columns: Array.from(currentHidden)
		};
		onConfigChange?.({
			...effectiveConfig,
			hidden_columns: Array.from(currentHidden)
		} as KanbanConfig);
	}

	function toggleColumnCollapsed(columnId: string) {
		const currentCollapsed = new Set(effectiveConfig.collapsed_columns || []);
		if (currentCollapsed.has(columnId)) {
			currentCollapsed.delete(columnId);
		} else {
			currentCollapsed.add(columnId);
		}
		localConfig = {
			...localConfig,
			collapsed_columns: Array.from(currentCollapsed)
		};
		onConfigChange?.({
			...effectiveConfig,
			collapsed_columns: Array.from(currentCollapsed)
		} as KanbanConfig);
	}

	function setColumnColor(columnId: string, color: string) {
		const currentSettings = { ...(effectiveConfig.column_settings || {}) };
		currentSettings[columnId] = {
			...(currentSettings[columnId] || {}),
			color
		};
		localConfig = {
			...localConfig,
			column_settings: currentSettings
		};
		onConfigChange?.({
			...effectiveConfig,
			column_settings: currentSettings
		} as KanbanConfig);
	}

	function setColumnWipLimit(columnId: string, limit: number | undefined) {
		const currentSettings = { ...(effectiveConfig.column_settings || {}) };
		currentSettings[columnId] = {
			...(currentSettings[columnId] || {}),
			wip_limit: limit
		};
		localConfig = {
			...localConfig,
			column_settings: currentSettings
		};
		onConfigChange?.({
			...effectiveConfig,
			column_settings: currentSettings
		} as KanbanConfig);
	}

	function showAllColumns() {
		localConfig = {
			...localConfig,
			hidden_columns: []
		};
		onConfigChange?.({
			...effectiveConfig,
			hidden_columns: []
		} as KanbanConfig);
	}

	// Drag and drop handlers
	function handleDragStart(record: KanbanRecord, columnId: string) {
		draggedRecord = record;
		draggedFromColumnId = columnId;
	}

	function handleDragEnd() {
		draggedRecord = null;
		draggedFromColumnId = null;
		dragOverColumnId = null;
	}

	function handleDragOver(e: DragEvent, columnId: string) {
		e.preventDefault();
		if (draggedRecord && columnId !== draggedFromColumnId) {
			dragOverColumnId = columnId;
		}
	}

	function handleDragLeave() {
		dragOverColumnId = null;
	}

	async function handleDrop(toColumnId: string) {
		if (!draggedRecord || !kanbanData || draggedFromColumnId === toColumnId) {
			handleDragEnd();
			return;
		}

		const record = draggedRecord;
		const fromColumnId = draggedFromColumnId;

		// Check WIP limit
		const targetColumn = visibleColumns.find((c) => c.id === toColumnId);
		if (targetColumn?.wip_limit && targetColumn.count >= targetColumn.wip_limit) {
			toast.error(`Column "${targetColumn.name}" has reached its WIP limit of ${targetColumn.wip_limit}`);
			handleDragEnd();
			return;
		}

		// Optimistic UI update
		const updatedColumns = kanbanData.columns.map((col) => {
			if (col.id === fromColumnId) {
				return {
					...col,
					records: col.records.filter((r) => r.id !== record.id),
					count: col.count - 1,
					total: col.total - (record.value ?? 0)
				};
			}
			if (col.id === toColumnId) {
				return {
					...col,
					records: [...col.records, record],
					count: col.count + 1,
					total: col.total + (record.value ?? 0)
				};
			}
			return col;
		});

		kanbanData = { ...kanbanData, columns: updatedColumns };
		handleDragEnd();

		try {
			const viewId = view?.id || 0;
			await moveKanbanRecord(moduleApiName, viewId, record.id, toColumnId, groupByField || undefined);
			toast.success('Record moved');
		} catch (error) {
			console.error('Failed to move record:', error);
			toast.error('Failed to move record');
			loadData();
		}
	}

	function formatCurrency(value: number): string {
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	// Load kanban fields when moduleApiName changes
	$effect(() => {
		const _module = moduleApiName;
		loadKanbanFields();
	});

	// Load data when selectedField or view config changes
	$effect(() => {
		// Track all dependencies explicitly by reading them
		const currentSelectedField = selectedField;
		const configField = view?.kanban_config?.group_by_field;
		const _module = moduleApiName;

		const field = currentSelectedField || configField;
		if (field) {
			loadData(field);
		}
	});

	export function refresh() {
		loadData();
	}
</script>

<div class={cn('flex h-full flex-col', className)}>
	<!-- Toolbar -->
	<div class="mb-4 flex items-center justify-between gap-4">
		<div class="flex items-center gap-2">
			<!-- Field selector -->
			{#if kanbanFields.length > 0}
				<Select.Root type="single" value={groupByField} onValueChange={handleFieldChange}>
					<Select.Trigger class="w-[200px]">
						<LayoutGrid class="mr-2 h-4 w-4" />
						<span>
							{kanbanFields.find((f) => f.api_name === groupByField)?.label ?? 'Select field'}
						</span>
					</Select.Trigger>
					<Select.Content>
						{#each kanbanFields as field}
							<Select.Item value={field.api_name}>
								{field.label}
								<span class="ml-2 text-xs text-muted-foreground">
									({field.options.length} options)
								</span>
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			{/if}

			<!-- Search -->
			<div class="relative">
				<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					type="text"
					placeholder="Search records..."
					class="w-64 pl-9"
					value={searchTerm}
					oninput={handleSearch}
				/>
			</div>
		</div>

		<div class="flex items-center gap-2">
			{#if kanbanData}
				<span class="text-sm text-muted-foreground">
					{kanbanData.columns.reduce((sum, c) => sum + c.count, 0)} records
					{#if valueField}
						| Total: {formatCurrency(kanbanData.columns.reduce((sum, c) => sum + c.total, 0))}
					{/if}
				</span>
			{/if}

			<!-- Column settings -->
			<Popover.Root bind:open={settingsOpen}>
				<Popover.Trigger>
					{#snippet child({ props })}
						<Button {...props} variant="outline" size="sm">
							<Settings2 class="mr-2 h-4 w-4" />
							Columns
							{#if hiddenColumns.size > 0}
								<Badge variant="secondary" class="ml-2">{hiddenColumns.size} hidden</Badge>
							{/if}
						</Button>
					{/snippet}
				</Popover.Trigger>
				<Popover.Content class="w-80" align="end">
					<div class="space-y-4">
						<div class="flex items-center justify-between">
							<h4 class="font-medium">Column Settings</h4>
							{#if hiddenColumns.size > 0}
								<Button variant="ghost" size="sm" onclick={showAllColumns}>Show All</Button>
							{/if}
						</div>

						<ScrollArea class="h-[300px]">
							<div class="space-y-2 pr-4">
								{#each allColumns as column (column.id)}
									<div
										class="flex items-center justify-between rounded-lg border p-2 {column.hidden
											? 'opacity-50'
											: ''}"
									>
										<div class="flex items-center gap-2">
											<!-- Color picker -->
											<Popover.Root>
												<Popover.Trigger>
													{#snippet child({ props })}
														<button
															{...props}
															class="h-5 w-5 rounded border"
															style="background-color: {column.color}"
														></button>
													{/snippet}
												</Popover.Trigger>
												<Popover.Content class="w-auto p-2" align="start">
													<div class="grid grid-cols-6 gap-1">
														{#each colorPalette as color}
															<button
																class="h-6 w-6 rounded border hover:scale-110 transition-transform {column.color ===
																color
																	? 'ring-2 ring-primary ring-offset-1'
																	: ''}"
																style="background-color: {color}"
																onclick={() => setColumnColor(column.id, color)}
															></button>
														{/each}
													</div>
												</Popover.Content>
											</Popover.Root>

											<span class="text-sm font-medium">{column.name}</span>
											<Badge variant="outline" class="text-xs">{column.count}</Badge>
										</div>

										<div class="flex items-center gap-1">
											<!-- WIP Limit dropdown -->
											<DropdownMenu.Root>
												<DropdownMenu.Trigger>
													{#snippet child({ props })}
														<Button {...props} variant="ghost" size="icon" class="h-7 w-7">
															{#if column.wip_limit}
																<span class="text-xs font-medium">{column.wip_limit}</span>
															{:else}
																<AlertCircle class="h-3 w-3 text-muted-foreground" />
															{/if}
														</Button>
													{/snippet}
												</DropdownMenu.Trigger>
												<DropdownMenu.Content align="end">
													<DropdownMenu.Label>WIP Limit</DropdownMenu.Label>
													<DropdownMenu.Separator />
													<DropdownMenu.Item onclick={() => setColumnWipLimit(column.id, undefined)}>
														No limit
													</DropdownMenu.Item>
													{#each [3, 5, 10, 15, 20] as limit}
														<DropdownMenu.Item onclick={() => setColumnWipLimit(column.id, limit)}>
															{limit} items
														</DropdownMenu.Item>
													{/each}
												</DropdownMenu.Content>
											</DropdownMenu.Root>

											<!-- Visibility toggle -->
											<Button
												variant="ghost"
												size="icon"
												class="h-7 w-7"
												onclick={() => toggleColumnVisibility(column.id)}
											>
												{#if column.hidden}
													<EyeOff class="h-4 w-4" />
												{:else}
													<Eye class="h-4 w-4" />
												{/if}
											</Button>
										</div>
									</div>
								{/each}
							</div>
						</ScrollArea>
					</div>
				</Popover.Content>
			</Popover.Root>

			<Button variant="outline" size="icon" onclick={() => loadData()} disabled={loading}>
				<RefreshCw class="h-4 w-4 {loading ? 'animate-spin' : ''}" />
			</Button>
		</div>
	</div>

	<!-- Kanban Board -->
	{#if loadingFields}
		<div class="flex flex-1 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if kanbanFields.length === 0}
		<div class="flex flex-1 items-center justify-center text-muted-foreground">
			No fields with options available for kanban view
		</div>
	{:else if loading && !kanbanData}
		<div class="flex flex-1 items-center justify-center">
			<Spinner class="h-8 w-8" />
		</div>
	{:else if !kanbanData || !groupByField}
		<div class="flex flex-1 items-center justify-center text-muted-foreground">
			Select a field to group records by
		</div>
	{:else}
		<div class="flex flex-1 gap-3 overflow-x-auto pb-4">
			{#each visibleColumns as column (column.id)}
				{@const isOverWipLimit = column.wip_limit && column.count > column.wip_limit}
				{@const isAtWipLimit = column.wip_limit && column.count === column.wip_limit}
				<div
					class={cn(
						'flex flex-shrink-0 flex-col rounded-lg border bg-muted/30 transition-all duration-200',
						column.collapsed ? 'w-14' : 'w-72'
					)}
					ondragover={(e) => handleDragOver(e, column.id)}
					ondragleave={handleDragLeave}
					ondrop={() => handleDrop(column.id)}
					role="region"
					aria-label="{column.name} column"
				>
					<!-- Column Header -->
					<div
						class={cn(
							'flex items-center border-b p-3',
							column.collapsed ? 'flex-col gap-2 p-2' : 'justify-between'
						)}
						style="border-left: 4px solid {column.color}"
					>
						{#if column.collapsed}
							<!-- Collapsed header -->
							<Button
								variant="ghost"
								size="icon"
								class="h-6 w-6 shrink-0"
								onclick={() => toggleColumnCollapsed(column.id)}
							>
								<ChevronRight class="h-4 w-4" />
							</Button>
							<div class="flex-1 flex flex-col items-center justify-center min-h-0">
								<span
									class="[writing-mode:vertical-rl] rotate-180 font-medium text-sm whitespace-nowrap max-h-[200px] overflow-hidden text-ellipsis"
								>
									{column.name}
								</span>
							</div>
							<Badge
								variant={isOverWipLimit ? 'destructive' : isAtWipLimit ? 'secondary' : 'outline'}
								class="px-1.5 shrink-0"
							>
								{column.count}
							</Badge>
						{:else}
							<!-- Expanded header -->
							<div class="flex items-center gap-2">
								<Button
									variant="ghost"
									size="icon"
									class="h-6 w-6 -ml-1"
									onclick={() => toggleColumnCollapsed(column.id)}
								>
									<ChevronDown class="h-4 w-4" />
								</Button>
								<span class="font-medium">{column.name}</span>
								<Badge
									variant={isOverWipLimit ? 'destructive' : isAtWipLimit ? 'secondary' : 'outline'}
									class="text-xs"
								>
									{column.count}
									{#if column.wip_limit}
										/ {column.wip_limit}
									{/if}
								</Badge>
							</div>
							<div class="flex items-center gap-2">
								{#if valueField && column.total > 0}
									<span class="text-sm text-muted-foreground">
										{formatCurrency(column.total)}
									</span>
								{/if}
							</div>
						{/if}
					</div>

					<!-- Cards (hidden when collapsed) -->
					{#if !column.collapsed}
						<ScrollArea class="flex-1 p-2">
							<div
								class="space-y-2 min-h-[100px] rounded transition-colors {dragOverColumnId ===
								column.id
									? 'bg-primary/10'
									: ''}"
							>
								{#each column.records as record (record.id)}
									{@const cardStyle = getCardStyle(record, column.id, column.color)}
									{@const layout = cardConfig?.layout}
									<div
										class={cn(
											'rounded-lg cursor-pointer transition-all hover:shadow-md',
											draggedRecord?.id === record.id && 'opacity-50 scale-95'
										)}
										style="background-color: {cardStyle.backgroundColor || '#ffffff'};
											   border: 1px solid {cardStyle.borderColor || '#e5e7eb'};
											   border-left: {cardStyle.accentWidth || 3}px solid {cardStyle.accentColor || '#3b82f6'};"
										draggable="true"
										ondragstart={() => handleDragStart(record, column.id)}
										ondragend={handleDragEnd}
										onclick={() => onRecordClick?.(record)}
										onkeypress={(e) => e.key === 'Enter' && onRecordClick?.(record)}
										role="button"
										tabindex={0}
									>
										<div class="p-4 space-y-2">
											{#if layout && layout.fields && layout.fields.length > 0}
												<!-- Render fields based on configured layout -->
												{#each layout.fields as field}
													{@const value = getCardFieldValue(record, field)}
													{#if value}
														{#if field.displayAs === 'title'}
															<h4
																class="font-semibold text-base line-clamp-2"
																style="color: {cardStyle.titleColor || '#111827'}"
															>
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs font-medium text-muted-foreground">
																		{field.fieldApiName}:
																	</span>
																{/if}
																{value}
															</h4>
														{:else if field.displayAs === 'subtitle'}
															<p
																class="text-sm line-clamp-1"
																style="color: {cardStyle.subtitleColor || '#6b7280'}"
															>
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs font-medium">{field.fieldApiName}:</span>
																{/if}
																{value}
															</p>
														{:else if field.displayAs === 'badge'}
															<div class="flex items-center gap-2">
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs font-medium" style="color: {cardStyle.textColor || '#374151'}">
																		{field.fieldApiName}:
																	</span>
																{/if}
																<Badge variant="outline" class="text-xs">{value}</Badge>
															</div>
														{:else if field.displayAs === 'value'}
															<div class="flex items-center gap-2">
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs font-medium" style="color: {cardStyle.textColor || '#374151'}">
																		{field.fieldApiName}:
																	</span>
																{/if}
																<span class="text-lg font-bold text-primary">{value}</span>
															</div>
														{:else if field.displayAs === 'text'}
															<div class="flex items-center gap-2">
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs font-medium" style="color: {cardStyle.textColor || '#374151'}">
																		{field.fieldApiName}:
																	</span>
																{/if}
																<span class="text-sm" style="color: {cardStyle.textColor || '#374151'}">{value}</span>
															</div>
														{:else if field.displayAs === 'small'}
															<div class="flex items-center gap-2">
																{#if field.showLabel || layout.showFieldLabels}
																	<span class="text-xs text-muted-foreground">{field.fieldApiName}:</span>
																{/if}
																<span class="text-xs text-muted-foreground">{value}</span>
															</div>
														{/if}
													{/if}
												{/each}
											{:else}
												<!-- Fallback to default rendering if no layout configured -->
												<h4
													class="font-semibold text-sm line-clamp-2 mb-1"
													style="color: {cardStyle.titleColor || '#111827'}"
												>
													{truncateText(record.title, 60)}
												</h4>

												{#if subtitleField && record.data[subtitleField]}
													<p class="text-xs mb-2" style="color: {cardStyle.subtitleColor || '#6b7280'}">
														{truncateText(String(record.data[subtitleField]), 50)}
													</p>
												{/if}

												{#if groupByField && record.data[groupByField]}
													<div class="flex items-center gap-2 mb-2">
														<Badge variant="outline" class="text-xs">
															{record.data[groupByField]}
														</Badge>
													</div>
												{/if}

												{#if record.value !== undefined}
													<div class="mb-2">
														<p class="text-lg font-bold text-primary">
															{formatCurrency(record.value)}
														</p>
													</div>
												{/if}

												{#if cardFields.length > 0}
													<div class="space-y-1.5 mt-2 pt-2 border-t" style="border-color: {cardStyle.borderColor || '#e5e7eb'}">
														{#each cardFields as fieldName}
															{#if record.data[fieldName] !== undefined && record.data[fieldName] !== null && record.data[fieldName] !== ''}
																{@const fieldValue = record.data[fieldName]}
																{@const fieldType = kanbanFields
																	.flatMap(f => f.options)
																	.find(opt => opt.value === fieldName)?.label || 'text'}
																<div class="flex items-start gap-2">
																	<span
																		class="text-xs min-w-0 flex-1 truncate"
																		style="color: {cardStyle.textColor || '#374151'}"
																	>
																		{truncateText(formatFieldValue(fieldValue, fieldType), 100)}
																	</span>
																</div>
															{/if}
														{/each}
													</div>
												{/if}
											{/if}
										</div>
									</div>
								{:else}
									<div
										class="flex items-center justify-center h-20 text-sm text-muted-foreground"
									>
										No records
									</div>
								{/each}
							</div>
						</ScrollArea>
					{/if}
				</div>
			{/each}

			<!-- Hidden columns indicator -->
			{#if hiddenColumns.size > 0}
				<div class="flex w-48 flex-shrink-0 flex-col items-center justify-center rounded-lg border border-dashed bg-muted/20 p-4">
					<EyeOff class="h-8 w-8 text-muted-foreground mb-2" />
					<p class="text-sm text-muted-foreground text-center">
						{hiddenColumns.size} column{hiddenColumns.size > 1 ? 's' : ''} hidden
					</p>
					<Button variant="link" size="sm" onclick={showAllColumns}>Show All</Button>
				</div>
			{/if}
		</div>
	{/if}
</div>
