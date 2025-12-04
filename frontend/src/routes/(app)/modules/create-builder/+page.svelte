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
		ArrowDown
	} from 'lucide-svelte';
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

			const request: CreateModuleRequest = {
				name: moduleName,
				singular_name: singularName,
				description: description || undefined,
				icon: icon || undefined,
				is_active: true,
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
							class="flex h-6 w-6 items-center justify-center rounded-lg {currentStep === 'settings'
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
									Define the basic details and metadata for your module
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
									data-testid="module-name"
									class="h-12 border-2 text-lg focus-visible:ring-2 focus-visible:ring-primary/20"
								/>
								<p class="text-xs text-muted-foreground">
									The plural name of your module as it will appear in navigation
								</p>
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
									data-testid="singular-name"
									class="h-11 border-2 focus-visible:ring-2 focus-visible:ring-primary/20"
								/>
								<p class="text-xs text-muted-foreground">Used in forms and single record views</p>
							</div>

							<div class="space-y-2">
								<Label for="icon" class="text-sm font-semibold">Icon (Optional)</Label>
								<Input
									id="icon"
									bind:value={icon}
									placeholder="e.g., TrendingUp, Folder, FileText"
									data-testid="module-icon"
									class="h-11 border-2 focus-visible:ring-2 focus-visible:ring-primary/20"
								/>
								<p class="text-xs text-muted-foreground">Lucide icon name for navigation menu</p>
							</div>

							<div class="space-y-2 md:col-span-2">
								<Label for="description" class="text-sm font-semibold">
									Description (Optional)
								</Label>
								<Textarea
									id="description"
									bind:value={description}
									placeholder="Brief description of what this module manages and its purpose..."
									rows={3}
									data-testid="module-description"
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
												Choose which columns are visible by default. Users can always toggle columns
												in the datatable.
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
																<span class="block truncate text-sm font-medium">{field.label}</span
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
