<script lang="ts">
	import { onMount } from 'svelte';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Tabs, TabsContent, TabsList, TabsTrigger } from '$lib/components/ui/tabs';
	import * as Select from '$lib/components/ui/select';
	import MeetingHeatmap from './MeetingHeatmap.svelte';
	import MeetingList from './MeetingList.svelte';
	import UpcomingMeetings from './UpcomingMeetings.svelte';
	import CreateMeetingModal from './CreateMeetingModal.svelte';
	import LogOutcomeModal from './LogOutcomeModal.svelte';
	import {
		meetingsApi,
		type Meeting,
		type MeetingAnalyticsOverview,
		type MeetingHeatmap as HeatmapType
	} from '$lib/api/meetings';
	import {
		Calendar,
		Clock,
		Users,
		TrendingUp,
		TrendingDown,
		Plus,
		RefreshCw
	} from 'lucide-svelte';

	const periodOptions = [
		{ value: 'week', label: 'Week' },
		{ value: 'month', label: 'Month' },
		{ value: 'quarter', label: 'Quarter' }
	];

	let loading = $state(true);
	let refreshing = $state(false);
	let activeTab = $state('overview');
	let periodValue = $state('month');
	const period = $derived(periodOptions.find(p => p.value === periodValue) || periodOptions[1]);

	let overview = $state<MeetingAnalyticsOverview | null>(null);
	let heatmap = $state<HeatmapType | null>(null);
	let upcomingMeetings = $state<Meeting[]>([]);
	let todaysMeetings = $state<Meeting[]>([]);
	let allMeetings = $state<Meeting[]>([]);

	let showCreateModal = $state(false);
	let showOutcomeModal = $state(false);
	let selectedMeeting = $state<Meeting | null>(null);

	async function loadData() {
		try {
			const [overviewData, heatmapData, upcomingData, todaysData] = await Promise.all([
				meetingsApi.getAnalyticsOverview(period.value as 'week' | 'month' | 'quarter'),
				meetingsApi.getAnalyticsHeatmap(4),
				meetingsApi.getUpcomingMeetings(5),
				meetingsApi.getTodaysMeetings()
			]);

			overview = overviewData;
			heatmap = heatmapData;
			upcomingMeetings = upcomingData;
			todaysMeetings = todaysData;
		} catch (err) {
			console.error('Failed to load meeting data:', err);
		}

		loading = false;
	}

	async function loadAllMeetings() {
		try {
			const now = new Date();
			const from = new Date(now.getFullYear(), now.getMonth() - 1, 1).toISOString().split('T')[0];
			const to = new Date(now.getFullYear(), now.getMonth() + 2, 0).toISOString().split('T')[0];

			allMeetings = await meetingsApi.getMeetings({ from, to });
		} catch (err) {
			console.error('Failed to load all meetings:', err);
		}
	}

	async function refresh() {
		refreshing = true;
		await loadData();
		if (activeTab === 'all') await loadAllMeetings();
		refreshing = false;
	}

	function handleLogOutcome(meeting: Meeting) {
		selectedMeeting = meeting;
		showOutcomeModal = true;
	}

	function handleJoinMeeting(meeting: Meeting) {
		if (meeting.meeting_url) {
			window.open(meeting.meeting_url, '_blank');
		}
	}

	onMount(() => {
		loadData();
	});

	$effect(() => {
		if (activeTab === 'all' && allMeetings.length === 0) {
			loadAllMeetings();
		}
	});

	$effect(() => {
		// Reload overview when period changes
		if (!loading && period.value) {
			meetingsApi.getAnalyticsOverview(period.value as 'week' | 'month' | 'quarter').then((data) => {
				overview = data;
			});
		}
	});
</script>

