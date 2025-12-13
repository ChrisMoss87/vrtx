<script lang="ts">
  import { onMount } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import { Badge } from '$lib/components/ui/badge';
  import * as Tooltip from '$lib/components/ui/tooltip';
  import { getRecordScore, scoreRecord, type LeadScore } from '$lib/api/ai';
  import { Loader2, RefreshCw, TrendingUp, Brain, Sparkles } from 'lucide-svelte';

  export let module: string;
  export let recordId: number;

  let score: LeadScore | null = null;
  let loading = true;
  let scoring = false;
  let error: string | null = null;

  onMount(async () => {
    await loadScore();
  });

  async function loadScore() {
    loading = true;
    error = null;
    try {
      const result = await getRecordScore(module, recordId);
      score = result.score;
    } catch (e) {
      error = e instanceof Error ? e.message : 'Failed to load score';
    } finally {
      loading = false;
    }
  }

  async function handleScore(useAi = false) {
    scoring = true;
    error = null;
    try {
      const result = await scoreRecord(module, recordId, useAi);
      score = result.score;
    } catch (e) {
      error = e instanceof Error ? e.message : 'Failed to score record';
    } finally {
      scoring = false;
    }
  }

  function getGradeColor(grade: string): string {
    switch (grade) {
      case 'A':
        return 'bg-green-500';
      case 'B':
        return 'bg-blue-500';
      case 'C':
        return 'bg-yellow-500';
      case 'D':
        return 'bg-orange-500';
      case 'F':
        return 'bg-red-500';
      default:
        return 'bg-gray-500';
    }
  }

  function getScoreColor(scoreValue: number): string {
    if (scoreValue >= 80) return 'text-green-600';
    if (scoreValue >= 60) return 'text-blue-600';
    if (scoreValue >= 40) return 'text-yellow-600';
    if (scoreValue >= 20) return 'text-orange-600';
    return 'text-red-600';
  }
</script>

<Card.Root>
  <Card.Header class="pb-2">
    <Card.Title class="text-sm flex items-center gap-2">
      <TrendingUp class="h-4 w-4" />
      Lead Score
    </Card.Title>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-4">
        <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    {:else if error}
      <div class="text-sm text-red-500">{error}</div>
    {:else if score}
      <div class="flex items-center gap-4">
        <!-- Grade Circle -->
        <div class="relative">
          <div
            class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-bold {getGradeColor(score.grade)}"
          >
            {score.grade}
          </div>
          <div class="absolute -bottom-1 -right-1 bg-background rounded-full p-1">
            <span class="text-sm font-semibold {getScoreColor(score.score)}">{score.score}</span>
          </div>
        </div>

        <!-- Score Details -->
        <div class="flex-1 space-y-2">
          {#if score.breakdown && Object.keys(score.breakdown).length > 0}
            <div class="space-y-1">
              {#each Object.entries(score.breakdown).slice(0, 3) as [factor, points]}
                <div class="flex items-center justify-between text-xs">
                  <span class="text-muted-foreground capitalize">{factor.replace(/_/g, ' ')}</span>
                  <span class="font-medium">{points}</span>
                </div>
              {/each}
            </div>
          {/if}

          {#if score.ai_insights}
            <Tooltip.Root>
              <Tooltip.Trigger>
                <Badge variant="outline" class="text-xs">
                  <Brain class="h-3 w-3 mr-1" />
                  AI Insight
                </Badge>
              </Tooltip.Trigger>
              <Tooltip.Content class="max-w-xs">
                <p>{score.ai_insights}</p>
              </Tooltip.Content>
            </Tooltip.Root>
          {/if}
        </div>
      </div>

      {#if score.explanations && score.explanations.length > 0}
        <div class="mt-3 pt-3 border-t">
          <p class="text-xs text-muted-foreground">
            {score.explanations[0]}
          </p>
        </div>
      {/if}
    {:else}
      <div class="text-center py-4">
        <p class="text-sm text-muted-foreground mb-3">Not scored yet</p>
        <div class="flex justify-center gap-2">
          <Button size="sm" variant="outline" onclick={() => handleScore(false)} disabled={scoring}>
            {#if scoring}
              <Loader2 class="mr-1 h-3 w-3 animate-spin" />
            {:else}
              <RefreshCw class="mr-1 h-3 w-3" />
            {/if}
            Score
          </Button>
          <Button size="sm" onclick={() => handleScore(true)} disabled={scoring}>
            <Sparkles class="mr-1 h-3 w-3" />
            AI Score
          </Button>
        </div>
      </div>
    {/if}
  </Card.Content>
  {#if score}
    <Card.Footer class="pt-0">
      <div class="flex justify-between w-full">
        <Button size="sm" variant="ghost" onclick={() => handleScore(false)} disabled={scoring}>
          <RefreshCw class="mr-1 h-3 w-3" />
          Rescore
        </Button>
        <Button size="sm" variant="ghost" onclick={() => handleScore(true)} disabled={scoring}>
          <Sparkles class="mr-1 h-3 w-3" />
          AI Rescore
        </Button>
      </div>
    </Card.Footer>
  {/if}
</Card.Root>
