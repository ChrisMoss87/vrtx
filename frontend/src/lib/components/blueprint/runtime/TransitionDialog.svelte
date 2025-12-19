<script lang="ts">
	import { createEventDispatcher } from 'svelte';
	import type {
		AvailableTransition,
		TransitionExecution,
		FormattedRequirement
	} from '$lib/api/blueprints';
	import * as blueprintApi from '$lib/api/blueprints';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Button } from '$lib/components/ui/button';
	import { toast } from 'svelte-sonner';
	import RequirementForm from './RequirementForm.svelte';
	import ArrowRightIcon from '@lucide/svelte/icons/arrow-right';
	import CheckCircleIcon from '@lucide/svelte/icons/check-circle';
	import LoaderIcon from '@lucide/svelte/icons/loader';
	import ShieldCheckIcon from '@lucide/svelte/icons/shield-check';

	interface Props {
		open?: boolean;
		recordId: number;
		transition: AvailableTransition | null;
		recordData?: Record<string, unknown>;
		onComplete?: (newState: { id: number; name: string }) => void;
		onClose?: () => void;
	}

	let {
		open = $bindable(false),
		recordId,
		transition,
		recordData = {},
		onComplete,
		onClose
	}: Props = $props();

	const dispatch = createEventDispatcher();

	// Execution state
	let execution = $state<TransitionExecution | null>(null);
	let requirements = $state<FormattedRequirement[]>([]);
	let step = $state<'confirm' | 'requirements' | 'approval' | 'completing' | 'completed'>('confirm');
	let loading = $state(false);
	let requirementData = $state<{
		fields: Record<string, unknown>;
		attachments: Array<{ name: string; size?: number; path?: string }>;
		note: string;
		checklist: Record<string | number, boolean>;
	}>({
		fields: {},
		attachments: [],
		note: '',
		checklist: {}
	});

	// Reset state when dialog opens/closes
	$effect(() => {
		if (open && transition) {
			step = 'confirm';
			execution = null;
			requirements = [];
			requirementData = { fields: {}, attachments: [], note: '', checklist: {} };
		}
	});

	// Start the transition
	async function startTransition() {
		if (!transition) return;

		loading = true;
		try {
			const result = await blueprintApi.startTransition(recordId, transition.id, recordData);
			execution = result.execution;
			requirements = result.requirements;

			if (requirements.length > 0) {
				step = 'requirements';
			} else if (transition.requires_approval) {
				step = 'approval';
				// Auto-complete to pending approval state
				await completeExecution();
			} else {
				step = 'completing';
				await completeExecution();
			}
		} catch (error) {
			console.error('Failed to start transition:', error);
			toast.error('Failed to start transition');
		} finally {
			loading = false;
		}
	}

	// Submit requirements
	async function submitRequirements() {
		if (!execution) return;

		loading = true;
		try {
			const result = await blueprintApi.submitRequirements(execution.id, {
				fields: Object.keys(requirementData.fields).length > 0 ? requirementData.fields : undefined,
				attachments: requirementData.attachments.length > 0 ? requirementData.attachments : undefined,
				note: requirementData.note || undefined,
				checklist: Object.keys(requirementData.checklist).length > 0 ? requirementData.checklist : undefined
			});

			execution = result.execution;

			if (result.next_step === 'pending_approval') {
				step = 'approval';
			} else if (result.next_step === 'completed') {
				step = 'completed';
				onComplete?.(execution.to_state!);
			} else {
				step = 'completing';
				await completeExecution();
			}
		} catch (error) {
			console.error('Failed to submit requirements:', error);
			toast.error('Failed to submit requirements');
		} finally {
			loading = false;
		}
	}

	// Complete the execution
	async function completeExecution() {
		if (!execution) return;

		loading = true;
		try {
			const result = await blueprintApi.completeExecution(execution.id);
			execution = result.execution;
			step = 'completed';
			toast.success(`Moved to ${result.new_state.name}`);
			onComplete?.(result.new_state);
		} catch (error: any) {
			console.error('Failed to complete transition:', error);
			// If pending approval, show approval step
			if (error?.response?.data?.status === 'pending_approval') {
				step = 'approval';
			} else {
				toast.error('Failed to complete transition');
			}
		} finally {
			loading = false;
		}
	}

	// Cancel and close
	async function handleCancel() {
		if (execution && step !== 'completed') {
			try {
				await blueprintApi.cancelExecution(execution.id);
			} catch (error) {
				console.error('Failed to cancel execution:', error);
			}
		}
		onClose?.();
	}

	function handleClose() {
		if (step === 'completed') {
			onClose?.();
		} else {
			handleCancel();
		}
	}
