<script lang="ts">
  import { ticketsApi, type SupportTicket, type TicketReply } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { Label } from '$lib/components/ui/label';
  import { Separator } from '$lib/components/ui/separator';
  import {
    ArrowLeft,
    Send,
    User,
    Clock,
    AlertTriangle,
    CheckCircle,
    CircleDot,
    MessageSquare,
    Lock,
    MoreHorizontal,
  } from 'lucide-svelte';

  interface Props {
    ticketId: number;
    onBack: () => void;
  }

  let { ticketId, onBack }: Props = $props();

  let ticket = $state<SupportTicket | null>(null);
  let loading = $state(true);
  let replyContent = $state('');
  let isInternalNote = $state(false);
  let sendingReply = $state(false);

  async function loadTicket() {
    loading = true;
    try {
      const response = await ticketsApi.get(ticketId);
      ticket = response.ticket;
    } catch (error) {
      console.error('Failed to load ticket:', error);
    } finally {
      loading = false;
    }
  }

  async function sendReply() {
    if (!replyContent.trim() || !ticket) return;

    sendingReply = true;
    try {
      await ticketsApi.reply(ticket.id, {
        content: replyContent,
        is_internal: isInternalNote,
      });
      replyContent = '';
      isInternalNote = false;
      await loadTicket();
    } catch (error) {
      console.error('Failed to send reply:', error);
    } finally {
      sendingReply = false;
    }
  }

  async function updateStatus(status: string) {
    if (!ticket) return;
    try {
      if (status === 'resolved') {
        await ticketsApi.resolve(ticket.id);
      } else if (status === 'closed') {
        await ticketsApi.close(ticket.id);
      } else if (status === 'open' && (ticket.status === 'resolved' || ticket.status === 'closed')) {
        await ticketsApi.reopen(ticket.id);
      } else {
        await ticketsApi.update(ticket.id, { status } as Partial<SupportTicket>);
      }
      await loadTicket();
    } catch (error) {
      console.error('Failed to update status:', error);
    }
  }

  function getStatusBadge(status: string) {
    switch (status) {
      case 'open':
        return { variant: 'default' as const, class: 'bg-blue-500' };
      case 'pending':
        return { variant: 'secondary' as const, class: '' };
      case 'in_progress':
        return { variant: 'default' as const, class: 'bg-yellow-500' };
      case 'resolved':
        return { variant: 'default' as const, class: 'bg-green-500' };
      case 'closed':
        return { variant: 'outline' as const, class: '' };
      default:
        return { variant: 'secondary' as const, class: '' };
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
    return new Date(date).toLocaleString();
  }

  $effect(() => {
    if (ticketId) {
      loadTicket();
    }
  });
</script>

{#if loading}
  <div class="flex items-center justify-center py-8">
    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
  </div>
{:else if ticket}
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <Button variant="ghost" size="icon" onclick={onBack}>
        <ArrowLeft class="h-5 w-5" />
      </Button>
      <div class="flex-1">
        <div class="flex items-center gap-2">
          <span class="font-mono text-sm text-muted-foreground">{ticket.ticket_number}</span>
          {#if ticket.sla_response_breached || ticket.sla_resolution_breached}
            <Badge variant="destructive" class="text-xs">
              <AlertTriangle class="mr-1 h-3 w-3" />
              SLA Breached
            </Badge>
          {/if}
        </div>
        <h1 class="text-xl font-semibold">{ticket.subject}</h1>
      </div>
      {#if ticket}
        {@const statusBadge = getStatusBadge(ticket.status)}
        {@const priorityBadge = getPriorityBadge(ticket.priority)}
        <div class="flex items-center gap-2">
          <Badge variant={statusBadge.variant} class={statusBadge.class}>
            {ticket.status.replace('_', ' ')}
          </Badge>
          <Badge variant="outline" class={priorityBadge.class}>
            {priorityBadge.label}
          </Badge>
        </div>
      {/if}
    </div>

    <div class="grid gap-6 md:grid-cols-[1fr_300px]">
      <!-- Main Content -->
      <div class="space-y-6">
        <!-- Original Message -->
        <Card.Root>
          <Card.Content class="pt-6">
            <div class="flex items-start gap-4">
              <div class="rounded-full bg-primary/10 p-2">
                <User class="h-5 w-5 text-primary" />
              </div>
              <div class="flex-1">
                <div class="flex items-center justify-between">
                  <div>
                    <span class="font-medium">{ticket.portal_user?.name || 'Unknown'}</span>
                    <span class="text-sm text-muted-foreground ml-2">
                      {formatDate(ticket.created_at)}
                    </span>
                  </div>
                  <Badge variant="outline">{ticket.channel}</Badge>
                </div>
                <div class="mt-2 prose prose-sm max-w-none">
                  {ticket.description}
                </div>
              </div>
            </div>
          </Card.Content>
        </Card.Root>

        <!-- Replies -->
        {#if ticket.replies && ticket.replies.length > 0}
          <div class="space-y-4">
            {#each ticket.replies as reply}
              <Card.Root class={reply.is_internal ? 'bg-yellow-50 dark:bg-yellow-950/20' : ''}>
                <Card.Content class="pt-4">
                  <div class="flex items-start gap-4">
                    <div class="rounded-full bg-muted p-2">
                      {#if reply.is_internal}
                        <Lock class="h-5 w-5 text-yellow-600" />
                      {:else}
                        <MessageSquare class="h-5 w-5 text-muted-foreground" />
                      {/if}
                    </div>
                    <div class="flex-1">
                      <div class="flex items-center justify-between">
                        <div>
                          <span class="font-medium">
                            {reply.user?.name || reply.portal_user?.name || 'System'}
                          </span>
                          {#if reply.is_internal}
                            <Badge variant="outline" class="ml-2 text-yellow-600">Internal Note</Badge>
                          {/if}
                          <span class="text-sm text-muted-foreground ml-2">
                            {formatDate(reply.created_at)}
                          </span>
                        </div>
                      </div>
                      <div class="mt-2 prose prose-sm max-w-none">
                        {reply.content}
                      </div>
                    </div>
                  </div>
                </Card.Content>
              </Card.Root>
            {/each}
          </div>
        {/if}

        <!-- Reply Form -->
        <Card.Root>
          <Card.Content class="pt-6">
            <div class="space-y-4">
              <Textarea
                bind:value={replyContent}
                placeholder="Type your reply..."
                rows={4}
              />
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <Switch id="internal" bind:checked={isInternalNote} />
                  <Label for="internal" class="text-sm">Internal note (not visible to customer)</Label>
                </div>
                <Button onclick={sendReply} disabled={!replyContent.trim() || sendingReply}>
                  <Send class="mr-2 h-4 w-4" />
                  {sendingReply ? 'Sending...' : isInternalNote ? 'Add Note' : 'Send Reply'}
                </Button>
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <!-- Actions -->
        <Card.Root>
          <Card.Header>
            <Card.Title class="text-sm">Actions</Card.Title>
          </Card.Header>
          <Card.Content class="space-y-3">
            {#if ticket.status !== 'resolved' && ticket.status !== 'closed'}
              <Button class="w-full" variant="outline" onclick={() => updateStatus('resolved')}>
                <CheckCircle class="mr-2 h-4 w-4" />
                Resolve Ticket
              </Button>
            {/if}
            {#if ticket.status === 'resolved'}
              <Button class="w-full" variant="outline" onclick={() => updateStatus('closed')}>
                Close Ticket
              </Button>
            {/if}
            {#if ticket.status === 'resolved' || ticket.status === 'closed'}
              <Button class="w-full" variant="outline" onclick={() => updateStatus('open')}>
                Reopen Ticket
              </Button>
            {/if}
          </Card.Content>
        </Card.Root>

        <!-- Details -->
        <Card.Root>
          <Card.Header>
            <Card.Title class="text-sm">Details</Card.Title>
          </Card.Header>
          <Card.Content class="space-y-4 text-sm">
            <div>
              <span class="text-muted-foreground">Assignee</span>
              <div class="font-medium">
                {ticket.assignee?.name || 'Unassigned'}
              </div>
            </div>
            <Separator />
            <div>
              <span class="text-muted-foreground">Category</span>
              <div class="font-medium">
                {ticket.category?.name || 'None'}
              </div>
            </div>
            <Separator />
            <div>
              <span class="text-muted-foreground">Created</span>
              <div class="font-medium">
                {formatDate(ticket.created_at)}
              </div>
            </div>
            {#if ticket.first_response_at}
              <Separator />
              <div>
                <span class="text-muted-foreground">First Response</span>
                <div class="font-medium">
                  {formatDate(ticket.first_response_at)}
                </div>
              </div>
            {/if}
            {#if ticket.resolved_at}
              <Separator />
              <div>
                <span class="text-muted-foreground">Resolved</span>
                <div class="font-medium">
                  {formatDate(ticket.resolved_at)}
                </div>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>

        <!-- SLA Info -->
        {#if ticket.sla_response_due_at || ticket.sla_resolution_due_at}
          <Card.Root>
            <Card.Header>
              <Card.Title class="text-sm">SLA</Card.Title>
            </Card.Header>
            <Card.Content class="space-y-3 text-sm">
              {#if ticket.sla_response_due_at}
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">Response Due</span>
                  <div class={ticket.sla_response_breached ? 'text-red-500 font-medium' : ''}>
                    {formatDate(ticket.sla_response_due_at)}
                  </div>
                </div>
              {/if}
              {#if ticket.sla_resolution_due_at}
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">Resolution Due</span>
                  <div class={ticket.sla_resolution_breached ? 'text-red-500 font-medium' : ''}>
                    {formatDate(ticket.sla_resolution_due_at)}
                  </div>
                </div>
              {/if}
            </Card.Content>
          </Card.Root>
        {/if}
      </div>
    </div>
  </div>
{:else}
  <div class="text-center py-8 text-muted-foreground">
    Ticket not found
  </div>
{/if}
