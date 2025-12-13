<script lang="ts">
  import { onMount } from 'svelte';
  import { renewalsApi, type Renewal, type RenewalPipelineSummary } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import {
    Clock,
    PlayCircle,
    CheckCircle,
    XCircle,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    ArrowRight,
    RefreshCw,
  } from 'lucide-svelte';

  interface Props {
    onSelectRenewal?: (renewal: Renewal) => void;
  }

  let { onSelectRenewal }: Props = $props();

  let pipeline = $state<RenewalPipelineSummary | null>(null);
  let renewals = $state<Renewal[]>([]);
  let loading = $state(true);

  async function loadData() {
    loading = true;
    try {
      const [pipelineRes, renewalsRes] = await Promise.all([
        renewalsApi.getPipeline(),
        renewalsApi.list({ per_page: 50 }),
      ]);
      pipeline = pipelineRes;
      renewals = renewalsRes.data;
    } catch (error) {
      console.error('Failed to load renewal data:', error);
    } finally {
      loading = false;
    }
  }

  async function generateRenewals() {
    try {
      const result = await renewalsApi.generate();
      alert(result.message);
      await loadData();
    } catch (error) {
      console.error('Failed to generate renewals:', error);
    }
  }

  function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value);
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }

  function getDaysUntilDue(dueDate: string): number {
    const due = new Date(dueDate);
    const now = new Date();
    return Math.floor((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
  }

  function getRenewalsByStatus(status: string): Renewal[] {
    return renewals.filter(r => r.status === status);
  }

  onMount(() => {
    loadData();
  });
</script>

<div class="space-y-6">
  <!-- Pipeline Summary -->
  <div class="grid gap-4 md:grid-cols-3">
    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted-foreground">Pending</p>
            <p class="text-2xl font-bold">{pipeline?.pending.count ?? 0}</p>
            <p class="text-sm text-muted-foreground">
              {formatCurrency(pipeline?.pending.value ?? 0)}
            </p>
          </div>
          <Clock class="h-8 w-8 text-muted-foreground" />
        </div>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted-foreground">In Progress</p>
            <p class="text-2xl font-bold">{pipeline?.in_progress.count ?? 0}</p>
            <p class="text-sm text-muted-foreground">
              {formatCurrency(pipeline?.in_progress.value ?? 0)}
            </p>
          </div>
          <PlayCircle class="h-8 w-8 text-blue-500" />
        </div>
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Content class="pt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted-foreground">Total Pipeline</p>
            <p class="text-2xl font-bold">{pipeline?.total.count ?? 0}</p>
            <p class="text-sm text-muted-foreground">
              {formatCurrency(pipeline?.total.value ?? 0)}
            </p>
          </div>
          <TrendingUp class="h-8 w-8 text-green-500" />
        </div>
      </Card.Content>
    </Card.Root>
  </div>

  <!-- Generate Button -->
  <div class="flex justify-end">
    <Button variant="outline" onclick={generateRenewals}>
      <RefreshCw class="mr-2 h-4 w-4" />
      Generate Pending Renewals
    </Button>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-8">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
    </div>
  {:else}
    <!-- Kanban-style Board -->
    <div class="grid gap-4 md:grid-cols-4">
      <!-- Pending Column -->
      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="flex items-center gap-2 text-sm">
            <Clock class="h-4 w-4" />
            Pending
            <Badge variant="secondary">{getRenewalsByStatus('pending').length}</Badge>
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2">
          {#each getRenewalsByStatus('pending') as renewal}
            {@const daysUntilDue = getDaysUntilDue(renewal.due_date)}
            <div
              class="p-3 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors"
              class:border-red-200={daysUntilDue < 0}
              class:bg-red-50={daysUntilDue < 0}
              onclick={() => onSelectRenewal?.(renewal)}
            >
              <div class="font-medium text-sm truncate">{renewal.contract?.name}</div>
              <div class="text-xs text-muted-foreground">{formatCurrency(renewal.original_value)}</div>
              <div class="flex items-center gap-1 mt-2">
                {#if daysUntilDue < 0}
                  <Badge variant="destructive" class="text-xs">
                    <AlertTriangle class="mr-1 h-3 w-3" />
                    {Math.abs(daysUntilDue)}d overdue
                  </Badge>
                {:else if daysUntilDue <= 7}
                  <Badge variant="outline" class="text-xs border-yellow-500 text-yellow-600">
                    Due {formatDate(renewal.due_date)}
                  </Badge>
                {:else}
                  <span class="text-xs text-muted-foreground">Due {formatDate(renewal.due_date)}</span>
                {/if}
              </div>
            </div>
          {/each}
          {#if getRenewalsByStatus('pending').length === 0}
            <div class="text-center py-4 text-sm text-muted-foreground">
              No pending renewals
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- In Progress Column -->
      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="flex items-center gap-2 text-sm">
            <PlayCircle class="h-4 w-4 text-blue-500" />
            In Progress
            <Badge variant="secondary">{getRenewalsByStatus('in_progress').length}</Badge>
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2">
          {#each getRenewalsByStatus('in_progress') as renewal}
            {@const daysUntilDue = getDaysUntilDue(renewal.due_date)}
            <div
              class="p-3 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors"
              onclick={() => onSelectRenewal?.(renewal)}
            >
              <div class="font-medium text-sm truncate">{renewal.contract?.name}</div>
              <div class="text-xs text-muted-foreground">{formatCurrency(renewal.original_value)}</div>
              <div class="flex items-center gap-1 mt-2">
                {#if daysUntilDue < 0}
                  <Badge variant="destructive" class="text-xs">Overdue</Badge>
                {:else}
                  <span class="text-xs text-muted-foreground">Due {formatDate(renewal.due_date)}</span>
                {/if}
              </div>
              {#if renewal.owner}
                <div class="text-xs text-muted-foreground mt-1">
                  {renewal.owner.name}
                </div>
              {/if}
            </div>
          {/each}
          {#if getRenewalsByStatus('in_progress').length === 0}
            <div class="text-center py-4 text-sm text-muted-foreground">
              No renewals in progress
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Won Column -->
      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="flex items-center gap-2 text-sm">
            <CheckCircle class="h-4 w-4 text-green-500" />
            Won
            <Badge variant="secondary">{getRenewalsByStatus('won').length}</Badge>
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2">
          {#each getRenewalsByStatus('won').slice(0, 5) as renewal}
            <div
              class="p-3 rounded-lg border bg-green-50 dark:bg-green-950/20 cursor-pointer hover:bg-green-100 dark:hover:bg-green-950/30 transition-colors"
              onclick={() => onSelectRenewal?.(renewal)}
            >
              <div class="font-medium text-sm truncate">{renewal.contract?.name}</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="text-xs text-muted-foreground">{formatCurrency(renewal.original_value)}</span>
                {#if renewal.renewal_value && renewal.renewal_value !== renewal.original_value}
                  <ArrowRight class="h-3 w-3" />
                  <span class="text-xs font-medium text-green-600">{formatCurrency(renewal.renewal_value)}</span>
                {/if}
              </div>
              {#if renewal.renewal_type}
                <Badge variant="outline" class="mt-2 text-xs">
                  {renewal.renewal_type}
                </Badge>
              {/if}
            </div>
          {/each}
          {#if getRenewalsByStatus('won').length === 0}
            <div class="text-center py-4 text-sm text-muted-foreground">
              No won renewals yet
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Lost Column -->
      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="flex items-center gap-2 text-sm">
            <XCircle class="h-4 w-4 text-red-500" />
            Lost
            <Badge variant="secondary">{getRenewalsByStatus('lost').length}</Badge>
          </Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2">
          {#each getRenewalsByStatus('lost').slice(0, 5) as renewal}
            <div
              class="p-3 rounded-lg border bg-red-50 dark:bg-red-950/20 cursor-pointer hover:bg-red-100 dark:hover:bg-red-950/30 transition-colors"
              onclick={() => onSelectRenewal?.(renewal)}
            >
              <div class="font-medium text-sm truncate">{renewal.contract?.name}</div>
              <div class="text-xs text-muted-foreground">{formatCurrency(renewal.original_value)}</div>
              {#if renewal.loss_reason}
                <div class="text-xs text-red-600 mt-1 truncate">
                  {renewal.loss_reason}
                </div>
              {/if}
            </div>
          {/each}
          {#if getRenewalsByStatus('lost').length === 0}
            <div class="text-center py-4 text-sm text-muted-foreground">
              No lost renewals
            </div>
          {/if}
        </Card.Content>
      </Card.Root>
    </div>
  {/if}
</div>
