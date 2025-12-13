<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Tabs from '$lib/components/ui/tabs';
  import * as Select from '$lib/components/ui/select';
  import type { ApprovalRequest } from '$lib/api/approvals';
  import ApprovalRequestCard from './ApprovalRequestCard.svelte';

  export let requests: ApprovalRequest[] = [];
  export let loading = false;

  const dispatch = createEventDispatcher<{
    approve: number;
    reject: number;
    delegate: number;
    view: number;
  }>();

  let search = '';
  let statusFilter = 'pending';
  let entityFilter = '';

  const entityTypes = [
    { value: '', label: 'All Types' },
    { value: 'quote', label: 'Quotes' },
    { value: 'discount', label: 'Discounts' },
    { value: 'contract', label: 'Contracts' },
    { value: 'deal', label: 'Deals' },
  ];

  $: pendingCount = requests.filter(r => r.status === 'pending').length;
  $: approvedCount = requests.filter(r => r.status === 'approved').length;
  $: rejectedCount = requests.filter(r => r.status === 'rejected').length;

  $: filteredRequests = requests.filter(r => {
    const matchesSearch = !search ||
      r.title?.toLowerCase().includes(search.toLowerCase()) ||
      r.requester?.name?.toLowerCase().includes(search.toLowerCase());
    const matchesStatus = !statusFilter || r.status === statusFilter;
    const matchesEntity = !entityFilter || r.entity_type === entityFilter;
    return matchesSearch && matchesStatus && matchesEntity;
  });
</script>

<div class="space-y-4">
  <!-- Summary Cards -->
  <div class="grid grid-cols-3 gap-4">
    <div class="p-4 rounded-lg border bg-yellow-50 border-yellow-200">
      <div class="text-2xl font-bold text-yellow-800">{pendingCount}</div>
      <div class="text-sm text-yellow-700">Pending Approval</div>
    </div>
    <div class="p-4 rounded-lg border bg-green-50 border-green-200">
      <div class="text-2xl font-bold text-green-800">{approvedCount}</div>
      <div class="text-sm text-green-700">Approved</div>
    </div>
    <div class="p-4 rounded-lg border bg-red-50 border-red-200">
      <div class="text-2xl font-bold text-red-800">{rejectedCount}</div>
      <div class="text-sm text-red-700">Rejected</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="flex gap-4 items-center">
    <Input
      bind:value={search}
      placeholder="Search approvals..."
      class="w-64"
    />
    <Select.Root
      selected={{ value: entityFilter, label: entityTypes.find(e => e.value === entityFilter)?.label || 'All Types' }}
      onSelectedChange={(v) => entityFilter = v?.value || ''}
    >
      <Select.Trigger class="w-40">
        <Select.Value placeholder="Type" />
      </Select.Trigger>
      <Select.Content>
        {#each entityTypes as type}
          <Select.Item value={type.value}>{type.label}</Select.Item>
        {/each}
      </Select.Content>
    </Select.Root>
  </div>

  <!-- Tabs -->
  <Tabs.Root bind:value={statusFilter}>
    <Tabs.List>
      <Tabs.Trigger value="pending">
        Pending
        {#if pendingCount > 0}
          <Badge class="ml-2 bg-yellow-500">{pendingCount}</Badge>
        {/if}
      </Tabs.Trigger>
      <Tabs.Trigger value="approved">Approved</Tabs.Trigger>
      <Tabs.Trigger value="rejected">Rejected</Tabs.Trigger>
      <Tabs.Trigger value="">All</Tabs.Trigger>
    </Tabs.List>
  </Tabs.Root>

  <!-- Request List -->
  {#if loading}
    <div class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
    </div>
  {:else if filteredRequests.length === 0}
    <div class="text-center py-12">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 11l3 3L22 4" />
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
      </svg>
      <p class="text-muted-foreground">
        {#if statusFilter === 'pending'}
          No pending approvals
        {:else}
          No approvals found
        {/if}
      </p>
    </div>
  {:else}
    <div class="grid gap-4 md:grid-cols-2">
      {#each filteredRequests as request}
        <ApprovalRequestCard
          {request}
          showActions={request.status === 'pending'}
          on:approve={() => dispatch('approve', request.id)}
          on:reject={() => dispatch('reject', request.id)}
          on:delegate={() => dispatch('delegate', request.id)}
          on:view={() => dispatch('view', request.id)}
        />
      {/each}
    </div>
  {/if}
</div>
