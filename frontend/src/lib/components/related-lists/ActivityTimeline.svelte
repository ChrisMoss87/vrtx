<script lang="ts">
	import { onMount } from 'svelte';
	import { activitiesApi, type Activity, type ActivityType } from '$lib/api/activities';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import {
		Activity as ActivityIcon,
		StickyNote,
		Phone,
		Calendar,
		CheckSquare,
		Mail,
		GitBranch,
		Edit,
		MessageCircle,
		Paperclip,
		PlusCircle,
		Trash,
		Pin,
		PinOff,
		Clock,
		Check,
		ChevronDown,
		ChevronUp,
		Plus,
		User
	} from 'lucide-svelte';
	import { formatDistanceToNow, format, parseISO, isPast } from 'date-fns';

	interface Props {
		subjectType: string;
		subjectId: number;
		limit?: number;
		showAddButton?: boolean;
		onAddActivity?: () => void;
	}

	let { subjectType, subjectId, limit = 10, showAddButton = true, onAddActivity }: Props = $props();

	let activities = $state<Activity[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let expanded = $state(true);
	let showAll = $state(false);

	const typeIcons: Record<string, typeof ActivityIcon> = {
		note: StickyNote,
		call: Phone,
		meeting: Calendar,
		task: CheckSquare,
		email: Mail,
		status_change: GitBranch,
		field_update: Edit,
		comment: MessageCircle,
		attachment: Paperclip,
		created: PlusCircle,
		deleted: Trash
	};

	const typeColors: Record<string, string> = {
		note: 'bg-yellow-500',
		call: 'bg-blue-500',
		meeting: 'bg-purple-500',
		task: 'bg-green-500',
		email: 'bg-cyan-500',
		status_change: 'bg-orange-500',
		field_update: 'bg-gray-500',
		comment: 'bg-pink-500',
		attachment: 'bg-indigo-500',
		created: 'bg-green-500',
		deleted: 'bg-red-500'
	};

	async function loadActivities() {
		loading = true;
		error = null;

		try {
			const response = await activitiesApi.timeline({
				subject_type: subjectType,
				subject_id: subjectId,
				limit: showAll ? 100 : limit
			});
			activities = response.data;
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load activities';
		} finally {
			loading = false;
		}
	}

	async function togglePin(activity: Activity) {
		try {
			const result = await activitiesApi.togglePin(activity.id);
			const index = activities.findIndex((a) => a.id === activity.id);
			if (index !== -1) {
				activities[index] = result.data;
			}
		} catch (err) {
			console.error('Failed to toggle pin:', err);
		}
	}

	async function completeActivity(activity: Activity) {
		try {
			const result = await activitiesApi.complete(activity.id);
			const index = activities.findIndex((a) => a.id === activity.id);
			if (index !== -1) {
				activities[index] = result.data;
			}
		} catch (err) {
			console.error('Failed to complete activity:', err);
		}
	}

	function formatActivityDate(dateString: string): string {
		const date = parseISO(dateString);
		return formatDistanceToNow(date, { addSuffix: true });
	}

	function isOverdue(activity: Activity): boolean {
		if (!activity.scheduled_at || activity.completed_at) return false;
		return isPast(parseISO(activity.scheduled_at));
	}

	onMount(() => {
		loadActivities();
	});

	$effect(() => {
		if (subjectType && subjectId) {
			loadActivities();
		}
	});
</script>

<Card.Root>
	<Card.Header class="pb-3">
		<div class="flex items-center justify-between">
			<button
				class="flex items-center gap-2 hover:text-primary transition-colors"
				onclick={() => (expanded = !expanded)}
			>
				{#if expanded}
					<ChevronUp class="h-4 w-4" />
				{:else}
					<ChevronDown class="h-4 w-4" />
				{/if}
				<Card.Title class="text-base">Activities</Card.Title>
				{#if !loading}
					<Badge variant="secondary" class="ml-2">{activities.length}</Badge>
				{/if}
			</button>
			{#if showAddButton && onAddActivity}
				<Button variant="outline" size="sm" onclick={onAddActivity}>
					<Plus class="mr-1 h-3 w-3" />
					Add
				</Button>
			{/if}
		</div>
	</Card.Header>

	{#if expanded}
		<Card.Content>
			{#if loading}
				<div class="space-y-3">
					{#each [1, 2, 3] as _}
						<div class="flex gap-3">
							<Skeleton class="h-8 w-8 rounded-full flex-shrink-0" />
							<div class="flex-1 space-y-2">
								<Skeleton class="h-4 w-3/4" />
								<Skeleton class="h-3 w-1/2" />
							</div>
						</div>
					{/each}
				</div>
			{:else if error}
				<p class="text-sm text-destructive">{error}</p>
			{:else if activities.length === 0}
				<p class="text-sm text-muted-foreground text-center py-4">No activities yet</p>
			{:else}
				<div class="relative">
					<!-- Timeline line -->
					<div class="absolute left-4 top-0 bottom-0 w-px bg-border"></div>

					<div class="space-y-4">
						{#each activities as activity (activity.id)}
							{@const Icon = typeIcons[activity.type] || ActivityIcon}
							{@const colorClass = typeColors[activity.type] || 'bg-gray-500'}
							{@const overdue = isOverdue(activity)}

							<div class="relative flex gap-3 pl-1">
								<!-- Timeline dot -->
								<div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full {colorClass} text-white flex-shrink-0">
									<Icon class="h-4 w-4" />
								</div>

								<!-- Content -->
								<div class="flex-1 min-w-0 pb-2">
									<div class="flex items-start justify-between gap-2">
										<div class="min-w-0">
											<div class="flex items-center gap-2 flex-wrap">
												<span class="font-medium text-sm truncate">{activity.title}</span>
												{#if activity.is_pinned}
													<Pin class="h-3 w-3 text-primary flex-shrink-0" />
												{/if}
												{#if overdue}
													<Badge variant="destructive" class="text-xs">Overdue</Badge>
												{/if}
												{#if activity.completed_at}
													<Badge variant="outline" class="text-xs text-green-600">
														<Check class="mr-1 h-2 w-2" />
														Done
													</Badge>
												{/if}
											</div>
											{#if activity.description}
												<p class="text-xs text-muted-foreground mt-0.5 line-clamp-2">
													{activity.description}
												</p>
											{/if}
											<div class="flex items-center gap-2 mt-1 text-xs text-muted-foreground">
												{#if activity.user}
													<span class="flex items-center gap-1">
														<User class="h-3 w-3" />
														{activity.user.name}
													</span>
												{/if}
												<span>{formatActivityDate(activity.created_at)}</span>
											</div>
										</div>

										<!-- Quick actions -->
										<div class="flex items-center gap-1 flex-shrink-0">
											{#if activity.scheduled_at && !activity.completed_at}
												<Button
													variant="ghost"
													size="icon"
													class="h-6 w-6"
													onclick={() => completeActivity(activity)}
													title="Mark complete"
												>
													<Check class="h-3 w-3" />
												</Button>
											{/if}
											<Button
												variant="ghost"
												size="icon"
												class="h-6 w-6"
												onclick={() => togglePin(activity)}
												title={activity.is_pinned ? 'Unpin' : 'Pin'}
											>
												{#if activity.is_pinned}
													<PinOff class="h-3 w-3" />
												{:else}
													<Pin class="h-3 w-3" />
												{/if}
											</Button>
										</div>
									</div>
								</div>
							</div>
						{/each}
					</div>
				</div>

				{#if activities.length >= limit && !showAll}
					<Button
						variant="ghost"
						size="sm"
						class="w-full mt-4"
						onclick={() => {
							showAll = true;
							loadActivities();
						}}
					>
						Show all activities
					</Button>
				{/if}
			{/if}
		</Card.Content>
	{/if}
</Card.Root>
