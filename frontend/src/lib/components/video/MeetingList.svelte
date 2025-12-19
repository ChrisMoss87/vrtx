<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Badge } from '$lib/components/ui/badge';
  import {
    videoMeetingApi,
    type VideoMeeting,
    formatDuration,
    getMeetingStatusColor,
    getProviderIcon,
  } from '$lib/api/video';
  import Calendar from '@lucide/svelte/icons/calendar';
  import Clock from '@lucide/svelte/icons/clock';
  import Users from '@lucide/svelte/icons/users';
  import Video from '@lucide/svelte/icons/video';
  import ExternalLink from '@lucide/svelte/icons/external-link';
  import RefreshCw from '@lucide/svelte/icons/refresh-cw';
  import Plus from '@lucide/svelte/icons/plus';
  import ChevronLeft from '@lucide/svelte/icons/chevron-left';
  import ChevronRight from '@lucide/svelte/icons/chevron-right';

  interface Props {
    onSchedule?: () => void;
    onMeetingClick?: (meeting: VideoMeeting) => void;
  }

  let { onSchedule, onMeetingClick }: Props = $props();

  let meetings = $state<VideoMeeting[]>([]);
  let loading = $state(true);
  let activeTab = $state('all');
  let searchQuery = $state('');
  let currentPage = $state(1);
  let totalPages = $state(1);
  let perPage = $state(10);

  async function loadMeetings() {
    loading = true;
    try {
      const status = activeTab === 'all' ? undefined : activeTab as VideoMeeting['status'];
      const response = await videoMeetingApi.list({
        status,
        my_meetings: true,
        page: currentPage,
        per_page: perPage,
      });
      meetings = response.data;
      totalPages = response.meta?.last_page || 1;
    } catch (error) {
      console.error('Failed to load meetings:', error);
    } finally {
      loading = false;
    }
  }

  function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function formatTime(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
  }

  function isUpcoming(meeting: VideoMeeting): boolean {
    return meeting.status === 'scheduled' && new Date(meeting.scheduled_at) > new Date();
  }

  function canJoin(meeting: VideoMeeting): boolean {
    if (meeting.status !== 'scheduled' && meeting.status !== 'started') return false;
    const meetingTime = new Date(meeting.scheduled_at);
    const now = new Date();
    const diff = (meetingTime.getTime() - now.getTime()) / 1000 / 60; // minutes
    return diff <= 15; // Can join 15 minutes before
  }

  function handleJoin(meeting: VideoMeeting) {
    if (meeting.join_url) {
      window.open(meeting.join_url, '_blank');
    }
  }

  $effect(() => {
    loadMeetings();
  });

  $effect(() => {
    // Reload when tab changes
    activeTab;
    currentPage = 1;
    loadMeetings();
  });
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-lg font-semibold">Video Meetings</h2>
      <p class="text-sm text-muted-foreground">
        Schedule, manage, and join your video meetings
      </p>
    </div>
    <Button onclick={onSchedule}>
      <Plus class="mr-2 h-4 w-4" />
      Schedule Meeting
    </Button>
  </div>

  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="all">All</Tabs.Trigger>
      <Tabs.Trigger value="scheduled">Upcoming</Tabs.Trigger>
      <Tabs.Trigger value="started">In Progress</Tabs.Trigger>
      <Tabs.Trigger value="ended">Completed</Tabs.Trigger>
      <Tabs.Trigger value="canceled">Canceled</Tabs.Trigger>
    </Tabs.List>
  </Tabs.Root>

  {#if loading}
    <div class="flex items-center justify-center py-12">
      <RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
    </div>
  {:else if meetings.length === 0}
    <Card.Root>
      <Card.Content class="flex flex-col items-center justify-center py-12">
        <Video class="h-12 w-12 text-muted-foreground mb-4" />
        <p class="text-muted-foreground">No meetings found</p>
        <Button variant="outline" class="mt-4" onclick={onSchedule}>
          <Plus class="mr-2 h-4 w-4" />
          Schedule Your First Meeting
        </Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="space-y-4">
      {#each meetings as meeting}
        <Card.Root
          class="cursor-pointer transition-shadow hover:shadow-md"
          onclick={() => onMeetingClick?.(meeting)}
        >
          <Card.Content class="p-4">
            <div class="flex items-start justify-between gap-4">
              <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-2xl">
                  {meeting.provider ? getProviderIcon(meeting.provider.provider) : '📹'}
                </div>
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium">{meeting.title}</h3>
                    <Badge class={getMeetingStatusColor(meeting.status)}>
                      {meeting.status}
                    </Badge>
                  </div>
                  <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <span class="flex items-center gap-1">
                      <Calendar class="h-4 w-4" />
                      {formatDate(meeting.scheduled_at)}
                    </span>
                    <span class="flex items-center gap-1">
                      <Clock class="h-4 w-4" />
                      {formatTime(meeting.scheduled_at)}
                    </span>
                    <span class="flex items-center gap-1">
                      <span class="text-muted-foreground">Duration:</span>
                      {meeting.duration_minutes} min
                    </span>
                    {#if meeting.participants && meeting.participants.length > 0}
                      <span class="flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        {meeting.participants.length} participants
                      </span>
                    {/if}
                  </div>
                  {#if meeting.description}
                    <p class="text-sm text-muted-foreground line-clamp-2">
                      {meeting.description}
                    </p>
                  {/if}
                </div>
              </div>

              <div class="flex items-center gap-2">
                {#if canJoin(meeting)}
                  <Button onclick={(e) => { e.stopPropagation(); handleJoin(meeting); }}>
                    <ExternalLink class="mr-2 h-4 w-4" />
                    Join
                  </Button>
                {:else if isUpcoming(meeting) && meeting.host_url}
                  <Button
                    variant="outline"
                    onclick={(e) => {
                      e.stopPropagation();
                      window.open(meeting.host_url!, '_blank');
                    }}
                  >
                    Start Meeting
                  </Button>
                {/if}
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      {/each}
    </div>

    <!-- Pagination -->
    {#if totalPages > 1}
      <div class="flex items-center justify-between">
        <p class="text-sm text-muted-foreground">
          Page {currentPage} of {totalPages}
        </p>
        <div class="flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage <= 1}
            onclick={() => { currentPage--; loadMeetings(); }}
          >
            <ChevronLeft class="h-4 w-4" />
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage >= totalPages}
            onclick={() => { currentPage++; loadMeetings(); }}
          >
            Next
            <ChevronRight class="h-4 w-4" />
          </Button>
        </div>
      </div>
    {/if}
  {/if}
</div>