</script>

<Dialog.Root bind:open onOpenChange={(isOpen) => !isOpen && handleClose()}>
	<Dialog.Content class="sm:max-w-lg">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				{#if step === 'completed'}
					<CheckCircleIcon class="h-5 w-5 text-green-500" />
					Transition Complete
				{:else if step === 'approval'}
					<ShieldCheckIcon class="h-5 w-5 text-purple-500" />
					Approval Required
				{:else}
					<ArrowRightIcon class="h-5 w-5" />
					{transition?.name || 'Transition'}
				{/if}
			</Dialog.Title>
			<Dialog.Description>
				{#if step === 'confirm'}
					Move this record to <strong>{transition?.to_state?.name}</strong>
				{:else if step === 'requirements'}
					Complete the required information to proceed
				{:else if step === 'approval'}
					This transition requires approval before it can be completed
				{:else if step === 'completing'}
					Processing transition...
				{:else if step === 'completed'}
					Record has been moved to <strong>{execution?.to_state?.name}</strong>
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		<div class="py-4">
			{#if step === 'confirm'}
				<!-- Confirmation step -->
				<div class="space-y-4">
					<div class="rounded-lg bg-muted p-4">
						<div class="flex items-center gap-3">
							<div
								class="flex h-10 w-10 items-center justify-center rounded-full"
								style="background-color: {transition?.to_state?.color || '#6b7280'}20"
							>
								<ArrowRightIcon
									class="h-5 w-5"
									style="color: {transition?.to_state?.color || '#6b7280'}"
								/>
							</div>
							<div>
								<p class="font-medium">{transition?.to_state?.name}</p>
								<p class="text-sm text-muted-foreground">
									{#if transition?.has_requirements}
										Additional information required
									{:else if transition?.requires_approval}
										Requires approval
									{:else}
										Ready to proceed
									{/if}
								</p>
							</div>
						</div>
					</div>

					{#if transition?.has_requirements || transition?.requires_approval}
						<div class="flex flex-wrap gap-2">
							{#if transition.has_requirements}
								<span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
									Has Requirements
								</span>
							{/if}
							{#if transition.requires_approval}
								<span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
									Needs Approval
								</span>
							{/if}
						</div>
					{/if}
				</div>
			{:else if step === 'requirements'}
				<!-- Requirements form -->
				<RequirementForm
					{requirements}
					bind:data={requirementData}
				/>
			{:else if step === 'approval'}
				<!-- Pending approval message -->
				<div class="flex flex-col items-center py-6 text-center">
					<ShieldCheckIcon class="mb-4 h-12 w-12 text-purple-500" />
					<h3 class="text-lg font-semibold">Awaiting Approval</h3>
					<p class="mt-2 text-sm text-muted-foreground">
						Your transition request has been submitted and is awaiting approval.
						You'll be notified when it's approved or rejected.
					</p>
				</div>
			{:else if step === 'completing'}
				<!-- Loading state -->
				<div class="flex flex-col items-center py-6 text-center">
					<LoaderIcon class="mb-4 h-12 w-12 animate-spin text-primary" />
					<p class="text-sm text-muted-foreground">Processing transition...</p>
				</div>
			{:else if step === 'completed'}
				<!-- Success state -->
				<div class="flex flex-col items-center py-6 text-center">
					<CheckCircleIcon class="mb-4 h-12 w-12 text-green-500" />
					<h3 class="text-lg font-semibold">Success!</h3>
					<p class="mt-2 text-sm text-muted-foreground">
						Record has been moved to {execution?.to_state?.name}
					</p>
				</div>
			{/if}
		</div>

		<Dialog.Footer>
			{#if step === 'confirm'}
				<Button variant="outline" onclick={handleCancel}>Cancel</Button>
				<Button onclick={startTransition} disabled={loading}>
					{#if loading}
						<LoaderIcon class="mr-2 h-4 w-4 animate-spin" />
					{/if}
					{transition?.has_requirements ? 'Continue' : 'Confirm'}
				</Button>
			{:else if step === 'requirements'}
				<Button variant="outline" onclick={handleCancel}>Cancel</Button>
				<Button onclick={submitRequirements} disabled={loading}>
					{#if loading}
						<LoaderIcon class="mr-2 h-4 w-4 animate-spin" />
					{/if}
					Submit
				</Button>
			{:else if step === 'approval'}
				<Button onclick={handleClose}>Close</Button>
			{:else if step === 'completed'}
				<Button onclick={handleClose}>Done</Button>
			{/if}
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
