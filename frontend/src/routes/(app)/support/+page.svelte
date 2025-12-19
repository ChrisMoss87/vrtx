<script lang="ts">
  import { TicketList, TicketDetail, TicketStats, CreateTicketModal } from '$lib/components/support';
  import { Button } from '$lib/components/ui/button';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Plus, Inbox, Clock, CheckCircle } from 'lucide-svelte';
  import type { SupportTicket } from '$lib/api/support';

  let selectedTicket = $state<SupportTicket | null>(null);
  let showCreateModal = $state(false);
  let activeTab = $state('all');

  function handleSelectTicket(ticket: SupportTicket) {
    selectedTicket = ticket;
  }

  function handleBack() {
    selectedTicket = null;
  }

  function handleTicketCreated() {
    // Refresh will happen via the TicketList component
  }
</script>

<svelte:head>
  <title>Support Tickets | VRTX</title>
</svelte:head>

<div class="container mx-auto py-6 space-y-6">
  {#if selectedTicket}
    <TicketDetail ticketId={selectedTicket.id} onBack={handleBack} />
  {:else}
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Support Tickets</h1>
        <p class="text-muted-foreground">Manage customer support requests</p>
      </div>
      <Button onclick={() => showCreateModal = true}>
        <Plus class="mr-2 h-4 w-4" />
        New Ticket
      </Button>
    </div>

    <!-- Stats -->
    <TicketStats />

    <!-- Tabs and List -->
    <Tabs.Root bind:value={activeTab}>
      <Tabs.List>
        <Tabs.Trigger value="all">
          <Inbox class="mr-2 h-4 w-4" />
          All Tickets
        </Tabs.Trigger>
        <Tabs.Trigger value="open">
          <Clock class="mr-2 h-4 w-4" />
          Open
        </Tabs.Trigger>
        <Tabs.Trigger value="resolved">
          <CheckCircle class="mr-2 h-4 w-4" />
          Resolved
        </Tabs.Trigger>
      </Tabs.List>

      <div class="mt-4">
        <Tabs.Content value="all">
          <TicketList onSelectTicket={handleSelectTicket} />
        </Tabs.Content>
        <Tabs.Content value="open">
          <TicketList onSelectTicket={handleSelectTicket} />
        </Tabs.Content>
        <Tabs.Content value="resolved">
          <TicketList onSelectTicket={handleSelectTicket} />
        </Tabs.Content>
      </div>
    </Tabs.Root>
  {/if}
</div>

<CreateTicketModal
  bind:open={showCreateModal}
  onClose={() => showCreateModal = false}
  onCreated={handleTicketCreated}
/>
