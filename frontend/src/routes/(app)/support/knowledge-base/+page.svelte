<script lang="ts">
  import { KnowledgeBase, ArticleView } from '$lib/components/support';
  import type { KbArticle } from '$lib/api/support';

  let selectedArticle = $state<KbArticle | null>(null);

  function handleSelectArticle(article: KbArticle) {
    selectedArticle = article;
  }

  function handleBack() {
    selectedArticle = null;
  }
</script>

<svelte:head>
  <title>Knowledge Base | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  {#if selectedArticle}
    <ArticleView article={selectedArticle} onBack={handleBack} />
  {:else}
    <div>
      <h1 class="text-2xl font-semibold">Knowledge Base</h1>
      <p class="text-muted-foreground">Find answers to common questions</p>
    </div>

    <KnowledgeBase onSelectArticle={handleSelectArticle} />
  {/if}
</div>
