<script lang="ts">
  import { onMount } from 'svelte';
  import { portalAdminApi, type PortalInvitation } from '$lib/api/portal';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import * as Table from '$lib/components/ui/table';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Mail, RefreshCw, X, Clock, CheckCircle, XCircle } from 'lucide-svelte';

  let invitations = $state<PortalInvitation[]>([]);
  let loading = $state(true);
  let statusFilter = $state<'pending' | 'accepted' | 'expired'>('pending');
  let currentPage = $state(1);
  let totalPages = $state(1);

  async function loadInvitations() {
    loading = true;
    try {
      const response = await portalAdminApi.getInvitations({
        status: statusFilter,
        page: currentPage,
      });
      invitations = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load invitations:', error);
    } finally {
      loading = false;
    }
  }

  async function resendInvitation(id: number) {
    try {
      await portalAdminApi.resendInvitation(id);
      await loadInvitations();
    } catch (error) {
      console.error('Failed to resend invitation:', error);
    }
  }

  async function cancelInvitation(id: number) {
    try {
      await portalAdminApi.cancelInvitation(id);
      await loadInvitations();
    } catch (error) {
      console.error('Failed to cancel invitation:', error);
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString();
  }

  function getStatusBadge(invitation: PortalInvitation) {
    if (invitation.accepted_at) {
      return { variant: 'default' as const, label: 'Accepted', class: 'bg-green-500' };
    }
    if (new Date(invitation.expires_at) < new Date()) {
      return { variant: 'secondary' as const, label: 'Expired', class: '' };
    }
    return { variant: 'outline' as const, label: 'Pending', class: '' };
  }

  onMount(() => {
    loadInvitations();
  });

  $effect(() => {
    if (statusFilter) {
      currentPage = 1;
      loadInvitations();
    }
  });
</script>

<Card.Root>
  <Card.Header>
    <Card.Title class="flex items-center gap-2">
      <Mail class="h-5 w-5" />
      Invitations
    </Card.Title>
    <Card.Description>Track and manage portal invitations</Card.Description>
  </Card.Header>
  <Card.Content>
    <Tabs.Root bind:value={statusFilter} class="mb-4">
      <Tabs.List>
        <Tabs.Trigger value="pending">
          <Clock class="mr-2 h-4 w-4" />
          Pending
        </Tabs.Trigger>
        <Tabs.Trigger value="accepted">
          <CheckCircle class="mr-2 h-4 w-4" />
          Accepted
        </Tabs.Trigger>
        <Tabs.Trigger value="expired">
          <XCircle class="mr-2 h-4 w-4" />
          Expired
        </Tabs.Trigger>
      </Tabs.List>
    </Tabs.Root>

    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if invitations.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        No {statusFilter} invitations found
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Email</Table.Head>
            <Table.Head>Role</Table.Head>
            <Table.Head>Invited By</Table.Head>
            <Table.Head>Sent</Table.Head>
            <Table.Head>Expires</Table.Head>
            <Table.Head>Status</Table.Head>
            {#if statusFilter === 'pending'}
              <Table.Head class="w-[100px]">Actions</Table.Head>
            {/if}
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each invitations as invitation}
            {@const status = getStatusBadge(invitation)}
            <Table.Row>
              <Table.Cell class="font-medium">{invitation.email}</Table.Cell>
              <Table.Cell>
                <Badge variant="outline">{invitation.role}</Badge>
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {invitation.inviter?.name || 'Unknown'}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {formatDate(invitation.created_at)}
              </Table.Cell>
              <Table.Cell class="text-muted-foreground">
                {formatDate(invitation.expires_at)}
              </Table.Cell>
              <Table.Cell>
                <Badge variant={status.variant} class={status.class}>
                  {status.label}
                </Badge>
              </Table.Cell>
              {#if statusFilter === 'pending'}
                <Table.Cell>
                  <div class="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      title="Resend"
                      onclick={() => resendInvitation(invitation.id)}
                    >
                      <RefreshCw class="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      title="Cancel"
                      onclick={() => cancelInvitation(invitation.id)}
                    >
                      <X class="h-4 w-4" />
                    </Button>
                  </div>
                </Table.Cell>
              {/if}
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
            onclick={() => { currentPage--; loadInvitations(); }}
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
            onclick={() => { currentPage++; loadInvitations(); }}
          >
            Next
          </Button>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
