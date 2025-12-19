<script lang="ts">
  import { page } from '$app/stores';
  import * as Tabs from '$lib/components/ui/tabs';
  import { VideoProviderSetup, MeetingScheduler, MeetingList, MeetingDetail } from '$lib/components/video';
  import type { VideoMeeting } from '$lib/api/video';

  let activeTab = $state('meetings');
  let showScheduler = $state(false);
  let selectedMeetingId = $state<number | null>(null);

  function handleSchedule() {
    showScheduler = true;
  }

  function handleMeetingScheduled(meeting: VideoMeeting) {
    showScheduler = false;
    selectedMeetingId = meeting.id;
  }

  function handleMeetingClick(meeting: VideoMeeting) {
    selectedMeetingId = meeting.id;
  }

  function handleBackFromDetail() {
    selectedMeetingId = null;
  }

  // Check URL params for initial tab
  $effect(() => {
    const tab = $page.url.searchParams.get('tab');
    if (tab && ['meetings', 'providers'].includes(tab)) {
      activeTab = tab;
    }
  });
</script>

<svelte:head>
  <title>Video Meetings | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Video Meetings</h1>
    <p class="text-muted-foreground">
      Schedule, manage, and join video meetings with your team and clients
    </p>
  </div>

  {#if selectedMeetingId}
    <MeetingDetail
      meetingId={selectedMeetingId}
      onBack={handleBackFromDetail}
      onUpdated={() => {}}
    />
  {:else}
    <Tabs.Root bind:value={activeTab}>
      <Tabs.List>
        <Tabs.Trigger value="meetings">Meetings</Tabs.Trigger>
        <Tabs.Trigger value="providers">Providers</Tabs.Trigger>
      </Tabs.List>

      <div class="mt-6">
        {#if activeTab === 'meetings'}
          <MeetingList
            onSchedule={handleSchedule}
            onMeetingClick={handleMeetingClick}
          />
        {:else if activeTab === 'providers'}
          <VideoProviderSetup />
        {/if}
      </div>
    </Tabs.Root>
  {/if}
</div>

<MeetingScheduler
  bind:open={showScheduler}
  onScheduled={handleMeetingScheduled}
  onClose={() => (showScheduler = false)}
/>
