<script lang="ts">
  import { page } from '$app/stores';
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { contractsApi, renewalsApi, type Contract, type Renewal } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import {
    ArrowLeft,
    Edit,
    Trash2,
    FileText,
    Clock,
    CheckCircle,
    AlertTriangle,
    XCircle,
    User,
    Calendar,
    DollarSign,
    RefreshCw,
    Plus,
  } from 'lucide-svelte';

  let contractId = $derived(Number($page.params.id));
  let contract = $state<Contract | null>(null);
  let loading = $state(true);
  let creatingRenewal = $state(false);

  async function loadContract() {
    loading = true;
    try {
      const response = await contractsApi.get(contractId);
      contract = response.contract;
    } catch (error) {
      console.error('Failed to load contract:', error);
    } finally {
      loading = false;
    }
  }

  async function createRenewal() {
    if (!contract) return;

    creatingRenewal = true;
    try {
      const result = await renewalsApi.create({ contract_id: contract.id });
      goto(`/renewals/${result.renewal.id}`);
    } catch (error) {
      console.error('Failed to create renewal:', error);
      alert('Failed to create renewal. An active renewal may already exist.');
    } finally {
      creatingRenewal = false;
    }
  }

  async function deleteContract() {
    if (!contract) return;
    if (!confirm(`Delete contract "${contract.name}"? This cannot be undone.`)) return;

    try {
      await contractsApi.delete(contract.id);
      goto('/renewals?tab=contracts');
    } catch (error) {
      console.error('Failed to delete contract:', error);
      alert('Cannot delete contract with active renewals');
    }
  }

  function getStatusBadge(status: string) {
    switch (status) {
      case 'active':
        return { variant: 'default' as const, icon: CheckCircle, label: 'Active' };
      case 'draft':
        return { variant: 'secondary' as const, icon: FileText, label: 'Draft' };
      case 'pending':
        return { variant: 'outline' as const, icon: Clock, label: 'Pending' };
      case 'expired':
        return { variant: 'destructive' as const, icon: AlertTriangle, label: 'Expired' };
      case 'cancelled':
        return { variant: 'destructive' as const, icon: XCircle, label: 'Cancelled' };
      default:
        return { variant: 'outline' as const, icon: FileText, label: status };
    }
  }

  function formatCurrency(value: number, currency: string = 'USD'): string {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(value);
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    });
  }

  function getDaysUntilExpiry(endDate: string): number {
    const end = new Date(endDate);
    const now = new Date();
    return Math.floor((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
  }

  onMount(() => {
    loadContract();
  });
</script>

<svelte:head>
  <title>{contract?.name ?? 'Contract'} | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6">
  {#if loading}
    <div class="flex items-center justify-center py-8">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
    </div>
  {:else if contract}
    {@const statusInfo = getStatusBadge(contract.status)}
    {@const StatusIcon = statusInfo.icon}
    {@const daysLeft = getDaysUntilExpiry(contract.end_date)}

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Button variant="ghost" size="icon" onclick={() => goto('/renewals?tab=contracts')}>
            <ArrowLeft class="h-5 w-5" />
          </Button>
          <div>
            <h1 class="text-2xl font-semibold">{contract.name}</h1>
            <p class="text-muted-foreground">{contract.contract_number}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Badge variant={statusInfo.variant}>
            <StatusIcon class="mr-1 h-3 w-3" />
            {statusInfo.label}
          </Badge>
          {#if contract.status === 'active' && !contract.renewal_status}
            <Button variant="outline" onclick={createRenewal} disabled={creatingRenewal}>
              <Plus class="mr-2 h-4 w-4" />
              {creatingRenewal ? 'Creating...' : 'Create Renewal'}
            </Button>
          {/if}
          <Button variant="outline" size="icon" onclick={() => goto(`/renewals/contracts/${contract.id}/edit`)}>
            <Edit class="h-4 w-4" />
          </Button>
          <Button variant="destructive" size="icon" onclick={deleteContract}>
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Overview -->
          <Card.Root>
            <Card.Header>
              <Card.Title>Contract Overview</Card.Title>
            </Card.Header>
            <Card.Content>
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1">
                  <div class="text-sm text-muted-foreground">Type</div>
                  <div class="font-medium capitalize">{contract.type}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-sm text-muted-foreground">Related Record</div>
                  <div class="font-medium">
                    <a
                      href="/records/{contract.related_module}/{contract.related_id}"
                      class="text-primary hover:underline"
                    >
                      {contract.related_module} #{contract.related_id}
                    </a>
                  </div>
                </div>
                <div class="space-y-1">
                  <div class="text-sm text-muted-foreground">Contract Value</div>
                  <div class="font-medium text-lg">{formatCurrency(contract.value, contract.currency)}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-sm text-muted-foreground">Billing Frequency</div>
                  <div class="font-medium capitalize">{contract.billing_frequency ?? '-'}</div>
                </div>
                <div class="space-y-1">
                  <div class="text-sm text-muted-foreground">Auto Renew</div>
                  <div class="font-medium">{contract.auto_renew ? 'Yes' : 'No'}</div>
                </div>
                {#if contract.renewal_status}
                  <div class="space-y-1">
                    <div class="text-sm text-muted-foreground">Renewal Status</div>
                    <Badge variant="outline" class="capitalize">{contract.renewal_status}</Badge>
                  </div>
                {/if}
              </div>
            </Card.Content>
          </Card.Root>

          <!-- Line Items -->
          {#if contract.line_items && contract.line_items.length > 0}
            <Card.Root>
              <Card.Header>
                <Card.Title>Line Items</Card.Title>
              </Card.Header>
              <Card.Content>
                <Table.Root>
                  <Table.Header>
                    <Table.Row>
                      <Table.Head>Item</Table.Head>
                      <Table.Head class="text-right">Qty</Table.Head>
                      <Table.Head class="text-right">Unit Price</Table.Head>
                      <Table.Head class="text-right">Discount</Table.Head>
                      <Table.Head class="text-right">Total</Table.Head>
                    </Table.Row>
                  </Table.Header>
                  <Table.Body>
                    {#each contract.line_items as item}
                      <Table.Row>
                        <Table.Cell>
                          <div class="font-medium">{item.name}</div>
                          {#if item.description}
                            <div class="text-sm text-muted-foreground">{item.description}</div>
                          {/if}
                        </Table.Cell>
                        <Table.Cell class="text-right">{item.quantity}</Table.Cell>
                        <Table.Cell class="text-right">{formatCurrency(item.unit_price, contract.currency)}</Table.Cell>
                        <Table.Cell class="text-right">{item.discount_percent}%</Table.Cell>
                        <Table.Cell class="text-right font-medium">{formatCurrency(item.total, contract.currency)}</Table.Cell>
                      </Table.Row>
                    {/each}
                  </Table.Body>
                </Table.Root>
              </Card.Content>
            </Card.Root>
          {/if}

          <!-- Terms & Notes -->
          {#if contract.terms || contract.notes}
            <Card.Root>
              <Card.Header>
                <Card.Title>Additional Information</Card.Title>
              </Card.Header>
              <Card.Content class="space-y-4">
                {#if contract.terms}
                  <div>
                    <div class="text-sm font-medium mb-1">Terms & Conditions</div>
                    <div class="text-sm text-muted-foreground whitespace-pre-wrap">{contract.terms}</div>
                  </div>
                {/if}
                {#if contract.notes}
                  <div>
                    <div class="text-sm font-medium mb-1">Notes</div>
                    <div class="text-sm text-muted-foreground whitespace-pre-wrap">{contract.notes}</div>
                  </div>
                {/if}
              </Card.Content>
            </Card.Root>
          {/if}
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Contract Period -->
          <Card.Root>
            <Card.Header>
              <Card.Title class="flex items-center gap-2">
                <Calendar class="h-4 w-4" />
                Contract Period
              </Card.Title>
            </Card.Header>
            <Card.Content class="space-y-4">
              <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Start Date</div>
                <div class="font-medium">{formatDate(contract.start_date)}</div>
              </div>
              <div class="space-y-1">
                <div class="text-sm text-muted-foreground">End Date</div>
                <div class="font-medium">{formatDate(contract.end_date)}</div>
              </div>
              <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Days Remaining</div>
                {#if daysLeft <= 0}
                  <Badge variant="destructive">Expired</Badge>
                {:else if daysLeft <= 30}
                  <Badge variant="destructive">{daysLeft} days</Badge>
                {:else if daysLeft <= 90}
                  <Badge variant="outline" class="border-yellow-500 text-yellow-600">{daysLeft} days</Badge>
                {:else}
                  <div class="font-medium">{daysLeft} days</div>
                {/if}
              </div>
              <div class="space-y-1">
                <div class="text-sm text-muted-foreground">Renewal Notice</div>
                <div class="font-medium">{contract.renewal_notice_days} days before expiry</div>
              </div>
            </Card.Content>
          </Card.Root>

          <!-- Owner -->
          {#if contract.owner}
            <Card.Root>
              <Card.Header>
                <Card.Title class="flex items-center gap-2">
                  <User class="h-4 w-4" />
                  Owner
                </Card.Title>
              </Card.Header>
              <Card.Content>
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <User class="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <div class="font-medium">{contract.owner.name}</div>
                    <div class="text-sm text-muted-foreground">Contract Owner</div>
                  </div>
                </div>
              </Card.Content>
            </Card.Root>
          {/if}
        </div>
      </div>
    </div>
  {:else}
    <div class="text-center py-8 text-muted-foreground">
      Contract not found
    </div>
  {/if}
</div>
