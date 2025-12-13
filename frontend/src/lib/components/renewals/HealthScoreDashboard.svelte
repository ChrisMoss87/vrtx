<script lang="ts">
  import { onMount } from 'svelte';
  import { healthScoresApi, type CustomerHealthScore, type HealthSummary } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Progress } from '$lib/components/ui/progress';
  import {
    Heart,
    AlertTriangle,
    AlertCircle,
    TrendingUp,
    TrendingDown,
    RefreshCw,
    Users,
    Activity,
    HeadphonesIcon,
    BarChart3,
    CreditCard,
    Handshake,
  } from 'lucide-svelte';

  interface Props {
    onSelectCustomer?: (healthScore: CustomerHealthScore) => void;
  }

  let { onSelectCustomer }: Props = $props();

  let summary = $state<HealthSummary | null>(null);
  let atRiskCustomers = $state<CustomerHealthScore[]>([]);
  let loading = $state(true);
  let recalculating = $state(false);

  async function loadData() {
    loading = true;
    try {
      const [summaryRes, atRiskRes] = await Promise.all([
        healthScoresApi.getSummary(),
        healthScoresApi.getAtRisk(),
      ]);
      summary = summaryRes;
      atRiskCustomers = atRiskRes.customers;
    } catch (error) {
      console.error('Failed to load health score data:', error);
    } finally {
      loading = false;
    }
  }

  async function recalculateAll() {
    recalculating = true;
    try {
      const result = await healthScoresApi.recalculateAll();
      alert(result.message);
      await loadData();
    } catch (error) {
      console.error('Failed to recalculate:', error);
    } finally {
      recalculating = false;
    }
  }

  function getStatusColor(status: string): string {
    switch (status) {
      case 'healthy':
        return 'text-green-500';
      case 'at_risk':
        return 'text-yellow-500';
      case 'critical':
        return 'text-red-500';
      default:
        return 'text-muted-foreground';
    }
  }

  function getStatusBadge(status: string) {
    switch (status) {
      case 'healthy':
        return { variant: 'outline' as const, class: 'bg-green-500 text-white', icon: Heart };
      case 'at_risk':
        return { variant: 'outline' as const, class: 'bg-yellow-500 text-white', icon: AlertTriangle };
      case 'critical':
        return { variant: 'destructive' as const, class: '', icon: AlertCircle };
      default:
        return { variant: 'outline' as const, class: '', icon: Heart };
    }
  }

  function getScoreColor(score: number): string {
    if (score >= 70) return 'bg-green-500';
    if (score >= 40) return 'bg-yellow-500';
    return 'bg-red-500';
  }

  function getScoreIcon(category: string) {
    switch (category) {
      case 'engagement':
        return Activity;
      case 'support':
        return HeadphonesIcon;
      case 'product_usage':
        return BarChart3;
      case 'payment':
        return CreditCard;
      case 'relationship':
        return Handshake;
      default:
        return Activity;
    }
  }

  onMount(() => {
    loadData();
  });
</script>

