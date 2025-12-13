<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
  import * as Select from '$lib/components/ui/select';
  import type { DocumentTemplate } from '$lib/api/document-templates';

  export let templates: DocumentTemplate[] = [];
  export let loading = false;

  const dispatch = createEventDispatcher<{
    create: void;
    edit: number;
    duplicate: number;
    delete: number;
    generate: { templateId: number };
  }>();

  let search = $state('');
  let categoryFilter = $state('');

  const categories = [
    { value: '', label: 'All Categories' },
    { value: 'contract', label: 'Contract' },
    { value: 'proposal', label: 'Proposal' },
    { value: 'letter', label: 'Letter' },
    { value: 'agreement', label: 'Agreement' },
    { value: 'quote', label: 'Quote' },
    { value: 'invoice', label: 'Invoice' },
    { value: 'other', label: 'Other' },
  ];

  const filteredTemplates = $derived(templates.filter(t => {
    const matchesSearch = !search ||
      t.name.toLowerCase().includes(search.toLowerCase()) ||
      t.description?.toLowerCase().includes(search.toLowerCase());
    const matchesCategory = !categoryFilter || t.category === categoryFilter;
    return matchesSearch && matchesCategory;
  }));

  function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString();
  }

  function getCategoryLabel(category: string | null): string {
    if (!category) return 'Uncategorized';
    const cat = categories.find(c => c.value === category);
    return cat?.label || category;
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div class="flex gap-4 items-center">
      <Input
        bind:value={search}
        placeholder="Search templates..."
        class="w-64"
      />
      <Select.Root type="single" bind:value={categoryFilter}>
        <Select.Trigger class="w-40">
          {categories.find(c => c.value === categoryFilter)?.label || 'All Categories'}
        </Select.Trigger>
        <Select.Content>
          {#each categories as cat}
            <Select.Item value={cat.value} label={cat.label}>{cat.label}</Select.Item>
          {/each}
        </Select.Content>
      </Select.Root>
    </div>

    <Button onclick={() => dispatch('create')}>
      Create Template
    </Button>
  </div>

  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if filteredTemplates.length === 0}
    <Card.Root>
      <Card.Content class="py-12 text-center">
        <p class="text-muted-foreground">No templates found</p>
        {#if search || categoryFilter}
          <p class="text-sm text-muted-foreground mt-2">Try adjusting your filters</p>
        {:else}
          <Button variant="outline" class="mt-4" onclick={() => dispatch('create')}>
            Create your first template
          </Button>
        {/if}
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      {#each filteredTemplates as template}
        <Card.Root class="hover:shadow-md transition-shadow">
          <Card.Header class="pb-2">
            <div class="flex justify-between items-start">
              <div class="space-y-1">
                <Card.Title class="text-lg">{template.name}</Card.Title>
                {#if template.description}
                  <Card.Description class="text-sm line-clamp-2">
                    {template.description}
                  </Card.Description>
                {/if}
              </div>
              <DropdownMenu.Root>
                <DropdownMenu.Trigger>
                  <Button variant="ghost" size="sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="1" />
                      <circle cx="12" cy="5" r="1" />
                      <circle cx="12" cy="19" r="1" />
                    </svg>
                  </Button>
                </DropdownMenu.Trigger>
                <DropdownMenu.Content align="end">
                  <DropdownMenu.Item onclick={() => dispatch('edit', template.id)}>
                    Edit
                  </DropdownMenu.Item>
                  <DropdownMenu.Item onclick={() => dispatch('duplicate', template.id)}>
                    Duplicate
                  </DropdownMenu.Item>
                  <DropdownMenu.Item onclick={() => dispatch('generate', { templateId: template.id })}>
                    Generate Document
                  </DropdownMenu.Item>
                  <DropdownMenu.Separator />
                  <DropdownMenu.Item class="text-destructive" onclick={() => dispatch('delete', template.id)}>
                    Delete
                  </DropdownMenu.Item>
                </DropdownMenu.Content>
              </DropdownMenu.Root>
            </div>
          </Card.Header>
          <Card.Content>
            <div class="flex flex-wrap gap-2 mb-3">
              {#if template.category}
                <Badge variant="secondary">{getCategoryLabel(template.category)}</Badge>
              {/if}
              <Badge variant="outline">{template.output_format.toUpperCase()}</Badge>
              {#if template.is_shared}
                <Badge variant="outline">Shared</Badge>
              {/if}
              {#if !template.is_active}
                <Badge variant="destructive">Inactive</Badge>
              {/if}
            </div>
            <div class="text-xs text-muted-foreground">
              <p>Version {template.version}</p>
              <p>Updated {formatDate(template.updated_at)}</p>
            </div>
          </Card.Content>
          <Card.Footer class="pt-0">
            <Button
              variant="outline"
              size="sm"
              class="w-full"
              onclick={() => dispatch('generate', { templateId: template.id })}
            >
              Generate Document
            </Button>
          </Card.Footer>
        </Card.Root>
      {/each}
    </div>
  {/if}
</div>
