<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import {
		ChevronUp,
		ChevronDown,
		Trash2,
		Plus,
		X,
		FileText,
		Eye,
		CheckCircle
	} from 'lucide-svelte';
	import type { Field } from '$lib/types/modules';

	interface WizardStepConfig {
		id: string;
		title: string;
		description: string;
		type: 'form' | 'review' | 'confirmation';
		fields: string[];
		canSkip: boolean;
		order: number;
	}

	interface Props {
		step: WizardStepConfig;
		availableFields: Field[];
		moduleFields: Field[];
		onUpdate: (updates: Partial<WizardStepConfig>) => void;
		onAssignField: (fieldId: string) => void;
		onRemoveField: (fieldId: string) => void;
		onMoveUp: () => void;
		onMoveDown: () => void;
		onDelete: () => void;
		canMoveUp: boolean;
		canMoveDown: boolean;
		canDelete: boolean;
	}

	let {
		step,
		availableFields,
		moduleFields,
		onUpdate,
		onAssignField,
		onRemoveField,
		onMoveUp,
		onMoveDown,
		onDelete,
		canMoveUp,
		canMoveDown,
		canDelete
	}: Props = $props();

	let showFieldPicker = $state(false);

	const assignedFields = $derived(
		step.fields
			.map((fieldId) => moduleFields.find((f) => f.id?.toString() === fieldId))
			.filter((f) => f !== undefined)
	);

	const stepTypeOptions = [
		{
			value: 'form',
			label: 'Form Step',
			description: 'Regular form fields for data collection',
			icon: FileText
		},
		{
			value: 'review',
			label: 'Review Step',
			description: 'Summary of all entered data',
			icon: Eye
		},
		{
			value: 'confirmation',
			label: 'Confirmation Step',
			description: 'Success message and next actions',
			icon: CheckCircle
		}
	];
</script>

<Card.Root>
	<Card.Header>
		<div class="flex items-center justify-between">
			<div>
				<Card.Title>Step Configuration</Card.Title>
				<Card.Description>Configure this step's settings and fields</Card.Description>
			</div>
			<div class="flex items-center gap-1">
				<Button size="sm" variant="outline" onclick={onMoveUp} disabled={!canMoveUp}>
					<ChevronUp class="h-4 w-4" />
				</Button>
				<Button size="sm" variant="outline" onclick={onMoveDown} disabled={!canMoveDown}>
					<ChevronDown class="h-4 w-4" />
				</Button>
				<Button size="sm" variant="destructive" onclick={onDelete} disabled={!canDelete}>
					<Trash2 class="h-4 w-4" />
				</Button>
			</div>
		</div>
	</Card.Header>

	<Card.Content class="space-y-6">
		<!-- Basic Info -->
		<div class="space-y-4">
			<div class="space-y-2">
				<Label for="stepTitle">Step Title *</Label>
				<Input
					id="stepTitle"
					value={step.title}
					oninput={(e) => onUpdate({ title: e.currentTarget.value })}
					placeholder="e.g., Personal Information"
				/>
			</div>

			<div class="space-y-2">
				<Label for="stepDescription">Description</Label>
				<Textarea
					id="stepDescription"
					value={step.description}
					oninput={(e) => onUpdate({ description: e.currentTarget.value })}
					placeholder="Brief description of what this step collects"
					rows={2}
				/>
			</div>
		</div>

		<!-- Step Type -->
		<div class="space-y-2">
			<Label>Step Type</Label>
			<div class="grid grid-cols-1 gap-2">
				{#each stepTypeOptions as option}
					<button
						type="button"
						onclick={() => onUpdate({ type: option.value as any })}
						class="flex items-start gap-3 rounded-lg border p-3 text-left transition-colors {step.type ===
						option.value
							? 'border-primary bg-primary text-primary-foreground'
							: 'border-border bg-card hover:bg-accent'}"
					>
						<svelte:component
							this={option.icon}
							class="mt-0.5 h-5 w-5 {step.type === option.value ? '' : 'text-muted-foreground'}"
						/>
						<div class="flex-1">
							<div class="font-medium">{option.label}</div>
							<div class="mt-1 text-xs opacity-80">{option.description}</div>
						</div>
					</button>
				{/each}
			</div>
		</div>

		<!-- Options -->
		<div class="space-y-2">
			<Label>Options</Label>
			<div class="flex items-center gap-2">
				<input
					type="checkbox"
					id="canSkip"
					checked={step.canSkip}
					onchange={(e) => onUpdate({ canSkip: e.currentTarget.checked })}
					class="h-4 w-4"
				/>
				<Label for="canSkip" class="cursor-pointer font-normal">
					Allow users to skip this step
				</Label>
			</div>
		</div>

		<!-- Assigned Fields -->
		{#if step.type === 'form'}
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>Assigned Fields ({assignedFields.length})</Label>
					<Button size="sm" variant="outline" onclick={() => (showFieldPicker = !showFieldPicker)}>
						<Plus class="mr-2 h-3 w-3" />
						Add Fields
					</Button>
				</div>

				<!-- Field Picker -->
				{#if showFieldPicker && availableFields.length > 0}
					<div class="space-y-2 rounded-lg border bg-muted/30 p-3">
						<div class="text-sm font-medium">Available Fields</div>
						<div class="max-h-48 space-y-1 overflow-y-auto">
							{#each availableFields as field}
								<button
									type="button"
									onclick={() => {
										onAssignField(field.id?.toString() ?? '');
										showFieldPicker = false;
									}}
									class="flex w-full items-center justify-between rounded p-2 text-sm hover:bg-accent"
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
				{:else if showFieldPicker && availableFields.length === 0}
					<div class="rounded-lg border p-4 text-center text-sm text-muted-foreground">
						All fields have been assigned to steps
					</div>
				{/if}

				<!-- Assigned Fields List -->
				{#if assignedFields.length > 0}
					<div class="space-y-1">
						{#each assignedFields as field}
							<div
								class="flex items-center justify-between rounded-lg border bg-card p-2 hover:bg-accent"
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
									onclick={() => onRemoveField(field.id?.toString() ?? '')}
								>
									<X class="h-4 w-4" />
								</Button>
							</div>
						{/each}
					</div>
				{:else}
					<div class="rounded-lg border border-dashed p-6 text-center">
						<div class="text-sm text-muted-foreground">No fields assigned to this step yet</div>
						<Button
							size="sm"
							variant="outline"
							class="mt-2"
							onclick={() => (showFieldPicker = true)}
						>
							<Plus class="mr-2 h-3 w-3" />
							Add Fields
						</Button>
					</div>
				{/if}
			</div>
		{:else if step.type === 'review'}
			<div class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
				Review steps automatically display all data collected from previous steps
			</div>
		{:else if step.type === 'confirmation'}
			<div class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground">
				Confirmation steps display a success message after wizard completion
			</div>
		{/if}
	</Card.Content>
</Card.Root>
