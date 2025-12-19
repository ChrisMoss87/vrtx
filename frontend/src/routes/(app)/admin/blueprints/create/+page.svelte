<script lang="ts">
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import * as blueprintApi from '$lib/api/blueprints';
	import { getModules, getModuleById, type Module, type Field } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { toast } from 'svelte-sonner';
	import ArrowLeftIcon from '@lucide/svelte/icons/arrow-left';

	let modules = $state<Module[]>([]);
	let fields = $state<Field[]>([]);
	let loading = $state(true);
	let saving = $state(false);

	// Form state
	let name = $state('');
	let description = $state('');
	let selectedModuleId = $state<number | null>(null);
	let selectedFieldId = $state<number | null>(null);

	// Filtered fields (only select/picklist fields with options)
	const selectFields = $derived(
		fields.filter(
			(f) =>
				(f.type === 'select' || f.type === 'picklist' || f.type === 'status') &&
				f.options &&
				f.options.length > 0
		)
	);

	// Load modules
	async function loadModules() {
		try {
			modules = await getModules();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loading = false;
		}
	}

	// Load fields when module changes
	async function loadFields(moduleId: number) {
		try {
			const module = await getModuleById(moduleId);
			// Get fields from all blocks
			fields = module.blocks?.flatMap((block) => block.fields || []) || [];
		} catch (error) {
			console.error('Failed to load fields:', error);
			toast.error('Failed to load fields');
		}
	}

	// Handle module change
	function handleModuleChange(value: string) {
		selectedModuleId = parseInt(value);
		selectedFieldId = null;
		fields = [];
		if (selectedModuleId) {
			loadFields(selectedModuleId);
		}
	}

	// Handle form submit
	async function handleSubmit(e: SubmitEvent) {
		e.preventDefault();

		if (!name.trim()) {
			toast.error('Please enter a blueprint name');
			return;
		}
		if (!selectedModuleId) {
			toast.error('Please select a module');
			return;
		}
		if (!selectedFieldId) {
			toast.error('Please select a field');
			return;
		}

		saving = true;
		try {
			const blueprint = await blueprintApi.createBlueprint({
				name: name.trim(),
				description: description.trim() || undefined,
				module_id: selectedModuleId,
				field_id: selectedFieldId
			});

			toast.success('Blueprint created');
			goto(`/admin/blueprints/${blueprint.id}`);
		} catch (error) {
			console.error('Failed to create blueprint:', error);
			toast.error('Failed to create blueprint');
		} finally {
			saving = false;
		}
	}

	onMount(() => {
		loadModules();
	});
</script>

<svelte:head>
	<title>Create Blueprint | Admin</title>
</svelte:head>

<div class="container mx-auto max-w-2xl py-6">
	<!-- Back link -->
	<Button variant="ghost" href="/admin/blueprints" class="mb-4">
		<ArrowLeftIcon class="mr-2 h-4 w-4" />
		Back to Blueprints
	</Button>

	<Card.Root>
		<Card.Header>
			<Card.Title>Create Blueprint</Card.Title>
			<Card.Description>
				Set up a new blueprint to control stage transitions with conditions, requirements, and
				automated actions.
			</Card.Description>
		</Card.Header>

		<form onsubmit={handleSubmit}>
			<Card.Content class="space-y-6">
				<!-- Name -->
				<div class="space-y-2">
					<Label for="name">Blueprint Name</Label>
					<Input
						id="name"
						bind:value={name}
						placeholder="e.g., Lead Qualification Process"
						required
					/>
				</div>

				<!-- Description -->
				<div class="space-y-2">
					<Label for="description">Description (optional)</Label>
					<Textarea
						id="description"
						bind:value={description}
						placeholder="Describe the purpose of this blueprint..."
						rows={3}
					/>
				</div>

				<!-- Module Selection -->
				<div class="space-y-2">
					<Label>Module</Label>
					<Select.Root
						type="single"
						value={selectedModuleId?.toString() || ''}
						onValueChange={handleModuleChange}
						disabled={loading}
					>
						<Select.Trigger class="w-full">
							{selectedModuleId
								? modules.find((m) => m.id === selectedModuleId)?.name || 'Select module'
								: 'Select a module'}
						</Select.Trigger>
						<Select.Content>
							{#each modules as module}
								<Select.Item value={module.id.toString()}>{module.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
					<p class="text-xs text-muted-foreground">
						Choose the module this blueprint will apply to.
					</p>
				</div>

				<!-- Field Selection -->
				<div class="space-y-2">
					<Label>Stage Field</Label>
					<Select.Root
						type="single"
						value={selectedFieldId?.toString() || ''}
						onValueChange={(v) => (selectedFieldId = parseInt(v))}
						disabled={!selectedModuleId || selectFields.length === 0}
					>
						<Select.Trigger class="w-full">
							{selectedFieldId
								? selectFields.find((f) => f.id === selectedFieldId)?.label || 'Select field'
								: selectFields.length === 0 && selectedModuleId
									? 'No select fields available'
									: 'Select a stage field'}
						</Select.Trigger>
						<Select.Content>
							{#each selectFields as field}
								<Select.Item value={field.id.toString()}>
									{field.label}
									<span class="ml-2 text-xs text-muted-foreground">
										({field.options?.length || 0} options)
									</span>
								</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
					<p class="text-xs text-muted-foreground">
						Select the field that represents stages/status. Blueprint states will be created from
						its options.
					</p>
				</div>

				<!-- Preview of stages -->
				{#if selectedFieldId}
					{@const selectedField = selectFields.find((f) => f.id === selectedFieldId)}
					{#if selectedField?.options}
						<div class="space-y-2">
							<Label>States that will be created:</Label>
							<div class="flex flex-wrap gap-2">
								{#each selectedField.options as option}
									<span
										class="rounded-full px-3 py-1 text-sm"
										style="background-color: {option.color || '#6b7280'}20; color: {option.color || '#6b7280'}"
									>
										{option.label}
									</span>
								{/each}
							</div>
						</div>
					{/if}
				{/if}
			</Card.Content>

			<Card.Footer class="flex justify-end gap-2">
				<Button variant="outline" href="/admin/blueprints">Cancel</Button>
				<Button type="submit" disabled={saving}>
					{saving ? 'Creating...' : 'Create Blueprint'}
				</Button>
			</Card.Footer>
		</form>
	</Card.Root>
</div>
