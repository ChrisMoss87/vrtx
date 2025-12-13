<script lang="ts">
  import { goto } from '$app/navigation';
  import * as Tabs from '$lib/components/ui/tabs';
  import { ContractList, RenewalPipeline, HealthScoreDashboard, RenewalForecast } from '$lib/components/renewals';
  import type { Contract, Renewal, CustomerHealthScore } from '$lib/api/renewals';

  let activeTab = $state('pipeline');

  function handleSelectContract(contract: Contract) {
    goto(`/renewals/contracts/${contract.id}`);
  }

  function handleCreateContract() {
    goto('/renewals/contracts/new');
  }

  function handleSelectRenewal(renewal: Renewal) {
    goto(`/renewals/${renewal.id}`);
  }

  function handleSelectCustomer(healthScore: CustomerHealthScore) {
    // Navigate to the related record's page
    goto(`/records/${healthScore.related_module}/${healthScore.related_id}`);
  }
</script>

<svelte:head>
  <title>Renewal Management | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  <div>
    <h1 class="text-3xl font-bold">Renewal Management</h1>
    <p class="text-muted-foreground">
      Track contracts, renewals, and customer health
    </p>
  </div>

  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="pipeline">Renewal Pipeline</Tabs.Trigger>
      <Tabs.Trigger value="contracts">Contracts</Tabs.Trigger>
      <Tabs.Trigger value="health">Customer Health</Tabs.Trigger>
      <Tabs.Trigger value="forecast">Forecast</Tabs.Trigger>
    </Tabs.List>

    <div class="mt-6">
      <Tabs.Content value="pipeline">
        <RenewalPipeline onSelectRenewal={handleSelectRenewal} />
      </Tabs.Content>

      <Tabs.Content value="contracts">
        <ContractList
          onSelect={handleSelectContract}
          onCreate={handleCreateContract}
        />
      </Tabs.Content>

      <Tabs.Content value="health">
        <HealthScoreDashboard onSelectCustomer={handleSelectCustomer} />
      </Tabs.Content>

      <Tabs.Content value="forecast">
        <RenewalForecast />
      </Tabs.Content>
    </div>
  </Tabs.Root>
</div>
