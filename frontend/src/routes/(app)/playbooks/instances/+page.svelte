<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { playbookInstancesApi, type PlaybookInstance } from '$lib/api/playbooks';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import * as Select from '$lib/components/ui/select';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import { Progress } from '$lib/components/ui/progress';
  import {
    Search,
    PlayCircle,
    Pause,
    CheckCircle,
    XCircle,
    Eye,
    Clock,
    User,
  } from 'lucide-svelte';

  let instances = $state<PlaybookInstance[]>([]);
  let loading = $state(true);
  let statusFilter = $state<string | undefined>(undefined);
  let currentPage = $state(1);
  let totalPages = $state(1);

  async function loadInstances() {
    loading = true;
    try {
      const response = await playbookInstancesApi.list({
        status: statusFilter,
        page: currentPage,
      });
      instances = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load instances:', error);
    } finally {
      loading = false;
    }
  }

  function getStatusBadge(status: string) {
    switch (status) {
      case 'active':
        return { variant: 'default' as const, icon: PlayCircle, label: 'Active' };
      case 'paused':
        return { variant: 'secondary' as const, icon: Pause, label: 'Paused' };
      case 'completed':
        return { variant: 'outline' as const, icon: CheckCircle, label: 'Completed', class: 'bg-green-500 text-white' };
      case 'cancelled':
        return { variant: 'destructive' as const, icon: XCircle, label: 'Cancelled' };
      default:
        return { variant: 'outline' as const, icon: Clock, label: status };
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  onMount(() => {
    loadInstances();
  });

  $effect(() => {
    if (statusFilter !== undefined) {
      currentPage = 1;
      loadInstances();
    }
  });
</script>

<svelte:head>
  <title>Playbook Instances | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-3xl font-bold">Playbook Instances</h1>
    <p class="text-muted-foreground">
      Track all active and completed playbook executions
    </p>
  </div>

  <Card.Root>
    <Card.Header>
      <div class="flex items-center gap-4">
        <Select.Root type="single" value={statusFilter ?? ''} onValueChange={(val) => { statusFilter = val || undefined; }}>
          <Select.Trigger class="w-[180px]">
            <span>{statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : 'All Statuses'}</span>
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="">All Statuses</Select.Item>
            <Select.Item value="active">Active</Select.Item>
            <Select.Item value="paused">Paused</Select.Item>
            <Select.Item value="completed">Completed</Select.Item>
            <Select.Item value="cancelled">Cancelled</Select.Item>
          </Select.Content>
        </Select.Root>
      </div>
    </Card.Header>
    <Card.Content>
      {#if loading}
        <div class="flex items-center justify-center py-8">
          <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
        </div>
      {:else if instances.length === 0}
        <div class="text-center py-8 text-muted-foreground">
          No playbook instances found
        </div>
      {:else}
        <Table.Root>
          <Table.Header>
            <Table.Row>
              <Table.Head>Playbook</Table.Head>
              <Table.Head>Related Record</Table.Head>
              <Table.Head>Owner</Table.Head>
              <Table.Head>Progress</Table.Head>
              <Table.Head>Started</Table.Head>
              <Table.Head>Status</Table.Head>
              <Table.Head class="w-[50px]"></Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each instances as instance}
              {@const statusInfo = getStatusBadge(instance.status)}
              <Table.Row>
                <Table.Cell>
                  <div class="font-medium">{instance.playbook?.name}</div>
                </Table.Cell>
                <Table.Cell>
                  <Badge variant="outline">
                    {instance.related_module} #{instance.related_id}
                  </Badge>
                </Table.Cell>
                <Table.Cell>
                  {#if instance.owner}
                    <div class="flex items-center gap-2">
                      <User class="h-4 w-4 text-muted-foreground" />
                      <span>{instance.owner.name}</span>
                    </div>
                  {:else}
                    <span class="text-muted-foreground">-</span>
                  {/if}
                </Table.Cell>
                <Table.Cell>
                  <div class="flex items-center gap-2 min-w-[120px]">
                    <Progress value={instance.progress_percent} class="h-2 flex-1" />
                    <span class="text-sm text-muted-foreground">{instance.progress_percent}%</span>
                  </div>
                </Table.Cell>
                <Table.Cell>
                  <span class="text-sm">{formatDate(instance.started_at)}</span>
                </Table.Cell>
                <Table.Cell>
                  {@const StatusIcon = statusInfo.icon}
                  <Badge variant={statusInfo.variant} class={statusInfo.class || ''}>
                    <StatusIcon class="mr-1 h-3 w-3" />
                    {statusInfo.label}
                  </Badge>
                </Table.Cell>
                <Table.Cell>
                  <Button
                    variant="ghost"
                    size="icon"
                    onclick={() => goto(`/playbooks/instances/${instance.id}`)}
                  >
                    <Eye class="h-4 w-4" />
                  </Button>
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>

        {#if totalPages > 1}
          <div class="mt-4 flex items-center justify-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === 1}
              onclick={() => { currentPage--; loadInstances(); }}
            >
              Previous
            </Button>
            <span class="text-sm text-muted-foreground">
              Page {currentPage} of {totalPages}
            </span>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === totalPages}
              onclick={() => { currentPage++; loadInstances(); }}
            >
              Next
            </Button>
          </div>
        {/if}
      {/if}
    </Card.Content>
  </Card.Root>
</div>
