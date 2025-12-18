<script lang="ts">
	import { onMount } from 'svelte';
	import { activitiesApi, type Activity, type ActivityType } from '$lib/api/activities';
	import * as Card from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
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
		AlertTriangle,
		Check,
		Filter,
		RefreshCw,
		ChevronLeft,
		ChevronRight,
		User
	} from 'lucide-svelte';
	import { formatDistanceToNow, format, parseISO, isPast } from 'date-fns';

	let activities = $state<Activity[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);
	let currentPage = $state(1);
	let lastPage = $state(1);
	let total = $state(0);
	let perPage = $state(25);

	// Filters
	let selectedType = $state<ActivityType | 'all'>('all');
	let includeSystem = $state(true);
	let scheduledOnly = $state(false);
	let overdueOnly = $state(false);

	// Quick views
	let activeTab = $state('all');

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
		note: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
		call: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
		meeting: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
		task: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
		email: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
		status_change: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
		field_update: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
		comment: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
		attachment: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
		created: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
		deleted: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
	};

	const typeLabels: Record<string, string> = {
		note: 'Note',
		call: 'Call',
		meeting: 'Meeting',
		task: 'Task',
		email: 'Email',
		status_change: 'Status Change',
		field_update: 'Field Update',
		comment: 'Comment',
		attachment: 'Attachment',
		created: 'Created',
		deleted: 'Deleted'
	};

	async function loadActivities() {
		loading = true;
		error = null;

		try {
			const params: Record<string, unknown> = {
				page: currentPage,
				per_page: perPage,
				include_system: includeSystem
			};

			if (selectedType !== 'all') {
				params.type = selectedType;
			}

			if (activeTab === 'scheduled' || scheduledOnly) {
				params.scheduled_only = true;
			}

			if (activeTab === 'overdue' || overdueOnly) {
				params.overdue_only = true;
			}

			const response = await activitiesApi.list(params as any);
			activities = response.data;
			currentPage = response.meta.current_page;
			lastPage = response.meta.last_page;
			total = response.meta.total;
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
		const now = new Date();
		const diff = now.getTime() - date.getTime();
		const daysDiff = diff / (1000 * 60 * 60 * 24);

		if (daysDiff < 1) {
			return formatDistanceToNow(date, { addSuffix: true });
		} else if (daysDiff < 7) {
			return format(date, "EEEE 'at' h:mm a");
		} else {
			return format(date, "MMM d, yyyy 'at' h:mm a");
		}
	}

	function isOverdue(activity: Activity): boolean {
		if (!activity.scheduled_at || activity.completed_at) return false;
		return isPast(parseISO(activity.scheduled_at));
	}

	function handleTabChange(value: string) {
		activeTab = value;
		currentPage = 1;
		loadActivities();
	}

	function handleFilterChange() {
		currentPage = 1;
		loadActivities();
	}

	function goToPage(page: number) {
		if (page >= 1 && page <= lastPage) {
			currentPage = page;
			loadActivities();
		}
	}

	onMount(() => {
		loadActivities();
	});
</script>

<svelte:head>
	<title>Activity Feed - VRTX CRM</title>
</svelte:head>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
		<div>
			<h1 class="text-3xl font-bold tracking-tight">Activity Feed</h1>
			<p class="text-muted-foreground">
				Track all activities across your CRM records.
			</p>
		</div>
		<Button onclick={() => loadActivities()} variant="outline" disabled={loading}>
			<RefreshCw class="mr-2 h-4 w-4 {loading ? 'animate-spin' : ''}" />
			Refresh
		</Button>
	</div>

	<!-- Tabs for quick views -->
	<Tabs.Root value={activeTab} onValueChange={handleTabChange}>
		<Tabs.List>
			<Tabs.Trigger value="all">All Activities</Tabs.Trigger>
			<Tabs.Trigger value="scheduled">Scheduled</Tabs.Trigger>
			<Tabs.Trigger value="overdue">Overdue</Tabs.Trigger>
		</Tabs.List>
	</Tabs.Root>

	<!-- Filters -->
	<Card.Root>
		<Card.Header class="pb-3">
			<div class="flex items-center gap-2">
				<Filter class="h-4 w-4 text-muted-foreground" />
				<Card.Title class="text-base">Filters</Card.Title>
			</div>
		</Card.Header>
		<Card.Content>
			<div class="flex flex-wrap items-center gap-4">
				<div class="min-w-[180px]">
					<Label class="text-sm">Activity Type</Label>
					<Select.Root
						type="single"
						value={selectedType}
						onValueChange={(v) => {
							if (v) {
								selectedType = v as ActivityType | 'all';
								handleFilterChange();
							}
						}}
					>
						<Select.Trigger class="mt-1">
							{selectedType === 'all' ? 'All Types' : typeLabels[selectedType]}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="all">All Types</Select.Item>
							{#each Object.entries(typeLabels) as [value, label]}
								<Select.Item {value}>{label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="flex items-center gap-2">
					<Checkbox
						id="include-system"
						checked={includeSystem}
						onCheckedChange={(checked) => {
							includeSystem = checked === true;
							handleFilterChange();
						}}
					/>
					<Label for="include-system" class="text-sm">Include system activities</Label>
				</div>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Activities List -->
	{#if loading}
		<div class="space-y-4">
			{#each [1, 2, 3, 4, 5] as _}
				<Card.Root>
					<Card.Content class="flex gap-4 pt-6">
						<Skeleton class="h-10 w-10 rounded-full" />
						<div class="flex-1 space-y-2">
							<Skeleton class="h-4 w-3/4" />
							<Skeleton class="h-3 w-1/2" />
							<Skeleton class="h-3 w-1/4" />
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>
	{:else if error}
		<Card.Root class="border-destructive/50 bg-destructive/5">
			<Card.Content class="flex items-center gap-4 pt-6">
				<AlertTriangle class="h-8 w-8 text-destructive" />
				<div>
					<p class="font-medium text-destructive">Failed to load activities</p>
					<p class="text-sm text-muted-foreground">{error}</p>
				</div>
			</Card.Content>
		</Card.Root>
	{:else if activities.length === 0}
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12 text-center">
				<div class="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<ActivityIcon class="h-8 w-8 text-muted-foreground" />
				</div>
				<h3 class="mt-4 font-semibold">No activities found</h3>
				<p class="mt-2 max-w-sm text-sm text-muted-foreground">
					{#if activeTab === 'overdue'}
						You don't have any overdue activities. Great job staying on top of things!
					{:else if activeTab === 'scheduled'}
						You don't have any scheduled activities.
					{:else}
						No activities match your current filters.
					{/if}
				</p>
			</Card.Content>
		</Card.Root>
	{:else}
		<div class="space-y-3">
			{#each activities as activity (activity.id)}
				{@const Icon = typeIcons[activity.type] || ActivityIcon}
				{@const colorClass = typeColors[activity.type] || typeColors.field_update}
				{@const overdue = isOverdue(activity)}

				<Card.Root class="{activity.is_pinned ? 'border-primary/50 bg-primary/5' : ''} {overdue ? 'border-destructive/50' : ''}">
					<Card.Content class="pt-6">
						<div class="flex gap-4">
							<!-- Icon -->
							<div class="flex-shrink-0">
								<div class="flex h-10 w-10 items-center justify-center rounded-full {colorClass}">
									<Icon class="h-5 w-5" />
								</div>
							</div>

							<!-- Content -->
							<div class="min-w-0 flex-1">
								<div class="flex items-start justify-between gap-4">
									<div class="min-w-0">
										<div class="flex items-center gap-2 flex-wrap">
											<h3 class="font-medium truncate">{activity.title}</h3>
											{#if activity.is_pinned}
												<Pin class="h-3 w-3 text-primary flex-shrink-0" />
											{/if}
											{#if activity.is_system}
												<Badge variant="secondary" class="text-xs">System</Badge>
											{/if}
											{#if overdue}
												<Badge variant="destructive" class="text-xs">Overdue</Badge>
											{/if}
											{#if activity.completed_at}
												<Badge variant="outline" class="text-xs text-green-600">
													<Check class="mr-1 h-3 w-3" />
													Completed
												</Badge>
											{/if}
										</div>

										{#if activity.description}
											<p class="mt-1 text-sm text-muted-foreground line-clamp-2">
												{activity.description}
											</p>
										{/if}

										<div class="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
											<span class="flex items-center gap-1">
												<User class="h-3 w-3" />
												{activity.user?.name || 'Unknown User'}
											</span>
											<span>{formatActivityDate(activity.created_at)}</span>
											{#if activity.scheduled_at}
												<span class="flex items-center gap-1 {overdue ? 'text-destructive' : ''}">
													<Clock class="h-3 w-3" />
													Scheduled: {format(parseISO(activity.scheduled_at), 'MMM d, h:mm a')}
												</span>
											{/if}
											{#if activity.duration_minutes}
												<span>{activity.duration_minutes} min</span>
											{/if}
										</div>

										{#if activity.subject_type}
											<div class="mt-2">
												<Badge variant="outline" class="text-xs">
													{activity.subject_type} #{activity.subject_id}
												</Badge>
											</div>
										{/if}
									</div>

									<!-- Actions -->
									<div class="flex items-center gap-1 flex-shrink-0">
										{#if activity.scheduled_at && !activity.completed_at}
											<Button
												variant="ghost"
												size="sm"
												onclick={() => completeActivity(activity)}
												title="Mark as completed"
											>
												<Check class="h-4 w-4" />
											</Button>
										{/if}
										<Button
											variant="ghost"
											size="sm"
											onclick={() => togglePin(activity)}
											title={activity.is_pinned ? 'Unpin' : 'Pin'}
										>
											{#if activity.is_pinned}
												<PinOff class="h-4 w-4" />
											{:else}
												<Pin class="h-4 w-4" />
											{/if}
										</Button>
									</div>
								</div>
							</div>
						</div>
					</Card.Content>
				</Card.Root>
			{/each}
		</div>

		<!-- Pagination -->
		{#if lastPage > 1}
			<div class="flex items-center justify-between">
				<p class="text-sm text-muted-foreground">
					Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, total)} of {total} activities
				</p>
				<div class="flex items-center gap-2">
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === 1}
						onclick={() => goToPage(currentPage - 1)}
					>
						<ChevronLeft class="h-4 w-4" />
						Previous
					</Button>
					<span class="text-sm">
						Page {currentPage} of {lastPage}
					</span>
					<Button
						variant="outline"
						size="sm"
						disabled={currentPage === lastPage}
						onclick={() => goToPage(currentPage + 1)}
					>
						Next
						<ChevronRight class="h-4 w-4" />
					</Button>
				</div>
			</div>
		{/if}
	{/if}
</div>
