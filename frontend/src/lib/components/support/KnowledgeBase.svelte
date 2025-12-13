<script lang="ts">
  import { onMount } from 'svelte';
  import { knowledgeBaseApi, type KbCategory, type KbArticle } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import { Search, FolderOpen, FileText, ChevronRight, ThumbsUp, ThumbsDown, Eye } from 'lucide-svelte';

  interface Props {
    onSelectArticle?: (article: KbArticle) => void;
    publicOnly?: boolean;
  }

  let { onSelectArticle, publicOnly = false }: Props = $props();

  let categories = $state<KbCategory[]>([]);
  let articles = $state<KbArticle[]>([]);
  let searchResults = $state<KbArticle[]>([]);
  let searchQuery = $state('');
  let selectedCategory = $state<KbCategory | null>(null);
  let loading = $state(true);
  let searching = $state(false);

  async function loadCategories() {
    try {
      const response = await knowledgeBaseApi.categories({
        public_only: publicOnly,
        top_level_only: true,
      });
      categories = response.categories;
    } catch (error) {
      console.error('Failed to load categories:', error);
    }
  }

  async function loadArticles(categoryId?: number) {
    loading = true;
    try {
      const response = await knowledgeBaseApi.articles({
        category_id: categoryId,
        public_only: publicOnly,
        per_page: 50,
      });
      articles = response.data;
    } catch (error) {
      console.error('Failed to load articles:', error);
    } finally {
      loading = false;
    }
  }

  async function handleSearch() {
    if (!searchQuery.trim()) {
      searchResults = [];
      return;
    }

    searching = true;
    try {
      const response = await knowledgeBaseApi.search(searchQuery);
      searchResults = response.results;
    } catch (error) {
      console.error('Failed to search:', error);
    } finally {
      searching = false;
    }
  }

  function selectCategory(category: KbCategory) {
    selectedCategory = category;
    searchQuery = '';
    searchResults = [];
    loadArticles(category.id);
  }

  function clearCategory() {
    selectedCategory = null;
    loadArticles();
  }

  onMount(() => {
    loadCategories();
    loadArticles();
  });

  $effect(() => {
    if (searchQuery) {
      const timeout = setTimeout(() => {
        handleSearch();
      }, 300);
      return () => clearTimeout(timeout);
    } else {
      searchResults = [];
    }
  });
</script>

<div class="space-y-6">
  <!-- Search -->
  <div class="relative">
    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
    <Input
      type="text"
      placeholder="Search knowledge base..."
      bind:value={searchQuery}
      class="pl-9"
    />
  </div>

  {#if searchQuery && searchResults.length > 0}
    <!-- Search Results -->
    <div class="space-y-2">
      <h3 class="text-sm font-medium text-muted-foreground">
        {searchResults.length} result{searchResults.length !== 1 ? 's' : ''} found
      </h3>
      <div class="space-y-2">
        {#each searchResults as article}
          <Card.Root
            class="cursor-pointer hover:bg-muted/50 transition-colors"
            onclick={() => onSelectArticle?.(article)}
          >
            <Card.Content class="py-4">
              <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                  <h4 class="font-medium truncate">{article.title}</h4>
                  {#if article.excerpt}
                    <p class="text-sm text-muted-foreground line-clamp-2 mt-1">
                      {article.excerpt}
                    </p>
                  {/if}
                  <div class="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                    {#if article.category}
                      <span>{article.category.name}</span>
                    {/if}
                    <span class="flex items-center gap-1">
                      <Eye class="h-3 w-3" />
                      {article.view_count}
                    </span>
                  </div>
                </div>
                <ChevronRight class="h-5 w-5 text-muted-foreground flex-shrink-0" />
              </div>
            </Card.Content>
          </Card.Root>
        {/each}
      </div>
    </div>
  {:else if searchQuery && !searching}
    <div class="text-center py-8 text-muted-foreground">
      No articles found matching "{searchQuery}"
    </div>
  {:else}
    <!-- Categories and Articles -->
    <div class="grid gap-6 md:grid-cols-[250px_1fr]">
      <!-- Categories Sidebar -->
      <div class="space-y-2">
        <h3 class="text-sm font-medium text-muted-foreground mb-3">Categories</h3>
        <Button
          variant={selectedCategory === null ? 'secondary' : 'ghost'}
          class="w-full justify-start"
          onclick={clearCategory}
        >
          <FolderOpen class="mr-2 h-4 w-4" />
          All Articles
        </Button>
        {#each categories as category}
          <Button
            variant={selectedCategory?.id === category.id ? 'secondary' : 'ghost'}
            class="w-full justify-between"
            onclick={() => selectCategory(category)}
          >
            <span class="flex items-center">
              <FolderOpen class="mr-2 h-4 w-4" />
              {category.name}
            </span>
            {#if category.published_articles_count}
              <Badge variant="secondary" class="ml-2">
                {category.published_articles_count}
              </Badge>
            {/if}
          </Button>
        {/each}
      </div>

      <!-- Articles List -->
      <div>
        {#if selectedCategory}
          <div class="flex items-center gap-2 mb-4">
            <Button variant="ghost" size="sm" onclick={clearCategory}>
              All
            </Button>
            <ChevronRight class="h-4 w-4 text-muted-foreground" />
            <span class="font-medium">{selectedCategory.name}</span>
          </div>
        {/if}

        {#if loading}
          <div class="flex items-center justify-center py-8">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
          </div>
        {:else if articles.length === 0}
          <div class="text-center py-8 text-muted-foreground">
            No articles found
          </div>
        {:else}
          <div class="space-y-2">
            {#each articles as article}
              <Card.Root
                class="cursor-pointer hover:bg-muted/50 transition-colors"
                onclick={() => onSelectArticle?.(article)}
              >
                <Card.Content class="py-4">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <FileText class="h-4 w-4 text-muted-foreground flex-shrink-0" />
                        <h4 class="font-medium truncate">{article.title}</h4>
                      </div>
                      {#if article.excerpt}
                        <p class="text-sm text-muted-foreground line-clamp-2 mt-1 ml-6">
                          {article.excerpt}
                        </p>
                      {/if}
                      <div class="flex items-center gap-4 mt-2 ml-6 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1">
                          <Eye class="h-3 w-3" />
                          {article.view_count}
                        </span>
                        {#if article.helpful_count > 0 || article.not_helpful_count > 0}
                          <span class="flex items-center gap-1">
                            <ThumbsUp class="h-3 w-3" />
                            {article.helpful_count}
                          </span>
                          <span class="flex items-center gap-1">
                            <ThumbsDown class="h-3 w-3" />
                            {article.not_helpful_count}
                          </span>
                        {/if}
                        {#if article.tags && article.tags.length > 0}
                          <div class="flex gap-1">
                            {#each article.tags.slice(0, 3) as tag}
                              <Badge variant="outline" class="text-xs">{tag}</Badge>
                            {/each}
                          </div>
                        {/if}
                      </div>
                    </div>
                    <ChevronRight class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                  </div>
                </Card.Content>
              </Card.Root>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  {/if}
</div>
