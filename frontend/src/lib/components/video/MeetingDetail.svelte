<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Badge } from '$lib/components/ui/badge';
  import {
    videoMeetingApi,
    videoParticipantApi,
    videoRecordingApi,
    type VideoMeeting,
    type VideoMeetingParticipant,
    type VideoMeetingRecording,
    formatDuration,
    formatFileSize,
    getMeetingStatusColor,
    getParticipantStatusColor,
    getProviderIcon,
  } from '$lib/api/video';
  import Calendar from '@lucide/svelte/icons/calendar';
  import Clock from '@lucide/svelte/icons/clock';
  import Users from '@lucide/svelte/icons/users';
  import Video from '@lucide/svelte/icons/video';
  import ExternalLink from '@lucide/svelte/icons/external-link';
  import Copy from '@lucide/svelte/icons/copy';
  import Play from '@lucide/svelte/icons/play';
  import Download from '@lucide/svelte/icons/download';
  import FileText from '@lucide/svelte/icons/file-text';
  import Trash2 from '@lucide/svelte/icons/trash-2';
  import Plus from '@lucide/svelte/icons/plus';
  import RefreshCw from '@lucide/svelte/icons/refresh-cw';
  import X from '@lucide/svelte/icons/x';
  import ArrowLeft from '@lucide/svelte/icons/arrow-left';

  interface Props {
    meetingId: number;
    onBack?: () => void;
    onUpdated?: () => void;
  }

  let { meetingId, onBack, onUpdated }: Props = $props();

  let meeting = $state<VideoMeeting | null>(null);
  let loading = $state(true);
  let activeTab = $state('details');
  let showAddParticipant = $state(false);
  let syncing = $state(false);

  let newParticipantEmail = $state('');
  let newParticipantName = $state('');

  async function loadMeeting() {
    loading = true;
    try {
      meeting = await videoMeetingApi.get(meetingId);
    } catch (error) {
      console.error('Failed to load meeting:', error);
    } finally {
      loading = false;
    }
  }

  function formatDateTime(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
  }

  async function handleCancel() {
    if (!meeting || !confirm('Are you sure you want to cancel this meeting?')) return;

    try {
      await videoMeetingApi.cancel(meeting.id);
      await loadMeeting();
      onUpdated?.();
    } catch (error) {
      console.error('Failed to cancel meeting:', error);
    }
  }

  async function handleEnd() {
    if (!meeting) return;

    try {
      await videoMeetingApi.end(meeting.id);
      await loadMeeting();
      onUpdated?.();
    } catch (error) {
      console.error('Failed to end meeting:', error);
    }
  }

  async function handleSyncRecordings() {
    if (!meeting) return;

    syncing = true;
    try {
      await videoMeetingApi.syncRecordings(meeting.id);
      await loadMeeting();
    } catch (error) {
      console.error('Failed to sync recordings:', error);
    } finally {
      syncing = false;
    }
  }

  async function handleSyncParticipants() {
    if (!meeting) return;

    syncing = true;
    try {
      await videoMeetingApi.syncParticipants(meeting.id);
      await loadMeeting();
    } catch (error) {
      console.error('Failed to sync participants:', error);
    } finally {
      syncing = false;
    }
  }

  async function handleAddParticipant() {
    if (!meeting || !newParticipantEmail) return;

    try {
      await videoParticipantApi.add(meeting.id, {
        email: newParticipantEmail,
        name: newParticipantName || undefined,
      });
      newParticipantEmail = '';
      newParticipantName = '';
      showAddParticipant = false;
      await loadMeeting();
    } catch (error) {
      console.error('Failed to add participant:', error);
    }
  }

  async function handleRemoveParticipant(participant: VideoMeetingParticipant) {
    if (!meeting || !confirm(`Remove ${participant.name} from this meeting?`)) return;

    try {
      await videoParticipantApi.remove(meeting.id, participant.id);
      await loadMeeting();
    } catch (error) {
      console.error('Failed to remove participant:', error);
    }
  }

  async function handleDeleteRecording(recording: VideoMeetingRecording) {
    if (!meeting || !confirm('Are you sure you want to delete this recording?')) return;

    try {
      await videoRecordingApi.delete(meeting.id, recording.id);
      await loadMeeting();
    } catch (error) {
      console.error('Failed to delete recording:', error);
    }
  }

  function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text);
  }

  $effect(() => {
    loadMeeting();
  });
