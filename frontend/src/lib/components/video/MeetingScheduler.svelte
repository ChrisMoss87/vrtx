<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Checkbox } from '$lib/components/ui/checkbox';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Badge } from '$lib/components/ui/badge';
  import {
    videoProviderApi,
    videoMeetingApi,
    type VideoProvider,
    type VideoMeeting,
    getProviderIcon,
  } from '$lib/api/video';
  import Calendar from '@lucide/svelte/icons/calendar';
  import Clock from '@lucide/svelte/icons/clock';
  import Users from '@lucide/svelte/icons/users';
  import Video from '@lucide/svelte/icons/video';
  import X from '@lucide/svelte/icons/x';

  interface Props {
    open?: boolean;
    dealId?: number;
    dealModule?: string;
    onScheduled?: (meeting: VideoMeeting) => void;
    onClose?: () => void;
  }

  let { open = $bindable(false), dealId, dealModule, onScheduled, onClose }: Props = $props();

  let providers = $state<VideoProvider[]>([]);
  let loading = $state(false);
  let submitting = $state(false);

  interface Participant {
    email: string;
    name: string;
    role: 'attendee' | 'co-host';
  }

  interface FormData {
    provider_id: number;
    title: string;
    description: string;
    scheduled_date: string;
    scheduled_time: string;
    duration_minutes: number;
    waiting_room_enabled: boolean;
    recording_enabled: boolean;
    recording_auto_start: boolean;
    meeting_type: 'scheduled' | 'recurring';
    recurrence_type: 'daily' | 'weekly' | 'monthly' | '';
  }

  let formData = $state<FormData>({
    provider_id: 0,
    title: '',
    description: '',
    scheduled_date: '',
    scheduled_time: '',
    duration_minutes: 60,
    waiting_room_enabled: true,
    recording_enabled: false,
    recording_auto_start: false,
    meeting_type: 'scheduled',
    recurrence_type: '',
  });

  let participants = $state<Participant[]>([]);
  let newParticipantEmail = $state('');
  let newParticipantName = $state('');

  const durationOptions = [
    { value: 15, label: '15 minutes' },
    { value: 30, label: '30 minutes' },
    { value: 45, label: '45 minutes' },
    { value: 60, label: '1 hour' },
    { value: 90, label: '1.5 hours' },
    { value: 120, label: '2 hours' },
    { value: 180, label: '3 hours' },
    { value: 240, label: '4 hours' },
  ];

  async function loadProviders() {
    loading = true;
    try {
      const allProviders = await videoProviderApi.list();
      providers = allProviders.filter((p) => p.is_active && p.is_verified);
      if (providers.length > 0 && !formData.provider_id) {
        formData.provider_id = providers[0].id;
      }
    } catch (error) {
      console.error('Failed to load providers:', error);
    } finally {
      loading = false;
    }
  }

  function addParticipant() {
    if (!newParticipantEmail) return;

    participants = [
      ...participants,
      {
        email: newParticipantEmail,
        name: newParticipantName || newParticipantEmail.split('@')[0],
        role: 'attendee',
      },
    ];

    newParticipantEmail = '';
    newParticipantName = '';
  }

  function removeParticipant(index: number) {
    participants = participants.filter((_, i) => i !== index);
  }

  async function handleSubmit() {
    if (!formData.provider_id || !formData.title || !formData.scheduled_date || !formData.scheduled_time) {
      return;
    }

    submitting = true;
    try {
      const scheduledAt = new Date(`${formData.scheduled_date}T${formData.scheduled_time}`).toISOString();

      const meeting = await videoMeetingApi.create({
        provider_id: formData.provider_id,
        title: formData.title,
        description: formData.description || undefined,
        scheduled_at: scheduledAt,
        duration_minutes: formData.duration_minutes,
        waiting_room_enabled: formData.waiting_room_enabled,
        recording_enabled: formData.recording_enabled,
        recording_auto_start: formData.recording_auto_start,
        meeting_type: formData.meeting_type,
        recurrence_type: formData.recurrence_type || undefined,
        deal_id: dealId,
        deal_module: dealModule,
        participants: participants.length > 0 ? participants : undefined,
      });

      onScheduled?.(meeting);
      handleClose();
    } catch (error) {
      console.error('Failed to schedule meeting:', error);
    } finally {
      submitting = false;
    }
  }

  function handleClose() {
    open = false;
    resetForm();
    onClose?.();
  }

  function resetForm() {
    formData = {
      provider_id: providers[0]?.id || 0,
      title: '',
      description: '',
      scheduled_date: '',
      scheduled_time: '',
      duration_minutes: 60,
      waiting_room_enabled: true,
      recording_enabled: false,
      recording_auto_start: false,
      meeting_type: 'scheduled',
      recurrence_type: '',
    };
    participants = [];
  }

  $effect(() => {
    if (open) {
      loadProviders();

      // Set default date/time to tomorrow at 10:00
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      formData.scheduled_date = tomorrow.toISOString().split('T')[0];
      formData.scheduled_time = '10:00';
    }
  });
</script>

