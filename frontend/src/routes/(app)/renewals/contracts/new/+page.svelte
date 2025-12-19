<script lang="ts">
  import { goto } from '$app/navigation';
  import { contractsApi } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { ArrowLeft, Plus, Trash2 } from 'lucide-svelte';

  let saving = $state(false);
  let name = $state('');
  let type = $state('subscription');
  let relatedModule = $state('accounts');
  let relatedId = $state('');
  let value = $state('');
  let currency = $state('USD');
  let billingFrequency = $state('annual');
  let startDate = $state('');
  let endDate = $state('');
  let renewalNoticeDays = $state('30');
  let autoRenew = $state(false);
  let terms = $state('');
  let notes = $state('');
  let lineItems = $state<Array<{ name: string; quantity: string; unit_price: string; discount_percent: string }>>([]);

  function addLineItem() {
    lineItems = [...lineItems, { name: '', quantity: '1', unit_price: '', discount_percent: '0' }];
  }

  function removeLineItem(index: number) {
    lineItems = lineItems.filter((_, i) => i !== index);
  }

  async function handleSubmit(e: Event) {
    e.preventDefault();
    saving = true;

    try {
      const result = await contractsApi.create({
        name,
        type,
        related_module: relatedModule,
        related_id: parseInt(relatedId),
        value: parseFloat(value) || 0,
        currency,
        billing_frequency: billingFrequency,
        start_date: startDate,
        end_date: endDate,
        renewal_notice_days: parseInt(renewalNoticeDays) || 30,
        auto_renew: autoRenew,
        terms: terms || undefined,
        notes: notes || undefined,
        line_items: lineItems.map(item => ({
          name: item.name,
          quantity: parseFloat(item.quantity) || 1,
          unit_price: parseFloat(item.unit_price) || 0,
          discount_percent: parseFloat(item.discount_percent) || 0,
        })),
      });

      goto(`/renewals/contracts/${result.contract.id}`);
    } catch (error) {
      console.error('Failed to create contract:', error);
      alert('Failed to create contract');
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head>
  <title>New Contract | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 max-w-3xl">
  <div class="flex items-center gap-4 mb-6">
    <Button variant="ghost" size="icon" onclick={() => goto('/renewals?tab=contracts')}>
      <ArrowLeft class="h-5 w-5" />
    </Button>
    <div>
      <h1 class="text-2xl font-semibold">New Contract</h1>
      <p class="text-muted-foreground">Create a new customer contract</p>
    </div>
  </div>

  <form onsubmit={handleSubmit}>
    <div class="space-y-6">
      <Card.Root>
        <Card.Header>
          <Card.Title>Contract Details</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="name">Contract Name *</Label>
              <Input id="name" bind:value={name} required placeholder="e.g., Annual Subscription" />
            </div>

            <div class="space-y-2">
              <Label for="type">Contract Type</Label>
              <Select.Root type="single" value={type} onValueChange={(val) => { if (val) type = val; }}>
                <Select.Trigger>
                  <span>{type.charAt(0).toUpperCase() + type.slice(1)}</span>
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="subscription">Subscription</Select.Item>
                  <Select.Item value="license">License</Select.Item>
                  <Select.Item value="support">Support</Select.Item>
                  <Select.Item value="service">Service</Select.Item>
                  <Select.Item value="other">Other</Select.Item>
                </Select.Content>
              </Select.Root>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="relatedModule">Related Module *</Label>
              <Select.Root type="single" value={relatedModule} onValueChange={(val) => { if (val) relatedModule = val; }}>
                <Select.Trigger>
                  <span>{relatedModule.charAt(0).toUpperCase() + relatedModule.slice(1)}</span>
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="accounts">Accounts</Select.Item>
                  <Select.Item value="contacts">Contacts</Select.Item>
                  <Select.Item value="deals">Deals</Select.Item>
                </Select.Content>
              </Select.Root>
            </div>

            <div class="space-y-2">
              <Label for="relatedId">Related Record ID *</Label>
              <Input id="relatedId" type="number" bind:value={relatedId} required placeholder="Record ID" />
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <Card.Title>Financial Details</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="grid gap-4 md:grid-cols-3">
            <div class="space-y-2">
              <Label for="value">Contract Value</Label>
              <Input id="value" type="number" step="0.01" bind:value={value} placeholder="0.00" />
            </div>

            <div class="space-y-2">
              <Label for="currency">Currency</Label>
              <Select.Root type="single" value={currency} onValueChange={(val) => { if (val) currency = val; }}>
                <Select.Trigger>
                  <span>{currency}</span>
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="USD">USD</Select.Item>
                  <Select.Item value="EUR">EUR</Select.Item>
                  <Select.Item value="GBP">GBP</Select.Item>
                  <Select.Item value="AUD">AUD</Select.Item>
                  <Select.Item value="CAD">CAD</Select.Item>
                </Select.Content>
              </Select.Root>
            </div>

            <div class="space-y-2">
              <Label for="billingFrequency">Billing Frequency</Label>
              <Select.Root type="single" value={billingFrequency} onValueChange={(val) => { if (val) billingFrequency = val; }}>
                <Select.Trigger>
                  <span>{billingFrequency.charAt(0).toUpperCase() + billingFrequency.slice(1)}</span>
                </Select.Trigger>
                <Select.Content>
                  <Select.Item value="monthly">Monthly</Select.Item>
                  <Select.Item value="quarterly">Quarterly</Select.Item>
                  <Select.Item value="annual">Annual</Select.Item>
                  <Select.Item value="one_time">One Time</Select.Item>
                </Select.Content>
              </Select.Root>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <Card.Title>Contract Period</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="startDate">Start Date *</Label>
              <Input id="startDate" type="date" bind:value={startDate} required />
            </div>

            <div class="space-y-2">
              <Label for="endDate">End Date *</Label>
              <Input id="endDate" type="date" bind:value={endDate} required />
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label for="renewalNoticeDays">Renewal Notice (Days)</Label>
              <Input id="renewalNoticeDays" type="number" bind:value={renewalNoticeDays} placeholder="30" />
            </div>

            <div class="flex items-center space-x-2 pt-6">
              <Switch id="autoRenew" bind:checked={autoRenew} />
              <Label for="autoRenew">Auto-renew contract</Label>
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <div class="flex items-center justify-between">
            <Card.Title>Line Items</Card.Title>
            <Button type="button" variant="outline" size="sm" onclick={addLineItem}>
              <Plus class="mr-2 h-4 w-4" />
              Add Item
            </Button>
          </div>
        </Card.Header>
        <Card.Content>
          {#if lineItems.length === 0}
            <div class="text-center py-4 text-muted-foreground">
              No line items. Click "Add Item" to add products or services.
            </div>
          {:else}
            <div class="space-y-3">
              {#each lineItems as item, index}
                <div class="grid gap-4 grid-cols-12 items-end p-3 rounded-lg border">
                  <div class="col-span-4 space-y-2">
                    <Label>Name</Label>
                    <Input bind:value={item.name} placeholder="Product/Service name" />
                  </div>
                  <div class="col-span-2 space-y-2">
                    <Label>Quantity</Label>
                    <Input type="number" bind:value={item.quantity} placeholder="1" />
                  </div>
                  <div class="col-span-2 space-y-2">
                    <Label>Unit Price</Label>
                    <Input type="number" step="0.01" bind:value={item.unit_price} placeholder="0.00" />
                  </div>
                  <div class="col-span-2 space-y-2">
                    <Label>Discount %</Label>
                    <Input type="number" step="0.01" bind:value={item.discount_percent} placeholder="0" />
                  </div>
                  <div class="col-span-2">
                    <Button type="button" variant="ghost" size="icon" onclick={() => removeLineItem(index)}>
                      <Trash2 class="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              {/each}
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header>
          <Card.Title>Additional Information</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-4">
          <div class="space-y-2">
            <Label for="terms">Terms & Conditions</Label>
            <Textarea id="terms" bind:value={terms} rows={4} placeholder="Contract terms..." />
          </div>

          <div class="space-y-2">
            <Label for="notes">Notes</Label>
            <Textarea id="notes" bind:value={notes} rows={3} placeholder="Internal notes..." />
          </div>
        </Card.Content>
      </Card.Root>

      <div class="flex justify-end gap-3">
        <Button type="button" variant="outline" onclick={() => goto('/renewals?tab=contracts')}>
          Cancel
        </Button>
        <Button type="submit" disabled={saving}>
          {saving ? 'Creating...' : 'Create Contract'}
        </Button>
      </div>
    </div>
  </form>
</div>
