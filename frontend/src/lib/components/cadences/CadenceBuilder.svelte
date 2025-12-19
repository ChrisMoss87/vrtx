<script lang="ts">
	import type { Cadence, CreateCadenceRequest, UpdateCadenceRequest } from '$lib/api/cadences';
	import { createCadence, updateCadence } from '$lib/api/cadences';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Switch } from '$lib/components/ui/switch';
	import { toast } from 'svelte-sonner';
	import { Loader2, Workflow } from 'lucide-svelte';

	interface Props {
		cadence?: Cadence;
		onSave?: (cadence: Cadence) => void;
		onCancel?: () => void;
	}

	let { cadence, onSave, onCancel }: Props = $props();

	let loading = $state(false);
	let loadingModules = $state(true);
	let modules = $state<Module[]>([]);

	// Form state
	let name = $state(cadence?.name ?? '');
	let description = $state(cadence?.description ?? '');
	let moduleId = $state<number | null>(cadence?.module_id ?? null);
	let autoEnroll = $state(cadence?.auto_enroll ?? false);
	let allowReEnrollment = $state(cadence?.allow_re_enrollment ?? false);
	let reEnrollmentDays = $state(cadence?.re_enrollment_days?.toString() ?? '');
	let maxEnrollmentsPerDay = $state(cadence?.max_enrollments_per_day?.toString() ?? '');

	const isEditing = $derived(!!cadence?.id);

	async function loadModules() {
		loadingModules = true;
		try {
			modules = await getActiveModules();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loadingModules = false;
		}
	}

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Cadence name is required');
			return;
		}

		if (!moduleId) {
			toast.error('Please select a target module');
			return;
		}

		loading = true;
		try {
			let savedCadence: Cadence;

			if (isEditing && cadence) {
				const data: UpdateCadenceRequest = {
					name: name.trim(),
					description: description.trim() || undefined,
					auto_enroll: autoEnroll,
					allow_re_enrollment: allowReEnrollment,
					re_enrollment_days: reEnrollmentDays ? parseInt(reEnrollmentDays) : undefined,
					max_enrollments_per_day: maxEnrollmentsPerDay ? parseInt(maxEnrollmentsPerDay) : undefined
				};
				savedCadence = await updateCadence(cadence.id, data);
				toast.success('Cadence updated successfully');
			} else {
				const data: CreateCadenceRequest = {
					name: name.trim(),
					description: description.trim() || undefined,
					module_id: moduleId,
					auto_enroll: autoEnroll,
					allow_re_enrollment: allowReEnrollment,
					re_enrollment_days: reEnrollmentDays ? parseInt(reEnrollmentDays) : undefined,
					max_enrollments_per_day: maxEnrollmentsPerDay ? parseInt(maxEnrollmentsPerDay) : undefined
				};
				savedCadence = await createCadence(data);
				toast.success('Cadence created successfully');
			}

			onSave?.(savedCadence);
		} catch (error) {
			console.error('Failed to save cadence:', error);
			toast.error('Failed to save cadence');
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		loadModules();
	});
</script>

<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-6">
	<!-- Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Workflow class="h-5 w-5" />
				Cadence Details
			</Card.Title>
			<Card.Description>Configure the basic settings for your outreach sequence</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Cadence Name *</Label>
				<Input
					id="name"
					bind:value={name}
					placeholder="e.g., New Lead Outreach"
					required
				/>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Describe the purpose and target audience"
					rows={2}
				/>
			</div>

			<div class="space-y-2">
				<Label for="module">Target Module *</Label>
				<Select.Root
					type="single"
					value={moduleId?.toString()}
					onValueChange={(v) => (moduleId = v ? parseInt(v) : null)}
					disabled={isEditing}
				>
					<Select.Trigger id="module">
						{#if loadingModules}
							<span class="text-muted-foreground">Loading...</span>
						{:else}
							<span>
								{modules.find((m) => m.id === moduleId)?.name ?? 'Select module'}
							</span>
						{/if}
					</Select.Trigger>
					<Select.Content>
						{#each modules as mod}
							<Select.Item value={mod.id.toString()}>{mod.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
				<p class="text-xs text-muted-foreground">
					Select the module containing records you want to enroll (e.g., Leads, Contacts)
				</p>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Enrollment Settings -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Enrollment Settings</Card.Title>
			<Card.Description>Configure how contacts are enrolled and re-enrolled</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Auto-Enroll</Label>
					<p class="text-xs text-muted-foreground">
						Automatically enroll records that match entry criteria
					</p>
				</div>
				<Switch bind:checked={autoEnroll} />
			</div>

			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Allow Re-Enrollment</Label>
					<p class="text-xs text-muted-foreground">
						Allow contacts to be enrolled again after completion
					</p>
				</div>
				<Switch bind:checked={allowReEnrollment} />
			</div>

			{#if allowReEnrollment}
				<div class="space-y-2">
					<Label for="reEnrollmentDays">Re-enrollment Cooldown (days)</Label>
					<Input
						id="reEnrollmentDays"
						type="number"
						bind:value={reEnrollmentDays}
						placeholder="e.g., 30"
						min="1"
					/>
					<p class="text-xs text-muted-foreground">
						Minimum days before a contact can be re-enrolled
					</p>
				</div>
			{/if}

			<div class="space-y-2">
				<Label for="maxEnrollments">Max Enrollments Per Day</Label>
				<Input
					id="maxEnrollments"
					type="number"
					bind:value={maxEnrollmentsPerDay}
					placeholder="No limit"
					min="1"
				/>
				<p class="text-xs text-muted-foreground">
					Limit how many new contacts can be enrolled daily
				</p>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Actions -->
	<div class="flex justify-end gap-3">
		{#if onCancel}
			<Button type="button" variant="outline" onclick={onCancel}>Cancel</Button>
		{/if}
		<Button type="submit" disabled={loading || !name.trim() || !moduleId}>
			{#if loading}
				<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			{/if}
			{isEditing ? 'Update Cadence' : 'Create Cadence'}
		</Button>
	</div>
</form>
