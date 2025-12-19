<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import * as Table from '$lib/components/ui/table';
  import {
    Phone, PhoneIncoming, PhoneOutgoing, PhoneMissed,
    Play, FileText, Clock, User, Search, Filter
  } from 'lucide-svelte';
  import { callApi, formatDuration, getCallStatusColor, getCallStatusLabel, type Call } from '$lib/api/calls';

  interface Props {
    contactId?: number;
    contactModule?: string;
    onSelectCall?: (call: Call) => void;
  }

  let { contactId, contactModule, onSelectCall }: Props = $props();

  let calls = $state<Call[]>([]);
  let isLoading = $state(true);
  let currentPage = $state(1);
  let totalPages = $state(1);
  let total = $state(0);

  // Filters
  let searchQuery = $state('');
  let directionFilter = $state<string | undefined>(undefined);
  let statusFilter = $state<string | undefined>(undefined);
  let dateFrom = $state('');
  let dateTo = $state('');

  const directionOptions = [
    { value: '', label: 'All Directions' },
    { value: 'inbound', label: 'Inbound' },
    { value: 'outbound', label: 'Outbound' },
  ];

  const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'completed', label: 'Completed' },
    { value: 'no_answer', label: 'No Answer' },
    { value: 'busy', label: 'Busy' },
    { value: 'voicemail', label: 'Voicemail' },
    { value: 'canceled', label: 'Canceled' },
  ];

  async function loadCalls() {
    try {
      isLoading = true;
      const params: Record<string, unknown> = {
        page: currentPage,
        per_page: 25,
      };

      if (contactId) params.contact_id = contactId;
      if (directionFilter) params.direction = directionFilter;
      if (statusFilter) params.status = statusFilter;
      if (dateFrom) params.date_from = dateFrom;
      if (dateTo) params.date_to = dateTo;

      const response = await callApi.list(params);
      calls = response.data;
      totalPages = response.meta.last_page;
      total = response.meta.total;
    } catch (error) {
      console.error('Failed to load calls:', error);
    } finally {
      isLoading = false;
    }
  }

  function getDirectionIcon(direction: string) {
    switch (direction) {
      case 'inbound':
        return PhoneIncoming;
      case 'outbound':
        return PhoneOutgoing;
      default:
        return Phone;
    }
  }

  function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString();
  }

  $effect(() => {
    loadCalls();
  });

  // Reload when filters change
  $effect(() => {
    directionFilter;
    statusFilter;
    dateFrom;
    dateTo;
    currentPage = 1;
    loadCalls();
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <div>
        <Card.Title>Call History</Card.Title>
        <Card.Description>{total} calls total</Card.Description>
      </div>
      <div class="flex items-center gap-2">
        <Select.Root type="single" name="direction" bind:value={directionFilter}>
          <Select.Trigger class="w-[140px]">
            <span>{directionOptions.find((d) => d.value === directionFilter)?.label || 'All Directions'}</span>
          </Select.Trigger>
          <Select.Content>
            {#each directionOptions as option}
              <Select.Item value={option.value}>{option.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>

        <Select.Root type="single" name="status" bind:value={statusFilter}>
          <Select.Trigger class="w-[140px]">
            <span>{statusOptions.find((s) => s.value === statusFilter)?.label || 'All Statuses'}</span>
          </Select.Trigger>
          <Select.Content>
            {#each statusOptions as option}
              <Select.Item value={option.value}>{option.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>

        <Input type="date" bind:value={dateFrom} class="w-[140px]" placeholder="From" />
        <Input type="date" bind:value={dateTo} class="w-[140px]" placeholder="To" />
      </div>
    </div>
  </Card.Header>
  <Card.Content>
    {#if isLoading}
      <div class="text-center py-8 text-muted-foreground">Loading calls...</div>
    {:else if calls.length === 0}
      <div class="text-center py-8">
        <Phone class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
        <h3 class="font-medium mb-2">No calls found</h3>
        <p class="text-sm text-muted-foreground">Call history will appear here.</p>
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Direction</Table.Head>
            <Table.Head>Contact</Table.Head>
            <Table.Head>Agent</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head>Duration</Table.Head>
            <Table.Head>Date</Table.Head>
            <Table.Head>Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each calls as call}
            {@const DirectionIcon = getDirectionIcon(call.direction)}
            <Table.Row class="cursor-pointer hover:bg-muted/50" onclick={() => onSelectCall?.(call)}>
              <Table.Cell>
                <div class="flex items-center gap-2">
                  <DirectionIcon class={`h-4 w-4 ${call.direction === 'inbound' ? 'text-blue-500' : 'text-green-500'}`} />
                  <span class="capitalize">{call.direction}</span>
                </div>
              </Table.Cell>
              <Table.Cell>
                <div>
                  <div class="font-medium">{call.direction === 'inbound' ? call.from_number : call.to_number}</div>
                  {#if call.contact_id}
                    <div class="text-sm text-muted-foreground">Linked to {call.contact_module}</div>
                  {/if}
                </div>
              </Table.Cell>
              <Table.Cell>
                {#if call.user}
                  <div class="flex items-center gap-2">
                    <User class="h-4 w-4 text-muted-foreground" />
                    {call.user.name}
                  </div>
                {:else}
                  <span class="text-muted-foreground">-</span>
                {/if}
              </Table.Cell>
              <Table.Cell>
                <Badge variant="outline" class={getCallStatusColor(call.status)}>
                  {getCallStatusLabel(call.status)}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  <Clock class="h-4 w-4 text-muted-foreground" />
                  {call.formatted_duration || formatDuration(call.duration_seconds)}
                </div>
              </Table.Cell>
              <Table.Cell>
                <div class="text-sm">{formatDate(call.started_at)}</div>
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  {#if call.has_recording}
                    <Button variant="ghost" size="icon" title="Play recording">
                      <Play class="h-4 w-4" />
                    </Button>
                  {/if}
                  {#if call.has_transcription}
                    <Button variant="ghost" size="icon" title="View transcription">
                      <FileText class="h-4 w-4" />
                    </Button>
                  {/if}
                </div>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>

      {#if totalPages > 1}
        <div class="flex items-center justify-between mt-4">
          <div class="text-sm text-muted-foreground">
            Page {currentPage} of {totalPages}
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === 1}
              onclick={() => { currentPage--; loadCalls(); }}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={currentPage === totalPages}
              onclick={() => { currentPage++; loadCalls(); }}
            >
              Next
            </Button>
          </div>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
