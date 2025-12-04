<script lang="ts">
	import type { Snippet } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import { Plus, Eye, Save } from 'lucide-svelte';
	import StepConfigPanel from './StepConfigPanel.svelte';
	import WizardPreview from './WizardPreview.svelte';
	import type { Field } from '$lib/types/modules';
	import { generateId } from '$lib/utils/id';

	interface WizardStepConfig {
		id: string;
		title: string;
		description: string;
		type: 'form' | 'review' | 'confirmation';
		fields: string[]; // Field IDs assigned to this step
		canSkip: boolean;
		order: number;
		conditionalLogic?: {
			enabled: boolean;
			skipIf?: {
				field: string;
				operator: string;
				value: any;
			}[];
		};
	}

	interface WizardConfig {
		name: string;
		description: string;
		steps: WizardStepConfig[];
		settings: {
			showProgress: boolean;
			allowClickNavigation: boolean;
			saveAsDraft: boolean;
		};
	}

	interface Props {
		moduleFields?: Field[];
		initialConfig?: WizardConfig;
		onSave?: (config: WizardConfig) => void;
		onPreview?: (config: WizardConfig) => void;
	}

	let { moduleFields = [], initialConfig, onSave, onPreview }: Props = $props();

	// Wizard configuration state
	let wizardConfig = $state<WizardConfig>(
		initialConfig || {
			name: '',
			description: '',
			steps: [
				{
					id: generateId(),
					title: 'Step 1',
					description: '',
					type: 'form',
					fields: [],
					canSkip: false,
					order: 0
				}
			],
			settings: {
				showProgress: true,
				allowClickNavigation: false,
				saveAsDraft: true
			}
		}
	);

	let selectedStepId = $state<string | null>(wizardConfig.steps[0]?.id || null);
	let activeTab = $state<string>('design');
	let showPreview = $state(false);

	const selectedStep = $derived(
		wizardConfig.steps.find((s) => s.id === selectedStepId) || wizardConfig.steps[0]
	);

	const availableFields = $derived(
		moduleFields.filter((field) => {
			// Filter out fields already assigned to other steps
			const assignedFields = wizardConfig.steps
				.filter((s) => s.id !== selectedStepId)
				.flatMap((s) => s.fields);
			return !assignedFields.includes(field.id?.toString() ?? '');
		})
	);

	function addStep() {
		const newStep: WizardStepConfig = {
			id: generateId(),
			title: `Step ${wizardConfig.steps.length + 1}`,
			description: '',
			type: 'form',
			fields: [],
			canSkip: false,
			order: wizardConfig.steps.length
		};
		wizardConfig.steps = [...wizardConfig.steps, newStep];
		selectedStepId = newStep.id;
	}

	function removeStep(stepId: string) {
		if (wizardConfig.steps.length <= 1) return; // Keep at least one step
		wizardConfig.steps = wizardConfig.steps.filter((s) => s.id !== stepId);
		// Reorder remaining steps
		wizardConfig.steps = wizardConfig.steps.map((s, idx) => ({ ...s, order: idx }));
		// Select first step if current was deleted
		if (selectedStepId === stepId) {
			selectedStepId = wizardConfig.steps[0]?.id || null;
		}
	}

	function moveStepUp(stepId: string) {
		const index = wizardConfig.steps.findIndex((s) => s.id === stepId);
		if (index > 0) {
			const steps = [...wizardConfig.steps];
			[steps[index - 1], steps[index]] = [steps[index], steps[index - 1]];
			wizardConfig.steps = steps.map((s, idx) => ({ ...s, order: idx }));
		}
	}

	function moveStepDown(stepId: string) {
		const index = wizardConfig.steps.findIndex((s) => s.id === stepId);
		if (index < wizardConfig.steps.length - 1) {
			const steps = [...wizardConfig.steps];
			[steps[index], steps[index + 1]] = [steps[index + 1], steps[index]];
			wizardConfig.steps = steps.map((s, idx) => ({ ...s, order: idx }));
		}
	}

	function updateStep(stepId: string, updates: Partial<WizardStepConfig>) {
		wizardConfig.steps = wizardConfig.steps.map((s) =>
			s.id === stepId ? { ...s, ...updates } : s
		);
	}

	function assignFieldToStep(stepId: string, fieldId: string) {
		wizardConfig.steps = wizardConfig.steps.map((s) =>
			s.id === stepId ? { ...s, fields: [...s.fields, fieldId] } : s
		);
	}

	function removeFieldFromStep(stepId: string, fieldId: string) {
		wizardConfig.steps = wizardConfig.steps.map((s) =>
			s.id === stepId ? { ...s, fields: s.fields.filter((f) => f !== fieldId) } : s
		);
	}

	function handleSave() {
		if (onSave) {
			onSave(wizardConfig);
		}
	}

	function handlePreview() {
		showPreview = true;
		if (onPreview) {
			onPreview(wizardConfig);
		}
	}