<div class="space-y-6">
  <!-- Summary Cards -->
  {#if loading}
    <div class="flex items-center justify-center py-8">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
    </div>
  {:else}
    <div class="grid gap-4 md:grid-cols-5">
      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Total Customers</p>
              <p class="text-2xl font-bold">{summary?.total_customers ?? 0}</p>
            </div>
            <Users class="h-8 w-8 text-muted-foreground" />
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Healthy</p>
              <p class="text-2xl font-bold text-green-500">{summary?.healthy ?? 0}</p>
            </div>
            <Heart class="h-8 w-8 text-green-500" />
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">At Risk</p>
              <p class="text-2xl font-bold text-yellow-500">{summary?.at_risk ?? 0}</p>
            </div>
            <AlertTriangle class="h-8 w-8 text-yellow-500" />
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Critical</p>
              <p class="text-2xl font-bold text-red-500">{summary?.critical ?? 0}</p>
            </div>
            <AlertCircle class="h-8 w-8 text-red-500" />
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="pt-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-muted-foreground">Average Score</p>
              <p class="text-2xl font-bold">{summary?.average_score ?? 0}</p>
            </div>
            <TrendingUp class="h-8 w-8 text-primary" />
          </div>
        </Card.Content>
      </Card.Root>
    </div>

    <!-- Recalculate Button -->
    <div class="flex justify-end">
      <Button variant="outline" onclick={recalculateAll} disabled={recalculating}>
        <RefreshCw class="mr-2 h-4 w-4" class:animate-spin={recalculating} />
        {recalculating ? 'Recalculating...' : 'Recalculate All Scores'}
      </Button>
    </div>

    <!-- At Risk Customers -->
    <Card.Root>
      <Card.Header>
        <Card.Title class="flex items-center gap-2">
          <AlertTriangle class="h-5 w-5 text-yellow-500" />
          Customers Needing Attention
        </Card.Title>
        <Card.Description>
          Customers with at-risk or critical health scores
        </Card.Description>
      </Card.Header>
      <Card.Content>
        {#if atRiskCustomers.length === 0}
          <div class="text-center py-8 text-muted-foreground">
            <Heart class="h-12 w-12 mx-auto mb-2 text-green-500" />
            <p>All customers are healthy!</p>
          </div>
        {:else}
          <div class="space-y-4">
            {#each atRiskCustomers as customer}
              {@const statusInfo = getStatusBadge(customer.health_status)}
              {@const StatusIcon = statusInfo.icon}
              <div
                class="p-4 rounded-lg border cursor-pointer hover:bg-muted/50 transition-colors"
                onclick={() => onSelectCustomer?.(customer)}
              >
                <div class="flex items-start justify-between gap-4">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="font-medium">
                        {customer.related_module} #{customer.related_id}
                      </span>
                      <Badge variant={statusInfo.variant} class={statusInfo.class}>
                        <StatusIcon class="mr-1 h-3 w-3" />
                        {customer.health_status.replace('_', ' ')}
                      </Badge>
                    </div>

                    <!-- Score Breakdown -->
                    <div class="grid grid-cols-5 gap-4 mt-3">
                      {#each ['engagement', 'support', 'product_usage', 'payment', 'relationship'] as category}
                        {@const ScoreIcon = getScoreIcon(category)}
                        {@const score = customer[`${category}_score` as keyof CustomerHealthScore] as number}
                        <div class="text-center">
                          <ScoreIcon class="h-4 w-4 mx-auto mb-1 text-muted-foreground" />
                          <div class="text-xs text-muted-foreground capitalize">
                            {category.replace('_', ' ')}
                          </div>
                          <div class="text-sm font-medium {score < 40 ? 'text-red-500' : score < 70 ? 'text-yellow-500' : 'text-green-500'}">
                            {score}
                          </div>
                        </div>
                      {/each}
                    </div>

                    <!-- Risk Factors -->
                    {#if customer.risk_factors && customer.risk_factors.length > 0}
                      <div class="mt-3 space-y-1">
                        {#each customer.risk_factors.slice(0, 2) as factor}
                          <div class="text-xs text-muted-foreground flex items-start gap-1">
                            <AlertTriangle class="h-3 w-3 mt-0.5 flex-shrink-0 {factor.severity === 'high' ? 'text-red-500' : 'text-yellow-500'}" />
                            {factor.description}
                          </div>
                        {/each}
                      </div>
                    {/if}
                  </div>

                  <!-- Overall Score -->
                  <div class="text-center">
                    <div class="text-3xl font-bold {getStatusColor(customer.health_status)}">
                      {customer.overall_score}
                    </div>
                    <div class="text-xs text-muted-foreground">Score</div>
                  </div>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </Card.Content>
    </Card.Root>
  {/if}
</div>
