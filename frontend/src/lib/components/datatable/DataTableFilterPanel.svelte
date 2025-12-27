<script lang="ts">
	import { getContext } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import * as Popover from '$lib/components/ui/popover';
	import { Calendar } from '$lib/components/ui/calendar';
	import { X, RotateCcw, Loader2, CalendarIcon, CalendarRange } from 'lucide-svelte';
	import type { ColumnDef, TableContext, FilterConfig } from './types';
	import { cn } from '$lib/utils';
	import { slide } from 'svelte/transition';
	import {
		DateFormatter,
		type DateValue,
		parseDate,
		getLocalTimeZone
	} from '@internationalized/date';
	import axios from 'axios';

	interface UserRecord {
		id: number;
		name: string;
		email?: string;
	}

	interface Props {
		open?: boolean;
		onClose?: () => void;
	}

	let { open = false, onClose }: Props = $props();

	const table = getContext<TableContext>('table');
	const df = new DateFormatter('en-US', { dateStyle: 'medium' });

	// Track date range picker open states
	let dateRangeOpen = $state<Record<string, boolean>>({});
	let datePickerOpen1 = $state<Record<string, boolean>>({});
	let datePickerOpen2 = $state<Record<string, boolean>>({});

	// Format date for display
	function formatDate(date: DateValue | undefined): string {
		if (!date) return '';
		return df.format(date.toDate(getLocalTimeZone()));
	}

	// Parse date string to DateValue safely
	function parseDateSafe(dateStr: string | undefined): DateValue | undefined {
		if (!dateStr) return undefined;
		try {
			return parseDate(dateStr);
		} catch {
			return undefined;
		}
	}

	// Get all filterable columns
	const filterableColumns = $derived(table.columns.filter((col) => col.filterable !== false));

	// Track pending filter values (not applied yet)
	let pendingFilters = $state<Record<string, any>>({});

	// Users for lookup filters
	let users = $state<UserRecord[]>([]);
	let usersLoading = $state(false);

	// Date presets matching DateFilter.svelte (what all major CRMs use)
	const datePresets = [
		{ value: 'today', label: 'Today' },
		{ value: 'yesterday', label: 'Yesterday' },
		{ value: 'last_7_days', label: 'Last 7 days' },
		{ value: 'last_30_days', label: 'Last 30 days' },
		{ value: 'this_week', label: 'This week' },
		{ value: 'last_week', label: 'Last week' },
		{ value: 'this_month', label: 'This month' },
		{ value: 'last_month', label: 'Last month' },
		{ value: 'this_quarter', label: 'This quarter' },
		{ value: 'last_quarter', label: 'Last quarter' },
		{ value: 'between', label: 'Custom Range...' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Has value' }
	];

	// Get display text for date filter
	function getDateDisplayText(columnId: string, filter: any): string {
		if (!filter?.operator) return 'Any';
		if (filter.operator === 'between' && Array.isArray(filter.value) && filter.value.length === 2) {
			const d1 = parseDateSafe(filter.value[0]);
			const d2 = parseDateSafe(filter.value[1]);
			if (d1 && d2) {
				return `${formatDate(d1)} - ${formatDate(d2)}`;
			}
			return 'Select range...';
		}
		return datePresets.find(p => p.value === filter.operator)?.label || 'Any';
	}

	// Fetch users for user lookup filters
	async function fetchUsers() {
		if (users.length > 0 || usersLoading) return;
		usersLoading = true;
		try {
			const token = localStorage.getItem('auth_token');
			const response = await axios.get('/api/v1/users', {
				headers: token ? { Authorization: `Bearer ${token}` } : {}
			});
			users = response.data.data || response.data || [];
		} catch (error) {
			console.error('Failed to fetch users:', error);
			users = [];
		} finally {
			usersLoading = false;
		}
	}

	// Check if column is a user lookup
	function isUserLookup(column: ColumnDef): boolean {
		const lookupTarget = column.meta?.lookupModule || column.meta?.lookup_module || '';
		const fieldName = column.id.toLowerCase();
		return (
			column.type === 'user' ||
			lookupTarget === 'users' ||
			fieldName.includes('assigned') ||
			fieldName.includes('owner') ||
			fieldName.includes('user_id') ||
			fieldName.includes('created_by') ||
			fieldName.includes('updated_by')
		);
	}

	// Sync pending filters when panel opens
	$effect(() => {
		if (open) {
			// Initialize pending filters from current table state
			const newPending: Record<string, any> = {};
			table.state.filters.forEach((filter) => {
				newPending[filter.field] = {
					operator: filter.operator,
					value: filter.value
				};
			});
			pendingFilters = newPending;

			// Fetch users if we have user lookup columns
			const hasUserLookup = filterableColumns.some(col =>
				col.type === 'lookup' && isUserLookup(col) || col.type === 'user'
			);
			if (hasUserLookup) {
				fetchUsers();
			}
		}
	});

	// Get default operator for a column type
	function getDefaultOperator(columnType: string): string {
		switch (columnType) {
			case 'text':
			case 'email':
			case 'phone':
			case 'url':
			case 'textarea':
				return 'contains';
			case 'select':
			case 'multiselect':
			case 'radio':
			case 'tags':
				return 'in';
			case 'boolean':
			case 'checkbox':
			case 'toggle':
				return 'equals';
			case 'number':
			case 'decimal':
			case 'currency':
			case 'percent':
				return 'equals';
			case 'date':
			case 'datetime':
			case 'time':
				return 'today';
			case 'lookup':
			case 'user':
				return 'in';
			default:
				return 'equals';
		}
	}

	// Update a pending filter value
	function updatePendingFilter(columnId: string, value: any, operator?: string) {
		const column = filterableColumns.find((col) => col.id === columnId);
		if (!column) return;

		const currentOperator = operator || pendingFilters[columnId]?.operator || getDefaultOperator(column.type || 'text');

		// Handle empty values - remove from pending
		if (value === '' || value === null || value === undefined || (Array.isArray(value) && value.length === 0)) {
			const { [columnId]: _, ...rest } = pendingFilters;
			pendingFilters = rest;
			return;
		}

		pendingFilters = {
			...pendingFilters,
			[columnId]: {
				operator: currentOperator,
				value: value
			}
		};
	}

	// Clear a single pending filter
	function clearPendingFilter(columnId: string) {
		const { [columnId]: _, ...rest } = pendingFilters;
		pendingFilters = rest;
	}

	// Clear all pending filters
	function clearAllPending() {
		pendingFilters = {};
	}

	// Apply all pending filters
	function applyFilters() {
		// Build all filters at once
		const filters = Object.entries(pendingFilters)
			.filter(([field, filterData]) => {
				if (!filterData) return false;
				const column = filterableColumns.find(c => c.id === field);
				const isDateField = column?.type === 'date' || column?.type === 'datetime' || column?.type === 'time';

				if (isDateField) {
					// For date fields, check operator is set
					if (!filterData.operator) return false;
					// For 'between', also need both dates
					if (filterData.operator === 'between') {
						return Array.isArray(filterData.value) &&
							filterData.value.length === 2 &&
							filterData.value[0] && filterData.value[1];
					}
					// For other date presets, operator is enough
					return true;
				}
				// For other fields, value must be set
				return filterData.value !== undefined && filterData.value !== '' &&
					(!Array.isArray(filterData.value) || filterData.value.length > 0);
			})
			.map(([field, filterData]) => ({
				field,
				operator: filterData.operator,
				value: filterData.value
			}));

		// Set all filters in one call (triggers single API request)
		table.setFilters(filters);

		onClose?.();
	}

	// Reset to current applied filters
	function resetFilters() {
		const newPending: Record<string, any> = {};
		table.state.filters.forEach((filter) => {
			newPending[filter.field] = {
				operator: filter.operator,
				value: filter.value
			};
		});
		pendingFilters = newPending;
	}

	// Count pending changes
	const pendingCount = $derived(Object.keys(pendingFilters).length);
	const appliedCount = $derived(table.state.filters.length);
	const hasChanges = $derived(() => {
		const pendingKeys = Object.keys(pendingFilters);
		const appliedKeys = table.state.filters.map(f => f.field);

		if (pendingKeys.length !== appliedKeys.length) return true;

		for (const key of pendingKeys) {
			const applied = table.state.filters.find(f => f.field === key);
			if (!applied) return true;
			const pending = pendingFilters[key];
			if (pending.operator !== applied.operator) return true;
			if (JSON.stringify(pending.value) !== JSON.stringify(applied.value)) return true;
		}
		return false;
	});

</script>

{#if open}
	<div
		class="rounded-lg border bg-card shadow-sm"
		transition:slide={{ duration: 200 }}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b px-4 py-3">
			<div class="flex items-center gap-2">
				<h4 class="font-semibold">Filters</h4>
				{#if pendingCount > 0}
					<span class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
						{pendingCount}
					</span>
				{/if}
			</div>
			<div class="flex items-center gap-2">
				{#if pendingCount > 0}
					<Button variant="ghost" size="sm" onclick={clearAllPending} class="h-7 text-xs">
						Clear all
					</Button>
				{/if}
				<Button variant="ghost" size="icon" onclick={onClose} class="h-7 w-7">
					<X class="h-4 w-4" />
				</Button>
			</div>
		</div>

		<!-- Filter grid -->
		<div class="p-4">
			<div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
				{#each filterableColumns as column (column.id)}
					{@const pendingFilter = pendingFilters[column.id]}
					{@const isDateField = column.type === 'date' || column.type === 'datetime' || column.type === 'time'}
					{@const hasValue = pendingFilter && (
						isDateField
							? pendingFilter.operator !== undefined && pendingFilter.operator !== ''
							: pendingFilter.value !== undefined && pendingFilter.value !== '' && (!Array.isArray(pendingFilter.value) || pendingFilter.value.length > 0)
					)}

					<div class="space-y-1.5">
						<label
							for="filter-{column.id}"
							class="flex items-center gap-1.5 text-sm font-medium"
						>
							{column.header}
							{#if hasValue}
								<button
									type="button"
									onclick={() => clearPendingFilter(column.id)}
									class="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
									aria-label="Clear filter"
								>
									<X class="h-3 w-3" />
								</button>
							{/if}
						</label>

						{#if column.type === 'text' || column.type === 'email' || column.type === 'phone' || column.type === 'url' || column.type === 'textarea'}
							<Input
								id="filter-{column.id}"
								type="text"
								placeholder="Contains..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value, 'contains')}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>

						{:else if column.type === 'number' || column.type === 'decimal' || column.type === 'currency' || column.type === 'percent'}
							<Input
								id="filter-{column.id}"
								type="number"
								placeholder="Equals..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value ? parseFloat(e.currentTarget.value) : '', 'equals')}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>

						{:else if column.type === 'select' || column.type === 'multiselect'}
							{@const options = column.filterOptions || column.options || []}
							{@const selectedValues: string[] = Array.isArray(pendingFilter?.value) ? (pendingFilter.value as (string | number | boolean)[]).map(v => String(v)) : (pendingFilter?.value != null && pendingFilter.value !== '' ? [String(pendingFilter.value)] : [])}
							{@const selectedLabels = options.filter((opt) => selectedValues.includes(String(opt.value))).map((opt) => opt.label)}

							<Select.Root
								type="multiple"
								value={selectedValues}
								onValueChange={(vals) => updatePendingFilter(column.id, vals && vals.length > 0 ? vals : '', 'in')}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span class="truncate">
										{#if selectedLabels.length === 0}
											Any
										{:else if selectedLabels.length === 1}
											{selectedLabels[0]}
										{:else}
											{selectedLabels.length} selected
										{/if}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each options as option (option.value)}
										<Select.Item value={String(option.value)}>
											{option.label}
											{#if option.count !== undefined}
												<span class="ml-auto text-xs text-muted-foreground">
													({option.count})
												</span>
											{/if}
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

						{:else if column.type === 'boolean' || column.type === 'checkbox' || column.type === 'toggle'}
							<Select.Root
								type="single"
								value={pendingFilter?.value !== undefined ? String(pendingFilter.value) : ''}
								onValueChange={(val) => {
									if (val !== undefined && val !== '') {
										updatePendingFilter(column.id, val === 'true', 'equals');
									} else {
										clearPendingFilter(column.id);
									}
								}}
							>
								<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
									<span>
										{pendingFilter?.value !== undefined
											? pendingFilter.value ? 'Yes' : 'No'
											: 'Any'}
									</span>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="">
										<span class="text-muted-foreground">Any</span>
									</Select.Item>
									<Select.Item value="true">Yes</Select.Item>
									<Select.Item value="false">No</Select.Item>
								</Select.Content>
							</Select.Root>

						{:else if column.type === 'date' || column.type === 'datetime'}
							{@const isBetween = pendingFilter?.operator === 'between'}
							{@const date1 = parseDateSafe(Array.isArray(pendingFilter?.value) ? pendingFilter.value[0] : undefined)}
							{@const date2 = parseDateSafe(Array.isArray(pendingFilter?.value) ? pendingFilter.value[1] : undefined)}

							<Popover.Root bind:open={dateRangeOpen[column.id]}>
								<Popover.Trigger>
									{#snippet child({ props })}
										<Button
											{...props}
											variant="outline"
											size="sm"
											class={cn('h-8 w-full justify-start text-left font-normal text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
										>
											<CalendarIcon class="mr-1.5 h-3.5 w-3.5 flex-shrink-0" />
											<span class="truncate">{getDateDisplayText(column.id, pendingFilter)}</span>
										</Button>
									{/snippet}
								</Popover.Trigger>
								<Popover.Content class="w-auto p-0" align="start">
									<div class="p-2 space-y-2">
										<!-- Presets -->
										<div class="grid grid-cols-2 gap-1">
											{#each datePresets.filter(p => p.value !== 'between') as preset (preset.value)}
												<Button
													variant={pendingFilter?.operator === preset.value ? 'default' : 'ghost'}
													size="sm"
													class="h-7 justify-start text-xs"
													onclick={() => {
														updatePendingFilter(column.id, '', preset.value);
														if (preset.value !== 'between') {
															dateRangeOpen[column.id] = false;
														}
													}}
												>
													{preset.label}
												</Button>
											{/each}
										</div>

										<!-- Custom Range -->
										<div class="border-t pt-2">
											<p class="text-xs font-medium text-muted-foreground mb-2">Custom Range</p>
											<div class="flex items-center gap-2">
												<Popover.Root bind:open={datePickerOpen1[column.id]}>
													<Popover.Trigger>
														{#snippet child({ props })}
															<Button
																{...props}
																variant="outline"
																size="sm"
																class={cn('flex-1 justify-start text-left font-normal h-8 text-xs', !date1 && 'text-muted-foreground')}
															>
																<CalendarIcon class="mr-1 h-3 w-3" />
																{date1 ? formatDate(date1) : 'From'}
															</Button>
														{/snippet}
													</Popover.Trigger>
													<Popover.Content class="w-auto p-0" align="start">
														<Calendar
															type="single"
															value={date1}
															onValueChange={(d) => {
																const newVal = [d?.toString() || '', date2?.toString() || ''];
																updatePendingFilter(column.id, newVal, 'between');
																datePickerOpen1[column.id] = false;
															}}
														/>
													</Popover.Content>
												</Popover.Root>
												<CalendarRange class="h-3.5 w-3.5 text-muted-foreground flex-shrink-0" />
												<Popover.Root bind:open={datePickerOpen2[column.id]}>
													<Popover.Trigger>
														{#snippet child({ props })}
															<Button
																{...props}
																variant="outline"
																size="sm"
																class={cn('flex-1 justify-start text-left font-normal h-8 text-xs', !date2 && 'text-muted-foreground')}
															>
																<CalendarIcon class="mr-1 h-3 w-3" />
																{date2 ? formatDate(date2) : 'To'}
															</Button>
														{/snippet}
													</Popover.Trigger>
													<Popover.Content class="w-auto p-0" align="end">
														<Calendar
															type="single"
															value={date2}
															onValueChange={(d) => {
																const newVal = [date1?.toString() || '', d?.toString() || ''];
																updatePendingFilter(column.id, newVal, 'between');
																datePickerOpen2[column.id] = false;
															}}
														/>
													</Popover.Content>
												</Popover.Root>
											</div>
										</div>

										<!-- Clear -->
										{#if hasValue}
											<div class="border-t pt-2">
												<Button
													variant="ghost"
													size="sm"
													class="w-full h-7 text-xs"
													onclick={() => {
														clearPendingFilter(column.id);
														dateRangeOpen[column.id] = false;
													}}
												>
													<X class="mr-1 h-3 w-3" />
													Clear
												</Button>
											</div>
										{/if}
									</div>
								</Popover.Content>
							</Popover.Root>

						{:else if column.type === 'lookup' || column.type === 'user'}
							{@const isUserField = isUserLookup(column)}
							{@const options = isUserField ? users.map(u => ({ value: u.id, label: u.name })) : (column.filterOptions || [])}
							{@const selectedValues: string[] = Array.isArray(pendingFilter?.value) ? (pendingFilter.value as (string | number | boolean)[]).map(v => String(v)) : (pendingFilter?.value != null && pendingFilter.value !== '' ? [String(pendingFilter.value)] : [])}
							{@const selectedLabels = options.filter((opt) => selectedValues.includes(String(opt.value))).map((opt) => opt.label)}

							{#if isUserField && usersLoading}
								<div class="flex h-8 items-center justify-center rounded-md border">
									<Loader2 class="h-4 w-4 animate-spin text-muted-foreground" />
								</div>
							{:else}
								<Select.Root
									type="multiple"
									value={selectedValues}
									onValueChange={(vals) => updatePendingFilter(column.id, vals && vals.length > 0 ? vals.map(v => parseInt(v, 10) || v) : '', 'in')}
								>
									<Select.Trigger class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}>
										<span class="truncate">
											{#if selectedLabels.length === 0}
												Any
											{:else if selectedLabels.length === 1}
												{selectedLabels[0]}
											{:else}
												{selectedLabels.length} selected
											{/if}
										</span>
									</Select.Trigger>
									<Select.Content>
										{#if isUserField}
											<Select.Item value="__empty__" onclick={() => {
												updatePendingFilter(column.id, '', 'is_empty');
											}}>
												<span class="text-muted-foreground italic">Unassigned</span>
											</Select.Item>
										{/if}
										{#each options as option (option.value)}
											<Select.Item value={String(option.value)}>{option.label}</Select.Item>
										{/each}
									</Select.Content>
								</Select.Root>
							{/if}

						{:else}
							<!-- Default text input for unknown types -->
							<Input
								id="filter-{column.id}"
								type="text"
								placeholder="Filter..."
								value={pendingFilter?.value || ''}
								oninput={(e) => updatePendingFilter(column.id, e.currentTarget.value)}
								class={cn('h-8 text-sm', hasValue && 'border-primary ring-1 ring-primary/20')}
							/>
						{/if}
					</div>
				{/each}
			</div>
		</div>

		<!-- Footer with Apply/Reset buttons -->
		<div class="flex items-center justify-between border-t bg-muted/30 px-4 py-3">
			<div class="text-sm text-muted-foreground">
				{#if pendingCount > 0}
					{pendingCount} filter{pendingCount === 1 ? '' : 's'} selected
				{:else}
					No filters selected
				{/if}
			</div>
			<div class="flex items-center gap-2">
				{#if hasChanges()}
					<Button variant="outline" size="sm" onclick={resetFilters}>
						<RotateCcw class="mr-1.5 h-3.5 w-3.5" />
						Reset
					</Button>
				{/if}
				<Button size="sm" onclick={applyFilters}>
					Apply Filters
				</Button>
			</div>
		</div>
	</div>
{/if}
