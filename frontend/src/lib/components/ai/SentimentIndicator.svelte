<script lang="ts">
  import { onMount } from 'svelte';
  import * as Tooltip from '$lib/components/ui/tooltip';
  import { Badge } from '$lib/components/ui/badge';
  import { getSentimentSummary, type SentimentSummary } from '$lib/api/ai';
  import { Smile, Meh, Frown, TrendingUp, TrendingDown, Minus } from 'lucide-svelte';

  export let module: string;
  export let recordId: number;
  export let compact = false;

  let summary: SentimentSummary | null = null;
  let loading = true;

  onMount(async () => {
    try {
      const result = await getSentimentSummary(module, recordId);
      summary = result.summary;
    } catch (error) {
      console.error('Failed to load sentiment:', error);
    } finally {
      loading = false;
    }
  });

  function getSentimentIcon(sentiment: string | null) {
    switch (sentiment) {
      case 'positive':
        return Smile;
      case 'negative':
        return Frown;
      default:
        return Meh;
    }
  }

  function getSentimentColor(sentiment: string | null): string {
    switch (sentiment) {
      case 'positive':
        return 'text-green-500';
      case 'negative':
        return 'text-red-500';
      default:
        return 'text-gray-500';
    }
  }

  function getTrendIcon(trend: number | null) {
    if (trend === null) return Minus;
    if (trend > 0.1) return TrendingUp;
    if (trend < -0.1) return TrendingDown;
    return Minus;
  }

  function getTrendColor(trend: number | null): string {
    if (trend === null) return 'text-gray-400';
    if (trend > 0.1) return 'text-green-500';
    if (trend < -0.1) return 'text-red-500';
    return 'text-gray-400';
  }
</script>

{#if loading}
  <div class="animate-pulse bg-muted h-6 w-16 rounded"></div>
{:else if summary?.has_data}
  <Tooltip.Root>
    <Tooltip.Trigger>
      {#if compact}
        <div class="flex items-center gap-1">
          <svelte:component
            this={getSentimentIcon(summary.overall_sentiment)}
            class="h-4 w-4 {getSentimentColor(summary.overall_sentiment)}"
          />
          <svelte:component
            this={getTrendIcon(summary.trend)}
            class="h-3 w-3 {getTrendColor(summary.trend)}"
          />
        </div>
      {:else}
        <Badge
          variant="outline"
          class="flex items-center gap-1 {getSentimentColor(summary.overall_sentiment)}"
        >
          <svelte:component
            this={getSentimentIcon(summary.overall_sentiment)}
            class="h-3 w-3"
          />
          <span class="capitalize">{summary.overall_sentiment}</span>
          <svelte:component
            this={getTrendIcon(summary.trend)}
            class="h-3 w-3 {getTrendColor(summary.trend)}"
          />
        </Badge>
      {/if}
    </Tooltip.Trigger>
    <Tooltip.Content>
      <div class="space-y-2 text-sm">
        <div class="font-medium capitalize">
          {summary.overall_sentiment || 'No'} Sentiment
        </div>
        <div class="flex gap-4 text-xs">
          <span class="text-green-500">+{summary.breakdown.positive}</span>
          <span class="text-gray-500">{summary.breakdown.neutral}</span>
          <span class="text-red-500">-{summary.breakdown.negative}</span>
        </div>
        {#if summary.trend !== null}
          <div class="text-xs text-muted-foreground">
            Trend: {summary.trend > 0 ? '+' : ''}{summary.trend.toFixed(2)}
            {#if summary.is_improving}
              (improving)
            {:else if summary.is_declining}
              (declining)
            {/if}
          </div>
        {/if}
      </div>
    </Tooltip.Content>
  </Tooltip.Root>
{:else}
  <span class="text-xs text-muted-foreground">No sentiment data</span>
{/if}
