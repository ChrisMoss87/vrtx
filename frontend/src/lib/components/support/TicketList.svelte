<script lang="ts">
  import { onMount } from 'svelte';
  import { ticketsApi, type SupportTicket } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Table from '$lib/components/ui/table';
  import * as Select from '$lib/components/ui/select';
  import { Search, Clock, AlertTriangle, CheckCircle, CircleDot, User, Filter } from 'lucide-svelte';

  interface Props {
    onSelectTicket?: (ticket: SupportTicket) => void;
  }

  let { onSelectTicket }: Props = $props();

  let tickets = $state<SupportTicket[]>([]);
  let loading = $state(true);
  let searchQuery = $state('');
  let statusFilter = $state<string | undefined>();
  let assigneeFilter = $state<string | undefined>();
  let currentPage = $state(1);
  let totalPages = $state(1);

  const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'open', label: 'Open' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'resolved', label: 'Resolved' },
    { value: 'closed', label: 'Closed' },
  ];

  const assigneeOptions = [
    { value: '', label: 'All Assignees' },
    { value: 'me', label: 'Assigned to Me' },
    { value: 'unassigned', label: 'Unassigned' },
  ];

  async function loadTickets() {
    loading = true;
    try {
      const response = await ticketsApi.list({
        search: searchQuery || undefined,
        status: statusFilter || undefined,
        assigned_to: assigneeFilter || undefined,
        page: currentPage,
        per_page: 20,
      });
      tickets = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load tickets:', error);
    } finally {
      loading = false;
    }
  }

  function getStatusBadge(status: string) {
    switch (status) {
      case 'open':
        return { variant: 'default' as const, class: 'bg-blue-500', icon: CircleDot };
      case 'pending':
        return { variant: 'secondary' as const, class: '', icon: Clock };
      case 'in_progress':
        return { variant: 'default' as const, class: 'bg-yellow-500', icon: Clock };
      case 'resolved':
        return { variant: 'default' as const, class: 'bg-green-500', icon: CheckCircle };
      case 'closed':
        return { variant: 'outline' as const, class: '', icon: CheckCircle };
      default:
        return { variant: 'secondary' as const, class: '', icon: CircleDot };
    }
  }

  function getPriorityBadge(priority: number) {
    switch (priority) {
      case 4:
        return { label: 'Urgent', class: 'bg-red-500 text-white' };
      case 3:
        return { label: 'High', class: 'bg-orange-500 text-white' };
      case 2:
        return { label: 'Medium', class: 'bg-yellow-500 text-black' };
      default:
        return { label: 'Low', class: 'bg-gray-400 text-white' };
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  onMount(() => {
    loadTickets();
  });

  $effect(() => {
    if (searchQuery !== undefined) {
      const timeout = setTimeout(() => {
        currentPage = 1;
        loadTickets();
      }, 300);
      return () => clearTimeout(timeout);
    }
  });

</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between gap-4">
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Search tickets..."
          bind:value={searchQuery}
          class="pl-9"
        />
      </div>
      <div class="flex items-center gap-2">
        <Select.Root
          type="single"
          value={statusFilter}
          onValueChange={(val) => { statusFilter = val; currentPage = 1; loadTickets(); }}
        >
          <Select.Trigger class="w-[150px]">
            {statusOptions.find(o => o.value === statusFilter)?.label || 'Status'}
          </Select.Trigger>
          <Select.Content>
            {#each statusOptions as option}
              <Select.Item value={option.value}>
                {option.label}
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>

        <Select.Root
          type="single"
          value={assigneeFilter}
          onValueChange={(val) => { assigneeFilter = val; currentPage = 1; loadTickets(); }}
        >
          <Select.Trigger class="w-[150px]">
            {assigneeOptions.find(o => o.value === assigneeFilter)?.label || 'Assignee'}
          </Select.Trigger>
          <Select.Content>
            {#each assigneeOptions as option}
              <Select.Item value={option.value}>
                {option.label}
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if tickets.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        No tickets found
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head class="w-[100px]">Ticket #</Table.Head>
            <Table.Head>Subject</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head>Priority</Table.Head>
            <Table.Head>Assignee</Table.Head>
            <Table.Head>Created</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each tickets as ticket}
            {@const statusBadge = getStatusBadge(ticket.status)}
            {@const priorityBadge = getPriorityBadge(ticket.priority)}
            {@const StatusIcon = statusBadge.icon}
            <Table.Row
              class="cursor-pointer hover:bg-muted/50"
              onclick={() => onSelectTicket?.(ticket)}
            >
              <Table.Cell class="font-mono text-sm">
                <div class="flex items-center gap-2">
                  {#if ticket.sla_response_breached || ticket.sla_resolution_breached}
                    <AlertTriangle class="h-4 w-4 text-red-500" />
                  {/if}
                  {ticket.ticket_number}
                </div>
              </Table.Cell>
              <Table.Cell>
                <div>
                  <div class="font-medium truncate max-w-[300px]">{ticket.subject}</div>
                  <div class="text-sm text-muted-foreground">
                    {ticket.portal_user?.name || 'Unknown'}
                  </div>
                </div>
              </Table.Cell>
              <Table.Cell>
                <Badge variant={statusBadge.variant} class={statusBadge.class}>
                  <StatusIcon class="mr-1 h-3 w-3" />
                  {ticket.status.replace('_', ' ')}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <Badge variant="outline" class={priorityBadge.class}>
                  {priorityBadge.label}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                {#if ticket.assignee}
                  <div class="flex items-center gap-2">
                    <User class="h-4 w-4 text-muted-foreground" />
                    <span>{ticket.assignee.name}</span>
                  </div>
                {:else}
                  <span class="text-muted-foreground">Unassigned</span>
                {/if}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {formatDate(ticket.created_at)}
              </Table.Cell>
              <Table.Cell>
                {#if ticket.category}
                  <div
                    class="w-3 h-3 rounded-full"
                    style="background-color: {ticket.category.color}"
                    title={ticket.category.name}
                  ></div>
                {/if}
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
            onclick={() => { currentPage--; loadTickets(); }}
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
            onclick={() => { currentPage++; loadTickets(); }}
          >
            Next
          </Button>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
