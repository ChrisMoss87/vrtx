<script lang="ts">
	import { goto } from '$app/navigation';
	import {
		modulesApi,
		type CreateModuleRequest,
		type CreateBlockRequest,
		type CreateFieldRequest
	} from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Separator } from '$lib/components/ui/separator';
	import FieldPalette from '$lib/components/form-builder/FieldPalette.svelte';
	import FormCanvas from '$lib/components/form-builder/FormCanvas.svelte';
	import FieldConfigPanel from '$lib/components/form-builder/FieldConfigPanel.svelte';
	import IconPicker from '$lib/components/form-builder/IconPicker.svelte';
	import {
		ArrowLeft,
		Save,
		CheckCircle2,
		Box,
		Settings2,
		Table2,
		AlertCircle,
		Eye,
		EyeOff,
		ArrowUpDown,
		ArrowUp,
		ArrowDown,
		GripVertical,
		Columns3,
		Tag,
		Users,
		Building2,
		Briefcase,
		DollarSign,
		ShoppingCart,
		FileText,
		Mail,
		Phone,
		Calendar,
		Star,
		Folder,
		Target,
		TrendingUp,
		BarChart3,
		Activity,
		Package,
		Truck,
		MapPin,
		Globe,
		Bell,
		Clipboard,
		Layers,
		Wallet,
		CreditCard,
		Database,
		Rocket,
		Home,
		Store
	} from 'lucide-svelte';
	import type { ComponentType } from 'svelte';
	import { toast } from 'svelte-sonner';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Switch } from '$lib/components/ui/switch';

	let moduleName = $state('');
	let singularName = $state('');
	let description = $state('');
	let icon = $state('');

	// Default view settings
	let defaultPageSize = $state(50);
	let defaultColumnVisibility = $state<Record<string, boolean>>({});
	let defaultSortField = $state<string>('');
	let defaultSortDirection = $state<'asc' | 'desc'>('asc');
	let defaultColumnOrder = $state<string[]>([]);
	let recordNameField = $state<string>('');

	// Dragging state for column reorder
	let draggedColumnIndex = $state<number | null>(null);
	let dragOverColumnIndex = $state<number | null>(null);

	let blocks = $state<CreateBlockRequest[]>([]);
	let selectedBlockIndex = $state(-1);
	let selectedFieldIndex = $state(-1);
	let showFieldConfig = $state(false);

	let loading = $state(false);
	let error = $state<string | null>(null);
	let currentStep = $state<'details' | 'builder' | 'settings'>('details');

	// Validation states
	let isStep1Valid = $derived(moduleName.trim() && singularName.trim());
	let isStep2Valid = $derived(
		blocks.length > 0 && blocks.every((b) => b.fields && b.fields.length > 0)
	);

	// Get selected field
	let selectedField = $derived.by(() => {
		if (selectedBlockIndex >= 0 && selectedFieldIndex >= 0) {
			return blocks[selectedBlockIndex]?.fields?.[selectedFieldIndex];
		}
		return null;
	});

	// Get all fields from all blocks (for conditional visibility and formulas)
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

	// Fields ordered by column order preference
	let orderedFields = $derived.by(() => {
		if (defaultColumnOrder.length === 0) {
			return availableFields;
		}
		// Sort fields based on defaultColumnOrder, putting unordered fields at the end
		const ordered = [...availableFields].sort((a, b) => {
			const aIndex = defaultColumnOrder.indexOf(a.api_name);
			const bIndex = defaultColumnOrder.indexOf(b.api_name);
			if (aIndex === -1 && bIndex === -1) return 0;
			if (aIndex === -1) return 1;
			if (bIndex === -1) return -1;
			return aIndex - bIndex;
		});
		return ordered;
	});

	// Initialize column order when fields change
	$effect(() => {
		if (availableFields.length > 0 && defaultColumnOrder.length === 0) {
			defaultColumnOrder = availableFields.map((f) => f.api_name);
		}
	});

	// Column drag handlers
	function handleColumnDragStart(index: number) {
		draggedColumnIndex = index;
	}

	function handleColumnDragOver(e: DragEvent, index: number) {
		e.preventDefault();
		if (draggedColumnIndex !== null && draggedColumnIndex !== index) {
			dragOverColumnIndex = index;
		}
	}

	function handleColumnDrop(targetIndex: number) {
		if (draggedColumnIndex === null || draggedColumnIndex === targetIndex) {
			resetColumnDragState();
			return;
		}

		const newOrder = [...defaultColumnOrder];
		const [movedItem] = newOrder.splice(draggedColumnIndex, 1);
		newOrder.splice(targetIndex, 0, movedItem);
		defaultColumnOrder = newOrder;
		resetColumnDragState();
	}

	function resetColumnDragState() {
		draggedColumnIndex = null;
		dragOverColumnIndex = null;
	}

	// Icon mapping for preview
	const ICON_MAP: Record<string, ComponentType> = {
		Box, Users, Building2, Briefcase, DollarSign, ShoppingCart, FileText,
		Mail, Phone, Calendar, Star, Folder, Settings2, Target, TrendingUp,
		BarChart3, Activity, Package, Truck, MapPin, Globe, Bell, Clipboard,
		Layers, Wallet, CreditCard, Database, Rocket, Home, Store
	};

	function getIconComponent(name: string): ComponentType | null {
		return ICON_MAP[name] || null;
	}

	// Mock available modules (in real app, fetch from API)
	let availableModules = $state([
		{ id: 1, name: 'Contacts', api_name: 'contacts' },
		{ id: 2, name: 'Companies', api_name: 'companies' },
		{ id: 3, name: 'Deals', api_name: 'deals' }
	]);

	function handleBlocksChange(newBlocks: CreateBlockRequest[]) {
		blocks = newBlocks;
	}

	function handleFieldSelect(blockIndex: number, fieldIndex: number) {
		selectedBlockIndex = blockIndex;
		selectedFieldIndex = fieldIndex;
		showFieldConfig = true;
	}

	function handleFieldChange(updatedField: CreateFieldRequest) {
		if (selectedBlockIndex >= 0 && selectedFieldIndex >= 0) {
			const updatedBlocks = [...blocks];
			const fields = [...(updatedBlocks[selectedBlockIndex].fields || [])];
			fields[selectedFieldIndex] = updatedField;
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
		// Allow navigating to any step freely
		currentStep = step;
	}

	async function handleSubmit() {
		error = null;

		// Final validation
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

			// Build settings with record_name_field
			const moduleSettings: Record<string, unknown> = {
				has_import: true,
				has_export: true,
				has_mass_actions: true,
				has_comments: true,
				has_attachments: true,
				has_activity_log: true,
				has_custom_views: true,
				record_name_field: recordNameField || null,
				default_column_order: defaultColumnOrder.length > 0 ? defaultColumnOrder : undefined
			};

			const request: CreateModuleRequest = {
				name: moduleName,
				singular_name: singularName,
				description: description || undefined,
				icon: icon || undefined,
				is_active: true,
				settings: moduleSettings as any,
				default_page_size: defaultPageSize,
				default_sorting: defaultSorting,
				default_column_visibility: columnVisibility,
				blocks
			};

			console.log('Creating module with request:', JSON.stringify(request, null, 2));
			const module = await modulesApi.create(request);
			console.log('Module created successfully:', module);
			toast.success('Module created successfully!');
			goto('/modules');
		} catch (err: any) {
			console.error('Module creation failed:', err);
			console.error('Error response:', err.response?.data);
			error = err.response?.data?.message || err.message || 'Failed to create module';

			// Show validation errors if present
			if (err.response?.data?.errors) {
				const errors = Object.values(err.response.data.errors).flat();
				error = errors.join(', ');
			}

			toast.error(error || 'Failed to create module');
		} finally {
			loading = false;
		}
	}

	// Auto-generate singular name from module name
	$effect(() => {
		if (moduleName && !singularName) {
			// Simple singularization (remove trailing 's' if present)
			const singular = moduleName.endsWith('s') ? moduleName.slice(0, -1) : moduleName;
			singularName = singular;
		}
	});
