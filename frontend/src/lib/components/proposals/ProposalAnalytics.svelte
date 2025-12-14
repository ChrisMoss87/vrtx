<script lang="ts">
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import type { ProposalView } from '$lib/api/proposals';

  interface Props {
    views?: ProposalView[];
    totalViews?: number;
    uniqueViews?: number;
    averageViewTime?: number;
  }

  let {
    views = [],
    totalViews = 0,
    uniqueViews = 0,
    averageViewTime = 0,
  }: Props = $props();

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function formatDuration(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
  }

  // Calculate section engagement
  const sectionEngagement = $derived(views.reduce((acc, view) => {
    if (view.sections_viewed) {
      for (const [sectionId, time] of Object.entries(view.sections_viewed)) {
        acc[sectionId] = (acc[sectionId] || 0) + (time as number);
      }
    }
    return acc;
  }, {} as Record<string, number>));
</script>

<div class="space-y-6">
  <!-- Summary Cards -->
  <div class="grid grid-cols-3 gap-4">
    <Card.Root>
      <Card.Header class="pb-2">
        <Card.Description>Total Views</Card.Description>
        <Card.Title class="text-3xl">{totalViews}</Card.Title>
      </Card.Header>
    </Card.Root>
    <Card.Root>
      <Card.Header class="pb-2">
        <Card.Description>Unique Viewers</Card.Description>
        <Card.Title class="text-3xl">{uniqueViews}</Card.Title>
      </Card.Header>
    </Card.Root>
    <Card.Root>
      <Card.Header class="pb-2">
        <Card.Description>Avg. View Time</Card.Description>
        <Card.Title class="text-3xl">{formatDuration(averageViewTime)}</Card.Title>
      </Card.Header>
    </Card.Root>
  </div>

  <!-- View History -->
  <Card.Root>
    <Card.Header>
      <Card.Title>View History</Card.Title>
      <Card.Description>Recent views of this proposal</Card.Description>
    </Card.Header>
    <Card.Content>
      {#if views.length === 0}
        <p class="text-center text-muted-foreground py-8">No views yet</p>
      {:else}
        <Table.Root>
          <Table.Header>
            <Table.Row>
              <Table.Head>Time</Table.Head>
              <Table.Head>Duration</Table.Head>
              <Table.Head>Location</Table.Head>
              <Table.Head>Device</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each views as view}
              <Table.Row>
                <Table.Cell>{formatDate(view.viewed_at)}</Table.Cell>
                <Table.Cell>{formatDuration(view.duration_seconds || 0)}</Table.Cell>
                <Table.Cell>
                  {#if view.location}
                    {view.location.city}, {view.location.country}
                  {:else}
                    -
                  {/if}
                </Table.Cell>
                <Table.Cell>
                  <span class="text-xs">
                    {#if view.user_agent?.includes('Mobile')}
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="5" y="2" width="14" height="20" rx="2" ry="2" />
                        <line x1="12" y1="18" x2="12.01" y2="18" />
                      </svg>
                      Mobile
                    {:else}
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                      </svg>
                      Desktop
                    {/if}
                  </span>
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/if}
    </Card.Content>
  </Card.Root>

  <!-- Section Engagement -->
  {#if Object.keys(sectionEngagement).length > 0}
    <Card.Root>
      <Card.Header>
        <Card.Title>Section Engagement</Card.Title>
        <Card.Description>Time spent on each section</Card.Description>
      </Card.Header>
      <Card.Content>
        <div class="space-y-3">
          {#each Object.entries(sectionEngagement).sort((a, b) => b[1] - a[1]) as [sectionId, time]}
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span>Section {sectionId}</span>
                <span class="text-muted-foreground">{formatDuration(time)}</span>
              </div>
              <div class="h-2 bg-muted rounded-full overflow-hidden">
                <div
                  class="h-full bg-primary rounded-full"
                  style="width: {Math.min(100, (time / Math.max(...Object.values(sectionEngagement))) * 100)}%"
                ></div>
              </div>
            </div>
          {/each}
        </div>
      </Card.Content>
    </Card.Root>
  {/if}
</div>