<div class="space-y-6">
	<!-- Header -->
	<div class="flex items-center justify-between">
		<div>
			<h1 class="text-2xl font-bold">Meeting Intelligence</h1>
			<p class="text-muted-foreground">
				Track meetings, analyze engagement, and gain insights
			</p>
		</div>
		<div class="flex items-center gap-2">
			<Button variant="outline" size="sm" onclick={refresh} disabled={refreshing}>
				<RefreshCw class="h-4 w-4 mr-1 {refreshing ? 'animate-spin' : ''}" />
				Refresh
			</Button>
			<Button onclick={() => (showCreateModal = true)}>
				<Plus class="h-4 w-4 mr-1" />
				New Meeting
			</Button>
		</div>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<RefreshCw class="h-8 w-8 animate-spin text-muted-foreground" />
		</div>
	{:else}
		<!-- Stats Overview -->
		{#if overview}
			<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
				<Card>
					<CardContent class="pt-4">
						<div class="flex items-center justify-between">
							<div class="flex items-center gap-2">
								<Calendar class="h-4 w-4 text-muted-foreground" />
								<span class="text-sm text-muted-foreground">Meetings</span>
							</div>
							<Select.Root type="single" value={periodValue} onValueChange={(v) => { if (v) periodValue = v; }}>
								<Select.Trigger class="w-24 h-7 text-xs">
									{period.label}
								</Select.Trigger>
								<Select.Content>
									{#each periodOptions as opt}
										<Select.Item value={opt.value}>{opt.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>
						</div>
						<div class="flex items-baseline gap-2 mt-1">
							<p class="text-2xl font-bold">{overview.total_meetings}</p>
							{#if overview.change_percent !== null}
								<span
									class="text-xs flex items-center {overview.change_percent >= 0
										? 'text-green-600'
										: 'text-red-600'}"
								>
									{#if overview.change_percent >= 0}
										<TrendingUp class="h-3 w-3 mr-0.5" />
									{:else}
										<TrendingDown class="h-3 w-3 mr-0.5" />
									{/if}
									{Math.abs(overview.change_percent)}%
								</span>
							{/if}
						</div>
					</CardContent>
				</Card>

				<Card>
					<CardContent class="pt-4">
						<div class="flex items-center gap-2">
							<Clock class="h-4 w-4 text-muted-foreground" />
							<span class="text-sm text-muted-foreground">Hours in Meetings</span>
						</div>
						<p class="text-2xl font-bold mt-1">{overview.total_hours}</p>
					</CardContent>
				</Card>

				<Card>
					<CardContent class="pt-4">
						<div class="flex items-center gap-2">
							<Users class="h-4 w-4 text-muted-foreground" />
							<span class="text-sm text-muted-foreground">Stakeholders Met</span>
						</div>
						<p class="text-2xl font-bold mt-1">{overview.unique_stakeholders}</p>
					</CardContent>
				</Card>

				<Card>
					<CardContent class="pt-4">
						<div class="flex items-center gap-2">
							<Calendar class="h-4 w-4 text-muted-foreground" />
							<span class="text-sm text-muted-foreground">Today's Meetings</span>
						</div>
						<p class="text-2xl font-bold mt-1">{todaysMeetings.length}</p>
					</CardContent>
				</Card>
			</div>
		{/if}

		<!-- Main Content -->
		<Tabs bind:value={activeTab}>
			<TabsList>
				<TabsTrigger value="overview">Overview</TabsTrigger>
				<TabsTrigger value="today">Today</TabsTrigger>
				<TabsTrigger value="all">All Meetings</TabsTrigger>
			</TabsList>

			<TabsContent value="overview" class="space-y-4 mt-4">
				<div class="grid md:grid-cols-2 gap-4">
					<UpcomingMeetings
						meetings={upcomingMeetings}
						onJoin={handleJoinMeeting}
						onViewAll={() => (activeTab = 'all')}
					/>
					{#if heatmap}
						<MeetingHeatmap {heatmap} />
					{/if}
				</div>
			</TabsContent>

			<TabsContent value="today" class="mt-4">
				<MeetingList
					meetings={todaysMeetings}
					title="Today's Schedule"
					description={new Date().toLocaleDateString('en-US', {
						weekday: 'long',
						month: 'long',
						day: 'numeric'
					})}
					showDate={false}
					onLogOutcome={handleLogOutcome}
				/>
			</TabsContent>

			<TabsContent value="all" class="mt-4">
				<MeetingList
					meetings={allMeetings}
					title="All Meetings"
					description="Past and upcoming meetings"
					onLogOutcome={handleLogOutcome}
				/>
			</TabsContent>
		</Tabs>
	{/if}
</div>

<CreateMeetingModal
	bind:open={showCreateModal}
	onSuccess={() => {
		showCreateModal = false;
		refresh();
	}}
/>

<LogOutcomeModal
	bind:open={showOutcomeModal}
	meeting={selectedMeeting}
	onSuccess={() => {
		showOutcomeModal = false;
		selectedMeeting = null;
		refresh();
	}}
/>
