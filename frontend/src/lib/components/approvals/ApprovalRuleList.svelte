<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';
  import * as Table from '$lib/components/ui/table';
  import type { ApprovalRule } from '$lib/api/approvals';

  export let rules: ApprovalRule[] = [];
  export let loading = false;

  const dispatch = createEventDispatcher<{
    create: void;
    edit: number;
    duplicate: number;
    delete: number;
    toggle: number;
  }>();

  let search = '';

  const entityLabels: Record<string, string> = {
    quote: 'Quote',
    discount: 'Discount',
    contract: 'Contract',
    deal: 'Deal',
    expense: 'Expense',
    custom: 'Custom',
  };

  $: filteredRules = rules.filter(r => {
    return !search ||
      r.name.toLowerCase().includes(search.toLowerCase()) ||
      r.description?.toLowerCase().includes(search.toLowerCase());
  });

  function formatConditions(rule: ApprovalRule): string {
    if (!rule.conditions || rule.conditions.length === 0) {
      return 'Always applies';
    }
    return rule.conditions.map(c => `${c.field} ${c.operator} ${c.value}`).join(' AND ');
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <Input
      bind:value={search}
      placeholder="Search rules..."
      class="w-64"
    />
    <Button on:click={() => dispatch('create')}>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      New Rule
    </Button>
  </div>

  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if filteredRules.length === 0}
    <Card.Root>
      <Card.Content class="py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        </svg>
        <p class="text-muted-foreground">No approval rules configured</p>
        <Button variant="outline" class="mt-4" on:click={() => dispatch('create')}>
          Create your first rule
        </Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="border rounded-lg overflow-hidden">
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Rule Name</Table.Head>
            <Table.Head>Applies To</Table.Head>
            <Table.Head>Conditions</Table.Head>
            <Table.Head>Steps</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each filteredRules as rule}
            <Table.Row>
              <Table.Cell>
                <div>
                  <p class="font-medium">{rule.name}</p>
                  {#if rule.description}
                    <p class="text-xs text-muted-foreground line-clamp-1">{rule.description}</p>
                  {/if}
                </div>
              </Table.Cell>
              <Table.Cell>
                <Badge variant="outline">{entityLabels[rule.entity_type] || rule.entity_type}</Badge>
              </Table.Cell>
              <Table.Cell>
                <span class="text-sm text-muted-foreground line-clamp-1">
                  {formatConditions(rule)}
                </span>
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  {#each rule.steps || [] as step, index}
                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center text-xs">
                      {index + 1}
                    </div>
                    {#if index < (rule.steps?.length || 0) - 1}
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                      </svg>
                    {/if}
                  {/each}
                </div>
              </Table.Cell>
              <Table.Cell>
                <Badge class={rule.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}>
                  {rule.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <DropdownMenu.Root>
                  <DropdownMenu.Trigger asChild let:builder>
                    <Button variant="ghost" size="sm" builders={[builder]}>
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1" />
                        <circle cx="12" cy="5" r="1" />
                        <circle cx="12" cy="19" r="1" />
                      </svg>
                    </Button>
                  </DropdownMenu.Trigger>
                  <DropdownMenu.Content align="end">
                    <DropdownMenu.Item on:click={() => dispatch('edit', rule.id)}>
                      Edit
                    </DropdownMenu.Item>
                    <DropdownMenu.Item on:click={() => dispatch('duplicate', rule.id)}>
                      Duplicate
                    </DropdownMenu.Item>
                    <DropdownMenu.Item on:click={() => dispatch('toggle', rule.id)}>
                      {rule.is_active ? 'Deactivate' : 'Activate'}
                    </DropdownMenu.Item>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Item class="text-destructive" on:click={() => dispatch('delete', rule.id)}>
                      Delete
                    </DropdownMenu.Item>
                  </DropdownMenu.Content>
                </DropdownMenu.Root>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  {/if}
</div>
