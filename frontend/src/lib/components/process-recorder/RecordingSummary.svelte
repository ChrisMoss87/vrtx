<script lang="ts">
	import {
		Plus, Edit, ArrowRight, Mail, CheckSquare, FileText,
		Tag, UserPlus, Activity, GripVertical, Trash2, Settings2
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { removeStep, type Recording, type RecordingStep } from '$lib/api/recordings';
	import { tryCatch } from '$lib/utils/tryCatch';
	import { toast } from 'svelte-sonner';
	import { createEventDispatcher } from 'svelte';
	import ParameterizeModal from './ParameterizeModal.svelte';

	export let recording: Recording;
	export let steps: RecordingStep[] = [];

	const dispatch = createEventDispatcher<{
		stepRemoved: number;
		stepParameterized: RecordingStep;
		reorder: number[];
	}>();

	let showParameterizeModal = false;
	let selectedStep: RecordingStep | null = null;
	let deletingStepId: number | null = null;

	function getActionIcon(actionType: string) {
		const icons: Record<string, typeof Plus> = {
			create_record: Plus,
			update_field: Edit,
			change_stage: ArrowRight,
			send_email: Mail,
			create_task: CheckSquare,
			add_note: FileText,
			add_tag: Tag,
			remove_tag: Tag,
			assign_user: UserPlus,
			log_activity: Activity
		};
		return icons[actionType] || Activity;
	}

	async function handleRemoveStep(stepId: number) {
		deletingStepId = stepId;
		const { error } = await tryCatch(removeStep(recording.id, stepId));
		deletingStepId = null;

		if (error) {
			toast.error('Failed to remove step');
			return;
		}

		toast.success('Step removed');
		dispatch('stepRemoved', stepId);
	}

	function openParameterizeModal(step: RecordingStep) {
		selectedStep = step;
		showParameterizeModal = true;
	}

	function handleParameterized(event: CustomEvent<RecordingStep>) {
		dispatch('stepParameterized', event.detail);
		showParameterizeModal = false;
	}

	function formatCapturedTime(dateStr: string): string {
		const date = new Date(dateStr);
		return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
	}

	function getParameterizedFields(step: RecordingStep): string[] {
		if (!step.parameterized_data) return [];

		return Object.entries(step.parameterized_data)
			.filter(([_, value]) => typeof value === 'object' && (value as Record<string, unknown>)?.type === 'reference')
			.map(([key]) => key);
	}
</script>

<div class="space-y-3">
	{#if steps.length === 0}
		<div class="text-center py-8 text-muted-foreground">
			<Activity class="h-12 w-12 mx-auto mb-3 opacity-50" />
			<p>No actions captured yet</p>
			{#if recording.status === 'recording'}
				<p class="text-sm mt-1">Perform actions in the CRM to capture them</p>
			{/if}
		</div>
	{:else}
		{#each steps as step, index (step.id)}
			<div class="group flex items-start gap-3 p-3 rounded-lg border bg-card hover:bg-muted/30 transition-colors">
				<!-- Drag handle -->
				<div class="pt-0.5 cursor-grab opacity-0 group-hover:opacity-50 hover:opacity-100">
					<GripVertical class="h-4 w-4" />
				</div>

				<!-- Step number -->
				<div class="flex-shrink-0 w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-medium flex items-center justify-center">
					{index + 1}
				</div>

				<!-- Icon -->
				<div class="flex-shrink-0 w-8 h-8 rounded-lg bg-muted flex items-center justify-center">
					<svelte:component this={getActionIcon(step.action_type)} class="h-4 w-4 text-muted-foreground" />
				</div>

				<!-- Content -->
				<div class="flex-1 min-w-0">
					<div class="flex items-center gap-2">
						<span class="text-sm font-medium">{step.action_label}</span>
						{#if step.is_parameterized}
							<span class="px-1.5 py-0.5 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
								Parameterized
							</span>
						{/if}
					</div>
					<p class="text-sm text-muted-foreground mt-0.5">{step.description}</p>

					{#if step.target_module}
						<p class="text-xs text-muted-foreground mt-1">
							Module: {step.target_module}
							{#if step.target_record_id}
								(Record #{step.target_record_id})
							{/if}
						</p>
					{/if}

					<!-- Parameterized fields -->
					{#if step.is_parameterized}
						{@const fields = getParameterizedFields(step)}
						{#if fields.length > 0}
							<div class="flex flex-wrap gap-1 mt-2">
								{#each fields as field}
									<span class="px-1.5 py-0.5 text-xs rounded bg-muted text-muted-foreground">
										{field} → dynamic
									</span>
								{/each}
							</div>
						{/if}
					{/if}
				</div>

				<!-- Time -->
				<div class="text-xs text-muted-foreground">
					{formatCapturedTime(step.captured_at)}
				</div>

				<!-- Actions -->
				<div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
					<Button
						variant="ghost"
						size="icon"
						class="h-7 w-7"
						onclick={() => openParameterizeModal(step)}
					>
						<Settings2 class="h-3.5 w-3.5" />
					</Button>
					<Button
						variant="ghost"
						size="icon"
						class="h-7 w-7 text-destructive hover:text-destructive"
						onclick={() => handleRemoveStep(step.id)}
						disabled={deletingStepId === step.id}
					>
						<Trash2 class="h-3.5 w-3.5" />
					</Button>
				</div>
			</div>
		{/each}
	{/if}
</div>

{#if showParameterizeModal && selectedStep}
	<ParameterizeModal
		recordingId={recording.id}
		step={selectedStep}
		onClose={() => (showParameterizeModal = false)}
		on:parameterized={handleParameterized}
	/>
{/if}
