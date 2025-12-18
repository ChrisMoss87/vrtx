<script lang="ts">
  import { AiSettingsPanel } from '$lib/components/ai';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Button } from '$lib/components/ui/button';
  import * as Card from '$lib/components/ui/card';
  import { Badge } from '$lib/components/ui/badge';
  import { onMount } from 'svelte';
  import {
    getScoringModels,
    deleteScoringModel,
    getAiPrompts,
    deleteAiPrompt,
    type ScoringModel,
    type AiPrompt
  } from '$lib/api/ai';
  import { getModules } from '$lib/api/modules';
  import { ScoringModelBuilder } from '$lib/components/ai';
  import { Loader2, Plus, Edit, Trash2, Sparkles, Brain, MessageSquare, Settings } from 'lucide-svelte';

  let activeTab = 'settings';

  // Scoring Models
  let scoringModels: ScoringModel[] = [];
  let loadingModels = true;
  let editingModel: ScoringModel | null = null;
  let showModelBuilder = false;
  let modules: Array<{ api_name: string; label: string }> = [];

  // Prompts
  let prompts: AiPrompt[] = [];
  let loadingPrompts = true;

  onMount(async () => {
    await Promise.all([
      loadModules(),
      loadScoringModels(),
      loadPrompts()
    ]);
  });

  async function loadModules() {
    try {
      const result = await getModules();
      modules = result.map((m) => ({
        api_name: m.api_name,
        label: m.name
      }));
    } catch (error) {
      console.error('Failed to load modules:', error);
    }
  }

  async function loadScoringModels() {
    loadingModels = true;
    try {
      const result = await getScoringModels();
      scoringModels = result.models;
    } catch (error) {
      console.error('Failed to load scoring models:', error);
    } finally {
      loadingModels = false;
    }
  }

  async function loadPrompts() {
    loadingPrompts = true;
    try {
      const result = await getAiPrompts();
      prompts = result.prompts;
    } catch (error) {
      console.error('Failed to load prompts:', error);
    } finally {
      loadingPrompts = false;
    }
  }

  async function handleDeleteModel(id: number) {
    if (!confirm('Are you sure you want to delete this scoring model?')) return;

    try {
      await deleteScoringModel(id);
      await loadScoringModels();
    } catch (error) {
      console.error('Failed to delete model:', error);
    }
  }

  async function handleDeletePrompt(id: number) {
    if (!confirm('Are you sure you want to delete this prompt?')) return;

    try {
      await deleteAiPrompt(id);
      await loadPrompts();
    } catch (error) {
      console.error('Failed to delete prompt:', error);
    }
  }

  function handleModelSaved() {
    showModelBuilder = false;
    editingModel = null;
    loadScoringModels();
  }
</script>

<svelte:head>
  <title>AI Settings - VRTX</title>
</svelte:head>