</script>

<div class="space-y-6">
  {#if loading}
    <div class="flex items-center justify-center py-12">
      <RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
    </div>
  {:else if !meeting}
    <Card.Root>
      <Card.Content class="flex flex-col items-center justify-center py-12">
        <p class="text-muted-foreground">Meeting not found</p>
        <Button variant="outline" class="mt-4" onclick={onBack}>
          <ArrowLeft class="mr-2 h-4 w-4" />
          Go Back
        </Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="flex items-center gap-4">
      <Button variant="ghost" size="sm" onclick={onBack}>
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
    </div>

    <Card.Root>
      <Card.Header>
        <div class="flex items-start justify-between">
          <div class="flex items-start gap-4">
            <div class="flex h-14 w-14 items-center justify-center rounded-lg bg-muted text-3xl">
              {meeting.provider ? getProviderIcon(meeting.provider.provider) : '📹'}
            </div>
            <div>
              <Card.Title class="text-xl">{meeting.title}</Card.Title>
              <div class="flex items-center gap-2 mt-1">
                <Badge class={getMeetingStatusColor(meeting.status)}>
                  {meeting.status}
                </Badge>
                {#if meeting.meeting_type === 'recurring'}
                  <Badge variant="outline">Recurring</Badge>
                {/if}
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            {#if meeting.status === 'scheduled'}
              {#if meeting.host_url}
                <Button onclick={() => window.open(meeting!.host_url!, '_blank')}>
                  <Video class="mr-2 h-4 w-4" />
                  Start Meeting
                </Button>
              {/if}
              <Button variant="destructive" onclick={handleCancel}>
                Cancel
              </Button>
            {:else if meeting.status === 'started'}
              {#if meeting.join_url}
                <Button onclick={() => window.open(meeting!.join_url!, '_blank')}>
                  <ExternalLink class="mr-2 h-4 w-4" />
                  Join
                </Button>
              {/if}
              <Button variant="outline" onclick={handleEnd}>
                End Meeting
              </Button>
            {/if}
          </div>
        </div>
      </Card.Header>

      <Card.Content>
        <Tabs.Root bind:value={activeTab}>
          <Tabs.List>
            <Tabs.Trigger value="details">Details</Tabs.Trigger>
            <Tabs.Trigger value="participants">
              Participants ({meeting.participants?.length || 0})
            </Tabs.Trigger>
            <Tabs.Trigger value="recordings">
              Recordings ({meeting.recordings?.length || 0})
            </Tabs.Trigger>
          </Tabs.List>

          <div class="mt-6">
            {#if activeTab === 'details'}
              <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                  <div class="space-y-1">
                    <Label class="text-muted-foreground">Date & Time</Label>
                    <p class="font-medium">{formatDateTime(meeting.scheduled_at)}</p>
                  </div>
                  <div class="space-y-1">
                    <Label class="text-muted-foreground">Duration</Label>
                    <p class="font-medium">{meeting.duration_minutes} minutes</p>
                  </div>
                  {#if meeting.host}
                    <div class="space-y-1">
                      <Label class="text-muted-foreground">Host</Label>
                      <p class="font-medium">{meeting.host.name}</p>
                    </div>
                  {/if}
                  {#if meeting.provider}
                    <div class="space-y-1">
                      <Label class="text-muted-foreground">Provider</Label>
                      <p class="font-medium capitalize">{meeting.provider.provider.replace('_', ' ')}</p>
                    </div>
                  {/if}
                </div>

                {#if meeting.description}
                  <div class="space-y-1">
                    <Label class="text-muted-foreground">Description</Label>
                    <p class="whitespace-pre-wrap">{meeting.description}</p>
                  </div>
                {/if}

                {#if meeting.join_url}
                  <div class="space-y-2">
                    <Label class="text-muted-foreground">Join Link</Label>
                    <div class="flex items-center gap-2">
                      <Input value={meeting.join_url} readonly class="flex-1" />
                      <Button variant="outline" size="icon" onclick={() => copyToClipboard(meeting!.join_url!)}>
                        <Copy class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                {/if}

                <div class="grid gap-2 sm:grid-cols-3">
                  <div class="flex items-center gap-2 rounded-lg border p-3">
                    <span class="text-muted-foreground">Waiting Room:</span>
                    <span class="font-medium">{meeting.waiting_room_enabled ? 'Enabled' : 'Disabled'}</span>
                  </div>
                  <div class="flex items-center gap-2 rounded-lg border p-3">
                    <span class="text-muted-foreground">Recording:</span>
                    <span class="font-medium">{meeting.recording_enabled ? 'Enabled' : 'Disabled'}</span>
                  </div>
                  <div class="flex items-center gap-2 rounded-lg border p-3">
                    <span class="text-muted-foreground">Auto-Record:</span>
                    <span class="font-medium">{meeting.recording_auto_start ? 'Yes' : 'No'}</span>
                  </div>
                </div>
              </div>
            {:else if activeTab === 'participants'}
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-muted-foreground">
                    {meeting.participants?.length || 0} participant(s)
                  </p>
                  <div class="flex items-center gap-2">
                    {#if meeting.status === 'ended'}
                      <Button
                        variant="outline"
                        size="sm"
                        onclick={handleSyncParticipants}
                        disabled={syncing}
                      >
                        {#if syncing}
                          <RefreshCw class="mr-2 h-4 w-4 animate-spin" />
                        {:else}
                          <RefreshCw class="mr-2 h-4 w-4" />
                        {/if}
                        Sync
                      </Button>
                    {/if}
                    {#if meeting.status === 'scheduled'}
                      <Button size="sm" onclick={() => (showAddParticipant = true)}>
                        <Plus class="mr-2 h-4 w-4" />
                        Add
                      </Button>
                    {/if}
                  </div>
                </div>

                {#if meeting.participants && meeting.participants.length > 0}
                  <div class="divide-y rounded-lg border">
                    {#each meeting.participants as participant}
                      <div class="flex items-center justify-between p-3">
                        <div class="flex items-center gap-3">
                          <div class="flex h-10 w-10 items-center justify-center rounded-full bg-muted font-medium">
                            {participant.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <div class="flex items-center gap-2">
                              <p class="font-medium">{participant.name}</p>
                              <Badge variant="outline" class="text-xs">
                                {participant.role}
                              </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">{participant.email}</p>
                          </div>
                        </div>
                        <div class="flex items-center gap-2">
                          <Badge class={getParticipantStatusColor(participant.status)}>
                            {participant.status.replace('_', ' ')}
                          </Badge>
                          {#if participant.duration_seconds}
                            <span class="text-sm text-muted-foreground">
                              {formatDuration(participant.duration_seconds)}
                            </span>
                          {/if}
                          {#if meeting.status === 'scheduled' && participant.role !== 'host'}
                            <Button
                              variant="ghost"
                              size="icon"
                              onclick={() => handleRemoveParticipant(participant)}
                            >
                              <X class="h-4 w-4" />
                            </Button>
                          {/if}
                        </div>
                      </div>
                    {/each}
                  </div>
                {:else}
                  <p class="text-center text-muted-foreground py-8">No participants</p>
                {/if}
              </div>
            {:else if activeTab === 'recordings'}
              <div class="space-y-4">
                <div class="flex items-center justify-between">
                  <p class="text-sm text-muted-foreground">
                    {meeting.recordings?.length || 0} recording(s)
                  </p>
                  <Button
                    variant="outline"
                    size="sm"
                    onclick={handleSyncRecordings}
                    disabled={syncing}
                  >
                    {#if syncing}
                      <RefreshCw class="mr-2 h-4 w-4 animate-spin" />
                    {:else}
                      <RefreshCw class="mr-2 h-4 w-4" />
                    {/if}
                    Sync Recordings
                  </Button>
                </div>

                {#if meeting.recordings && meeting.recordings.length > 0}
                  <div class="space-y-3">
                    {#each meeting.recordings as recording}
                      <Card.Root>
                        <Card.Content class="flex items-center justify-between p-4">
                          <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                              {#if recording.type === 'video'}
                                <Video class="h-5 w-5" />
                              {:else if recording.type === 'audio'}
                                <span>🎵</span>
                              {:else if recording.type === 'transcript'}
                                <FileText class="h-5 w-5" />
                              {:else}
                                <span>📄</span>
                              {/if}
                            </div>
                            <div>
                              <p class="font-medium capitalize">{recording.type} Recording</p>
                              <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                {#if recording.duration_seconds}
                                  <span>{formatDuration(recording.duration_seconds)}</span>
                                {/if}
                                {#if recording.file_size}
                                  <span>•</span>
                                  <span>{formatFileSize(recording.file_size)}</span>
                                {/if}
                                {#if recording.format}
                                  <span>•</span>
                                  <span class="uppercase">{recording.format}</span>
                                {/if}
                              </div>
                            </div>
                          </div>
                          <div class="flex items-center gap-2">
                            <Badge
                              variant={recording.status === 'completed' ? 'default' : 'secondary'}
                            >
                              {recording.status}
                            </Badge>
                            {#if recording.play_url && recording.status === 'completed'}
                              <Button
                                variant="outline"
                                size="sm"
                                onclick={() => window.open(recording.play_url!, '_blank')}
                              >
                                <Play class="mr-2 h-4 w-4" />
                                Play
                              </Button>
                            {/if}
                            {#if recording.download_url && recording.status === 'completed'}
                              <Button
                                variant="outline"
                                size="sm"
                                onclick={() => window.open(recording.download_url!, '_blank')}
                              >
                                <Download class="h-4 w-4" />
                              </Button>
                            {/if}
                            <Button
                              variant="ghost"
                              size="icon"
                              onclick={() => handleDeleteRecording(recording)}
                            >
                              <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                          </div>
                        </Card.Content>
                      </Card.Root>
                    {/each}
                  </div>
                {:else}
                  <p class="text-center text-muted-foreground py-8">
                    No recordings available
                  </p>
                {/if}
              </div>
            {/if}
          </div>
        </Tabs.Root>
      </Card.Content>
    </Card.Root>
  {/if}
</div>

<!-- Add Participant Dialog -->
<Dialog.Root bind:open={showAddParticipant}>
  <Dialog.Content class="max-w-md">
    <Dialog.Header>
      <Dialog.Title>Add Participant</Dialog.Title>
      <Dialog.Description>
        Invite someone to this meeting
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-4">
      <div class="space-y-2">
        <Label>Email Address</Label>
        <Input
          bind:value={newParticipantEmail}
          type="email"
          placeholder="participant@example.com"
        />
      </div>
      <div class="space-y-2">
        <Label>Name (optional)</Label>
        <Input
          bind:value={newParticipantName}
          placeholder="Participant Name"
        />
      </div>
    </div>

    <Dialog.Footer>
      <Button variant="outline" onclick={() => (showAddParticipant = false)}>Cancel</Button>
      <Button onclick={handleAddParticipant} disabled={!newParticipantEmail}>
        Add Participant
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
