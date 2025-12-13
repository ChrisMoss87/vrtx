<script lang="ts">
	import type { CadenceStep, CadenceChannel, DelayType, CreateStepRequest, UpdateStepRequest } from '$lib/api/cadences';
	import { addStep, updateStep, deleteStep } from '$lib/api/cadences';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { toast } from 'svelte-sonner';
	import {
		Loader2,
		Mail,
		Phone,
		MessageSquare,
		Linkedin,
		ClipboardList,
		Clock,
		Trash2,
		GripVertical
	} from 'lucide-svelte';

	interface Props {
		cadenceId: number;
		step?: CadenceStep;
		stepOrder?: number;
		existingSteps?: CadenceStep[];
		onSave?: (step: CadenceStep) => void;
		onDelete?: () => void;
		onCancel?: () => void;
	}

	let { cadenceId, step, stepOrder = 1, existingSteps = [], onSave, onDelete, onCancel }: Props = $props();

	let loading = $state(false);
	let deleting = $state(false);
	let deleteDialogOpen = $state(false);

	// Form state
	let name = $state(step?.name ?? '');
	let channel = $state<CadenceChannel>(step?.channel ?? 'email');
	let delayType = $state<DelayType>(step?.delay_type ?? 'days');
	let delayValue = $state(step?.delay_value?.toString() ?? '1');
	let preferredTime = $state(step?.preferred_time ?? '');
	let subject = $state(step?.subject ?? '');
	let content = $state(step?.content ?? '');
	let isActive = $state(step?.is_active ?? true);

	// Branching
	let onReplyGotoStep = $state<number | null>(step?.on_reply_goto_step ?? null);
	let onClickGotoStep = $state<number | null>(step?.on_click_goto_step ?? null);
	let onNoResponseGotoStep = $state<number | null>(step?.on_no_response_goto_step ?? null);

	const isEditing = $derived(!!step?.id);

	const channelOptions: { value: CadenceChannel; label: string; icon: typeof Mail }[] = [
		{ value: 'email', label: 'Email', icon: Mail },
		{ value: 'call', label: 'Call', icon: Phone },
		{ value: 'sms', label: 'SMS', icon: MessageSquare },
		{ value: 'linkedin', label: 'LinkedIn', icon: Linkedin },
		{ value: 'task', label: 'Task', icon: ClipboardList },
		{ value: 'wait', label: 'Wait', icon: Clock }
	];

	const delayOptions: { value: DelayType; label: string }[] = [
		{ value: 'immediate', label: 'Immediately' },
		{ value: 'hours', label: 'Hours' },
		{ value: 'days', label: 'Days' },
		{ value: 'business_days', label: 'Business Days' }
	];

	const otherSteps = $derived(existingSteps.filter((s) => s.id !== step?.id));

	async function handleSubmit() {
		if (channel === 'email' && !subject.trim()) {
			toast.error('Email subject is required');
			return;
		}

		if (channel !== 'wait' && !content.trim()) {
			toast.error('Content is required');
			return;
		}

		loading = true;
		try {
			let savedStep: CadenceStep;

			const stepData = {
				name: name.trim() || undefined,
				channel,
				delay_type: delayType,
				delay_value: parseInt(delayValue) || 0,
				preferred_time: preferredTime || undefined,
				subject: subject.trim() || undefined,
				content: content.trim() || undefined,
				is_active: isActive,
				on_reply_goto_step: onReplyGotoStep ?? undefined,
				on_click_goto_step: onClickGotoStep ?? undefined,
				on_no_response_goto_step: onNoResponseGotoStep ?? undefined
			};

			if (isEditing && step) {
				savedStep = await updateStep(cadenceId, step.id, stepData as UpdateStepRequest);
				toast.success('Step updated successfully');
			} else {
				savedStep = await addStep(cadenceId, { ...stepData, step_order: stepOrder } as CreateStepRequest);
				toast.success('Step added successfully');
			}

			onSave?.(savedStep);
		} catch (error) {
			console.error('Failed to save step:', error);
			toast.error('Failed to save step');
		} finally {
			loading = false;
		}
	}

	async function handleDelete() {
		if (!step) return;

		deleting = true;
		try {
			await deleteStep(cadenceId, step.id);
			toast.success('Step deleted');
			deleteDialogOpen = false;
			onDelete?.();
		} catch (error) {
			console.error('Failed to delete step:', error);
			toast.error('Failed to delete step');
		} finally {
			deleting = false;
		}
	}

	function getChannelIcon(ch: CadenceChannel) {
		return channelOptions.find((o) => o.value === ch)?.icon ?? Mail;
	}
