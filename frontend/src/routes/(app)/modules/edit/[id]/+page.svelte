<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import {
		modulesApi,
		type Module,
		type CreateModuleRequest,
		type CreateBlockRequest,
		type CreateFieldRequest,
		type UpdateModuleRequest,
		type UpdateBlockRequest,
		type UpdateFieldRequest
	} from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Separator } from '$lib/components/ui/separator';
	import FieldPalette from '$lib/components/form-builder/FieldPalette.svelte';
	import FormCanvas from '$lib/components/form-builder/FormCanvas.svelte';
	import FieldConfigPanel from '$lib/components/form-builder/FieldConfigPanel.svelte';
	import {
		ArrowLeft,
		Save,
		CheckCircle2,
		Box,
		Settings2,
		Table2,
		AlertCircle,
		Loader2,
		Eye,
		EyeOff,
		ArrowUpDown,
		ArrowUp,
		ArrowDown
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import * as Select from '$lib/components/ui/select';

	// Extended block type that includes IDs for tracking
	interface EditableBlock extends CreateBlockRequest {
		id?: number;
		fields?: EditableField[];
	}

	interface EditableField extends CreateFieldRequest {
		id?: number;
		options?: EditableFieldOption[];
	}

	interface EditableFieldOption {
		id?: number;
		label: string;
		value: string;
		color?: string;
		display_order?: number;
	}

	const moduleId = Number($page.params.id);

	let moduleName = $state('');
	let singularName = $state('');
	let description = $state('');
	let icon = $state('');
	let defaultPageSize = $state(50);
	let defaultColumnVisibility = $state<Record<string, boolean>>({});
	let defaultSortField = $state<string>('');
	let defaultSortDirection = $state<'asc' | 'desc'>('asc');

	let blocks = $state<EditableBlock[]>([]);
	let selectedBlockIndex = $state(-1);
	let selectedFieldIndex = $state(-1);
	let showFieldConfig = $state(false);

	let loading = $state(false);
	let loadingModule = $state(true);
	let error = $state<string | null>(null);
	let currentStep = $state<'details' | 'builder' | 'settings'>('details');

	let isStep1Valid = $derived(moduleName.trim() && singularName.trim());
	let isStep2Valid = $derived(
		blocks.length > 0 && blocks.every((b) => b.fields && b.fields.length > 0)
	);

	let selectedField = $derived.by(() => {
		if (selectedBlockIndex >= 0 && selectedFieldIndex >= 0) {
			return blocks[selectedBlockIndex]?.fields?.[selectedFieldIndex];
		}
		return null;
	});

	let availableFields = $derived.by(() => {
		const fields: Array<{ api_name: string; label: string; type: string }> = [];
		blocks.forEach((block) => {
			block.fields?.forEach((field) => {
				fields.push({
					api_name: field.api_name || field.label.toLowerCase().replace(/\s+/g, '_'),
					label: field.label,
					type: field.type
				});
			});
		});
		return fields;
	});

	let availableModules = $state([
		{ id: 1, name: 'Contacts', api_name: 'contacts' },
		{ id: 2, name: 'Companies', api_name: 'companies' },
		{ id: 3, name: 'Deals', api_name: 'deals' }
	]);

	onMount(async () => {
		await loadModule();
	});

	async function loadModule() {
		try {
			loadingModule = true;
			const module = await modulesApi.getById(moduleId);

			// Populate form fields
			moduleName = module.name;
			singularName = module.singular_name;
			description = module.description || '';
			icon = module.icon || '';
			defaultPageSize = module.default_page_size || 50;
			defaultColumnVisibility = module.default_column_visibility || {};

			// Load default sorting
			if (module.default_sorting && module.default_sorting.length > 0) {
				defaultSortField = module.default_sorting[0].id || '';
				defaultSortDirection = module.default_sorting[0].desc ? 'desc' : 'asc';
			}

			// Convert module blocks to EditableBlock format (preserving IDs for updates)
			if (module.blocks) {
				blocks = module.blocks.map((block) => ({
					id: block.id, // Preserve block ID for updates
					name: block.name,
					type: block.type,
					display_order: block.display_order,
					settings: block.settings,
					fields: block.fields.map((field) => ({
						id: field.id, // Preserve field ID for updates
						label: field.label,
						api_name: field.api_name,
						type: field.type,
						description: field.description || undefined,
						help_text: field.help_text || undefined,
						placeholder: field.placeholder || undefined,
						is_required: field.is_required,
						is_unique: field.is_unique,
						is_searchable: field.is_searchable,
						is_filterable: field.is_filterable,
						is_sortable: field.is_sortable,
						default_value: field.default_value || undefined,
						display_order: field.display_order,
						width: field.width,
						validation_rules: field.validation_rules,
						settings: field.settings,
						conditional_visibility: field.conditional_visibility || undefined,
						field_dependency: field.field_dependency || undefined,
						formula_definition: field.formula_definition || undefined,
						options: field.options?.map((opt) => ({
							id: opt.id, // Preserve option ID for updates
							label: opt.label,
							value: opt.value,
							color: opt.color || undefined,
							display_order: opt.display_order
						}))
					}))
				}));
			}
		} catch (err: any) {
			console.error('Failed to load module:', err);
			error = 'Failed to load module: ' + (err.message || 'Unknown error');
			toast.error(error);
		} finally {
			loadingModule = false;
		}
	}

	function handleBlocksChange(newBlocks: EditableBlock[]) {
		blocks = newBlocks;
	}

	function handleFieldSelect(blockIndex: number, fieldIndex: number) {
		selectedBlockIndex = blockIndex;
		selectedFieldIndex = fieldIndex;
		showFieldConfig = true;
	}

	function handleFieldChange(updatedField: EditableField) {
		if (selectedBlockIndex >= 0 && selectedFieldIndex >= 0) {
			const updatedBlocks = [...blocks];
			const fields = [...(updatedBlocks[selectedBlockIndex].fields || [])];
			// Preserve the ID when updating
			fields[selectedFieldIndex] = {
				...updatedField,
				id: fields[selectedFieldIndex]?.id
			};
			updatedBlocks[selectedBlockIndex] = {
				...updatedBlocks[selectedBlockIndex],
				fields
			};
			blocks = updatedBlocks;
		}
	}

	function closeFieldConfig() {
		showFieldConfig = false;
		selectedBlockIndex = -1;
		selectedFieldIndex = -1;
	}

	function goToStep(step: 'details' | 'builder' | 'settings') {
		if (step === 'builder' && !isStep1Valid) {
			toast.error('Please fill in module name and singular name first');
			return;
		}
		if (step === 'settings' && !isStep2Valid) {
			toast.error('Please add at least one block with fields first');
			return;
		}
		currentStep = step;
	}

	async function handleSubmit() {
		error = null;

		if (!isStep1Valid) {
			error = 'Module name and singular name are required';
			currentStep = 'details';
			return;
		}

		if (!isStep2Valid) {
			error = 'At least one block with fields is required';
			currentStep = 'builder';
			return;
		}

		loading = true;

		try {
			// Build default sorting array if a sort field is selected
			const defaultSorting = defaultSortField
				? [{ id: defaultSortField, desc: defaultSortDirection === 'desc' }]
				: undefined;

			// Only include column visibility if any columns are hidden
			const hasHiddenColumns = Object.values(defaultColumnVisibility).some((v) => v === false);
			const columnVisibility = hasHiddenColumns ? defaultColumnVisibility : undefined;

			// Convert blocks to UpdateBlockRequest format with IDs preserved
			const updateBlocks: UpdateBlockRequest[] = blocks.map((block, blockIndex) => ({
				id: block.id, // Preserve ID for existing blocks
				name: block.name,
				type: block.type,
				display_order: block.display_order ?? blockIndex,
				settings: block.settings || {},
				fields: block.fields?.map((field, fieldIndex) => ({
					id: field.id, // Preserve ID for existing fields
					label: field.label,
					api_name: field.api_name,
					type: field.type,
					description: field.description,
					help_text: field.help_text,
					placeholder: field.placeholder,
					is_required: field.is_required,
					is_unique: field.is_unique,
					is_searchable: field.is_searchable,
					is_filterable: field.is_filterable,
					is_sortable: field.is_sortable,
					default_value: field.default_value,
					display_order: field.display_order ?? fieldIndex,
					width: field.width,
					validation_rules: field.validation_rules,
					settings: field.settings,
					conditional_visibility: field.conditional_visibility,
					field_dependency: field.field_dependency,
					formula_definition: field.formula_definition,
					options: field.options?.map((opt, optIndex) => ({
						id: opt.id, // Preserve ID for existing options
						label: opt.label,
						value: opt.value,
						color: opt.color,
						display_order: opt.display_order ?? optIndex
					}))
				}))
			}));

			const request: UpdateModuleRequest = {
				name: moduleName,
				singular_name: singularName,
				description: description || undefined,
				icon: icon || undefined,
				default_page_size: defaultPageSize,
				default_sorting: defaultSorting,
				default_column_visibility: columnVisibility,
				blocks: updateBlocks
			};

			console.log('Updating module with request:', JSON.stringify(request, null, 2));

			// Update module with all settings including blocks
			await modulesApi.update(moduleId, request);

			console.log('Module updated successfully');
			toast.success('Module updated successfully!');
			goto('/modules');
		} catch (err: any) {
			console.error('Module update failed:', err);
			console.error('Error response:', err.response?.data);
			error = err.response?.data?.message || err.message || 'Failed to update module';

			if (err.response?.data?.errors) {
				const errors = Object.values(err.response.data.errors).flat();
				error = errors.join(', ');
			}

			toast.error(error || 'Failed to update module');
		} finally {
			loading = false;
		}
	}
</script>

<svelte:head>
	<title>Edit Module - Form Builder</title>
</svelte:head>

{#if loadingModule}
	<div class="flex h-screen items-center justify-center">
		<div class="text-center">
			<Loader2 class="mx-auto mb-4 h-12 w-12 animate-spin text-primary" />
			<p class="text-lg text-muted-foreground">Loading module...</p>
		</div>
	</div>
{:else}
	<div class="flex h-screen flex-col bg-gradient-to-b from-background to-muted/20">
		<!-- Modern Header with Progress -->
		<div
			class="sticky top-0 z-50 border-b bg-card/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-card/80"
		>
			<div class="container mx-auto px-4 md:px-6">
				<!-- Top bar -->
				<div class="flex items-center justify-between py-4">
					<div class="flex items-center gap-4">
						<Button variant="ghost" size="icon" onclick={() => goto('/modules')} class="shrink-0">
							<ArrowLeft class="h-4 w-4" />
						</Button>
						<div>
							<h1
								class="bg-gradient-to-r from-foreground to-foreground/70 bg-clip-text text-2xl font-bold text-transparent"
							>
								Edit Module
							</h1>
							<p class="mt-0.5 text-sm text-muted-foreground">Modify your module configuration</p>
						</div>
					</div>
					<Button
						onclick={handleSubmit}
						disabled={loading || !isStep1Valid || !isStep2Valid}
						class="gap-2 bg-primary hover:bg-primary/90"
					>
						<Save class="h-4 w-4" />
						{loading ? 'Saving...' : 'Save Changes'}
					</Button>
				</div>

				<!-- Progress Steps -->
				<div class="pb-4">
					<div class="flex items-center gap-2">
						<button
							onclick={() => goToStep('details')}
							class="flex items-center gap-2 rounded-lg px-4 py-2 transition-all {currentStep ===
							'details'
								? 'bg-primary text-primary-foreground shadow-sm'
								: 'hover:bg-muted/50'}"
						>
							<div
								class="flex h-6 w-6 items-center justify-center rounded-full {currentStep ===
								'details'
									? 'bg-primary-foreground/20'
									: isStep1Valid
										? 'bg-green-500/20'
										: 'bg-muted'}"
							>
								{#if isStep1Valid && currentStep !== 'details'}
									<CheckCircle2 class="h-4 w-4 text-green-600" />
								{:else}
									<span class="text-xs font-semibold">1</span>
								{/if}
							</div>
							<span class="text-sm font-medium">Module Details</span>
						</button>

						<Separator orientation="horizontal" class="w-8" />

						<button
							onclick={() => goToStep('builder')}
							class="flex items-center gap-2 rounded-lg px-4 py-2 transition-all {currentStep ===
							'builder'
								? 'bg-primary text-primary-foreground shadow-sm'
								: 'hover:bg-muted/50'}"
							disabled={!isStep1Valid}
						>
							<div
								class="flex h-6 w-6 items-center justify-center rounded-full {currentStep ===
								'builder'
									? 'bg-primary-foreground/20'
									: isStep2Valid
										? 'bg-green-500/20'
										: 'bg-muted'}"
							>
								{#if isStep2Valid && currentStep !== 'builder'}
									<CheckCircle2 class="h-4 w-4 text-green-600" />
								{:else}
									<Box class="h-4 w-4" />
								{/if}
							</div>
							<span class="text-sm font-medium">Build Fields</span>
						</button>

						<Separator orientation="horizontal" class="w-8" />

						<button
							onclick={() => goToStep('settings')}
							class="flex items-center gap-2 rounded-lg px-4 py-2 transition-all {currentStep ===
							'settings'
								? 'bg-primary text-primary-foreground shadow-sm'
								: 'hover:bg-muted/50'}"
							disabled={!isStep2Valid}
						>
							<div
								class="flex h-6 w-6 items-center justify-center rounded-lg {currentStep ===
								'settings'
									? 'bg-primary-foreground/20'
									: 'bg-muted'}"
							>
								<Settings2 class="h-4 w-4" />
							</div>
							<span class="text-sm font-medium">Table Settings</span>
						</button>
					</div>
				</div>
			</div>
		</div>

		{#if error}
			<div class="border-b border-destructive/20 bg-destructive/10 px-4 py-3 md:px-6">
				<div class="container mx-auto flex items-center gap-2 text-sm text-destructive">
					<AlertCircle class="h-4 w-4" />
					{error}
				</div>
			</div>
		{/if}

		<!-- Step Content -->
		<div class="flex-1 overflow-hidden">
			{#if currentStep === 'details'}
				<!-- Step 1: Module Details -->
				<div class="container mx-auto h-full max-w-4xl overflow-y-auto px-4 py-8 md:px-6">
					<Card.Root class="border-2 shadow-lg">
						<Card.Header class="space-y-2 pb-6">
							<div class="flex items-center gap-3">
								<div class="rounded-xl bg-primary/10 p-3">
									<Box class="h-6 w-6 text-primary" />
								</div>
								<div>
									<Card.Title class="text-2xl">Module Information</Card.Title>
									<Card.Description class="text-base">
										Update the basic details and metadata for your module
									</Card.Description>
								</div>
							</div>
						</Card.Header>
						<Card.Content class="space-y-6">
							<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
								<div class="space-y-2 md:col-span-2">
									<Label for="module-name" class="flex items-center gap-2 text-sm font-semibold">
										Module Name
										<Badge variant="destructive" class="text-xs">Required</Badge>
									</Label>
									<Input
										id="module-name"
										bind:value={moduleName}
										placeholder="e.g., Sales Opportunities, Projects, Invoices"
										class="h-12 border-2 text-lg focus-visible:ring-2 focus-visible:ring-primary/20"
									/>
								</div>

								<div class="space-y-2">
									<Label for="singular-name" class="flex items-center gap-2 text-sm font-semibold">
										Singular Name
										<Badge variant="destructive" class="text-xs">Required</Badge>
									</Label>
									<Input
										id="singular-name"
										bind:value={singularName}
										placeholder="e.g., Opportunity, Project, Invoice"
										class="h-11 border-2 focus-visible:ring-2 focus-visible:ring-primary/20"
									/>
								</div>

								<div class="space-y-2">
									<Label for="icon" class="text-sm font-semibold">Icon (Optional)</Label>
									<Input
										id="icon"
										bind:value={icon}
										placeholder="e.g., TrendingUp, Folder, FileText"
										class="h-11 border-2 focus-visible:ring-2 focus-visible:ring-primary/20"
									/>
								</div>

								<div class="space-y-2 md:col-span-2">
									<Label for="description" class="text-sm font-semibold">
										Description (Optional)
									</Label>
									<Textarea
										id="description"
										bind:value={description}
										placeholder="Brief description of what this module manages..."
										rows={3}
										class="resize-none border-2 focus-visible:ring-2 focus-visible:ring-primary/20"
									/>
								</div>
							</div>

							<div class="flex justify-end pt-4">
								<Button
									onclick={() => goToStep('builder')}
									disabled={!isStep1Valid}
									size="lg"
									class="gap-2"
								>
									Continue to Field Builder
									<ArrowLeft class="h-4 w-4 rotate-180" />
								</Button>
							</div>
						</Card.Content>
					</Card.Root>
				</div>
			{:else if currentStep === 'builder'}
				<!-- Step 2: Form Builder -->
				<div class="flex h-full overflow-hidden">
					<div class="hidden lg:block {showFieldConfig ? 'lg:block' : 'md:block'}">
						<FieldPalette />
					</div>

					<FormCanvas
						bind:blocks
						onBlocksChange={handleBlocksChange}
						onFieldSelect={handleFieldSelect}
						{selectedBlockIndex}
						{selectedFieldIndex}
					/>

					{#if showFieldConfig && selectedField}
						<div class="absolute inset-0 z-10 lg:relative lg:inset-auto lg:z-0">
							<FieldConfigPanel
								field={selectedField}
								onFieldChange={handleFieldChange}
								onClose={closeFieldConfig}
								{availableFields}
								{availableModules}
							/>
						</div>
					{/if}
				</div>

				{#if isStep2Valid}
					<div class="absolute right-6 bottom-6 lg:right-8 lg:bottom-8">
						<Button
							onclick={() => goToStep('settings')}
							size="lg"
							class="h-12 gap-2 px-6 shadow-2xl"
						>
							<Table2 class="h-5 w-5" />
							Configure Table Settings
						</Button>
					</div>
				{/if}
			{:else if currentStep === 'settings'}
				<!-- Step 3: DataTable Settings -->
				<div class="container mx-auto h-full max-w-4xl overflow-y-auto px-4 py-8 md:px-6">
					<Card.Root class="border-2 shadow-lg">
						<Card.Header class="space-y-2 pb-6">
							<div class="flex items-center gap-3">
								<div class="rounded-xl bg-primary/10 p-3">
									<Table2 class="h-6 w-6 text-primary" />
								</div>
								<div>
									<Card.Title class="text-2xl">Default Table Settings</Card.Title>
									<Card.Description class="text-base">
										Configure how data will be displayed by default in the datatable
									</Card.Description>
								</div>
							</div>
						</Card.Header>
						<Card.Content class="space-y-6">
							<div class="grid gap-6">
								<!-- Page Size -->
								<div class="rounded-xl border-2 border-dashed border-primary/20 bg-primary/5 p-6">
									<div class="space-y-3">
										<div class="flex items-start gap-3">
											<div class="rounded-lg bg-primary/10 p-2">
												<Table2 class="h-5 w-5 text-primary" />
											</div>
											<div class="flex-1">
												<Label for="page-size" class="mb-2 block text-base font-semibold">
													Default Page Size
												</Label>
												<div class="flex items-center gap-4">
													<Input
														id="page-size"
														type="number"
														bind:value={defaultPageSize}
														min={10}
														max={200}
														class="h-12 max-w-xs border-2 text-lg focus-visible:ring-2 focus-visible:ring-primary/20"
													/>
													<span class="text-sm text-muted-foreground">records per page</span>
												</div>
												<p class="mt-2 text-sm text-muted-foreground">
													Choose between 10-200 records per page. Default is 50.
												</p>
											</div>
										</div>
									</div>
								</div>

								<!-- Default Sorting -->
								<div class="rounded-xl border-2 border-dashed border-blue-500/20 bg-blue-500/5 p-6">
									<div class="space-y-4">
										<div class="flex items-start gap-3">
											<div class="rounded-lg bg-blue-500/10 p-2">
												<ArrowUpDown class="h-5 w-5 text-blue-600" />
											</div>
											<div class="flex-1">
												<Label class="mb-2 block text-base font-semibold">Default Sort Order</Label>
												<p class="mb-4 text-sm text-muted-foreground">
													Choose which column to sort by when users first view the datatable
												</p>
												<div class="flex flex-wrap items-center gap-4">
													<Select.Root type="single" bind:value={defaultSortField}>
														<Select.Trigger class="w-[200px]">
															{#if defaultSortField}
																{availableFields.find((f) => f.api_name === defaultSortField)
																	?.label || 'Select field'}
															{:else}
																<span class="text-muted-foreground">No default sort</span>
															{/if}
														</Select.Trigger>
														<Select.Content>
															<Select.Item value="">No default sort</Select.Item>
															{#each availableFields as field}
																<Select.Item value={field.api_name}>{field.label}</Select.Item>
															{/each}
														</Select.Content>
													</Select.Root>
													{#if defaultSortField}
														<div class="flex items-center gap-2 rounded-lg bg-muted p-1">
															<button
																type="button"
																onclick={() => (defaultSortDirection = 'asc')}
																class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors {defaultSortDirection ===
																'asc'
																	? 'bg-background font-medium shadow-sm'
																	: 'hover:bg-background/50'}"
															>
																<ArrowUp class="h-4 w-4" />
																Ascending
															</button>
															<button
																type="button"
																onclick={() => (defaultSortDirection = 'desc')}
																class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm transition-colors {defaultSortDirection ===
																'desc'
																	? 'bg-background font-medium shadow-sm'
																	: 'hover:bg-background/50'}"
															>
																<ArrowDown class="h-4 w-4" />
																Descending
															</button>
														</div>
													{/if}
												</div>
											</div>
										</div>
									</div>
								</div>

								<!-- Column Visibility -->
								<div
									class="rounded-xl border-2 border-dashed border-purple-500/20 bg-purple-500/5 p-6"
								>
									<div class="space-y-4">
										<div class="flex items-start gap-3">
											<div class="rounded-lg bg-purple-500/10 p-2">
												<Eye class="h-5 w-5 text-purple-600" />
											</div>
											<div class="flex-1">
												<Label class="mb-2 block text-base font-semibold">
													Default Column Visibility
												</Label>
												<p class="mb-4 text-sm text-muted-foreground">
													Choose which columns are visible by default. Users can always toggle
													columns in the datatable.
												</p>
												{#if availableFields.length > 0}
													<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
														{#each availableFields as field}
															{@const isVisible = defaultColumnVisibility[field.api_name] !== false}
															<button
																type="button"
																onclick={() => {
																	defaultColumnVisibility = {
																		...defaultColumnVisibility,
																		[field.api_name]: !isVisible
																	};
																}}
																class="flex items-center gap-3 rounded-lg border-2 p-3 text-left transition-all {isVisible
																	? 'border-purple-500/30 bg-purple-500/10'
																	: 'border-muted bg-muted/30 opacity-60'}"
															>
																{#if isVisible}
																	<Eye class="h-4 w-4 shrink-0 text-purple-600" />
																{:else}
																	<EyeOff class="h-4 w-4 shrink-0 text-muted-foreground" />
																{/if}
																<div class="min-w-0">
																	<span class="block truncate text-sm font-medium"
																		>{field.label}</span
																	>
																	<span class="text-xs text-muted-foreground">{field.type}</span>
																</div>
															</button>
														{/each}
													</div>
												{:else}
													<p class="text-sm text-muted-foreground italic">
														Add fields in the previous step to configure visibility
													</p>
												{/if}
											</div>
										</div>
									</div>
								</div>

								<!-- Info Card -->
								<div class="rounded-xl border-2 bg-muted/30 p-6">
									<h4 class="mb-3 flex items-center gap-2 text-base font-semibold">
										<Settings2 class="h-4 w-4" />
										Additional Settings
									</h4>
									<div class="space-y-2 text-sm text-muted-foreground">
										<p>
											<strong class="text-foreground">Default filters</strong> can be configured by:
										</p>
										<ol class="ml-2 list-inside list-decimal space-y-1">
											<li>Navigate to your module's datatable</li>
											<li>Set up your preferred filters</li>
											<li>Save as a view and mark it as "Module Default"</li>
										</ol>
										<p class="mt-3">
											Users can also create their own personal views and share them with the team.
										</p>
									</div>
								</div>
							</div>

							<div class="flex items-center justify-between pt-4">
								<Button
									variant="outline"
									onclick={() => goToStep('builder')}
									size="lg"
									class="gap-2"
								>
									<ArrowLeft class="h-4 w-4" />
									Back to Builder
								</Button>
								<Button
									onclick={handleSubmit}
									disabled={loading}
									size="lg"
									class="gap-2 bg-green-600 hover:bg-green-700"
								>
									<CheckCircle2 class="h-5 w-5" />
									{loading ? 'Saving...' : 'Save Changes'}
								</Button>
							</div>
						</Card.Content>
					</Card.Root>
				</div>
			{/if}
		</div>
	</div>
{/if}
