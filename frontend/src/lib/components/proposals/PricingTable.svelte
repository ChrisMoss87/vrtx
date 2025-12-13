<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import * as Table from '$lib/components/ui/table';
  import * as Select from '$lib/components/ui/select';
  import type { ProposalPricingItem } from '$lib/api/proposals';

  export let items: ProposalPricingItem[] = [];

  const pricingTypes = [
    { value: 'one_time', label: 'One-time' },
    { value: 'recurring', label: 'Recurring' },
    { value: 'hourly', label: 'Hourly' },
    { value: 'optional', label: 'Optional Add-on' },
  ];

  function addItem() {
    items = [
      ...items,
      {
        id: 0,
        proposal_id: 0,
        name: '',
        description: null,
        quantity: 1,
        unit_price: 0,
        discount_percent: null,
        is_optional: false,
        is_selected: true,
        pricing_type: 'one_time',
        order: items.length,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      },
    ];
  }

  function removeItem(index: number) {
    items = items.filter((_, i) => i !== index);
    items = items.map((item, i) => ({ ...item, order: i }));
  }

  function calculateSubtotal(item: ProposalPricingItem): number {
    const subtotal = item.quantity * item.unit_price;
    const discount = item.discount_percent ? subtotal * (item.discount_percent / 100) : 0;
    return subtotal - discount;
  }

  function formatCurrency(amount: number): string {
    return amount.toLocaleString('en-US', { style: 'currency', currency: 'USD' });
  }
</script>

<div class="space-y-4">
  <div class="flex justify-between items-center">
    <div>
      <h4 class="font-medium">Pricing Items</h4>
      <p class="text-sm text-muted-foreground">Add products or services to your proposal</p>
    </div>
    <Button variant="outline" on:click={addItem}>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19" />
        <line x1="5" y1="12" x2="19" y2="12" />
      </svg>
      Add Item
    </Button>
  </div>

  {#if items.length === 0}
    <div class="border rounded-lg p-8 text-center">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="1" x2="12" y2="23" />
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
      </svg>
      <p class="text-muted-foreground">No pricing items yet</p>
      <Button variant="outline" class="mt-4" on:click={addItem}>
        Add your first item
      </Button>
    </div>
  {:else}
    <div class="border rounded-lg overflow-hidden">
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head class="w-[250px]">Item</Table.Head>
            <Table.Head>Type</Table.Head>
            <Table.Head class="text-right">Qty</Table.Head>
            <Table.Head class="text-right">Unit Price</Table.Head>
            <Table.Head class="text-right">Discount</Table.Head>
            <Table.Head class="text-right">Subtotal</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each items as item, index}
            <Table.Row class={item.is_optional ? 'bg-muted/50' : ''}>
              <Table.Cell>
                <div class="space-y-1">
                  <Input
                    bind:value={item.name}
                    placeholder="Item name"
                    class="h-8"
                  />
                  <Input
                    bind:value={item.description}
                    placeholder="Description (optional)"
                    class="h-7 text-xs"
                  />
                </div>
              </Table.Cell>
              <Table.Cell>
                <Select.Root
                  selected={{ value: item.pricing_type, label: pricingTypes.find(t => t.value === item.pricing_type)?.label || 'One-time' }}
                  onSelectedChange={(v) => item.pricing_type = v?.value || 'one_time'}
                >
                  <Select.Trigger class="w-28 h-8">
                    <Select.Value />
                  </Select.Trigger>
                  <Select.Content>
                    {#each pricingTypes as type}
                      <Select.Item value={type.value}>{type.label}</Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
              </Table.Cell>
              <Table.Cell class="text-right">
                <Input
                  type="number"
                  bind:value={item.quantity}
                  min="1"
                  class="w-16 h-8 text-right"
                />
              </Table.Cell>
              <Table.Cell class="text-right">
                <div class="relative">
                  <span class="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground">$</span>
                  <Input
                    type="number"
                    bind:value={item.unit_price}
                    min="0"
                    step="0.01"
                    class="w-24 h-8 text-right pl-6"
                  />
                </div>
              </Table.Cell>
              <Table.Cell class="text-right">
                <div class="relative">
                  <Input
                    type="number"
                    bind:value={item.discount_percent}
                    min="0"
                    max="100"
                    class="w-16 h-8 text-right pr-6"
                  />
                  <span class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground">%</span>
                </div>
              </Table.Cell>
              <Table.Cell class="text-right font-medium">
                {formatCurrency(calculateSubtotal(item))}
              </Table.Cell>
              <Table.Cell>
                <Button variant="ghost" size="sm" on:click={() => removeItem(index)}>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </Button>
              </Table.Cell>
            </Table.Row>
            {#if item.is_optional}
              <Table.Row>
                <Table.Cell colspan={7} class="py-1 text-xs text-muted-foreground bg-muted/50">
                  <label class="flex items-center gap-2">
                    <input type="checkbox" bind:checked={item.is_selected} class="rounded" />
                    Optional add-on - client can choose to include
                  </label>
                </Table.Cell>
              </Table.Row>
            {/if}
          {/each}
        </Table.Body>
      </Table.Root>
    </div>

    <div class="flex gap-4">
      <label class="flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          on:change={(e) => {
            if (e.currentTarget.checked && items.length > 0) {
              items[items.length - 1].is_optional = true;
              items = items;
            }
          }}
          class="rounded"
        />
        Make last item optional
      </label>
    </div>
  {/if}
</div>
