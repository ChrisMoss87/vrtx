<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { page } from '$app/stores';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Switch } from '$lib/components/ui/switch';
	import { Badge } from '$lib/components/ui/badge';
	import {
		ArrowLeft,
		Plus,
		Save,
		Eye,
		Trash2,
		ChevronUp,
		ChevronDown,
		FileText,
		CheckCircle,
		GripVertical,
		Loader2
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getWizard,
		updateWizard,
		type Wizard,
		type WizardStep,
		type WizardSettings
	} from '$lib/api/wizards';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { getModuleFields, type Field } from '$lib/api/modules';
	import { generateId } from '$lib/utils/id';
	import WizardPreview from '$lib/components/wizard-builder/WizardPreview.svelte';

	interface StepConfig {
		id?: number;
		clientId: string;
		title: string;
		description: string;
		type: 'form' | 'review' | 'confirmation' | 'custom';
		fields: string[];
		can_skip: boolean;
		conditional_logic?: {
			enabled: boolean;
			skipIf?: { field: string; operator: string; value: unknown }[];
		};
	}

	const wizardId = $derived(parseInt($page.params.id));

	let wizard = $state<Wizard | null>(null);
	let modules = $state<Module[]>([]);
	let moduleFields = $state<Field[]>([]);
	let loading = $state(true);
	let saving = $state(false);
	let showPreview = $state(false);
	let activeTab = $state('design');

	// Form state
	let name = $state('');
	let description = $state('');
	let wizardType = $state<'record_creation' | 'record_edit' | 'standalone'>('record_creation');
	let moduleId = $state<string>('');
	let isActive = $state(true);
	let isDefault = $state(false);
	let settings = $state<WizardSettings>({
		showProgress: true,
		allowClickNavigation: false,
		saveAsDraft: true
	});

	let steps = $state<StepConfig[]>([]);
	let selectedStepId = $state<string>('');

	const selectedStep = $derived(steps.find((s) => s.clientId === selectedStepId) || steps[0]);

	const availableFields = $derived(
		moduleFields.filter((field) => {
			const assignedFields = steps
				.filter((s) => s.clientId !== selectedStepId)
				.flatMap((s) => s.fields);
			return !assignedFields.includes(field.id?.toString() ?? '');
		})
	);

	const assignedFields = $derived(
		(selectedStep?.fields || [])
			.map((fieldId) => moduleFields.find((f) => f.id?.toString() === fieldId))
			.filter((f): f is Field => f !== undefined)
	);

	async function loadData() {
		loading = true;
		try {
			const [wizardData, modulesData] = await Promise.all([
				getWizard(wizardId),
				getActiveModules()
			]);

			wizard = wizardData;
			modules = modulesData;

			// Populate form state
			name = wizardData.name;
			description = wizardData.description || '';
			wizardType = wizardData.type;
			moduleId = wizardData.module?.id?.toString() || '';
			isActive = wizardData.is_active;
			isDefault = wizardData.is_default;
			settings = wizardData.settings;

			// Convert steps to internal format
			steps = wizardData.steps.map((step) => ({
				id: step.id,
				clientId: generateId(),
				title: step.title,
				description: step.description || '',
				type: step.type,
				fields: step.fields,
				can_skip: step.can_skip,
				conditional_logic: step.conditional_logic
			}));

			if (steps.length > 0) {
				selectedStepId = steps[0].clientId;
			}

			// Load module fields if module is set
			if (moduleId) {
				await loadModuleFields(moduleId);
			}
		} catch (error) {
			console.error('Failed to load wizard:', error);
			toast.error('Failed to load wizard');
			goto('/wizards');
		} finally {
			loading = false;
		}
	}

	async function loadModuleFields(modId: string) {
		if (!modId) {
			moduleFields = [];
			return;
		}
		try {
			const module = modules.find((m) => m.id.toString() === modId);
			if (module) {
				moduleFields = await getModuleFields(module.api_name);
			}
		} catch (error) {
			console.error('Failed to load module fields:', error);
		}
	}

	function addStep() {
		const newStep: StepConfig = {
			clientId: generateId(),
			title: `Step ${steps.length + 1}`,
			description: '',
			type: 'form',
			fields: [],
			can_skip: false
		};
		steps = [...steps, newStep];
		selectedStepId = newStep.clientId;
	}

	function removeStep(clientId: string) {
		if (steps.length <= 1) return;
		steps = steps.filter((s) => s.clientId !== clientId);
		if (selectedStepId === clientId) {
			selectedStepId = steps[0].clientId;
		}
	}

	function moveStep(clientId: string, direction: 'up' | 'down') {
		const index = steps.findIndex((s) => s.clientId === clientId);
		if (direction === 'up' && index > 0) {
			const newSteps = [...steps];
			[newSteps[index - 1], newSteps[index]] = [newSteps[index], newSteps[index - 1]];
			steps = newSteps;
		} else if (direction === 'down' && index < steps.length - 1) {
			const newSteps = [...steps];
			[newSteps[index], newSteps[index + 1]] = [newSteps[index + 1], newSteps[index]];
			steps = newSteps;
		}
	}

	function updateStep(clientId: string, updates: Partial<StepConfig>) {
		steps = steps.map((s) => (s.clientId === clientId ? { ...s, ...updates } : s));
	}

	function assignFieldToStep(fieldId: string) {
		if (!selectedStep) return;
		steps = steps.map((s) =>
			s.clientId === selectedStepId ? { ...s, fields: [...s.fields, fieldId] } : s
		);
	}

	function removeFieldFromStep(fieldId: string) {
		if (!selectedStep) return;
		steps = steps.map((s) =>
			s.clientId === selectedStepId ? { ...s, fields: s.fields.filter((f) => f !== fieldId) } : s
		);
	}

	async function handleSave() {
		if (!name.trim()) {
			toast.error('Please enter a wizard name');
			return;
		}

		if (wizardType !== 'standalone' && !moduleId) {
			toast.error('Please select a module');
			return;
		}

		saving = true;
		try {
			await updateWizard(wizardId, {
				name,
				description,
				type: wizardType,
				module_id: moduleId ? parseInt(moduleId) : undefined,
				is_active: isActive,
				is_default: isDefault,
				settings,
				steps: steps.map((step) => ({
					id: step.id,
					title: step.title,
					description: step.description,
					type: step.type,
					fields: step.fields,
					can_skip: step.can_skip,
					conditional_logic: step.conditional_logic
				}))
			});

			toast.success('Wizard updated successfully');
		} catch (error) {
			console.error('Failed to update wizard:', error);
			toast.error('Failed to update wizard');
		} finally {
			saving = false;
		}
	}

	const stepTypeOptions = [
		{ value: 'form', label: 'Form Step', icon: FileText },
		{ value: 'review', label: 'Review Step', icon: Eye },
		{ value: 'confirmation', label: 'Confirmation', icon: CheckCircle }
	];

	onMount(() => {
		loadData();
	});

	$effect(() => {
		if (moduleId && modules.length > 0) {
			loadModuleFields(moduleId);
		}
	});
