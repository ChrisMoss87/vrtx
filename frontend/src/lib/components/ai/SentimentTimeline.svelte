<script lang="ts">
  import { onMount } from 'svelte';
  import * as Card from '$lib/components/ui/card';
  import { Badge } from '$lib/components/ui/badge';
  import { getSentimentTimeline, type SentimentScore } from '$lib/api/ai';
  import { Loader2, Smile, Meh, Frown, AlertTriangle, HelpCircle, Mail, FileText } from 'lucide-svelte';

  export let module: string;
  export let recordId: number;
  export let limit = 10;

  let timeline: SentimentScore[] = [];
  let loading = true;

  onMount(async () => {
    try {
      const result = await getSentimentTimeline(module, recordId, limit);
      timeline = result.timeline;
    } catch (error) {
      console.error('Failed to load sentiment timeline:', error);
    } finally {
      loading = false;
    }
  });

  function getEmotionIcon(emotion: string) {
    switch (emotion) {
      case 'happy':
      case 'satisfied':
        return Smile;
      case 'frustrated':
      case 'angry':
        return Frown;
      case 'confused':
        return HelpCircle;
      case 'urgent':
        return AlertTriangle;
      default:
        return Meh;
    }
  }

  function getEntityIcon(entityType: string) {
    switch (entityType) {
      case 'email':
        return Mail;
      case 'note':
        return FileText;
      default:
        return FileText;
    }
  }

  function getCategoryColor(category: string): string {
    switch (category) {
      case 'positive':
        return 'bg-green-100 border-green-300 text-green-800';
      case 'negative':
        return 'bg-red-100 border-red-300 text-red-800';
      default:
        return 'bg-gray-100 border-gray-300 text-gray-800';
    }
  }

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
</script>

<Card.Root>
  <Card.Header>
    <Card.Title class="text-sm">Sentiment Timeline</Card.Title>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    {:else if timeline.length === 0}
      <p class="text-sm text-muted-foreground text-center py-4">
        No sentiment data yet
      </p>
    {:else}
      <div class="space-y-3">
        {#each timeline as item}
          <div class="flex items-start gap-3 p-2 rounded-lg border {getCategoryColor(item.category)}">
            <div class="flex-shrink-0 mt-0.5">
              <svelte:component this={getEmotionIcon(item.emotion)} class="h-5 w-5" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <Badge variant="outline" class="text-xs">
                  <svelte:component this={getEntityIcon(item.entity_type)} class="h-3 w-3 mr-1" />
                  {item.entity_type}
                </Badge>
                <span class="text-xs capitalize">{item.emotion}</span>
                <span class="text-xs text-muted-foreground ml-auto">
                  {Math.round(item.confidence * 100)}%
                </span>
              </div>
              <div class="flex items-center justify-between mt-1">
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-16 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all"
                      class:bg-green-500={item.score > 0.25}
                      class:bg-red-500={item.score < -0.25}
                      class:bg-gray-400={item.score >= -0.25 && item.score <= 0.25}
                      style="width: {Math.abs(item.score) * 100}%; margin-left: {item.score < 0 ? 0 : 50}%"
                    ></div>
                  </div>
                  <span class="text-xs">
                    {item.score > 0 ? '+' : ''}{item.score.toFixed(2)}
                  </span>
                </div>
                <span class="text-xs text-muted-foreground">
                  {formatDate(item.analyzed_at)}
                </span>
              </div>
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </Card.Content>
</Card.Root>
