<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Badge } from '$lib/components/ui/badge';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Table from '$lib/components/ui/table';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Select from '$lib/components/ui/select';
	import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
	import * as Tabs from '$lib/components/ui/tabs';
	import {
		ArrowLeft,
		Loader2,
		MoreHorizontal,
		Calendar,
		CheckCircle,
		XCircle,
		UserX,
		Mail,
		Phone,
		Clock,
		CalendarDays,
		TrendingUp,
		Users,
		Search,
		RefreshCw,
		ChevronLeft,
		ChevronRight
	} from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		getScheduledMeetings,
		getMeetingStats,
		cancelMeeting,
		markMeetingComplete,
		markMeetingNoShow,
		getMeetingStatusLabel,
		getMeetingStatusVariant,
		getLocationTypeLabel,
		type ScheduledMeeting,
		type MeetingStats
	} from '$lib/api/scheduling';

	let meetings = $state<ScheduledMeeting[]>([]);
	let upcomingMeetings = $state<ScheduledMeeting[]>([]);
	let stats = $state<MeetingStats | null>(null);
	let loading = $state(true);
	let statusFilter = $state<string>('all');
	let searchQuery = $state('');
	let currentPage = $state(1);
	let totalPages = $state(1);
	let total = $state(0);
	let activeTab = $state('upcoming');

	// Cancel dialog
	let cancelDialogOpen = $state(false);
	let meetingToCancel = $state<ScheduledMeeting | null>(null);
	let cancelReason = $state('');
	let canceling = $state(false);

	// Calendar navigation
	let calendarDate = $state(new Date());

	onMount(async () => {
		await loadData();
	});

	async function loadData() {
		loading = true;
		try {
			const [meetingsResponse, upcomingResponse, statsData] = await Promise.all([
				getScheduledMeetings({
					status: statusFilter !== 'all' ? (statusFilter as any) : undefined,
					page: currentPage,
					per_page: 20
				}),
				getScheduledMeetings({
					status: 'scheduled',
					start_date: new Date().toISOString().split('T')[0],
					per_page: 10
				}),
				getMeetingStats()
			]);

			meetings = meetingsResponse.data;
			totalPages = meetingsResponse.meta.last_page;
			total = meetingsResponse.meta.total;
			upcomingMeetings = upcomingResponse.data;
			stats = statsData;
		} catch (error) {
			console.error('Failed to load meetings:', error);
			toast.error('Failed to load meetings');
		} finally {
			loading = false;
		}
	}

	function handleStatusFilterChange(value: string) {
		statusFilter = value;
		currentPage = 1;
		loadData();
	}

	function handlePageChange(page: number) {
		currentPage = page;
		loadData();
	}

	function openCancelDialog(meeting: ScheduledMeeting) {
		meetingToCancel = meeting;
		cancelReason = '';
		cancelDialogOpen = true;
	}

	async function handleCancel() {
		if (!meetingToCancel) return;

		canceling = true;
		try {
			await cancelMeeting(meetingToCancel.id, cancelReason || undefined);
			toast.success('Meeting cancelled');
			cancelDialogOpen = false;
			meetingToCancel = null;
			await loadData();
		} catch (error) {
			console.error('Failed to cancel meeting:', error);
			toast.error('Failed to cancel meeting');
		} finally {
			canceling = false;
		}
	}

	async function handleMarkComplete(meeting: ScheduledMeeting) {
		try {
			await markMeetingComplete(meeting.id);
			toast.success('Meeting marked as completed');
			await loadData();
		} catch (error) {
			console.error('Failed to mark meeting complete:', error);
			toast.error('Failed to update meeting');
		}
	}

	async function handleMarkNoShow(meeting: ScheduledMeeting) {
		try {
			await markMeetingNoShow(meeting.id);
			toast.success('Meeting marked as no-show');
			await loadData();
		} catch (error) {
			console.error('Failed to mark meeting as no-show:', error);
			toast.error('Failed to update meeting');
		}
	}

	function formatDateTime(dateString: string): string {
		return new Date(dateString).toLocaleString('en-US', {
			weekday: 'short',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatTime(dateString: string): string {
		return new Date(dateString).toLocaleTimeString('en-US', {
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatDateShort(dateString: string): string {
		const date = new Date(dateString);
		const today = new Date();
		const tomorrow = new Date(today);
		tomorrow.setDate(tomorrow.getDate() + 1);

		if (date.toDateString() === today.toDateString()) {
			return 'Today';
		} else if (date.toDateString() === tomorrow.toDateString()) {
			return 'Tomorrow';
		} else {
			return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
		}
	}

	function getRelativeTime(dateString: string): string {
		const date = new Date(dateString);
		const now = new Date();
		const diffMs = date.getTime() - now.getTime();
		const diffMins = Math.floor(diffMs / (1000 * 60));
		const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
		const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

		if (diffMins < 0) return 'Past';
		if (diffMins < 60) return `In ${diffMins} min`;
		if (diffHours < 24) return `In ${diffHours} hr`;
		if (diffDays === 1) return 'Tomorrow';
		return `In ${diffDays} days`;
	}

	function isPast(dateString: string): boolean {
		return new Date(dateString) < new Date();
	}

	function isToday(dateString: string): boolean {
		const date = new Date(dateString);
		const today = new Date();
		return date.toDateString() === today.toDateString();
	}

	function isSoon(dateString: string): boolean {
		const date = new Date(dateString);
		const now = new Date();
		const diffMs = date.getTime() - now.getTime();
		return diffMs > 0 && diffMs < 60 * 60 * 1000; // Within 1 hour
	}

	// Group meetings by date
	function groupMeetingsByDate(meetings: ScheduledMeeting[]): Map<string, ScheduledMeeting[]> {
		const groups = new Map<string, ScheduledMeeting[]>();
		meetings.forEach(meeting => {
			const dateKey = new Date(meeting.start_time).toDateString();
			if (!groups.has(dateKey)) {
				groups.set(dateKey, []);
			}
			groups.get(dateKey)!.push(meeting);
		});
		return groups;
	}

	$effect(() => {
		// Re-filter when search changes
		if (searchQuery) {
			// Client-side filter for search
		}
	});
</script>

<svelte:head>
	<title>Scheduled Meetings | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto p-6">
	<!-- Header -->
	<div class="mb-6">
		<Button variant="ghost" onclick={() => goto('/settings/scheduling')}>
			<ArrowLeft class="mr-2 h-4 w-4" />
			Back to Scheduling
		</Button>
	</div>

	<div class="mb-6 flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Scheduled Meetings</h1>
			<p class="text-muted-foreground">View and manage your booked meetings</p>
		</div>
		<Button variant="outline" onclick={loadData} disabled={loading}>
			<RefreshCw class="mr-2 h-4 w-4 {loading ? 'animate-spin' : ''}" />
			Refresh
		</Button>
	</div>

	<!-- Stats Cards -->
	{#if stats}
		<div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
			<Card.Root class="border-l-4 border-l-blue-500">
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Total</p>
							<div class="text-2xl font-bold">{stats.total}</div>
						</div>
						<CalendarDays class="h-8 w-8 text-blue-500 opacity-50" />
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root class="border-l-4 border-l-green-500">
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Completed</p>
							<div class="text-2xl font-bold text-green-600">{stats.completed}</div>
						</div>
						<CheckCircle class="h-8 w-8 text-green-500 opacity-50" />
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root class="border-l-4 border-l-orange-500">
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">This Week</p>
							<div class="text-2xl font-bold text-orange-600">{stats.upcoming_week ?? 0}</div>
						</div>
						<Clock class="h-8 w-8 text-orange-500 opacity-50" />
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root class="border-l-4 border-l-red-500">
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Cancelled</p>
							<div class="text-2xl font-bold text-red-600">{stats.cancelled}</div>
						</div>
						<XCircle class="h-8 w-8 text-red-500 opacity-50" />
					</div>
				</Card.Content>
			</Card.Root>
			<Card.Root class="border-l-4 border-l-purple-500">
				<Card.Content class="pt-6">
					<div class="flex items-center justify-between">
						<div>
							<p class="text-sm font-medium text-muted-foreground">Show Rate</p>
							<div class="text-2xl font-bold text-purple-600">
								{stats.show_rate !== null ? `${stats.show_rate}%` : '—'}
							</div>
						</div>
						<TrendingUp class="h-8 w-8 text-purple-500 opacity-50" />
					</div>
				</Card.Content>
			</Card.Root>
		</div>
	{/if}

	<Tabs.Root bind:value={activeTab} class="space-y-4">
		<Tabs.List>
			<Tabs.Trigger value="upcoming">
				<Clock class="mr-2 h-4 w-4" />
				Upcoming
				{#if upcomingMeetings.length > 0}
					<Badge variant="secondary" class="ml-2">{upcomingMeetings.length}</Badge>
				{/if}
			</Tabs.Trigger>
			<Tabs.Trigger value="all">
				<Calendar class="mr-2 h-4 w-4" />
				All Meetings
			</Tabs.Trigger>
		</Tabs.List>

		<!-- Upcoming Meetings Tab -->
		<Tabs.Content value="upcoming">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
				</div>
			{:else if upcomingMeetings.length === 0}
				<Card.Root>
					<Card.Content class="flex flex-col items-center justify-center py-12">
						<Calendar class="mb-4 h-12 w-12 text-muted-foreground" />
						<h3 class="mb-2 text-lg font-medium">No upcoming meetings</h3>
						<p class="text-muted-foreground">
							Your calendar is clear! Meetings will appear here when people book with you.
						</p>
					</Card.Content>
				</Card.Root>
			{:else}
				<div class="space-y-4">
					{#each [...groupMeetingsByDate(upcomingMeetings)] as [dateKey, dayMeetings]}
						<div>
							<h3 class="mb-3 text-sm font-semibold text-muted-foreground">
								{formatDateShort(dayMeetings[0].start_time)}
							</h3>
							<div class="space-y-3">
								{#each dayMeetings as meeting}
									<Card.Root
										class="transition-all hover:shadow-md {isSoon(meeting.start_time) ? 'border-orange-300 bg-orange-50 dark:bg-orange-950' : ''}"
									>
										<Card.Content class="p-4">
											<div class="flex items-start justify-between">
												<div class="flex gap-4">
													<div class="flex flex-col items-center">
														<div
															class="flex h-12 w-12 items-center justify-center rounded-lg"
															style="background-color: {meeting.meeting_type?.color || '#6366f1'}20"
														>
															<Clock
																class="h-6 w-6"
																style="color: {meeting.meeting_type?.color || '#6366f1'}"
															/>
														</div>
														<span class="mt-1 text-xs font-medium" class:text-orange-600={isSoon(meeting.start_time)}>
															{getRelativeTime(meeting.start_time)}
														</span>
													</div>
													<div>
														<div class="flex items-center gap-2">
															<h4 class="font-semibold">{meeting.meeting_type?.name || 'Meeting'}</h4>
															<Badge variant="outline" class="text-xs">
																{meeting.meeting_type?.duration_minutes || 30} min
															</Badge>
														</div>
														<p class="mt-1 text-sm font-medium">{meeting.attendee_name}</p>
														<div class="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
															<span class="flex items-center gap-1">
																<Mail class="h-3 w-3" />
																{meeting.attendee_email}
															</span>
															{#if meeting.attendee_phone}
																<span class="flex items-center gap-1">
																	<Phone class="h-3 w-3" />
																	{meeting.attendee_phone}
																</span>
															{/if}
														</div>
														<p class="mt-2 text-sm">
															<span class="font-medium">{formatTime(meeting.start_time)}</span>
															<span class="text-muted-foreground"> - {formatTime(meeting.end_time)}</span>
														</p>
													</div>
												</div>
												<DropdownMenu.Root>
													<DropdownMenu.Trigger>
														{#snippet child({ props })}
															<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
																<MoreHorizontal class="h-4 w-4" />
															</Button>
														{/snippet}
													</DropdownMenu.Trigger>
													<DropdownMenu.Content align="end">
														{#if isPast(meeting.end_time)}
															<DropdownMenu.Item onclick={() => handleMarkComplete(meeting)}>
																<CheckCircle class="mr-2 h-4 w-4" />
																Mark Complete
															</DropdownMenu.Item>
															<DropdownMenu.Item onclick={() => handleMarkNoShow(meeting)}>
																<UserX class="mr-2 h-4 w-4" />
																Mark No-Show
															</DropdownMenu.Item>
															<DropdownMenu.Separator />
														{/if}
														<DropdownMenu.Item
															class="text-destructive focus:text-destructive"
															onclick={() => openCancelDialog(meeting)}
														>
															<XCircle class="mr-2 h-4 w-4" />
															Cancel Meeting
														</DropdownMenu.Item>
													</DropdownMenu.Content>
												</DropdownMenu.Root>
											</div>
										</Card.Content>
									</Card.Root>
								{/each}
							</div>
						</div>
					{/each}
				</div>
			{/if}
		</Tabs.Content>

		<!-- All Meetings Tab -->
		<Tabs.Content value="all">
			<!-- Filters -->
			<Card.Root class="mb-4">
				<Card.Content class="pt-6">
					<div class="flex items-center gap-4">
						<div class="relative flex-1">
							<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
							<Input
								placeholder="Search by name or email..."
								class="pl-9"
								bind:value={searchQuery}
							/>
						</div>
						<Select.Root type="single" value={statusFilter} onValueChange={handleStatusFilterChange}>
							<Select.Trigger class="w-[180px]">
								{statusFilter === 'all'
									? 'All Statuses'
									: getMeetingStatusLabel(statusFilter)}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="all">All Statuses</Select.Item>
								<Select.Item value="scheduled">Scheduled</Select.Item>
								<Select.Item value="completed">Completed</Select.Item>
								<Select.Item value="cancelled">Cancelled</Select.Item>
								<Select.Item value="no_show">No Show</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Meetings Table -->
			<Card.Root>
				<Card.Content class="p-0">
					{#if loading}
						<div class="flex items-center justify-center py-12">
							<Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
						</div>
					{:else if meetings.length === 0}
						<div class="flex flex-col items-center justify-center py-12">
							<Calendar class="mb-4 h-12 w-12 text-muted-foreground" />
							<h3 class="mb-2 text-lg font-medium">No meetings found</h3>
							<p class="text-muted-foreground">
								{statusFilter !== 'all'
									? 'Try changing the filter'
									: 'Meetings will appear here when people book with you'}
							</p>
						</div>
					{:else}
						<Table.Root>
							<Table.Header>
								<Table.Row>
									<Table.Head>Meeting</Table.Head>
									<Table.Head>Attendee</Table.Head>
									<Table.Head>Date & Time</Table.Head>
									<Table.Head>Status</Table.Head>
									<Table.Head class="w-[100px]"></Table.Head>
								</Table.Row>
							</Table.Header>
							<Table.Body>
								{#each meetings as meeting}
									<Table.Row>
										<Table.Cell>
											<div>
												<div class="flex items-center gap-2">
													<div
														class="h-2 w-2 rounded-full"
														style="background-color: {meeting.meeting_type?.color || '#6366f1'}"
													></div>
													<span class="font-medium">{meeting.meeting_type?.name || 'Meeting'}</span>
												</div>
												<div class="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
													<Clock class="h-3 w-3" />
													{meeting.meeting_type?.duration_minutes || 30} min
													{#if meeting.meeting_type?.location_type}
														<span>·</span>
														{getLocationTypeLabel(meeting.meeting_type.location_type)}
													{/if}
												</div>
											</div>
										</Table.Cell>
										<Table.Cell>
											<div>
												<p class="font-medium">{meeting.attendee_name}</p>
												<div class="flex items-center gap-3 text-sm text-muted-foreground">
													<span class="flex items-center gap-1">
														<Mail class="h-3 w-3" />
														{meeting.attendee_email}
													</span>
													{#if meeting.attendee_phone}
														<span class="flex items-center gap-1">
															<Phone class="h-3 w-3" />
															{meeting.attendee_phone}
														</span>
													{/if}
												</div>
											</div>
										</Table.Cell>
										<Table.Cell>
											<div>
												<p class="font-medium">{formatDateTime(meeting.start_time)}</p>
												<p class="text-sm text-muted-foreground">
													{formatTime(meeting.start_time)} - {formatTime(meeting.end_time)}
												</p>
											</div>
										</Table.Cell>
										<Table.Cell>
											<Badge variant={getMeetingStatusVariant(meeting.status)}>
												{getMeetingStatusLabel(meeting.status)}
											</Badge>
										</Table.Cell>
										<Table.Cell>
											{#if meeting.status === 'scheduled'}
												<DropdownMenu.Root>
													<DropdownMenu.Trigger>
														{#snippet child({ props })}
															<Button variant="ghost" size="icon" class="h-8 w-8" {...props}>
																<MoreHorizontal class="h-4 w-4" />
															</Button>
														{/snippet}
													</DropdownMenu.Trigger>
													<DropdownMenu.Content align="end">
														{#if isPast(meeting.end_time)}
															<DropdownMenu.Item onclick={() => handleMarkComplete(meeting)}>
																<CheckCircle class="mr-2 h-4 w-4" />
																Mark Complete
															</DropdownMenu.Item>
															<DropdownMenu.Item onclick={() => handleMarkNoShow(meeting)}>
																<UserX class="mr-2 h-4 w-4" />
																Mark No-Show
															</DropdownMenu.Item>
															<DropdownMenu.Separator />
														{/if}
														<DropdownMenu.Item
															class="text-destructive focus:text-destructive"
															onclick={() => openCancelDialog(meeting)}
														>
															<XCircle class="mr-2 h-4 w-4" />
															Cancel Meeting
														</DropdownMenu.Item>
													</DropdownMenu.Content>
												</DropdownMenu.Root>
											{/if}
										</Table.Cell>
									</Table.Row>
								{/each}
							</Table.Body>
						</Table.Root>

						<!-- Pagination -->
						{#if totalPages > 1}
							<div class="flex items-center justify-between border-t px-4 py-3">
								<p class="text-sm text-muted-foreground">
									Showing {meetings.length} of {total} meetings
								</p>
								<div class="flex items-center gap-2">
									<Button
										variant="outline"
										size="sm"
										disabled={currentPage === 1}
										onclick={() => handlePageChange(currentPage - 1)}
									>
										<ChevronLeft class="h-4 w-4" />
										Previous
									</Button>
									<span class="text-sm">
										Page {currentPage} of {totalPages}
									</span>
									<Button
										variant="outline"
										size="sm"
										disabled={currentPage === totalPages}
										onclick={() => handlePageChange(currentPage + 1)}
									>
										Next
										<ChevronRight class="h-4 w-4" />
									</Button>
								</div>
							</div>
						{/if}
					{/if}
				</Card.Content>
			</Card.Root>
		</Tabs.Content>
	</Tabs.Root>
</div>

<!-- Cancel Dialog -->
<Dialog.Root bind:open={cancelDialogOpen}>
	<Dialog.Content>
		<Dialog.Header>
			<Dialog.Title>Cancel Meeting</Dialog.Title>
			<Dialog.Description>
				Are you sure you want to cancel the meeting with {meetingToCancel?.attendee_name}?
			</Dialog.Description>
		</Dialog.Header>
		<div class="py-4">
			<label class="text-sm font-medium" for="cancel-reason">Reason (optional)</label>
			<textarea
				id="cancel-reason"
				class="mt-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
				rows="3"
				placeholder="Let the attendee know why..."
				bind:value={cancelReason}
			></textarea>
		</div>
		<Dialog.Footer>
			<Button variant="outline" onclick={() => (cancelDialogOpen = false)} disabled={canceling}>
				Keep Meeting
			</Button>
			<Button variant="destructive" onclick={handleCancel} disabled={canceling}>
				{#if canceling}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Cancel Meeting
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
