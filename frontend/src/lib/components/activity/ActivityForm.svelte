<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { activitiesApi, type Activity, type CreateActivityData } from '$lib/api/activity';

	type ActivityFormType = 'note' | 'call' | 'meeting' | 'task' | 'comment';

	interface Props {
		subjectType: string;
		subjectId: number;
		activity?: Activity | null;
		onsave?: (activity: Activity) => void;
		oncancel?: () => void;
	}

	let {
		subjectType,
		subjectId,
		activity = null,
		onsave,
		oncancel
	}: Props = $props();

	// Form state
	let type = $state<ActivityFormType>((activity?.type as ActivityFormType) ?? 'note');
	let title = $state(activity?.title ?? '');
	let description = $state(activity?.description ?? '');
	let content = $state(activity?.content ?? '');
	let scheduledAt = $state(activity?.scheduled_at?.slice(0, 16) ?? '');
	let durationMinutes = $state(activity?.duration_minutes ?? 30);
	let outcome = $state(activity?.outcome ?? '');
	let isInternal = $state(activity?.is_internal ?? false);
	let isPinned = $state(activity?.is_pinned ?? false);
	let isSaving = $state(false);

	const typeOptions = [
		{ value: 'note', label: 'Note' },
		{ value: 'call', label: 'Call' },
		{ value: 'meeting', label: 'Meeting' },
		{ value: 'task', label: 'Task' },
		{ value: 'comment', label: 'Comment' }
	];

	const outcomeOptions = [
		{ value: '', label: 'No outcome' },
		{ value: 'completed', label: 'Completed' },
		{ value: 'no_answer', label: 'No Answer' },
		{ value: 'left_voicemail', label: 'Left Voicemail' },
		{ value: 'busy', label: 'Busy' },
		{ value: 'rescheduled', label: 'Rescheduled' },
		{ value: 'cancelled', label: 'Cancelled' }
	];

	async function handleSubmit() {
		if (!title.trim()) return;

		isSaving = true;
		try {
			const data: CreateActivityData = {
				type,
				subject_type: subjectType,
				subject_id: subjectId,
				title: title.trim(),
				description: description.trim() || undefined,
				content: content.trim() || undefined,
				scheduled_at: scheduledAt || undefined,
				duration_minutes: durationMinutes || undefined,
				outcome: outcome || undefined,
				is_internal: isInternal,
				is_pinned: isPinned
			};

			let result: Activity;
			if (activity) {
				const response = await activitiesApi.update(activity.id, data);
				result = response.data;
			} else {
				const response = await activitiesApi.create(data);
				result = response.data;
			}

			onsave?.(result);
		} catch (error) {
			console.error('Failed to save activity:', error);
		} finally {
			isSaving = false;
		}
	}
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<Card.Title class="text-base">{activity ? 'Edit Activity' : 'New Activity'}</Card.Title>
	</Card.Header>
	<Card.Content class="space-y-4">
		<div class="grid grid-cols-2 gap-4">
			<div class="space-y-2">
				<Label for="type">Type</Label>
				<Select.Root
					type="single"
					value={type}
					onValueChange={(v) => v && (type = v as ActivityFormType)}
				>
					<Select.Trigger>
						{typeOptions.find(t => t.value === type)?.label ?? 'Select type'}
					</Select.Trigger>
					<Select.Content>
						{#each typeOptions as option}
							<Select.Item value={option.value}>{option.label}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
			</div>

			<div class="space-y-2">
				<Label for="title">Title</Label>
				<Input id="title" bind:value={title} placeholder="Activity title" />
			</div>
		</div>

		<div class="space-y-2">
			<Label for="description">Description</Label>
			<Input id="description" bind:value={description} placeholder="Brief description" />
		</div>

		{#if type === 'note' || type === 'comment'}
			<div class="space-y-2">
				<Label for="content">Content</Label>
				<Textarea id="content" bind:value={content} placeholder="Write your note..." rows={4} />
			</div>
		{/if}

		{#if type === 'call' || type === 'meeting' || type === 'task'}
			<div class="grid grid-cols-2 gap-4">
				<div class="space-y-2">
					<Label for="scheduled_at">Scheduled At</Label>
					<Input id="scheduled_at" type="datetime-local" bind:value={scheduledAt} />
				</div>

				<div class="space-y-2">
					<Label for="duration">Duration (minutes)</Label>
					<Input id="duration" type="number" bind:value={durationMinutes} min="1" />
				</div>
			</div>

			{#if type === 'call'}
				<div class="space-y-2">
					<Label for="outcome">Outcome</Label>
					<Select.Root
						type="single"
						value={outcome || undefined}
						onValueChange={(v) => outcome = v ?? ''}
					>
						<Select.Trigger>
							{outcomeOptions.find(o => o.value === outcome)?.label ?? 'Select outcome'}
						</Select.Trigger>
						<Select.Content>
							{#each outcomeOptions as option}
								<Select.Item value={option.value}>{option.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{/if}
		{/if}

		<div class="flex items-center gap-6">
			<div class="flex items-center gap-2">
				<Switch id="internal" bind:checked={isInternal} />
				<Label for="internal" class="text-sm">Internal only</Label>
			</div>
			<div class="flex items-center gap-2">
				<Switch id="pinned" bind:checked={isPinned} />
				<Label for="pinned" class="text-sm">Pin to top</Label>
			</div>
		</div>
	</Card.Content>
	<Card.Footer class="flex justify-end gap-2">
		<Button variant="outline" onclick={oncancel}>Cancel</Button>
		<Button onclick={handleSubmit} disabled={!title.trim() || isSaving}>
			{isSaving ? 'Saving...' : activity ? 'Update' : 'Create'}
		</Button>
	</Card.Footer>
</Card.Root>
