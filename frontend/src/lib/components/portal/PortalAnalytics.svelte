<script lang="ts">
  import { onMount } from 'svelte';
  import { portalAdminApi, type PortalActivityAnalytics } from '$lib/api/portal';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { BarChart3, Users, Activity, TrendingUp } from 'lucide-svelte';

  let analytics = $state<PortalActivityAnalytics | null>(null);
  let loading = $state(true);
  let days = $state('30');

  const timeRanges = [
    { value: '7', label: 'Last 7 days' },
    { value: '30', label: 'Last 30 days' },
    { value: '90', label: 'Last 90 days' },
  ];

  const getTimeRangeLabel = (value: string) => timeRanges.find(t => t.value === value)?.label ?? value;

  async function loadAnalytics() {
    loading = true;
    try {
      const response = await portalAdminApi.getAnalytics({ days: parseInt(days) });
      analytics = response;
    } catch (error) {
      console.error('Failed to load analytics:', error);
    } finally {
      loading = false;
    }
  }

  function getActionLabel(action: string): string {
    const labels: Record<string, string> = {
      login: 'Logins',
      logout: 'Logouts',
      view_deal: 'Deal Views',
      view_invoice: 'Invoice Views',
      view_quote: 'Quote Views',
      download_document: 'Document Downloads',
      sign_document: 'Document Signatures',
      submit_ticket: 'Tickets Submitted',
      reply_ticket: 'Ticket Replies',
      update_profile: 'Profile Updates',
      change_password: 'Password Changes',
    };
    return labels[action] || action;
  }

  onMount(() => {
    loadAnalytics();
  });

  $effect(() => {
    if (days) {
      loadAnalytics();
    }
  });
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-semibold flex items-center gap-2">
      <BarChart3 class="h-5 w-5" />
      Portal Analytics
    </h2>
    <Select.Root type="single" bind:value={days}>
      <Select.Trigger class="w-[180px]">
        {getTimeRangeLabel(days)}
      </Select.Trigger>
      <Select.Content>
        {#each timeRanges as range}
          <Select.Item value={range.value}>
            {range.label}
          </Select.Item>
        {/each}
      </Select.Content>
    </Select.Root>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-8">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
    </div>
  {:else if analytics}
    <!-- Summary Cards -->
    <div class="grid gap-4 md:grid-cols-4">
      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-4">
            <div class="rounded-full bg-primary/10 p-3">
              <Users class="h-6 w-6 text-primary" />
            </div>
            <div>
              <p class="text-2xl font-bold">{analytics.total_users}</p>
              <p class="text-sm text-muted-foreground">Total Users</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-4">
            <div class="rounded-full bg-green-500/10 p-3">
              <Activity class="h-6 w-6 text-green-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{analytics.active_users}</p>
              <p class="text-sm text-muted-foreground">Active Users</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-4">
            <div class="rounded-full bg-blue-500/10 p-3">
              <TrendingUp class="h-6 w-6 text-blue-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">{analytics.engagement_rate}%</p>
              <p class="text-sm text-muted-foreground">Engagement Rate</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-4">
            <div class="rounded-full bg-purple-500/10 p-3">
              <BarChart3 class="h-6 w-6 text-purple-500" />
            </div>
            <div>
              <p class="text-2xl font-bold">
                {analytics.logins_by_day.reduce((sum, d) => sum + d.count, 0)}
              </p>
              <p class="text-sm text-muted-foreground">Total Logins</p>
            </div>
          </div>
        </Card.Content>
      </Card.Root>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <!-- Logins Chart -->
      <Card.Root>
        <Card.Header>
          <Card.Title>Login Activity</Card.Title>
          <Card.Description>Daily login counts over time</Card.Description>
        </Card.Header>
        <Card.Content>
          {#if analytics.logins_by_day.length === 0}
            <div class="text-center py-8 text-muted-foreground">
              No login data available
            </div>
          {:else}
            <div class="space-y-2">
              {#each analytics.logins_by_day.slice(-14) as day}
                {@const maxCount = Math.max(...analytics.logins_by_day.map((d) => d.count))}
                {@const percentage = maxCount > 0 ? (day.count / maxCount) * 100 : 0}
                <div class="flex items-center gap-3">
                  <span class="w-20 text-sm text-muted-foreground">
                    {new Date(day.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                  </span>
                  <div class="flex-1 h-6 bg-muted rounded overflow-hidden">
                    <div
                      class="h-full bg-primary transition-all"
                      style="width: {percentage}%"
                    ></div>
                  </div>
                  <span class="w-8 text-sm text-right">{day.count}</span>
                </div>
              {/each}
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Top Actions -->
      <Card.Root>
        <Card.Header>
          <Card.Title>Top Actions</Card.Title>
          <Card.Description>Most common user activities</Card.Description>
        </Card.Header>
        <Card.Content>
          {#if analytics.top_actions.length === 0}
            <div class="text-center py-8 text-muted-foreground">
              No activity data available
            </div>
          {:else}
            <div class="space-y-3">
              {#each analytics.top_actions as action}
                {@const maxCount = analytics.top_actions[0]?.count || 1}
                {@const percentage = (action.count / maxCount) * 100}
                <div class="space-y-1">
                  <div class="flex items-center justify-between text-sm">
                    <span>{getActionLabel(action.action)}</span>
                    <span class="font-medium">{action.count}</span>
                  </div>
                  <div class="h-2 bg-muted rounded overflow-hidden">
                    <div
                      class="h-full bg-primary/60 transition-all"
                      style="width: {percentage}%"
                    ></div>
                  </div>
                </div>
              {/each}
            </div>
          {/if}
        </Card.Content>
      </Card.Root>
    </div>
  {:else}
    <div class="text-center py-8 text-muted-foreground">
      Failed to load analytics
    </div>
  {/if}
</div>
