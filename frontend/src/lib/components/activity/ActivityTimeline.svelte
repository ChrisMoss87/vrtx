<script lang="ts">
	import {
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
		MoreHorizontal,
		Pin,
		Clock,
		Check,
		X,
		ChevronDown,
		ChevronUp,
		Plus
	} from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { Avatar, AvatarFallback } from '$lib/components/ui/avatar';
	import { Separator } from '$lib/components/ui/separator';
	import { activitiesApi, type Activity, type ActivityType, getActivityColor } from '$lib/api/activity';
	import { cn } from '$lib/utils';
	import { formatDistanceToNow } from 'date-fns';
	import ActivityForm from './ActivityForm.svelte';
	import DOMPurify from 'isomorphic-dompurify';

	/**
	 * Sanitize HTML content to prevent XSS attacks.
	 */
	function sanitizeHtml(html: string): string {
		return DOMPurify.sanitize(html, {
			ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'div', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code'],
			ALLOWED_ATTR: ['href', 'class', 'style', 'target'],
			ALLOW_DATA_ATTR: false,
		});
	}

	interface Props {
		subjectType: string;
		subjectId: number;
		activities?: Activity[];
		readonly?: boolean;
		showSystemActivities?: boolean;
		onactivitycreated?: (activity: Activity) => void;
		onactivityupdated?: (activity: Activity) => void;
		onactivitydeleted?: (id: number) => void;
		class?: string;
	}

	let {
		subjectType,
		subjectId,
		activities = $bindable([]),
		readonly = false,
		showSystemActivities = true,
		onactivitycreated,
		onactivityupdated,
		onactivitydeleted,
		class: className = ''
	}: Props = $props();

	// State
	let isLoading = $state(false);
	let expandedIds = $state<Set<number>>(new Set());
	let showForm = $state(false);
	let editingActivity = $state<Activity | null>(null);
	let filterType = $state<ActivityType | 'all'>('all');

	// Derived
	let filteredActivities = $derived(() => {
		let result = activities;

		if (!showSystemActivities) {
			result = result.filter(a => !a.is_system);
		}

		if (filterType !== 'all') {
			result = result.filter(a => a.type === filterType);
		}

		// Sort: pinned first, then by date
		return [...result].sort((a, b) => {
			if (a.is_pinned && !b.is_pinned) return -1;
			if (!a.is_pinned && b.is_pinned) return 1;
			return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
		});
	});

	// Load activities if not provided
	$effect(() => {
		if (activities.length === 0 && subjectType && subjectId) {
			loadActivities();
		}
	});

	async function loadActivities() {
		isLoading = true;
		try {
			activities = await activitiesApi.getTimeline(subjectType, subjectId, {
				include_system: showSystemActivities
			});
		} catch (error) {
			console.error('Failed to load activities:', error);
		} finally {
			isLoading = false;
		}
	}

	function toggleExpanded(id: number) {
		const newSet = new Set(expandedIds);
		if (newSet.has(id)) {
			newSet.delete(id);
		} else {
			newSet.add(id);
		}
		expandedIds = newSet;
	}

	async function togglePin(activity: Activity) {
		try {
			const response = await activitiesApi.togglePin(activity.id);
			const index = activities.findIndex(a => a.id === activity.id);
			if (index !== -1) {
				activities[index] = response.data;
				activities = [...activities];
			}
		} catch (error) {
			console.error('Failed to toggle pin:', error);
		}
	}

	async function completeActivity(activity: Activity) {
		try {
			const response = await activitiesApi.complete(activity.id);
			const index = activities.findIndex(a => a.id === activity.id);
			if (index !== -1) {
				activities[index] = response.data;
				activities = [...activities];
			}
			onactivityupdated?.(response.data);
		} catch (error) {
			console.error('Failed to complete activity:', error);
		}
	}

	async function deleteActivity(activity: Activity) {
		if (!confirm('Are you sure you want to delete this activity?')) return;

		try {
			await activitiesApi.delete(activity.id);
			activities = activities.filter(a => a.id !== activity.id);
			onactivitydeleted?.(activity.id);
		} catch (error) {
			console.error('Failed to delete activity:', error);
		}
	}

	function handleActivitySaved(activity: Activity) {
		if (editingActivity) {
			const index = activities.findIndex(a => a.id === activity.id);
			if (index !== -1) {
				activities[index] = activity;
				activities = [...activities];
			}
			onactivityupdated?.(activity);
		} else {
			activities = [activity, ...activities];
			onactivitycreated?.(activity);
		}
		showForm = false;
		editingActivity = null;
	}

	function getIcon(type: ActivityType) {
		const icons = {
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
		return icons[type] ?? Edit;
	}

	function getColorClass(type: ActivityType): string {
		const colors: Record<ActivityType, string> = {
			note: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
			call: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
			meeting: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
			task: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
			email: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900 dark:text-cyan-300',
			status_change: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
			field_update: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
			comment: 'bg-pink-100 text-pink-700 dark:bg-pink-900 dark:text-pink-300',
			attachment: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
			created: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
			deleted: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
		};
		return colors[type] ?? 'bg-gray-100 text-gray-700';
	}

	function formatDate(dateString: string): string {
		return formatDistanceToNow(new Date(dateString), { addSuffix: true });
	}

	function getInitials(name: string): string {
		return name
			.split(' ')
			.map(n => n[0])
			.join('')
			.toUpperCase()
			.slice(0, 2);
	}

	const activityTypes: { value: ActivityType | 'all'; label: string }[] = [
		{ value: 'all', label: 'All Activities' },
		{ value: 'note', label: 'Notes' },
		{ value: 'call', label: 'Calls' },
		{ value: 'meeting', label: 'Meetings' },
		{ value: 'task', label: 'Tasks' },
		{ value: 'email', label: 'Emails' },
		{ value: 'comment', label: 'Comments' }
	];
</script>

<div class={cn('flex flex-col', className)}>
	<!-- Header -->
	<div class="mb-4 flex items-center justify-between">
		<div class="flex items-center gap-2">
			<h3 class="font-medium">Activity Timeline</h3>
			<Badge variant="secondary">{filteredActivities().length}</Badge>
		</div>
		<div class="flex items-center gap-2">
			<DropdownMenu.Root>
				<DropdownMenu.Trigger>
					{#snippet child({ props })}
						<Button {...props} variant="outline" size="sm">
							{activityTypes.find(t => t.value === filterType)?.label}
							<ChevronDown class="ml-1 h-3 w-3" />
						</Button>
					{/snippet}
				</DropdownMenu.Trigger>
				<DropdownMenu.Content>
					{#each activityTypes as actType}
						<DropdownMenu.Item onclick={() => filterType = actType.value}>
							{actType.label}
						</DropdownMenu.Item>
					{/each}
				</DropdownMenu.Content>
			</DropdownMenu.Root>

			{#if !readonly}
				<Button size="sm" onclick={() => { showForm = true; editingActivity = null; }}>
					<Plus class="mr-1 h-4 w-4" />
					Add
				</Button>
			{/if}
		</div>
	</div>

	<!-- Activity Form -->
	{#if showForm}
		<div class="mb-4">
			<ActivityForm
				{subjectType}
				{subjectId}
				activity={editingActivity}
				onsave={handleActivitySaved}
				oncancel={() => { showForm = false; editingActivity = null; }}
			/>
		</div>
	{/if}

	<!-- Timeline -->
	<div class="relative">
		<!-- Timeline line -->
		<div class="absolute left-5 top-0 h-full w-0.5 bg-border"></div>

		<!-- Activities -->
		<div class="space-y-4">
			{#each filteredActivities() as activity (activity.id)}
				{@const Icon = getIcon(activity.type)}
				{@const isExpanded = expandedIds.has(activity.id)}
				{@const hasContent = activity.content || activity.description || activity.metadata}

				<div class="relative flex gap-4">
					<!-- Icon -->
					<div
						class={cn(
							'relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
							getColorClass(activity.type)
						)}
					>
						<Icon class="h-4 w-4" />
					</div>

					<!-- Content -->
					<div class="flex-1 pb-4">
						<div
							class={cn(
								'rounded-lg border bg-card p-3',
								activity.is_pinned && 'border-primary'
							)}
						>
							<!-- Header -->
							<div class="flex items-start justify-between">
								<div class="flex items-center gap-2">
									{#if activity.is_pinned}
										<Pin class="h-3 w-3 text-primary" />
									{/if}
									<span class="font-medium">{activity.title}</span>
									{#if activity.is_internal}
										<Badge variant="outline" class="text-xs">Internal</Badge>
									{/if}
									{#if activity.is_system}
										<Badge variant="secondary" class="text-xs">System</Badge>
									{/if}
								</div>

								<div class="flex items-center gap-1">
									{#if activity.scheduled_at && !activity.completed_at}
										<Badge
											variant={new Date(activity.scheduled_at) < new Date() ? 'destructive' : 'outline'}
											class="text-xs"
										>
											<Clock class="mr-1 h-3 w-3" />
											{formatDate(activity.scheduled_at)}
										</Badge>
									{/if}

									{#if activity.completed_at}
										<Badge variant="default" class="text-xs">
											<Check class="mr-1 h-3 w-3" />
											Completed
										</Badge>
									{/if}

									{#if !readonly}
										<DropdownMenu.Root>
											<DropdownMenu.Trigger>
												{#snippet child({ props })}
													<Button {...props} variant="ghost" size="icon" class="h-6 w-6">
														<MoreHorizontal class="h-3 w-3" />
													</Button>
												{/snippet}
											</DropdownMenu.Trigger>
											<DropdownMenu.Content align="end">
												<DropdownMenu.Item onclick={() => togglePin(activity)}>
													<Pin class="mr-2 h-4 w-4" />
													{activity.is_pinned ? 'Unpin' : 'Pin'}
												</DropdownMenu.Item>
												{#if ['call', 'meeting', 'task'].includes(activity.type) && !activity.completed_at}
													<DropdownMenu.Item onclick={() => completeActivity(activity)}>
														<Check class="mr-2 h-4 w-4" />
														Mark Complete
													</DropdownMenu.Item>
												{/if}
												<DropdownMenu.Item onclick={() => { editingActivity = activity; showForm = true; }}>
													<Edit class="mr-2 h-4 w-4" />
													Edit
												</DropdownMenu.Item>
												<DropdownMenu.Separator />
												<DropdownMenu.Item
													class="text-destructive"
													onclick={() => deleteActivity(activity)}
												>
													<Trash class="mr-2 h-4 w-4" />
													Delete
												</DropdownMenu.Item>
											</DropdownMenu.Content>
										</DropdownMenu.Root>
									{/if}
								</div>
							</div>

							<!-- Meta -->
							<div class="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
								{#if activity.user}
									<span>{activity.user.name}</span>
									<span>•</span>
								{/if}
								<span>{formatDate(activity.created_at)}</span>
								{#if activity.duration_minutes}
									<span>•</span>
									<span>{activity.duration_minutes} min</span>
								{/if}
								{#if activity.outcome}
									<span>•</span>
									<span class="capitalize">{activity.outcome.replace('_', ' ')}</span>
								{/if}
							</div>

							<!-- Expandable content -->
							{#if hasContent}
								<Collapsible.Root open={isExpanded} onOpenChange={() => toggleExpanded(activity.id)}>
									<Collapsible.Trigger class="mt-2 flex items-center gap-1 text-xs text-primary hover:underline">
										{isExpanded ? 'Show less' : 'Show more'}
										{#if isExpanded}
											<ChevronUp class="h-3 w-3" />
										{:else}
											<ChevronDown class="h-3 w-3" />
										{/if}
									</Collapsible.Trigger>
									<Collapsible.Content>
										<div class="mt-2 space-y-2">
											{#if activity.description}
												<p class="text-sm text-muted-foreground">{activity.description}</p>
											{/if}
											{#if activity.content}
												<div class="prose prose-sm dark:prose-invert max-w-none rounded-md bg-muted/50 p-2">
													{@html sanitizeHtml(activity.content)}
												</div>
											{/if}
											{#if activity.metadata?.changes}
												<div class="text-sm">
													{#each Object.entries(activity.metadata.changes) as [field, change]}
														<div class="flex items-center gap-2">
															<span class="font-medium">{field}:</span>
															<span class="text-muted-foreground line-through">{change.old ?? '(empty)'}</span>
															<span>→</span>
															<span>{change.new ?? '(empty)'}</span>
														</div>
													{/each}
												</div>
											{/if}
										</div>
									</Collapsible.Content>
								</Collapsible.Root>
							{/if}
						</div>
					</div>
				</div>
			{/each}

			{#if filteredActivities().length === 0}
				<div class="py-8 text-center text-muted-foreground">
					<p>No activities yet</p>
				</div>
			{/if}
		</div>
	</div>
</div>
