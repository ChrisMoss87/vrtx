<script lang="ts">
  import * as Tabs from '$lib/components/ui/tabs';
  import { CallProviderSetup, CallQueueManager, CallHistory, CallDetail, CallDialer } from '$lib/components/calls';
  import { callApi, type Call, type CallStats } from '$lib/api/calls';
  import * as Card from '$lib/components/ui/card';
  import { Phone, PhoneIncoming, PhoneOutgoing, Clock, CheckCircle, XCircle, FileAudio } from 'lucide-svelte';

  let selectedCall = $state<Call | null>(null);
  let stats = $state<CallStats | null>(null);
  let currentTab = $state('calls');

  async function loadStats() {
    try {
      stats = await callApi.getStats('today');
    } catch (error) {
      console.error('Failed to load stats:', error);
    }
  }

  function formatDuration(seconds: number): string {
    if (!seconds) return '0m';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 0) {
      return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
  }

  $effect(() => {
    loadStats();
  });
</script>

<svelte:head>
  <title>Call Center | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Call Center</h1>
    <p class="text-muted-foreground">Make calls, manage queues, and view call recordings.</p>
  </div>

  {#if stats}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
              <Phone class="h-5 w-5 text-primary" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.total}</p>
              <p class="text-xs text-muted-foreground">Total Calls</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-blue-500/10 flex items-center justify-center">
              <PhoneIncoming class="h-5 w-5 text-blue-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.inbound}</p>
              <p class="text-xs text-muted-foreground">Inbound</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-green-500/10 flex items-center justify-center">
              <PhoneOutgoing class="h-5 w-5 text-green-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.outbound}</p>
              <p class="text-xs text-muted-foreground">Outbound</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-emerald-500/10 flex items-center justify-center">
              <CheckCircle class="h-5 w-5 text-emerald-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.completed}</p>
              <p class="text-xs text-muted-foreground">Completed</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-red-500/10 flex items-center justify-center">
              <XCircle class="h-5 w-5 text-red-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.missed}</p>
              <p class="text-xs text-muted-foreground">Missed</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-purple-500/10 flex items-center justify-center">
              <Clock class="h-5 w-5 text-purple-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{formatDuration(stats.total_duration)}</p>
              <p class="text-xs text-muted-foreground">Talk Time</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-orange-500/10 flex items-center justify-center">
              <FileAudio class="h-5 w-5 text-orange-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{stats.with_recording}</p>
              <p class="text-xs text-muted-foreground">Recordings</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>
    </div>
  {/if}

  <Tabs.Root bind:value={currentTab}>
    <Tabs.List>
      <Tabs.Trigger value="calls">Call History</Tabs.Trigger>
      <Tabs.Trigger value="dialer">Dialer</Tabs.Trigger>
      <Tabs.Trigger value="queues">Queues</Tabs.Trigger>
      <Tabs.Trigger value="providers">Providers</Tabs.Trigger>
    </Tabs.List>

    <Tabs.Content value="calls" class="mt-6">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class={selectedCall ? 'lg:col-span-2' : 'lg:col-span-3'}>
          <CallHistory onSelectCall={(call) => (selectedCall = call)} />
        </div>
        {#if selectedCall}
          <div class="lg:col-span-1">
            <CallDetail
              call={selectedCall}
              onClose={() => (selectedCall = null)}
              onUpdate={() => loadStats()}
            />
          </div>
        {/if}
      </div>
    </Tabs.Content>

    <Tabs.Content value="dialer" class="mt-6">
      <div class="flex justify-center">
        <CallDialer
          onCallStart={(call) => {
            console.log('Call started:', call);
          }}
          onCallEnd={() => {
            loadStats();
          }}
        />
      </div>
    </Tabs.Content>

    <Tabs.Content value="queues" class="mt-6">
      <CallQueueManager />
    </Tabs.Content>

    <Tabs.Content value="providers" class="mt-6">
      <CallProviderSetup />
    </Tabs.Content>
  </Tabs.Root>
</div>
