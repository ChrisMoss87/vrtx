<script lang="ts">
  import * as Card from '$lib/components/ui/card';
  import { Badge } from '$lib/components/ui/badge';
  import type { SignatureAuditLog } from '$lib/api/signatures';

  export let logs: SignatureAuditLog[] = [];

  const actionLabels: Record<string, string> = {
    created: 'Request Created',
    sent: 'Email Sent',
    viewed: 'Document Viewed',
    signed: 'Document Signed',
    declined: 'Signing Declined',
    completed: 'All Signatures Complete',
    voided: 'Request Voided',
    reminder_sent: 'Reminder Sent',
    downloaded: 'Document Downloaded',
  };

  const actionIcons: Record<string, string> = {
    created: 'M12 4v16m8-8H4',
    sent: 'M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z',
    viewed: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z M12 9a3 3 0 100 6 3 3 0 000-6z',
    signed: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    declined: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    completed: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    voided: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    reminder_sent: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    downloaded: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4',
  };

  const actionColors: Record<string, string> = {
    created: 'bg-blue-100 text-blue-700',
    sent: 'bg-purple-100 text-purple-700',
    viewed: 'bg-yellow-100 text-yellow-700',
    signed: 'bg-green-100 text-green-700',
    declined: 'bg-red-100 text-red-700',
    completed: 'bg-green-100 text-green-700',
    voided: 'bg-gray-100 text-gray-700',
    reminder_sent: 'bg-orange-100 text-orange-700',
    downloaded: 'bg-blue-100 text-blue-700',
  };

  function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
</script>

<Card.Root>
  <Card.Header>
    <Card.Title>Audit Log</Card.Title>
    <Card.Description>Complete history of all actions on this signature request</Card.Description>
  </Card.Header>
  <Card.Content>
    {#if logs.length === 0}
      <p class="text-center text-muted-foreground py-8">No audit log entries</p>
    {:else}
      <div class="relative">
        <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-muted"></div>
        <div class="space-y-6">
          {#each logs as log}
            <div class="relative pl-10">
              <div class="absolute left-0 w-8 h-8 rounded-full {actionColors[log.action] || 'bg-gray-100'} flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d={actionIcons[log.action] || 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'} />
                </svg>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-medium">{actionLabels[log.action] || log.action}</span>
                  <span class="text-xs text-muted-foreground">{formatDateTime(log.created_at)}</span>
                </div>
                {#if log.actor_name}
                  <p class="text-sm text-muted-foreground">
                    By {log.actor_name}
                    {#if log.actor_email}
                      ({log.actor_email})
                    {/if}
                  </p>
                {/if}
                {#if log.ip_address}
                  <p class="text-xs text-muted-foreground mt-1">
                    IP: {log.ip_address}
                    {#if log.user_agent}
                      • {log.user_agent.substring(0, 50)}...
                    {/if}
                  </p>
                {/if}
                {#if log.metadata && Object.keys(log.metadata).length > 0}
                  <div class="mt-2 p-2 rounded bg-muted text-xs">
                    <pre class="whitespace-pre-wrap">{JSON.stringify(log.metadata, null, 2)}</pre>
                  </div>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      </div>
    {/if}
  </Card.Content>
</Card.Root>