</script>

<svelte:head>
	<title>Create Module - Form Builder</title>
</svelte:head>

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
							Create New Module
						</h1>
						<p class="mt-0.5 text-sm text-muted-foreground">
							Build your custom module in 3 easy steps
						</p>
					</div>
				</div>
				<Button
					onclick={handleSubmit}
					disabled={loading || !isStep1Valid || !isStep2Valid}
					class="gap-2 bg-primary hover:bg-primary/90"
				>
					<Save class="h-4 w-4" />
					{loading ? 'Creating...' : 'Create Module'}
				</Button>
			</div>

			<!-- Tab Navigation -->
			<div class="pb-2">
				<div class="flex items-center gap-1 rounded-lg bg-muted/50 p-1">
					<button
						onclick={() => goToStep('details')}
						class="relative flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all {currentStep ===
						'details'
							? 'bg-background text-foreground shadow-sm'
							: 'text-muted-foreground hover:text-foreground'}"
					>
						{#if isStep1Valid}
							<CheckCircle2 class="h-4 w-4 text-green-600" />
						{:else}
							<Box class="h-4 w-4" />
						{/if}
						<span>Details</span>
						{#if !isStep1Valid}
							<span class="ml-1 flex h-2 w-2 rounded-full bg-amber-500"></span>
						{/if}
					</button>

					<button
						onclick={() => goToStep('builder')}
						class="relative flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all {currentStep ===
						'builder'
							? 'bg-background text-foreground shadow-sm'
							: 'text-muted-foreground hover:text-foreground'}"
					>
						{#if isStep2Valid}
							<CheckCircle2 class="h-4 w-4 text-green-600" />
						{:else}
							<Columns3 class="h-4 w-4" />
						{/if}
						<span>Fields</span>
						{#if availableFields.length > 0}
							<Badge variant="secondary" class="ml-1 h-5 px-1.5 text-xs">{availableFields.length}</Badge>
						{/if}
					</button>

					<button
						onclick={() => goToStep('settings')}
						class="relative flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-all {currentStep ===
						'settings'
							? 'bg-background text-foreground shadow-sm'
							: 'text-muted-foreground hover:text-foreground'}"
					>
						<Settings2 class="h-4 w-4" />
						<span>Table Settings</span>
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
			<!-- Step 1: Module Details - Two Column Layout -->
			<div class="h-full overflow-y-auto">
				<div class="container mx-auto px-4 py-6 md:px-6">
					<div class="grid gap-6 lg:grid-cols-5">
						<!-- Left: Form Fields -->
						<div class="space-y-6 lg:col-span-3">
							<!-- Module Name - Primary Input -->
							<div class="space-y-2">
								<Label for="module-name" class="text-base font-semibold">
									Module Name <span class="text-destructive">*</span>
								</Label>
								<Input
									id="module-name"
									bind:value={moduleName}
									placeholder="e.g., Opportunities, Projects, Invoices"
									data-testid="module-name"
									class="h-12 text-lg"
								/>
								<p class="text-sm text-muted-foreground">
									Plural name shown in navigation and list views
								</p>
							</div>

							<!-- Two Column Grid for Secondary Fields -->
							<div class="grid gap-4 sm:grid-cols-2">
								<div class="space-y-2">
									<Label for="singular-name" class="font-medium">
										Singular Name <span class="text-destructive">*</span>
									</Label>
									<Input
										id="singular-name"
										bind:value={singularName}
										placeholder="e.g., Opportunity"
										data-testid="singular-name"
									/>
									<p class="text-xs text-muted-foreground">Used for single records</p>
								</div>

								<div class="space-y-2">
									<Label for="icon" class="font-medium">Icon</Label>
									<IconPicker value={icon} onchange={(v) => (icon = v)} />
								</div>
							</div>

							<!-- Description -->
							<div class="space-y-2">
								<Label for="description" class="font-medium">Description</Label>
								<Textarea
									id="description"
									bind:value={description}
									placeholder="What does this module track? (optional)"
									rows={2}
									data-testid="module-description"
									class="resize-none"
								/>
							</div>
						</div>

						<!-- Right: Live Preview -->
						<div class="lg:col-span-2">
							<div class="sticky top-6">
								<div class="rounded-xl border-2 bg-card p-4">
									<p class="mb-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">Preview</p>

									<!-- Navigation Preview -->
									<div class="mb-4 rounded-lg bg-muted/50 p-3">
										<p class="mb-2 text-xs text-muted-foreground">Navigation Menu</p>
										<div class="flex items-center gap-3 rounded-md bg-background p-2.5 shadow-sm">
											{#if icon}
												{@const IconComponent = getIconComponent(icon)}
												{#if IconComponent}
													<IconComponent class="h-5 w-5 text-primary" />
												{:else}
													<Box class="h-5 w-5 text-muted-foreground" />
												{/if}
											{:else}
												<Box class="h-5 w-5 text-muted-foreground" />
											{/if}
											<span class="font-medium">{moduleName || 'Module Name'}</span>
										</div>
									</div>

									<!-- Page Header Preview -->
									<div class="mb-4 rounded-lg bg-muted/50 p-3">
										<p class="mb-2 text-xs text-muted-foreground">Page Header</p>
										<div class="space-y-1">
											<h3 class="text-lg font-semibold">{moduleName || 'Module Name'}</h3>
											{#if description}
												<p class="text-sm text-muted-foreground">{description}</p>
											{/if}
										</div>
									</div>

									<!-- Button Preview -->
									<div class="rounded-lg bg-muted/50 p-3">
										<p class="mb-2 text-xs text-muted-foreground">Create Button</p>
										<Button size="sm" class="gap-2">
											<span class="text-lg">+</span>
											New {singularName || 'Record'}
										</Button>
									</div>
								</div>

								<!-- Quick Stats -->
								{#if availableFields.length > 0}
									<div class="mt-4 rounded-lg border bg-card p-4">
										<p class="mb-3 text-xs font-medium uppercase tracking-wider text-muted-foreground">Module Summary</p>
										<div class="grid grid-cols-2 gap-3">
											<div class="rounded-md bg-muted/50 p-2 text-center">
												<p class="text-2xl font-bold text-primary">{blocks.length}</p>
												<p class="text-xs text-muted-foreground">Sections</p>
											</div>
											<div class="rounded-md bg-muted/50 p-2 text-center">
												<p class="text-2xl font-bold text-primary">{availableFields.length}</p>
												<p class="text-xs text-muted-foreground">Fields</p>
											</div>
										</div>
									</div>
								{/if}
							</div>
						</div>
					</div>
				</div>
			</div>
		{:else if currentStep === 'builder'}
			<!-- Step 2: Form Builder -->
			<div class="flex h-full overflow-hidden">
				<!-- Left: Field Palette -->
				<div class="hidden lg:block {showFieldConfig ? 'lg:block' : 'md:block'}">
					<FieldPalette />
				</div>

				<!-- Center: Form Canvas -->
				<FormCanvas
					bind:blocks
					onBlocksChange={handleBlocksChange}
					onFieldSelect={handleFieldSelect}
					{selectedBlockIndex}
					{selectedFieldIndex}
				/>

				<!-- Right: Field Config Panel -->
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

			<!-- Floating action button to continue -->
			{#if isStep2Valid}
				<div class="absolute right-6 bottom-6 lg:right-8 lg:bottom-8">
					<Button onclick={() => goToStep('settings')} size="lg" class="h-12 gap-2 px-6 shadow-2xl">
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
												<Select.Root
													type="single"
													value={defaultPageSize.toString()}
													onValueChange={(val) => {
														if (val) defaultPageSize = parseInt(val);
													}}
												>
													<Select.Trigger class="w-[140px]">
														<span>{defaultPageSize} rows</span>
													</Select.Trigger>
													<Select.Content>
														<Select.Item value="10">10 rows</Select.Item>
														<Select.Item value="25">25 rows</Select.Item>
														<Select.Item value="50">50 rows</Select.Item>
														<Select.Item value="100">100 rows</Select.Item>
														<Select.Item value="200">200 rows</Select.Item>
													</Select.Content>
												</Select.Root>
												<span class="text-sm text-muted-foreground">per page</span>
											</div>
											<p class="mt-2 text-sm text-muted-foreground">
												Number of records displayed per page in the datatable.
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

							<!-- Record Name Field -->
							<div
								class="rounded-xl border-2 border-dashed border-emerald-500/20 bg-emerald-500/5 p-6"
							>
								<div class="space-y-4">
									<div class="flex items-start gap-3">
										<div class="rounded-lg bg-emerald-500/10 p-2">
											<Tag class="h-5 w-5 text-emerald-600" />
										</div>
										<div class="flex-1">
											<Label class="mb-2 block text-base font-semibold">Record Name Field</Label>
											<p class="mb-4 text-sm text-muted-foreground">
												Choose which field identifies a record. This will be used as the primary display name in lists, links, and search results.
											</p>
											<Select.Root type="single" bind:value={recordNameField}>
												<Select.Trigger class="w-[280px]">
													{#if recordNameField}
														{availableFields.find((f) => f.api_name === recordNameField)?.label || 'Select field'}
													{:else}
														<span class="text-muted-foreground">Select a field...</span>
													{/if}
												</Select.Trigger>
												<Select.Content>
													{#each availableFields.filter(f => f.type === 'text' || f.type === 'email' || f.type === 'auto_number') as field}
														<Select.Item value={field.api_name}>{field.label}</Select.Item>
													{/each}
												</Select.Content>
											</Select.Root>
											<p class="mt-2 text-xs text-muted-foreground">
												Only text, email, and auto-number fields can be used as the record name.
											</p>
										</div>
									</div>
								</div>
							</div>

							<!-- Column Order & Visibility -->
							<div
								class="rounded-xl border-2 border-dashed border-purple-500/20 bg-purple-500/5 p-6"
							>
								<div class="space-y-4">
									<div class="flex items-start gap-3">
										<div class="rounded-lg bg-purple-500/10 p-2">
											<Columns3 class="h-5 w-5 text-purple-600" />
										</div>
										<div class="flex-1">
											<Label class="mb-2 block text-base font-semibold">
												Default Column Order & Visibility
											</Label>
											<p class="mb-4 text-sm text-muted-foreground">
												Drag to reorder columns and toggle visibility. This sets the default view for all users.
											</p>
											{#if orderedFields.length > 0}
												<div class="space-y-2">
													{#each orderedFields as field, index (field.api_name)}
														{@const isVisible = defaultColumnVisibility[field.api_name] !== false}
														{@const isDragging = draggedColumnIndex === index}
														{@const isDragOver = dragOverColumnIndex === index}
														<div
															role="listitem"
															draggable="true"
															ondragstart={() => handleColumnDragStart(index)}
															ondragover={(e) => handleColumnDragOver(e, index)}
															ondrop={() => handleColumnDrop(index)}
															ondragend={resetColumnDragState}
															class="flex items-center gap-3 rounded-lg border p-3 transition-all bg-card
																{isVisible
																	? 'border-border'
																	: 'border-muted opacity-60'}
																{isDragging ? 'opacity-50 scale-95' : ''}
																{isDragOver ? 'border-primary ring-2 ring-primary/20' : ''}"
														>
															<div class="cursor-grab active:cursor-grabbing">
																<GripVertical class="h-4 w-4 text-muted-foreground" />
															</div>
															<span class="flex h-6 w-6 items-center justify-center rounded bg-muted text-xs font-medium">
																{index + 1}
															</span>
															<div class="min-w-0 flex-1">
																<span class="block truncate text-sm font-medium">{field.label}</span>
																<span class="text-xs text-muted-foreground">{field.type}</span>
															</div>
															<button
																type="button"
																onclick={() => {
																	defaultColumnVisibility = {
																		...defaultColumnVisibility,
																		[field.api_name]: !isVisible
																	};
																}}
																class="rounded p-1.5 transition-colors hover:bg-background"
															>
																{#if isVisible}
																	<Eye class="h-4 w-4 text-purple-600" />
																{:else}
																	<EyeOff class="h-4 w-4 text-muted-foreground" />
																{/if}
															</button>
														</div>
													{/each}
												</div>
											{:else}
												<p class="text-sm text-muted-foreground italic">
													Add fields in the previous step to configure columns
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
										<strong class="text-foreground">Default filters</strong> can be configured after
										module creation by:
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
							<Button variant="outline" onclick={() => goToStep('builder')} size="lg" class="gap-2">
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
								{loading ? 'Creating Module...' : 'Create Module'}
							</Button>
						</div>
					</Card.Content>
				</Card.Root>
			</div>
		{/if}
	</div>
</div>
