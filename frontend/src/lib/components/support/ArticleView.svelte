<script lang="ts">
  import { knowledgeBaseApi, type KbArticle } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Separator } from '$lib/components/ui/separator';
  import {
    ArrowLeft,
    ThumbsUp,
    ThumbsDown,
    Eye,
    Calendar,
    User,
    FolderOpen,
  } from 'lucide-svelte';

  interface Props {
    article: KbArticle;
    onBack: () => void;
  }

  let { article, onBack }: Props = $props();

  let feedbackGiven = $state(false);
  let showFeedbackForm = $state(false);
  let feedbackComment = $state('');
  let submittingFeedback = $state(false);

  async function submitFeedback(isHelpful: boolean) {
    if (feedbackGiven) return;

    submittingFeedback = true;
    try {
      await knowledgeBaseApi.articleFeedback(article.id, {
        is_helpful: isHelpful,
        comment: feedbackComment || undefined,
      });
      feedbackGiven = true;
      showFeedbackForm = false;
    } catch (error) {
      console.error('Failed to submit feedback:', error);
    } finally {
      submittingFeedback = false;
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }
</script>

<div class="space-y-6">
  <!-- Header -->
  <div class="flex items-center gap-4">
    <Button variant="ghost" size="icon" onclick={onBack}>
      <ArrowLeft class="h-5 w-5" />
    </Button>
    <div class="flex-1">
      {#if article.category}
        <div class="flex items-center gap-2 text-sm text-muted-foreground mb-1">
          <FolderOpen class="h-4 w-4" />
          <span>{article.category.name}</span>
        </div>
      {/if}
      <h1 class="text-2xl font-semibold">{article.title}</h1>
    </div>
  </div>

  <!-- Article Meta -->
  <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
    {#if article.author}
      <div class="flex items-center gap-1">
        <User class="h-4 w-4" />
        <span>{article.author.name}</span>
      </div>
    {/if}
    {#if article.published_at}
      <div class="flex items-center gap-1">
        <Calendar class="h-4 w-4" />
        <span>{formatDate(article.published_at)}</span>
      </div>
    {/if}
    <div class="flex items-center gap-1">
      <Eye class="h-4 w-4" />
      <span>{article.view_count} views</span>
    </div>
    {#if article.tags && article.tags.length > 0}
      <div class="flex gap-1">
        {#each article.tags as tag}
          <Badge variant="outline">{tag}</Badge>
        {/each}
      </div>
    {/if}
  </div>

  <!-- Article Content -->
  <Card.Root>
    <Card.Content class="pt-6">
      <div class="prose prose-sm max-w-none dark:prose-invert">
        {@html article.content}
      </div>
    </Card.Content>
  </Card.Root>

  <!-- Feedback Section -->
  <Card.Root>
    <Card.Content class="pt-6">
      {#if feedbackGiven}
        <div class="text-center py-4">
          <p class="text-green-600 font-medium">Thank you for your feedback!</p>
        </div>
      {:else}
        <div class="space-y-4">
          <div class="text-center">
            <p class="text-sm text-muted-foreground mb-4">Was this article helpful?</p>
            <div class="flex items-center justify-center gap-4">
              <Button
                variant="outline"
                onclick={() => {
                  if (!showFeedbackForm) {
                    submitFeedback(true);
                  } else {
                    submitFeedback(true);
                  }
                }}
                disabled={submittingFeedback}
              >
                <ThumbsUp class="mr-2 h-4 w-4" />
                Yes ({article.helpful_count})
              </Button>
              <Button
                variant="outline"
                onclick={() => {
                  showFeedbackForm = true;
                }}
                disabled={submittingFeedback}
              >
                <ThumbsDown class="mr-2 h-4 w-4" />
                No ({article.not_helpful_count})
              </Button>
            </div>
          </div>

          {#if showFeedbackForm}
            <Separator />
            <div class="space-y-4">
              <p class="text-sm text-muted-foreground">
                We're sorry this article wasn't helpful. Please let us know how we can improve it:
              </p>
              <Textarea
                bind:value={feedbackComment}
                placeholder="What was missing or unclear?"
                rows={3}
              />
              <div class="flex justify-end gap-2">
                <Button
                  variant="outline"
                  onclick={() => {
                    showFeedbackForm = false;
                    feedbackComment = '';
                  }}
                >
                  Cancel
                </Button>
                <Button
                  onclick={() => submitFeedback(false)}
                  disabled={submittingFeedback}
                >
                  {submittingFeedback ? 'Submitting...' : 'Submit Feedback'}
                </Button>
              </div>
            </div>
          {/if}
        </div>
      {/if}
    </Card.Content>
  </Card.Root>
</div>
