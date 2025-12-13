<script lang="ts">
	import { X } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import { parameterizeStep, resetStepParameterization, type RecordingStep } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { createEventDispatcher } from 'svelte';

	export let recordingId: number;
	export let step: RecordingStep;
	export let onClose: () => void;

	const dispatch = createEventDispatcher<{
		parameterized: RecordingStep;
	}>();

	// Extract parameterizable fields from action_data
	$: parameterizableFields = getParameterizableFields(step.action_data);

	let selectedField = '';
	let referenceType: 'field' | 'current_user' | 'owner' | 'record_email' | 'custom' = 'field';
	let referenceField = '';
	let loading = false;

	function getParameterizableFields(data: Record<string, unknown>): { key: string; value: unknown; label: string }[] {
		const fields: { key: string; value: unknown; label: string }[] = [];
		const parameterizable = ['recipient', 'user_id', 'assignee_id', 'user_name', 'tag', 'field', 'new_value', 'subject'];

		for (const [key, value] of Object.entries(data)) {
			if (parameterizable.includes(key) && value !== null && value !== undefined) {
				fields.push({
					key,
					value,
					label: formatFieldLabel(key)
				});
			}
		}

		return fields;
	}

	function formatFieldLabel(key: string): string {
		return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
	}

	async function handleParameterize() {
		if (!selectedField) {
			toast.error('Please select a field to parameterize');
			return;
		}

		loading = true;
		const { data, error } = await tryCatch(
			parameterizeStep(
				recordingId,
				step.id,
				selectedField,
				referenceType,
				referenceType === 'field' ? referenceField : undefined
			)
		);
		loading = false;

		if (error) {
			toast.error('Failed to parameterize step');
			return;
		}

		toast.success('Step parameterized');
		dispatch('parameterized', data);
		onClose();
	}

	async function handleReset() {
		loading = true;
		const { data, error } = await tryCatch(resetStepParameterization(recordingId, step.id));
		loading = false;

		if (error) {
			toast.error('Failed to reset parameterization');
			return;
		}

		toast.success('Parameterization reset');
		dispatch('parameterized', data);
		onClose();
	}
</script>

<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
	class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
	onclick={onClose}
	onkeydown={(e) => e.key === 'Escape' && onClose()}
>
	<!-- svelte-ignore a11y_click_events_have_key_events -->
	<div
		class="w-full max-w-md rounded-lg bg-background shadow-xl"
		onclick={(e) => e.stopPropagation()}
	>
		<!-- Header -->
		<div class="flex items-center justify-between border-b p-4">
			<h2 class="text-lg font-semibold">Parameterize Value</h2>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<!-- Content -->
		<div class="p-4 space-y-4">
			<p class="text-sm text-muted-foreground">
				Replace specific values with dynamic references so the workflow works for any record.
			</p>

			<!-- Field selector -->
			{#if parameterizableFields.length > 0}
				<div class="space-y-2">
					<Label>Select field to parameterize</Label>
					<div class="space-y-2">
						{#each parameterizableFields as field}
							<label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer hover:bg-muted/50 {selectedField === field.key ? 'border-primary bg-primary/5' : ''}">
								<input
									type="radio"
									bind:group={selectedField}
									value={field.key}
									class="h-4 w-4"
								/>
								<div class="flex-1">
									<div class="font-medium text-sm">{field.label}</div>
									<div class="text-xs text-muted-foreground">
										Current value: "{field.value}"
									</div>
								</div>
							</label>
						{/each}
					</div>
				</div>
			{:else}
				<div class="text-center py-4 text-muted-foreground">
					No parameterizable fields found in this step.
				</div>
			{/if}

			{#if selectedField}
				<div class="space-y-2">
					<Label>Replace with</Label>
					<RadioGroup.Root bind:value={referenceType} class="space-y-2">
						<div class="flex items-center gap-3 p-3 rounded-lg border">
							<RadioGroup.Item value="field" id="ref-field" />
							<div>
								<label for="ref-field" class="font-medium text-sm cursor-pointer">Field reference</label>
								<p class="text-xs text-muted-foreground">Use a field from the record</p>
							</div>
						</div>
						<div class="flex items-center gap-3 p-3 rounded-lg border">
							<RadioGroup.Item value="current_user" id="ref-current-user" />
							<div>
								<label for="ref-current-user" class="font-medium text-sm cursor-pointer">Current user</label>
								<p class="text-xs text-muted-foreground">The user triggering the workflow</p>
							</div>
						</div>
						<div class="flex items-center gap-3 p-3 rounded-lg border">
							<RadioGroup.Item value="owner" id="ref-owner" />
							<div>
								<label for="ref-owner" class="font-medium text-sm cursor-pointer">Record owner</label>
								<p class="text-xs text-muted-foreground">The owner of the record</p>
							</div>
						</div>
						<div class="flex items-center gap-3 p-3 rounded-lg border">
							<RadioGroup.Item value="record_email" id="ref-record-email" />
							<div>
								<label for="ref-record-email" class="font-medium text-sm cursor-pointer">Record's email</label>
								<p class="text-xs text-muted-foreground">Email field from the record</p>
							</div>
						</div>
					</RadioGroup.Root>
				</div>

				{#if referenceType === 'field'}
					<div class="space-y-2">
						<Label for="reference-field">Field name</Label>
						<Input
							id="reference-field"
							bind:value={referenceField}
							placeholder="e.g., email, contact_email"
						/>
					</div>
				{/if}
			{/if}
		</div>

		<!-- Footer -->
		<div class="flex justify-between border-t p-4">
			<div>
				{#if step.is_parameterized}
					<Button variant="outline" onclick={handleReset} disabled={loading}>
						Reset to Original
					</Button>
				{/if}
			</div>
			<div class="flex gap-2">
				<Button variant="outline" onclick={onClose} disabled={loading}>
					Cancel
				</Button>
				<Button
					onclick={handleParameterize}
					disabled={loading || !selectedField || (referenceType === 'field' && !referenceField)}
				>
					{loading ? 'Applying...' : 'Apply'}
				</Button>
			</div>
		</div>
	</div>
</div>
