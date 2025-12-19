<script lang="ts">
  import { onMount } from 'svelte';
  import { ticketsApi, type TicketStats } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import { Inbox, UserX, AlertTriangle, CheckCircle, Clock, Timer } from 'lucide-svelte';

  interface Props {
    myStats?: boolean;
  }

  let { myStats = false }: Props = $props();

  let stats = $state<TicketStats | null>(null);
  let loading = $state(true);

  async function loadStats() {
    try {
      stats = await ticketsApi.stats(myStats);
    } catch (error) {
      console.error('Failed to load ticket stats:', error);
    } finally {
      loading = false;
    }
  }

  function formatTime(minutes?: number): string {
    if (!minutes) return '-';
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours < 24) return `${hours}h ${mins}m`;
    const days = Math.floor(hours / 24);
    const remainingHours = hours % 24;
    return `${days}d ${remainingHours}h`;
  }

  onMount(() => {
    loadStats();
  });
</script>

{#if loading}
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    {#each Array(4) as _}
      <Card.Root>
        <Card.Content class="pt-6">
          <div class="animate-pulse space-y-2">
            <div class="h-4 w-20 bg-muted rounded"></div>
            <div class="h-8 w-16 bg-muted rounded"></div>
          </div>
        </Card.Content>
      </Card.Root>
    {/each}
  </div>
{:else if stats}
  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-muted-foreground">Open Tickets</p>
            <p class="text-2xl font-bold">{stats.open}</p>
          </div>
          <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900/20">
            <Inbox class="h-5 w-5 text-blue-600" />
          </div>
        </div>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-muted-foreground">Unassigned</p>
            <p class="text-2xl font-bold">{stats.unassigned}</p>
          </div>
          <div class="rounded-full bg-yellow-100 p-3 dark:bg-yellow-900/20">
            <UserX class="h-5 w-5 text-yellow-600" />
          </div>
        </div>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-muted-foreground">Overdue SLA</p>
            <p class="text-2xl font-bold text-red-600">{stats.overdue_sla}</p>
          </div>
          <div class="rounded-full bg-red-100 p-3 dark:bg-red-900/20">
            <AlertTriangle class="h-5 w-5 text-red-600" />
          </div>
        </div>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-muted-foreground">Resolved Today</p>
            <p class="text-2xl font-bold text-green-600">{stats.resolved_today}</p>
          </div>
          <div class="rounded-full bg-green-100 p-3 dark:bg-green-900/20">
            <CheckCircle class="h-5 w-5 text-green-600" />
          </div>
        </div>
      </Card.Content>
    </Card.Root>
  </div>

  {#if stats.avg_response_time || stats.avg_resolution_time}
    <div class="grid gap-4 md:grid-cols-2 mt-4">
      {#if stats.avg_response_time}
        <Card.Root>
          <Card.Content class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-muted-foreground">Avg Response Time</p>
                <p class="text-xl font-bold">{formatTime(stats.avg_response_time)}</p>
              </div>
              <Clock class="h-5 w-5 text-muted-foreground" />
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      {#if stats.avg_resolution_time}
        <Card.Root>
          <Card.Content class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-muted-foreground">Avg Resolution Time</p>
                <p class="text-xl font-bold">{formatTime(stats.avg_resolution_time)}</p>
              </div>
              <Timer class="h-5 w-5 text-muted-foreground" />
            </div>
          </Card.Content>
        </Card.Root>
      {/if}
    </div>
  {/if}
{/if}
