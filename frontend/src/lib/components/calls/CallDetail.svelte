<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import * as Tabs from '$lib/components/ui/tabs';
  import {
    Phone, PhoneIncoming, PhoneOutgoing, Play, Pause,
    FileText, Clock, User, Link, Download, RefreshCw, X
  } from 'lucide-svelte';
  import { callApi, formatDuration, getCallStatusColor, getCallStatusLabel, type Call, type CallTranscription } from '$lib/api/calls';

  interface Props {
    call: Call;
    onClose?: () => void;
    onUpdate?: () => void;
  }

  let { call, onClose, onUpdate }: Props = $props();

  let isPlaying = $state(false);
  let audioElement = $state<HTMLAudioElement | null>(null);
  let transcription = $state<CallTranscription | null>(call.transcription || null);
  let isTranscribing = $state(false);
  let notes = $state(call.notes || '');
  let outcome = $state(call.outcome || '');

  const outcomeOptions = [
    { value: 'connected', label: 'Connected' },
    { value: 'voicemail', label: 'Left Voicemail' },
    { value: 'no_answer', label: 'No Answer' },
    { value: 'busy', label: 'Line Busy' },
    { value: 'wrong_number', label: 'Wrong Number' },
    { value: 'callback_scheduled', label: 'Callback Scheduled' },
    { value: 'not_interested', label: 'Not Interested' },
    { value: 'qualified', label: 'Qualified Lead' },
    { value: 'other', label: 'Other' },
  ];

  function togglePlayback() {
    if (!audioElement) return;

    if (isPlaying) {
      audioElement.pause();
    } else {
      audioElement.play();
    }
    isPlaying = !isPlaying;
  }

  async function startTranscription() {
    try {
      isTranscribing = true;
      transcription = await callApi.transcribe(call.id);
    } catch (error) {
      console.error('Transcription failed:', error);
    } finally {
      isTranscribing = false;
    }
  }

  async function saveOutcome() {
    try {
      await callApi.logOutcome(call.id, {
        outcome: outcome as any,
        notes: notes,
      });
      onUpdate?.();
    } catch (error) {
      console.error('Failed to save outcome:', error);
    }
  }

  function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
  }

  function formatSegmentTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        {#if call.direction === 'inbound'}
          <PhoneIncoming class="h-5 w-5 text-blue-500" />
        {:else}
          <PhoneOutgoing class="h-5 w-5 text-green-500" />
        {/if}
        <div>
          <Card.Title>
            {call.direction === 'inbound' ? 'Inbound Call' : 'Outbound Call'}
          </Card.Title>
          <Card.Description>
            {call.direction === 'inbound' ? `From ${call.from_number}` : `To ${call.to_number}`}
          </Card.Description>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Badge variant="outline" class={getCallStatusColor(call.status)}>
          {getCallStatusLabel(call.status)}
        </Badge>
        {#if onClose}
          <Button variant="ghost" size="icon" onclick={onClose}>
            <X class="h-4 w-4" />
          </Button>
        {/if}
      </div>
    </div>
  </Card.Header>
  <Card.Content class="space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="text-center p-4 bg-muted rounded-lg">
        <Clock class="mx-auto h-5 w-5 text-muted-foreground mb-1" />
        <div class="text-lg font-bold">{call.formatted_duration || formatDuration(call.duration_seconds)}</div>
        <div class="text-sm text-muted-foreground">Duration</div>
      </div>
      <div class="text-center p-4 bg-muted rounded-lg">
        <Phone class="mx-auto h-5 w-5 text-muted-foreground mb-1" />
        <div class="text-lg font-bold">{call.ring_duration_seconds || 0}s</div>
        <div class="text-sm text-muted-foreground">Ring Time</div>
      </div>
      <div class="text-center p-4 bg-muted rounded-lg">
        <User class="mx-auto h-5 w-5 text-muted-foreground mb-1" />
        <div class="text-lg font-bold truncate">{call.user?.name || 'Unassigned'}</div>
        <div class="text-sm text-muted-foreground">Agent</div>
      </div>
      <div class="text-center p-4 bg-muted rounded-lg">
        <Link class="mx-auto h-5 w-5 text-muted-foreground mb-1" />
        <div class="text-lg font-bold">{call.contact_id ? 'Linked' : 'Not Linked'}</div>
        <div class="text-sm text-muted-foreground">Contact</div>
      </div>
    </div>

    <Tabs.Root value="details">
      <Tabs.List>
        <Tabs.Trigger value="details">Details</Tabs.Trigger>
        {#if call.has_recording}
          <Tabs.Trigger value="recording">Recording</Tabs.Trigger>
        {/if}
        <Tabs.Trigger value="transcription">Transcription</Tabs.Trigger>
        <Tabs.Trigger value="outcome">Outcome</Tabs.Trigger>
      </Tabs.List>

      <Tabs.Content value="details" class="space-y-4 pt-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-muted-foreground">From:</span>
            <span class="ml-2 font-medium">{call.from_number}</span>
          </div>
          <div>
            <span class="text-muted-foreground">To:</span>
            <span class="ml-2 font-medium">{call.to_number}</span>
          </div>
          <div>
            <span class="text-muted-foreground">Started:</span>
            <span class="ml-2 font-medium">{formatDate(call.started_at)}</span>
          </div>
          <div>
            <span class="text-muted-foreground">Answered:</span>
            <span class="ml-2 font-medium">{formatDate(call.answered_at)}</span>
          </div>
          <div>
            <span class="text-muted-foreground">Ended:</span>
            <span class="ml-2 font-medium">{formatDate(call.ended_at)}</span>
          </div>
          <div>
            <span class="text-muted-foreground">Provider:</span>
            <span class="ml-2 font-medium">{call.provider?.name || 'Unknown'}</span>
          </div>
        </div>

        {#if call.metadata && Object.keys(call.metadata).length > 0}
          <div class="border-t pt-4">
            <h4 class="font-medium mb-2">Additional Info</h4>
            <div class="grid grid-cols-2 gap-2 text-sm">
              {#each Object.entries(call.metadata) as [key, value]}
                <div>
                  <span class="text-muted-foreground">{key}:</span>
                  <span class="ml-2">{String(value)}</span>
                </div>
              {/each}
            </div>
          </div>
        {/if}
      </Tabs.Content>

      {#if call.has_recording}
        <Tabs.Content value="recording" class="space-y-4 pt-4">
          <div class="bg-muted rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2">
                <Button variant="outline" size="icon" onclick={togglePlayback}>
                  {#if isPlaying}
                    <Pause class="h-4 w-4" />
                  {:else}
                    <Play class="h-4 w-4" />
                  {/if}
                </Button>
                <span class="font-medium">
                  {formatDuration(call.recording_duration_seconds)}
                </span>
              </div>
              <Button variant="outline" size="sm" onclick={() => window.open(call.recording_url!, '_blank')}>
                <Download class="h-4 w-4 mr-1" />
                Download
              </Button>
            </div>
            {#if call.recording_url}
              <audio
                bind:this={audioElement}
                src={call.recording_url}
                class="w-full"
                controls
                onplay={() => (isPlaying = true)}
                onpause={() => (isPlaying = false)}
                onended={() => (isPlaying = false)}
              ></audio>
            {/if}
          </div>
        </Tabs.Content>
      {/if}

      <Tabs.Content value="transcription" class="space-y-4 pt-4">
        {#if transcription && transcription.status === 'completed'}
          {#if transcription.summary}
            <div class="bg-muted rounded-lg p-4">
              <h4 class="font-medium mb-2">Summary</h4>
              <p class="text-sm">{transcription.summary}</p>
            </div>
          {/if}

          {#if transcription.key_points && transcription.key_points.length > 0}
            <div>
              <h4 class="font-medium mb-2">Key Points</h4>
              <ul class="list-disc list-inside text-sm space-y-1">
                {#each transcription.key_points as point}
                  <li>{point}</li>
                {/each}
              </ul>
            </div>
          {/if}

          {#if transcription.action_items && transcription.action_items.length > 0}
            <div>
              <h4 class="font-medium mb-2">Action Items</h4>
              <ul class="list-disc list-inside text-sm space-y-1">
                {#each transcription.action_items as item}
                  <li>{item}</li>
                {/each}
              </ul>
            </div>
          {/if}

          {#if transcription.segments && transcription.segments.length > 0}
            <div>
              <h4 class="font-medium mb-2">Full Transcription</h4>
              <div class="space-y-2 max-h-96 overflow-y-auto border rounded-lg p-4">
                {#each transcription.segments as segment}
                  <div class="flex gap-3">
                    <span class="text-xs text-muted-foreground font-mono w-12 shrink-0">
                      {formatSegmentTime(segment.start)}
                    </span>
                    <div>
                      <span class="text-xs font-medium text-primary">{segment.speaker}</span>
                      <p class="text-sm">{segment.text}</p>
                    </div>
                  </div>
                {/each}
              </div>
            </div>
          {:else if transcription.full_text}
            <div>
              <h4 class="font-medium mb-2">Full Transcription</h4>
              <div class="border rounded-lg p-4 max-h-96 overflow-y-auto">
                <p class="text-sm whitespace-pre-wrap">{transcription.full_text}</p>
              </div>
            </div>
          {/if}

          {#if transcription.sentiment}
            <div class="flex items-center gap-2">
              <span class="text-sm text-muted-foreground">Sentiment:</span>
              <Badge variant={transcription.sentiment === 'positive' ? 'default' : transcription.sentiment === 'negative' ? 'destructive' : 'secondary'}>
                {transcription.sentiment}
              </Badge>
            </div>
          {/if}
        {:else if transcription && transcription.status === 'processing'}
          <div class="text-center py-8">
            <RefreshCw class="mx-auto h-8 w-8 text-muted-foreground animate-spin mb-4" />
            <p class="text-muted-foreground">Transcription in progress...</p>
          </div>
        {:else if transcription && transcription.status === 'failed'}
          <div class="text-center py-8">
            <X class="mx-auto h-8 w-8 text-destructive mb-4" />
            <p class="text-destructive mb-2">Transcription failed</p>
            <p class="text-sm text-muted-foreground">{transcription.error_message}</p>
            <Button variant="outline" class="mt-4" onclick={startTranscription}>
              <RefreshCw class="h-4 w-4 mr-1" />
              Retry
            </Button>
          </div>
        {:else if call.has_recording}
          <div class="text-center py-8">
            <FileText class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
            <h3 class="font-medium mb-2">No transcription available</h3>
            <p class="text-sm text-muted-foreground mb-4">Generate a transcription from the call recording.</p>
            <Button onclick={startTranscription} disabled={isTranscribing}>
              {#if isTranscribing}
                <RefreshCw class="h-4 w-4 mr-1 animate-spin" />
                Transcribing...
              {:else}
                <FileText class="h-4 w-4 mr-1" />
                Generate Transcription
              {/if}
            </Button>
          </div>
        {:else}
          <div class="text-center py-8 text-muted-foreground">
            <p>No recording available for transcription.</p>
          </div>
        {/if}
      </Tabs.Content>

      <Tabs.Content value="outcome" class="space-y-4 pt-4">
        <div class="space-y-4">
          <div class="space-y-2">
            <label class="text-sm font-medium">Call Outcome</label>
            <Select.Root type="single" name="outcome" bind:value={outcome}>
              <Select.Trigger>
                <span>{outcomeOptions.find((o) => o.value === outcome)?.label || 'Select outcome'}</span>
              </Select.Trigger>
              <Select.Content>
                {#each outcomeOptions as option}
                  <Select.Item value={option.value}>{option.label}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>

          <div class="space-y-2">
            <label class="text-sm font-medium">Notes</label>
            <Textarea
              bind:value={notes}
              placeholder="Add notes about this call..."
              rows={4}
            />
          </div>

          <Button onclick={saveOutcome}>Save Outcome</Button>
        </div>
      </Tabs.Content>
    </Tabs.Root>
  </Card.Content>
</Card.Root>