</script>

<div class="wizard-builder space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h2 class="text-2xl font-bold tracking-tight">Wizard Builder</h2>
			<p class="text-muted-foreground">Create multi-step forms with progress tracking</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" onclick={handlePreview}>
				<Eye class="mr-2 h-4 w-4" />
				Preview
			</Button>
			<Button onclick={handleSave}>
				<Save class="mr-2 h-4 w-4" />
				Save Wizard
			</Button>
		</div>
	</div>

	<!-- Wizard Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Wizard Information</Card.Title>
			<Card.Description>Basic details about your wizard</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="space-y-2">
				<Label for="wizardName">Wizard Name</Label>
				<Input
					id="wizardName"
					bind:value={wizardConfig.name}
					placeholder="e.g., New Contact Wizard"
				/>
			</div>
			<div class="space-y-2">
				<Label for="wizardDescription">Description</Label>
				<Input
					id="wizardDescription"
					bind:value={wizardConfig.description}
					placeholder="Brief description of this wizard"
				/>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Main Builder Interface -->
	<Tabs.Root bind:value={activeTab}>
		<Tabs.List class="grid w-full grid-cols-2">
			<Tabs.Trigger value="design">Design</Tabs.Trigger>
			<Tabs.Trigger value="settings">Settings</Tabs.Trigger>
		</Tabs.List>

		<!-- Design Tab -->
		<Tabs.Content value="design" class="space-y-4">
			<div class="grid grid-cols-3 gap-6">
				<!-- Steps List -->
				<Card.Root class="col-span-1">
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
							{#each wizardConfig.steps as step, index}
								<button
									type="button"
									onclick={() => (selectedStepId = step.id)}
									class="w-full rounded-lg border p-3 text-left transition-colors {selectedStepId ===
									step.id
										? 'border-primary bg-primary text-primary-foreground'
										: 'border-border bg-card hover:bg-accent'}"
								>
									<div class="flex items-center justify-between">
										<div class="flex-1">
											<div class="font-medium">
												{index + 1}. {step.title}
											</div>
											{#if step.description}
												<div class="mt-1 text-xs opacity-80">{step.description}</div>
											{/if}
											<div class="mt-1 text-xs opacity-70">
												{step.fields.length} field{step.fields.length !== 1 ? 's' : ''}
												{#if step.canSkip}· Skippable{/if}
											</div>
										</div>
									</div>
								</button>
							{/each}
						</div>
					</Card.Content>
				</Card.Root>

				<!-- Step Configuration -->
				<div class="col-span-2">
					{#if selectedStep}
						<StepConfigPanel
							step={selectedStep}
							{availableFields}
							{moduleFields}
							onUpdate={(updates) => updateStep(selectedStep.id, updates)}
							onAssignField={(fieldId) => assignFieldToStep(selectedStep.id, fieldId)}
							onRemoveField={(fieldId) => removeFieldFromStep(selectedStep.id, fieldId)}
							onMoveUp={() => moveStepUp(selectedStep.id)}
							onMoveDown={() => moveStepDown(selectedStep.id)}
							onDelete={() => removeStep(selectedStep.id)}
							canMoveUp={selectedStep.order > 0}
							canMoveDown={selectedStep.order < wizardConfig.steps.length - 1}
							canDelete={wizardConfig.steps.length > 1}
						/>
					{/if}
				</div>
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
							<div class="text-sm text-muted-foreground">Display progress indicator at the top</div>
						</div>
						<input
							type="checkbox"
							bind:checked={wizardConfig.settings.showProgress}
							class="h-4 w-4"
						/>
					</div>

					<div class="flex items-center justify-between">
						<div>
							<div class="font-medium">Allow Click Navigation</div>
							<div class="text-sm text-muted-foreground">
								Let users click on step indicators to navigate
							</div>
						</div>
						<input
							type="checkbox"
							bind:checked={wizardConfig.settings.allowClickNavigation}
							class="h-4 w-4"
						/>
					</div>

					<div class="flex items-center justify-between">
						<div>
							<div class="font-medium">Save as Draft</div>
							<div class="text-sm text-muted-foreground">
								Auto-save user progress to localStorage
							</div>
						</div>
						<input
							type="checkbox"
							bind:checked={wizardConfig.settings.saveAsDraft}
							class="h-4 w-4"
						/>
					</div>
				</Card.Content>
			</Card.Root>
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Preview Dialog -->
{#if showPreview}
	<WizardPreview config={wizardConfig} {moduleFields} onClose={() => (showPreview = false)} />
{/if}
