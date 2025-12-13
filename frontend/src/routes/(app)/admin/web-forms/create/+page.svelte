<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import {
		ArrowLeft,
		Loader2,
		Mail,
		UserPlus,
		Users,
		Building,
		Building2,
		DollarSign,
		LifeBuoy,
		MessageSquare,
		CheckSquare,
		Calendar,
		FileText,
		Sparkles
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { WebFormBuilder } from '$lib/components/web-forms';
	import {
		createWebForm,
		getModulesForForms,
		type ModuleForForm,
		type WebFormData,
		type WebFormTemplate,
		FORM_TEMPLATES,
		getTemplatesForModule,
		generateFieldsFromTemplate,
		getDefaultStyling,
		getDefaultSettings
	} from '$lib/api/web-forms';

	let modules = $state<ModuleForForm[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let selectedModule = $state<ModuleForForm | null>(null);
	let selectedTemplate = $state<WebFormTemplate | null>(null);
	let showBuilder = $state(false);

	// Templates available for selected module
	let availableTemplates = $derived(
		selectedModule ? getTemplatesForModule(selectedModule.api_name) : []
	);

	// Initial form data from template
	let initialFormData = $state<Partial<WebFormData> | null>(null);

	// Icon mapping
	const iconMap: Record<string, typeof Mail> = {
		mail: Mail,
		'user-plus': UserPlus,
		users: Users,
		building: Building,
		'building-2': Building2,
		'dollar-sign': DollarSign,
		'life-buoy': LifeBuoy,
		'message-square': MessageSquare,
		'check-square': CheckSquare,
		calendar: Calendar,
		'file-text': FileText
	};

	onMount(async () => {
		try {
			modules = await getModulesForForms();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loading = false;
		}
	});

	function selectModule(moduleId: string) {
		const module = modules.find((m) => m.id === parseInt(moduleId));
		selectedModule = module ?? null;
		selectedTemplate = null;
	}

	function selectTemplate(template: WebFormTemplate) {
		if (!selectedModule) return;

		selectedTemplate = template;
		const fields = generateFieldsFromTemplate(template, selectedModule);

		initialFormData = {
			name: '',
			module_id: selectedModule.id,
			settings: { ...getDefaultSettings(), ...template.settings },
			styling: { ...getDefaultStyling(), ...template.styling },
			fields
		};
		showBuilder = true;
	}

	function startFromScratch() {
		if (!selectedModule) return;

		selectedTemplate = null;
		initialFormData = {
			name: '',
			module_id: selectedModule.id,
			settings: getDefaultSettings(),
			styling: getDefaultStyling(),
			fields: []
		};
		showBuilder = true;
	}

	function backToTemplates() {
		showBuilder = false;
		selectedTemplate = null;
		initialFormData = null;
	}

	function backToModuleSelection() {
		selectedModule = null;
		selectedTemplate = null;
		initialFormData = null;
		showBuilder = false;
	}

	async function handleSave(data: WebFormData) {
		saving = true;
		try {
			const form = await createWebForm(data);
			toast.success('Form created successfully');
			goto(`/admin/web-forms/${form.id}/edit`);
		} catch (error) {
			console.error('Failed to create form:', error);
			toast.error('Failed to create form');
		} finally {
			saving = false;
		}
	}

	function handleCancel() {
		if (showBuilder) {
			backToTemplates();
		} else if (selectedModule) {
			backToModuleSelection();
		} else {
			goto('/admin/web-forms');
		}
	}
</script>

<svelte:head>
	<title>Create Web Form | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={handleCancel}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold">
				{#if showBuilder}
					{selectedTemplate ? `Create: ${selectedTemplate.name}` : 'Create Web Form'}
				{:else if selectedModule}
					Choose a Template for {selectedModule.name}
				{:else}
					Create Web Form
				{/if}
			</h1>
			<p class="text-muted-foreground">
				{#if showBuilder}
					Design your form to capture {selectedModule?.singular_name ?? 'records'}
				{:else if selectedModule}
					Select a template or start from scratch
				{:else}
					First, choose which module this form will create records in
				{/if}
			</p>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
		</div>
	{:else if showBuilder && selectedModule}
		<div class="h-[calc(100vh-200px)]">
			<WebFormBuilder
				{modules}
				form={initialFormData
					? {
							id: 0,
							name: initialFormData.name ?? '',
							slug: '',
							description: null,
							module: {
								id: selectedModule.id,
								name: selectedModule.name,
								api_name: selectedModule.api_name
							},
							is_active: true,
							public_url: '',
							submission_count: 0,
							created_by: null,
							created_at: '',
							updated_at: '',
							settings: initialFormData.settings,
							styling: initialFormData.styling,
							fields: initialFormData.fields
						}
					: null}
				onSave={handleSave}
				onCancel={handleCancel}
				{saving}
			/>
		</div>
	{:else if selectedModule}
		<!-- Template Selection for chosen module -->
		<div class="space-y-6">
			<!-- Module indicator -->
			<Card.Root class="bg-muted/50">
				<Card.Content class="flex items-center justify-between p-4">
					<div class="flex items-center gap-3">
						<Badge variant="secondary" class="text-sm">Target Module</Badge>
						<span class="font-medium">{selectedModule.name}</span>
						<span class="text-sm text-muted-foreground">
							({selectedModule.fields.length} fields available)
						</span>
					</div>
					<Button variant="ghost" size="sm" onclick={backToModuleSelection}>Change Module</Button>
				</Card.Content>
			</Card.Root>

			<!-- Start from scratch option -->
			<Card.Root
				class="cursor-pointer border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50"
				onclick={startFromScratch}
			>
				<Card.Content class="flex items-center gap-4 p-6">
					<div
						class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary"
					>
						<Sparkles class="h-6 w-6" />
					</div>
					<div class="flex-1">
						<h3 class="font-semibold">Start from Scratch</h3>
						<p class="text-sm text-muted-foreground">
							Build a custom form by selecting fields from {selectedModule.name}
						</p>
					</div>
					<Button variant="outline">Create Blank Form</Button>
				</Card.Content>
			</Card.Root>

			<!-- Available templates for this module -->
			{#if availableTemplates.length > 0}
				<div>
					<h2 class="mb-4 text-lg font-semibold">Templates for {selectedModule.name}</h2>
					<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
						{#each availableTemplates as template}
							{@const IconComponent = iconMap[template.icon] || FileText}
							<Card.Root
								class="cursor-pointer transition-all hover:border-primary hover:shadow-md"
								onclick={() => selectTemplate(template)}
							>
								<Card.Header class="pb-2">
									<div class="flex items-start justify-between">
										<div
											class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"
										>
											<IconComponent class="h-5 w-5" />
										</div>
									</div>
									<Card.Title class="mt-3 text-base">{template.name}</Card.Title>
									<Card.Description class="text-sm">{template.description}</Card.Description>
								</Card.Header>
								<Card.Content class="pt-0">
									<div class="flex items-center gap-1 text-xs text-muted-foreground">
										<span>{template.fieldMappings.length} fields</span>
										<span>•</span>
										<span>
											{template.fieldMappings.filter((f) => f.is_required).length} required
										</span>
									</div>
								</Card.Content>
							</Card.Root>
						{/each}
					</div>
				</div>
			{:else}
				<Card.Root class="border-dashed">
					<Card.Content class="flex flex-col items-center justify-center py-12 text-center">
						<FileText class="mb-4 h-12 w-12 text-muted-foreground" />
						<h3 class="mb-2 text-lg font-medium">No templates for {selectedModule.name}</h3>
						<p class="mb-4 text-muted-foreground">
							Start from scratch to create a custom form for this module
						</p>
						<Button onclick={startFromScratch}>Create Blank Form</Button>
					</Card.Content>
				</Card.Root>
			{/if}
		</div>
	{:else}
		<!-- Module Selection -->
		<div class="mx-auto max-w-2xl space-y-6">
			<Card.Root>
				<Card.Header>
					<Card.Title>Select Target Module</Card.Title>
					<Card.Description>
						Choose which module this form will create records in. Form submissions will create new
						records in this module.
					</Card.Description>
				</Card.Header>
				<Card.Content>
					<div class="grid gap-3">
						{#each modules as module}
							<button
								class="flex items-center gap-4 rounded-lg border p-4 text-left transition-colors hover:border-primary hover:bg-muted/50"
								onclick={() => selectModule(String(module.id))}
							>
								<div
									class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"
								>
									{#if module.api_name === 'contacts'}
										<UserPlus class="h-5 w-5" />
									{:else if module.api_name === 'organizations'}
										<Building class="h-5 w-5" />
									{:else if module.api_name === 'deals'}
										<DollarSign class="h-5 w-5" />
									{:else if module.api_name === 'cases'}
										<LifeBuoy class="h-5 w-5" />
									{:else if module.api_name === 'tasks'}
										<CheckSquare class="h-5 w-5" />
									{:else if module.api_name === 'events'}
										<Calendar class="h-5 w-5" />
									{:else}
										<FileText class="h-5 w-5" />
									{/if}
								</div>
								<div class="flex-1">
									<h3 class="font-medium">{module.name}</h3>
									<p class="text-sm text-muted-foreground">
										{module.fields.length} fields •
										{getTemplatesForModule(module.api_name).length} templates available
									</p>
								</div>
								<Badge variant="secondary">{module.singular_name}</Badge>
							</button>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>
		</div>
	{/if}
</div>