<Dialog.Root bind:open>
  <Dialog.Content class="max-w-2xl max-h-[90vh] overflow-y-auto">
    <Dialog.Header>
      <Dialog.Title class="flex items-center gap-2">
        <Video class="h-5 w-5" />
        Schedule Video Meeting
      </Dialog.Title>
      <Dialog.Description>
        Create a new video meeting with your team or clients
      </Dialog.Description>
    </Dialog.Header>

    {#if loading}
      <div class="flex items-center justify-center py-8">
        <p class="text-muted-foreground">Loading providers...</p>
      </div>
    {:else if providers.length === 0}
      <div class="flex flex-col items-center justify-center py-8">
        <p class="text-muted-foreground">No video providers configured</p>
        <p class="text-sm text-muted-foreground mt-2">
          Please set up a video provider in settings first.
        </p>
      </div>
    {:else}
      <div class="space-y-6">
        <!-- Provider Selection -->
        <div class="space-y-2">
          <Label>Video Provider</Label>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {#each providers as provider}
              <button
                type="button"
                class="flex flex-col items-center gap-2 rounded-lg border p-3 transition-colors
                       {formData.provider_id === provider.id
                         ? 'border-primary bg-primary/5'
                         : 'hover:bg-muted'}"
                onclick={() => (formData.provider_id = provider.id)}
              >
                <span class="text-2xl">{getProviderIcon(provider.provider)}</span>
                <span class="text-sm font-medium">{provider.name}</span>
              </button>
            {/each}
          </div>
        </div>

        <!-- Meeting Details -->
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2 sm:col-span-2">
            <Label>Meeting Title</Label>
            <Input bind:value={formData.title} placeholder="e.g., Sales Demo with Acme Corp" />
          </div>

          <div class="space-y-2">
            <Label class="flex items-center gap-2">
              <Calendar class="h-4 w-4" />
              Date
            </Label>
            <Input type="date" bind:value={formData.scheduled_date} />
          </div>

          <div class="space-y-2">
            <Label class="flex items-center gap-2">
              <Clock class="h-4 w-4" />
              Time
            </Label>
            <Input type="time" bind:value={formData.scheduled_time} />
          </div>

          <div class="space-y-2">
            <Label>Duration</Label>
            <Select.Root
              type="single"
              value={String(formData.duration_minutes)}
              onValueChange={(v) => { if (v) formData.duration_minutes = Number(v); }}
            >
              <Select.Trigger>
                <span>
                  {durationOptions.find((d) => d.value === formData.duration_minutes)?.label || 'Select duration'}
                </span>
              </Select.Trigger>
              <Select.Content>
                {#each durationOptions as option}
                  <Select.Item value={String(option.value)}>{option.label}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>

          <div class="space-y-2">
            <Label>Meeting Type</Label>
            <Select.Root
              type="single"
              value={formData.meeting_type}
              onValueChange={(v) => { if (v) formData.meeting_type = v as 'scheduled' | 'recurring'; }}
            >
              <Select.Trigger>
                <span class="capitalize">{formData.meeting_type}</span>
              </Select.Trigger>
              <Select.Content>
                <Select.Item value="scheduled">One-time</Select.Item>
                <Select.Item value="recurring">Recurring</Select.Item>
              </Select.Content>
            </Select.Root>
          </div>

          <div class="space-y-2 sm:col-span-2">
            <Label>Description (optional)</Label>
            <Textarea
              bind:value={formData.description}
              placeholder="Meeting agenda, topics to discuss..."
              rows={3}
            />
          </div>
        </div>

        <!-- Meeting Options -->
        <div class="space-y-4">
          <Label>Meeting Options</Label>
          <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-2">
              <Checkbox
                id="waiting_room"
                checked={formData.waiting_room_enabled}
                onCheckedChange={(v) => (formData.waiting_room_enabled = !!v)}
              />
              <Label for="waiting_room" class="text-sm font-normal">
                Enable waiting room
              </Label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox
                id="recording"
                checked={formData.recording_enabled}
                onCheckedChange={(v) => (formData.recording_enabled = !!v)}
              />
              <Label for="recording" class="text-sm font-normal">
                Enable recording
              </Label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox
                id="auto_record"
                checked={formData.recording_auto_start}
                onCheckedChange={(v) => (formData.recording_auto_start = !!v)}
                disabled={!formData.recording_enabled}
              />
              <Label for="auto_record" class="text-sm font-normal">
                Auto-start recording
              </Label>
            </div>
          </div>
        </div>

        <!-- Participants -->
        <div class="space-y-4">
          <Label class="flex items-center gap-2">
            <Users class="h-4 w-4" />
            Participants
          </Label>

          <div class="flex gap-2">
            <Input
              bind:value={newParticipantEmail}
              placeholder="Email address"
              class="flex-1"
              onkeydown={(e) => e.key === 'Enter' && addParticipant()}
            />
            <Input
              bind:value={newParticipantName}
              placeholder="Name (optional)"
              class="flex-1"
              onkeydown={(e) => e.key === 'Enter' && addParticipant()}
            />
            <Button variant="outline" onclick={addParticipant}>Add</Button>
          </div>

          {#if participants.length > 0}
            <div class="flex flex-wrap gap-2">
              {#each participants as participant, index}
                <Badge variant="secondary" class="flex items-center gap-1">
                  {participant.name}
                  <span class="text-muted-foreground">({participant.email})</span>
                  <button type="button" onclick={() => removeParticipant(index)}>
                    <X class="h-3 w-3" />
                  </button>
                </Badge>
              {/each}
            </div>
          {:else}
            <p class="text-sm text-muted-foreground">
              No participants added. You can add participants now or invite them later.
            </p>
          {/if}
        </div>
      </div>

      <Dialog.Footer>
        <Button variant="outline" onclick={handleClose}>Cancel</Button>
        <Button
          onclick={handleSubmit}
          disabled={submitting || !formData.title || !formData.scheduled_date}
        >
          {#if submitting}
            Scheduling...
          {:else}
            Schedule Meeting
          {/if}
        </Button>
      </Dialog.Footer>
    {/if}
  </Dialog.Content>
</Dialog.Root>