</script>

<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
	<Card.Root>
		<Card.Header class="pb-3">
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-2">
					<GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
					<Card.Title class="text-base">
						{isEditing ? `Edit Step ${step?.step_order}` : `Step ${stepOrder}`}
					</Card.Title>
				</div>
				<div class="flex items-center gap-2">
					<Switch bind:checked={isActive} />
					<span class="text-sm text-muted-foreground">{isActive ? 'Active' : 'Inactive'}</span>
				</div>
			</div>
		</Card.Header>
		<Card.Content class="space-y-4">
			<!-- Step Name -->
			<div class="space-y-2">
				<Label for="stepName">Step Name (optional)</Label>
				<Input
					id="stepName"
					bind:value={name}
					placeholder="e.g., Initial outreach"
				/>
			</div>

			<!-- Channel Selection -->
			<div class="space-y-2">
				<Label>Channel</Label>
				<div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
					{#each channelOptions as opt}
						{@const Icon = opt.icon}
						<button
							type="button"
							onclick={() => (channel = opt.value)}
							class="flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-colors hover:bg-muted {channel === opt.value ? 'border-primary bg-primary/5' : 'border-border'}"
						>
							<Icon class="h-5 w-5 {channel === opt.value ? 'text-primary' : 'text-muted-foreground'}" />
							<span class="text-xs font-medium">{opt.label}</span>
						</button>
					{/each}
				</div>
			</div>

			<!-- Delay -->
			<div class="grid gap-4 sm:grid-cols-2">
				<div class="space-y-2">
					<Label for="delayType">Wait</Label>
					<Select.Root
						type="single"
						value={delayType}
						onValueChange={(v) => (delayType = (v as DelayType) || 'days')}
					>
						<Select.Trigger id="delayType">
							<span>{delayOptions.find((o) => o.value === delayType)?.label}</span>
						</Select.Trigger>
						<Select.Content>
							{#each delayOptions as opt}
								<Select.Item value={opt.value}>{opt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
				{#if delayType !== 'immediate'}
					<div class="space-y-2">
						<Label for="delayValue">Duration</Label>
						<Input
							id="delayValue"
							type="number"
							bind:value={delayValue}
							min="0"
						/>
					</div>
				{/if}
			</div>

			<!-- Preferred Time -->
			{#if channel !== 'wait'}
				<div class="space-y-2">
					<Label for="preferredTime">Preferred Time (optional)</Label>
					<Input
						id="preferredTime"
						type="time"
						bind:value={preferredTime}
					/>
					<p class="text-xs text-muted-foreground">
						If set, the step will execute around this time
					</p>
				</div>
			{/if}

			<!-- Channel-specific content -->
			{#if channel === 'email'}
				<div class="space-y-2">
					<Label for="subject">Subject *</Label>
					<Input
						id="subject"
						bind:value={subject}
						placeholder="Email subject line"
						required
					/>
				</div>
				<div class="space-y-2">
					<Label for="content">Email Body *</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="Write your email content here. Use merge fields like {'{'}first_name{'}'}"
						rows={6}
					/>
				</div>
			{:else if channel === 'sms'}
				<div class="space-y-2">
					<Label for="content">SMS Message *</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="SMS content (160 chars recommended)"
						rows={3}
						maxlength={320}
					/>
					<p class="text-xs text-muted-foreground">
						{content.length}/320 characters
					</p>
				</div>
			{:else if channel === 'call'}
				<div class="space-y-2">
					<Label for="content">Call Script</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="Talking points for the call"
						rows={4}
					/>
				</div>
			{:else if channel === 'linkedin'}
				<div class="space-y-2">
					<Label for="content">LinkedIn Message</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="LinkedIn connection request or message"
						rows={4}
					/>
				</div>
			{:else if channel === 'task'}
				<div class="space-y-2">
					<Label for="content">Task Description *</Label>
					<Textarea
						id="content"
						bind:value={content}
						placeholder="Describe the task to be created"
						rows={3}
					/>
				</div>
			{/if}

			<!-- Branching Options -->
			{#if channel === 'email' && otherSteps.length > 0}
				<div class="rounded-lg border bg-muted/30 p-4 space-y-3">
					<p class="text-sm font-medium">Behavior Branching (optional)</p>
					<div class="grid gap-3 sm:grid-cols-3">
						<div class="space-y-1">
							<Label class="text-xs">On Reply</Label>
							<Select.Root
								type="single"
								value={onReplyGotoStep?.toString() ?? ''}
								onValueChange={(v) => (onReplyGotoStep = v ? parseInt(v) : null)}
							>
								<Select.Trigger class="h-8 text-xs">
									<span>
										{onReplyGotoStep
											? `Step ${otherSteps.find((s) => s.id === onReplyGotoStep)?.step_order}`
											: 'Continue'}
									</span>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="">Continue sequence</Select.Item>
									{#each otherSteps as s}
										<Select.Item value={s.id.toString()}>
											Step {s.step_order}: {s.name || s.channel}
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
						<div class="space-y-1">
							<Label class="text-xs">On Click</Label>
							<Select.Root
								type="single"
								value={onClickGotoStep?.toString() ?? ''}
								onValueChange={(v) => (onClickGotoStep = v ? parseInt(v) : null)}
							>
								<Select.Trigger class="h-8 text-xs">
									<span>
										{onClickGotoStep
											? `Step ${otherSteps.find((s) => s.id === onClickGotoStep)?.step_order}`
											: 'Continue'}
									</span>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="">Continue sequence</Select.Item>
									{#each otherSteps as s}
										<Select.Item value={s.id.toString()}>
											Step {s.step_order}: {s.name || s.channel}
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
						<div class="space-y-1">
							<Label class="text-xs">No Response</Label>
							<Select.Root
								type="single"
								value={onNoResponseGotoStep?.toString() ?? ''}
								onValueChange={(v) => (onNoResponseGotoStep = v ? parseInt(v) : null)}
							>
								<Select.Trigger class="h-8 text-xs">
									<span>
										{onNoResponseGotoStep
											? `Step ${otherSteps.find((s) => s.id === onNoResponseGotoStep)?.step_order}`
											: 'Continue'}
									</span>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="">Continue sequence</Select.Item>
									{#each otherSteps as s}
										<Select.Item value={s.id.toString()}>
											Step {s.step_order}: {s.name || s.channel}
										</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
					</div>
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Actions -->
	<div class="flex justify-between">
		<div>
			{#if isEditing}
				<Button type="button" variant="destructive" size="sm" onclick={() => (deleteDialogOpen = true)}>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete Step
				</Button>
			{/if}
		</div>
		<div class="flex gap-3">
			{#if onCancel}
				<Button type="button" variant="outline" onclick={onCancel}>Cancel</Button>
			{/if}
			<Button type="submit" disabled={loading}>
				{#if loading}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				{isEditing ? 'Update Step' : 'Add Step'}
			</Button>
		</div>
	</div>
</form>

<!-- Delete Confirmation -->
<AlertDialog.Root bind:open={deleteDialogOpen}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Step</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete this step? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDelete}
				disabled={deleting}
			>
				{#if deleting}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
