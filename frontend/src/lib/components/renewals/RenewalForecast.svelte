<script lang="ts">
  import { onMount } from 'svelte';
  import { renewalsApi, type RenewalForecast } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Badge } from '$lib/components/ui/badge';
  import { Progress } from '$lib/components/ui/progress';
  import {
    TrendingUp,
    TrendingDown,
    Target,
    AlertTriangle,
    CheckCircle,
    XCircle,
    DollarSign,
    BarChart3,
  } from 'lucide-svelte';

  let forecast = $state<RenewalForecast | null>(null);
  let loading = $state(true);
  let selectedPeriod = $state<'month' | 'quarter' | 'year'>('month');

  async function loadForecast() {
    loading = true;
    try {
      const response = await renewalsApi.getForecast(selectedPeriod);
      forecast = response.forecast;
    } catch (error) {
      console.error('Failed to load forecast:', error);
    } finally {
      loading = false;
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

  function formatPercent(value: number | null | undefined): string {
    if (value == null) return '-';
    return `${value.toFixed(1)}%`;
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  }

  onMount(() => {
    loadForecast();
  });

  $effect(() => {
    if (selectedPeriod) {
      loadForecast();
    }
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <div>
        <Card.Title class="flex items-center gap-2">
          <BarChart3 class="h-5 w-5" />
          Renewal Forecast
        </Card.Title>
        {#if forecast}
          <Card.Description>
            {formatDate(forecast.period_start)} - {formatDate(forecast.period_end)}
          </Card.Description>
        {/if}
      </div>
      <Tabs.Root bind:value={selectedPeriod}>
        <Tabs.List>
          <Tabs.Trigger value="month">Month</Tabs.Trigger>
          <Tabs.Trigger value="quarter">Quarter</Tabs.Trigger>
          <Tabs.Trigger value="year">Year</Tabs.Trigger>
        </Tabs.List>
      </Tabs.Root>
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if forecast}
      <div class="space-y-6">
        <!-- Key Metrics -->
        <div class="grid gap-4 md:grid-cols-4">
          <div class="p-4 rounded-lg border">
            <div class="flex items-center gap-2 mb-2">
              <Target class="h-4 w-4 text-muted-foreground" />
              <span class="text-sm text-muted-foreground">Expected</span>
            </div>
            <div class="text-2xl font-bold">{formatCurrency(forecast.expected_renewals)}</div>
            <div class="text-xs text-muted-foreground">
              {forecast.total_contracts} contracts
            </div>
          </div>

          <div class="p-4 rounded-lg border bg-green-50 dark:bg-green-950/20">
            <div class="flex items-center gap-2 mb-2">
              <CheckCircle class="h-4 w-4 text-green-500" />
              <span class="text-sm text-muted-foreground">Renewed</span>
            </div>
            <div class="text-2xl font-bold text-green-600">{formatCurrency(forecast.renewed_value)}</div>
            <div class="text-xs text-muted-foreground">
              {forecast.renewed_count} contracts
            </div>
          </div>

          <div class="p-4 rounded-lg border bg-yellow-50 dark:bg-yellow-950/20">
            <div class="flex items-center gap-2 mb-2">
              <AlertTriangle class="h-4 w-4 text-yellow-500" />
              <span class="text-sm text-muted-foreground">At Risk</span>
            </div>
            <div class="text-2xl font-bold text-yellow-600">{formatCurrency(forecast.at_risk_value)}</div>
            <div class="text-xs text-muted-foreground">
              {forecast.at_risk_count} contracts
            </div>
          </div>

          <div class="p-4 rounded-lg border bg-red-50 dark:bg-red-950/20">
            <div class="flex items-center gap-2 mb-2">
              <XCircle class="h-4 w-4 text-red-500" />
              <span class="text-sm text-muted-foreground">Churned</span>
            </div>
            <div class="text-2xl font-bold text-red-600">{formatCurrency(forecast.churned_value)}</div>
            <div class="text-xs text-muted-foreground">
              {forecast.churned_count} contracts
            </div>
          </div>
        </div>

        <!-- Retention Metrics -->
        <div class="grid gap-4 md:grid-cols-2">
          <div class="p-4 rounded-lg border">
            <div class="flex items-center justify-between mb-4">
              <div>
                <div class="text-sm text-muted-foreground">Gross Retention Rate</div>
                <div class="text-3xl font-bold">
                  {formatPercent(forecast.retention_rate)}
                </div>
              </div>
              {#if forecast.retention_rate != null}
                {#if forecast.retention_rate >= 90}
                  <Badge class="bg-green-500 text-white">Excellent</Badge>
                {:else if forecast.retention_rate >= 80}
                  <Badge class="bg-yellow-500 text-white">Good</Badge>
                {:else}
                  <Badge variant="destructive">Needs Attention</Badge>
                {/if}
              {/if}
            </div>
            {#if forecast.retention_rate != null}
              <Progress value={forecast.retention_rate} class="h-2" />
            {/if}
          </div>

          <div class="p-4 rounded-lg border">
            <div class="flex items-center justify-between mb-4">
              <div>
                <div class="text-sm text-muted-foreground">Net Revenue Retention</div>
                <div class="text-3xl font-bold flex items-center gap-2">
                  {formatPercent(forecast.net_retention)}
                  {#if forecast.net_retention != null}
                    {#if forecast.net_retention > 100}
                      <TrendingUp class="h-5 w-5 text-green-500" />
                    {:else if forecast.net_retention < 100}
                      <TrendingDown class="h-5 w-5 text-red-500" />
                    {/if}
                  {/if}
                </div>
              </div>
              {#if forecast.net_retention != null}
                {#if forecast.net_retention >= 100}
                  <Badge class="bg-green-500 text-white">Growing</Badge>
                {:else}
                  <Badge variant="destructive">Shrinking</Badge>
                {/if}
              {/if}
            </div>
            <div class="text-sm text-muted-foreground">
              Expansion: {formatCurrency(forecast.expansion_value)}
            </div>
          </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="p-4 rounded-lg border">
          <div class="text-sm font-medium mb-4">Revenue Breakdown</div>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm">Expected Renewals</span>
              <span class="font-medium">{formatCurrency(forecast.expected_renewals)}</span>
            </div>
            <div class="flex items-center justify-between text-green-600">
              <span class="text-sm">+ Renewed</span>
              <span class="font-medium">{formatCurrency(forecast.renewed_value)}</span>
            </div>
            <div class="flex items-center justify-between text-green-600">
              <span class="text-sm">+ Expansion</span>
              <span class="font-medium">{formatCurrency(forecast.expansion_value)}</span>
            </div>
            <div class="flex items-center justify-between text-red-600">
              <span class="text-sm">- Churned</span>
              <span class="font-medium">-{formatCurrency(forecast.churned_value)}</span>
            </div>
            <div class="border-t pt-2 flex items-center justify-between font-bold">
              <span class="text-sm">Net Result</span>
              <span>{formatCurrency(forecast.renewed_value + forecast.expansion_value - forecast.churned_value)}</span>
            </div>
          </div>
        </div>
      </div>
    {:else}
      <div class="text-center py-8 text-muted-foreground">
        No forecast data available
      </div>
    {/if}
  </Card.Content>
</Card.Root>