</script>

{#if loading}
	<div class="flex h-96 items-center justify-center">
		<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
	</div>
{:else}
	<div class="container mx-auto py-8">
		<!-- Header -->
		<div class="mb-8">
			<div class="flex items-center gap-4">
				<Button variant="ghost" size="icon" onclick={() => goto('/wizards')}>
					<ArrowLeft class="h-4 w-4" />
				</Button>
				<div class="flex-1">
					<h1 class="text-3xl font-bold">Edit Wizard</h1>
					<p class="mt-1 text-muted-foreground">{wizard?.name}</p>
				</div>
				<div class="flex items-center gap-2">
					<Button variant="outline" onclick={() => (showPreview = true)}>
						<Eye class="mr-2 h-4 w-4" />
						Preview
					</Button>
					<Button onclick={handleSave} disabled={saving}>
						{#if saving}
							<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							Saving...
						{:else}
							<Save class="mr-2 h-4 w-4" />
							Save Changes
						{/if}
					</Button>
				</div>
			</div>
		</div>

		<!-- Basic Info Card -->
		<Card.Root class="mb-6">
			<Card.Header>
				<Card.Title>Basic Information</Card.Title>
				<Card.Description>Configure wizard settings and target module</Card.Description>
			</Card.Header>
			<Card.Content>
				<div class="grid gap-6 md:grid-cols-2">
					<div class="space-y-2">
						<Label for="name">Wizard Name *</Label>
						<Input id="name" bind:value={name} placeholder="e.g., New Contact Wizard" />
					</div>

					<div class="space-y-2">
						<Label for="type">Wizard Type *</Label>
						<Select.Root type="single" bind:value={wizardType}>
							<Select.Trigger>
								{wizardType === 'record_creation'
									? 'Record Creation'
									: wizardType === 'record_edit'
										? 'Record Edit'
										: 'Standalone'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="record_creation">Record Creation</Select.Item>
								<Select.Item value="record_edit">Record Edit</Select.Item>
								<Select.Item value="standalone">Standalone</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>

					{#if wizardType !== 'standalone'}
						<div class="space-y-2">
							<Label for="module">Module *</Label>
							<Select.Root type="single" bind:value={moduleId}>
								<Select.Trigger>
									{moduleId
										? modules.find((m) => m.id.toString() === moduleId)?.name
										: 'Select module...'}
								</Select.Trigger>
								<Select.Content>
									{#each modules as module}
										<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
					{/if}

					<div class="space-y-2 md:col-span-2">
						<Label for="description">Description</Label>
						<Textarea
							id="description"
							bind:value={description}
							placeholder="Brief description of this wizard"
							rows={2}
						/>
					</div>

					<div class="flex items-center gap-6">
						<div class="flex items-center gap-2">
							<Switch id="isActive" bind:checked={isActive} />
							<Label for="isActive">Active</Label>
						</div>
						<div class="flex items-center gap-2">
							<Switch id="isDefault" bind:checked={isDefault} />
							<Label for="isDefault">Set as Default</Label>
						</div>
					</div>
				</div>
			</Card.Content>
		</Card.Root>

		<!-- Builder Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List class="mb-4">
				<Tabs.Trigger value="design">Design Steps</Tabs.Trigger>
				<Tabs.Trigger value="settings">Settings</Tabs.Trigger>
			</Tabs.List>

			<!-- Design Tab -->
			<Tabs.Content value="design">
				<div class="grid gap-6 lg:grid-cols-3">
					<!-- Steps List -->
					<Card.Root class="lg:col-span-1">
						<Card.Header>
							<div class="flex items-center justify-between">
								<Card.Title>Steps</Card.Title>
								<Button size="sm" onclick={addStep}>
									<Plus class="h-4 w-4" />
								</Button>
							</div>
						</Card.Header>
						<Card.Content>
							<div class="space-y-2">
								{#each steps as step, index}
									<button
										type="button"
										onclick={() => (selectedStepId = step.clientId)}
										class="w-full rounded-lg border p-3 text-left transition-colors {selectedStepId ===
										step.clientId
											? 'border-primary bg-primary/10'
											: 'border-border bg-card hover:bg-accent'}"
									>
										<div class="flex items-center gap-2">
											<GripVertical class="h-4 w-4 text-muted-foreground" />
											<div class="flex-1">
												<div class="font-medium">
													{index + 1}. {step.title}
												</div>
												<div class="mt-1 text-xs text-muted-foreground">
													{step.type} - {step.fields.length} field{step.fields.length !== 1
														? 's'
														: ''}
												</div>
											</div>
										</div>
									</button>
								{/each}
							</div>
						</Card.Content>
					</Card.Root>

					<!-- Step Configuration -->
					<Card.Root class="lg:col-span-2">
						{#if selectedStep}
							<Card.Header>
								<div class="flex items-center justify-between">
									<Card.Title>Step Configuration</Card.Title>
									<div class="flex items-center gap-1">
										<Button
											size="sm"
											variant="outline"
											onclick={() => moveStep(selectedStep.clientId, 'up')}
											disabled={steps.indexOf(selectedStep) === 0}
										>
											<ChevronUp class="h-4 w-4" />
										</Button>
										<Button
											size="sm"
											variant="outline"
											onclick={() => moveStep(selectedStep.clientId, 'down')}
											disabled={steps.indexOf(selectedStep) === steps.length - 1}
										>
											<ChevronDown class="h-4 w-4" />
										</Button>
										<Button
											size="sm"
											variant="destructive"
											onclick={() => removeStep(selectedStep.clientId)}
											disabled={steps.length <= 1}
										>
											<Trash2 class="h-4 w-4" />
										</Button>
									</div>
								</div>
							</Card.Header>
							<Card.Content class="space-y-6">
								<!-- Title & Description -->
								<div class="grid gap-4 md:grid-cols-2">
									<div class="space-y-2">
										<Label>Step Title *</Label>
										<Input
											value={selectedStep.title}
											oninput={(e) =>
												updateStep(selectedStep.clientId, { title: e.currentTarget.value })}
											placeholder="e.g., Personal Information"
										/>
									</div>
									<div class="space-y-2">
										<Label>Step Type</Label>
										<Select.Root
											type="single"
											value={selectedStep.type}
											onValueChange={(v) =>
												updateStep(selectedStep.clientId, { type: v as StepConfig['type'] })}
										>
											<Select.Trigger>
												{stepTypeOptions.find((o) => o.value === selectedStep.type)?.label}
											</Select.Trigger>
											<Select.Content>
												{#each stepTypeOptions as option}
													<Select.Item value={option.value}>{option.label}</Select.Item>
												{/each}
											</Select.Content>
										</Select.Root>
									</div>
								</div>

								<div class="space-y-2">
									<Label>Description</Label>
									<Textarea
										value={selectedStep.description}
										oninput={(e) =>
											updateStep(selectedStep.clientId, { description: e.currentTarget.value })}
										placeholder="Brief description of this step"
										rows={2}
									/>
								</div>

								<div class="flex items-center gap-2">
									<Switch
										checked={selectedStep.can_skip}
										onCheckedChange={(checked) =>
											updateStep(selectedStep.clientId, { can_skip: checked })}
									/>
									<Label>Allow users to skip this step</Label>
								</div>

								<!-- Fields (only for form type) -->
								{#if selectedStep.type === 'form' && moduleId}
									<div class="space-y-3">
										<div class="flex items-center justify-between">
											<Label>Fields ({assignedFields.length})</Label>
										</div>

										<!-- Assigned Fields -->
										{#if assignedFields.length > 0}
											<div class="space-y-2">
												{#each assignedFields as field}
													<div
														class="flex items-center justify-between rounded-lg border bg-card p-2"
													>
														<div class="flex items-center gap-2">
															<Badge variant="secondary" class="text-xs">{field.type}</Badge>
															<span class="text-sm">{field.label}</span>
															{#if field.is_required}
																<span class="text-xs text-destructive">*</span>
															{/if}
														</div>
														<Button
															size="sm"
															variant="ghost"
															onclick={() => removeFieldFromStep(field.id?.toString() ?? '')}
														>
															<Trash2 class="h-4 w-4" />
														</Button>
													</div>
												{/each}
											</div>
										{/if}

										<!-- Available Fields -->
										{#if availableFields.length > 0}
											<div class="space-y-2 rounded-lg border bg-muted/30 p-3">
												<div class="text-sm font-medium">Available Fields</div>
												<div class="grid gap-1 max-h-48 overflow-y-auto">
													{#each availableFields as field}
														<button
															type="button"
															onclick={() => assignFieldToStep(field.id?.toString() ?? '')}
															class="flex items-center justify-between rounded p-2 text-sm hover:bg-accent text-left"
														>
															<div class="flex items-center gap-2">
																<Badge variant="outline" class="text-xs">{field.type}</Badge>
																<span>{field.label}</span>
															</div>
															<Plus class="h-4 w-4 text-muted-foreground" />
														</button>
													{/each}
												</div>
											</div>
										{/if}
									</div>
								{:else if selectedStep.type === 'form' && !moduleId}
									<div
										class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
									>
										Select a module to assign fields to this step
									</div>
								{:else if selectedStep.type === 'review'}
									<div
										class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
									>
										Review steps automatically display all data from previous steps
									</div>
								{:else if selectedStep.type === 'confirmation'}
									<div
										class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
									>
										Confirmation steps display a success message after completion
									</div>
								{/if}
							</Card.Content>
						{/if}
					</Card.Root>
				</div>
			</Tabs.Content>

			<!-- Settings Tab -->
			<Tabs.Content value="settings">
				<Card.Root>
					<Card.Header>
						<Card.Title>Wizard Settings</Card.Title>
						<Card.Description>Configure wizard behavior and appearance</Card.Description>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="flex items-center justify-between">
							<div>
								<div class="font-medium">Show Progress Bar</div>
								<div class="text-sm text-muted-foreground">
									Display progress indicator at the top
								</div>
							</div>
							<Switch bind:checked={settings.showProgress} />
						</div>

						<div class="flex items-center justify-between">
							<div>
								<div class="font-medium">Allow Click Navigation</div>
								<div class="text-sm text-muted-foreground">
									Let users click on step indicators to navigate
								</div>
							</div>
							<Switch bind:checked={settings.allowClickNavigation} />
						</div>

						<div class="flex items-center justify-between">
							<div>
								<div class="font-medium">Save as Draft</div>
								<div class="text-sm text-muted-foreground">Auto-save user progress</div>
							</div>
							<Switch bind:checked={settings.saveAsDraft} />
						</div>
					</Card.Content>
				</Card.Root>
			</Tabs.Content>
		</Tabs.Root>
	</div>

	<!-- Preview Dialog -->
	{#if showPreview}
		<WizardPreview
			config={{
				name,
				description,
				steps: steps.map((s) => ({
					id: s.clientId,
					title: s.title,
					description: s.description,
					type: s.type as 'form' | 'review' | 'confirmation',
					fields: s.fields,
					canSkip: s.can_skip,
					order: steps.indexOf(s)
				})),
				settings
			}}
			{moduleFields}
			onClose={() => (showPreview = false)}
		/>
	{/if}
{/if}