<div class="container py-6 max-w-6xl">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold flex items-center gap-2">
        <Sparkles class="h-6 w-6" />
        AI & Machine Learning
      </h1>
      <p class="text-muted-foreground">
        Configure AI features, scoring models, and prompts
      </p>
    </div>
  </div>

  <Tabs.Root value={activeTab} onValueChange={(v) => (activeTab = v)}>
    <Tabs.List class="mb-6">
      <Tabs.Trigger value="settings" class="flex items-center gap-2">
        <Settings class="h-4 w-4" />
        Settings
      </Tabs.Trigger>
      <Tabs.Trigger value="scoring" class="flex items-center gap-2">
        <Brain class="h-4 w-4" />
        Lead Scoring
      </Tabs.Trigger>
      <Tabs.Trigger value="prompts" class="flex items-center gap-2">
        <MessageSquare class="h-4 w-4" />
        Prompts
      </Tabs.Trigger>
    </Tabs.List>

    <!-- Settings Tab -->
    <Tabs.Content value="settings">
      <AiSettingsPanel />
    </Tabs.Content>

    <!-- Scoring Tab -->
    <Tabs.Content value="scoring">
      {#if showModelBuilder || editingModel}
        <ScoringModelBuilder
          model={editingModel}
          {modules}
          onSave={handleModelSaved}
          onCancel={() => {
            showModelBuilder = false;
            editingModel = null;
          }}
        />
      {:else}
        <div class="space-y-4">
          <div class="flex justify-end">
            <Button onclick={() => (showModelBuilder = true)}>
              <Plus class="h-4 w-4 mr-2" />
              New Scoring Model
            </Button>
          </div>

          {#if loadingModels}
            <div class="flex items-center justify-center py-12">
              <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          {:else if scoringModels.length === 0}
            <Card.Root>
              <Card.Content class="py-12 text-center">
                <Brain class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
                <h3 class="text-lg font-medium mb-2">No Scoring Models</h3>
                <p class="text-muted-foreground mb-4">
                  Create a scoring model to automatically score your leads and contacts
                </p>
                <Button onclick={() => (showModelBuilder = true)}>
                  <Plus class="h-4 w-4 mr-2" />
                  Create First Model
                </Button>
              </Card.Content>
            </Card.Root>
          {:else}
            <div class="grid gap-4">
              {#each scoringModels as model}
                <Card.Root>
                  <Card.Content class="py-4">
                    <div class="flex items-center justify-between">
                      <div>
                        <div class="flex items-center gap-2">
                          <h3 class="font-medium">{model.name}</h3>
                          <Badge variant={model.is_active ? 'default' : 'secondary'}>
                            {model.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                          Module: {modules.find(m => m.api_name === model.module_api_name)?.label || model.module_api_name}
                          {' '}&bull;{' '}
                          {model.factors.length} factors
                        </p>
                        {#if model.description}
                          <p class="text-sm text-muted-foreground mt-1">{model.description}</p>
                        {/if}
                      </div>
                      <div class="flex items-center gap-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          onclick={() => (editingModel = model)}
                        >
                          <Edit class="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="text-red-500 hover:text-red-600"
                          onclick={() => handleDeleteModel(model.id)}
                        >
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </Card.Content>
                </Card.Root>
              {/each}
            </div>
          {/if}
        </div>
      {/if}
    </Tabs.Content>

    <!-- Prompts Tab -->
    <Tabs.Content value="prompts">
      <div class="space-y-4">
        <div class="flex justify-end">
          <Button disabled>
            <Plus class="h-4 w-4 mr-2" />
            New Prompt
          </Button>
        </div>

        {#if loadingPrompts}
          <div class="flex items-center justify-center py-12">
            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
          </div>
        {:else if prompts.length === 0}
          <Card.Root>
            <Card.Content class="py-12 text-center">
              <MessageSquare class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
              <h3 class="text-lg font-medium mb-2">No Custom Prompts</h3>
              <p class="text-muted-foreground mb-4">
                Create custom prompts to tailor AI responses for your workflow
              </p>
              <Button disabled>
                <Plus class="h-4 w-4 mr-2" />
                Create First Prompt
              </Button>
            </Card.Content>
          </Card.Root>
        {:else}
          <div class="grid gap-4">
            {#each prompts as prompt}
              <Card.Root>
                <Card.Content class="py-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="font-medium">{prompt.name}</h3>
                        <Badge variant="outline">{prompt.category}</Badge>
                        <Badge variant={prompt.is_active ? 'default' : 'secondary'}>
                          {prompt.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </div>
                      <p class="text-sm text-muted-foreground mt-1">
                        Slug: {prompt.slug}
                        {#if prompt.variables?.length}
                          {' '}&bull;{' '}
                          Variables: {prompt.variables.join(', ')}
                        {/if}
                      </p>
                    </div>
                    <div class="flex items-center gap-2">
                      <Button variant="ghost" size="sm" disabled>
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        class="text-red-500 hover:text-red-600"
                        onclick={() => handleDeletePrompt(prompt.id)}
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </Card.Content>
              </Card.Root>
            {/each}
          </div>
        {/if}
      </div>
    </Tabs.Content>
  </Tabs.Root>
</div>
