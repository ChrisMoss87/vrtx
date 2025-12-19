<script lang="ts">
  import { onMount } from 'svelte';
  import { contractsApi, type Contract } from '$lib/api/renewals';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import * as Select from '$lib/components/ui/select';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import { Progress } from '$lib/components/ui/progress';
  import {
    Search,
    Plus,
    Eye,
    FileText,
    Clock,
    AlertTriangle,
    CheckCircle,
    XCircle,
  } from 'lucide-svelte';

  interface Props {
    onSelect?: (contract: Contract) => void;
    onCreate?: () => void;
  }

  let { onSelect, onCreate }: Props = $props();

  let contracts = $state<Contract[]>([]);
  let loading = $state(true);
  let searchQuery = $state('');
  let statusFilter = $state<string | undefined>(undefined);
  let currentPage = $state(1);
  let totalPages = $state(1);

  async function loadContracts() {
    loading = true;
    try {
      const response = await contractsApi.list({
        status: statusFilter,
        search: searchQuery || undefined,
        page: currentPage,
      });
      contracts = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load contracts:', error);
    } finally {
      loading = false;
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
      month: 'short',
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
    loadContracts();
  });

  $effect(() => {
    if (searchQuery !== undefined || statusFilter !== undefined) {
      const timeout = setTimeout(() => {
        currentPage = 1;
        loadContracts();
      }, 300);
      return () => clearTimeout(timeout);
    }
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between gap-4">
      <div class="flex items-center gap-4 flex-1">
        <div class="relative flex-1 max-w-md">
          <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            type="text"
            placeholder="Search contracts..."
            bind:value={searchQuery}
            class="pl-9"
          />
        </div>
        <Select.Root type="single" value={statusFilter ?? ''} onValueChange={(val) => { statusFilter = val || undefined; }}>
          <Select.Trigger class="w-[150px]">
            <span>{statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : 'All Status'}</span>
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="">All Status</Select.Item>
            <Select.Item value="active">Active</Select.Item>
            <Select.Item value="draft">Draft</Select.Item>
            <Select.Item value="pending">Pending</Select.Item>
            <Select.Item value="expired">Expired</Select.Item>
            <Select.Item value="cancelled">Cancelled</Select.Item>
          </Select.Content>
        </Select.Root>
      </div>
      {#if onCreate}
        <Button onclick={onCreate}>
          <Plus class="mr-2 h-4 w-4" />
          New Contract
        </Button>
      {/if}
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if contracts.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        {searchQuery || statusFilter ? 'No contracts found' : 'No contracts yet'}
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Contract</Table.Head>
            <Table.Head>Value</Table.Head>
            <Table.Head>Start Date</Table.Head>
            <Table.Head>End Date</Table.Head>
            <Table.Head>Days Left</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each contracts as contract}
            {@const statusInfo = getStatusBadge(contract.status)}
            {@const StatusIcon = statusInfo.icon}
            {@const daysLeft = getDaysUntilExpiry(contract.end_date)}
            <Table.Row class="cursor-pointer hover:bg-muted/50" onclick={() => onSelect?.(contract)}>
              <Table.Cell>
                <div>
                  <div class="font-medium">{contract.name}</div>
                  <div class="text-sm text-muted-foreground">{contract.contract_number}</div>
                </div>
              </Table.Cell>
              <Table.Cell>
                <div class="font-medium">{formatCurrency(contract.value, contract.currency)}</div>
                {#if contract.billing_frequency}
                  <div class="text-xs text-muted-foreground">{contract.billing_frequency}</div>
                {/if}
              </Table.Cell>
              <Table.Cell>{formatDate(contract.start_date)}</Table.Cell>
              <Table.Cell>{formatDate(contract.end_date)}</Table.Cell>
              <Table.Cell>
                {#if contract.status === 'active'}
                  {#if daysLeft <= 0}
                    <Badge variant="destructive">Expired</Badge>
                  {:else if daysLeft <= 30}
                    <Badge variant="destructive">{daysLeft} days</Badge>
                  {:else if daysLeft <= 90}
                    <Badge variant="outline" class="border-yellow-500 text-yellow-600">{daysLeft} days</Badge>
                  {:else}
                    <span class="text-muted-foreground">{daysLeft} days</span>
                  {/if}
                {:else}
                  <span class="text-muted-foreground">-</span>
                {/if}
              </Table.Cell>
              <Table.Cell>
                <Badge variant={statusInfo.variant}>
                  <StatusIcon class="mr-1 h-3 w-3" />
                  {statusInfo.label}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <Button variant="ghost" size="icon" onclick={(e) => { e.stopPropagation(); onSelect?.(contract); }}>
                  <Eye class="h-4 w-4" />
                </Button>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>

      {#if totalPages > 1}
        <div class="mt-4 flex items-center justify-center gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage === 1}
            onclick={() => { currentPage--; loadContracts(); }}
          >
            Previous
          </Button>
          <span class="text-sm text-muted-foreground">
            Page {currentPage} of {totalPages}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={currentPage === totalPages}
            onclick={() => { currentPage++; loadContracts(); }}
          >
            Next
          </Button>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
