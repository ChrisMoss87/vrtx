<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import * as Card from '$lib/components/ui/card';
  import * as Accordion from '$lib/components/ui/accordion';
  import type { MergeFieldVariable } from '$lib/api/document-templates';

  export let variables: Record<string, MergeFieldVariable[]> = {};

  const dispatch = createEventDispatcher<{
    insert: string;
  }>();

  let search = '';

  $: filteredVariables = Object.fromEntries(
    Object.entries(variables).map(([category, vars]) => [
      category,
      vars.filter(v =>
        v.name.toLowerCase().includes(search.toLowerCase()) ||
        v.api_name.toLowerCase().includes(search.toLowerCase())
      ),
    ]).filter(([_, vars]) => vars.length > 0)
  );

  const categoryLabels: Record<string, string> = {
    contact: 'Contact Fields',
    company: 'Company Fields',
    deal: 'Deal Fields',
    user: 'User Fields',
    system: 'System Variables',
    custom: 'Custom Variables',
  };

  const formatLabels: Record<string, string> = {
    date: 'Date',
    date_long: 'Long Date',
    date_short: 'Short Date',
    currency: 'Currency',
    currency_eur: 'Currency (EUR)',
    currency_gbp: 'Currency (GBP)',
    number: 'Number',
    percent: 'Percent',
    uppercase: 'UPPERCASE',
    lowercase: 'lowercase',
    capitalize: 'Capitalize',
  };

  function handleInsert(apiName: string) {
    dispatch('insert', apiName);
  }
</script>

<Card.Root class="h-fit">
  <Card.Header class="py-3">
    <Card.Title class="text-sm">Merge Fields</Card.Title>
  </Card.Header>
  <Card.Content class="p-3 pt-0">
    <Input
      bind:value={search}
      placeholder="Search fields..."
      class="mb-3"
    />

    <div class="max-h-[400px] overflow-y-auto">
      <Accordion.Root type="multiple" class="w-full">
        {#each Object.entries(filteredVariables) as [category, vars]}
          <Accordion.Item value={category}>
            <Accordion.Trigger class="text-sm py-2">
              {categoryLabels[category] || category}
              <span class="ml-2 text-xs text-muted-foreground">({vars.length})</span>
            </Accordion.Trigger>
            <Accordion.Content>
              <div class="space-y-1">
                {#each vars as variable}
                  <button
                    type="button"
                    class="w-full text-left px-2 py-1.5 text-sm rounded hover:bg-muted transition-colors"
                    on:click={() => handleInsert(variable.api_name)}
                  >
                    <div class="flex justify-between items-center">
                      <span class="font-medium">{variable.name}</span>
                      {#if variable.format}
                        <span class="text-xs text-muted-foreground">{formatLabels[variable.format] || variable.format}</span>
                      {/if}
                    </div>
                    <code class="text-xs text-muted-foreground">{`{{${variable.api_name}}}`}</code>
                  </button>
                {/each}
              </div>
            </Accordion.Content>
          </Accordion.Item>
        {/each}
      </Accordion.Root>

      {#if Object.keys(filteredVariables).length === 0}
        <p class="text-center text-sm text-muted-foreground py-4">
          No fields found
        </p>
      {/if}
    </div>

    <div class="mt-4 pt-3 border-t">
      <p class="text-xs text-muted-foreground mb-2">Format Examples:</p>
      <div class="space-y-1 text-xs">
        <div><code class="bg-muted px-1 rounded">{`{{deal.amount|currency}}`}</code> - $1,234.56</div>
        <div><code class="bg-muted px-1 rounded">{`{{deal.close_date|date_long}}`}</code> - January 15, 2025</div>
        <div><code class="bg-muted px-1 rounded">{`{{contact.name|uppercase}}`}</code> - JOHN DOE</div>
      </div>
    </div>
  </Card.Content>
</Card.Root>
