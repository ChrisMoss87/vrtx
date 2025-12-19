<script lang="ts">
  import { portalAdminApi } from '$lib/api/portal';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Select from '$lib/components/ui/select';
  import { Mail, Send } from 'lucide-svelte';

  interface Props {
    open: boolean;
    onClose: () => void;
    onInvited: () => void;
  }

  let { open = $bindable(), onClose, onInvited }: Props = $props();

  let email = $state('');
  let role = $state('member');
  let accountId = $state<number | undefined>();
  let contactId = $state<number | undefined>();
  let loading = $state(false);
  let error = $state('');

  const roles = [
    { value: 'admin', label: 'Admin' },
    { value: 'member', label: 'Member' },
    { value: 'viewer', label: 'Viewer' },
  ];

  const getRoleLabel = (value: string) => roles.find(r => r.value === value)?.label ?? value;

  async function handleSubmit() {
    if (!email) {
      error = 'Email is required';
      return;
    }

    loading = true;
    error = '';

    try {
      await portalAdminApi.createInvitation({
        email,
        role: role,
        account_id: accountId,
        contact_id: contactId,
      });

      // Reset form
      email = '';
      role = 'member';
      accountId = undefined;
      contactId = undefined;

      onInvited();
      onClose();
    } catch (err: unknown) {
      const apiError = err as { response?: { data?: { message?: string } } };
      error = apiError.response?.data?.message || 'Failed to send invitation';
    } finally {
      loading = false;
    }
  }

  function handleOpenChange(isOpen: boolean) {
    if (!isOpen) {
      onClose();
    }
  }
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title class="flex items-center gap-2">
        <Mail class="h-5 w-5" />
        Invite Portal User
      </Dialog.Title>
      <Dialog.Description>
        Send an invitation to give a customer access to the portal.
      </Dialog.Description>
    </Dialog.Header>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
      {#if error}
        <div class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      {/if}

      <div class="space-y-2">
        <Label for="email">Email Address</Label>
        <Input
          id="email"
          type="email"
          placeholder="customer@example.com"
          bind:value={email}
          required
        />
      </div>

      <div class="space-y-2">
        <Label>Role</Label>
        <Select.Root type="single" bind:value={role}>
          <Select.Trigger>
            {getRoleLabel(role)}
          </Select.Trigger>
          <Select.Content>
            {#each roles as r}
              <Select.Item value={r.value}>
                {r.label}
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
        <p class="text-xs text-muted-foreground">
          {#if role === 'admin'}
            Full access to portal features and can manage other users
          {:else if role === 'member'}
            Can view deals, invoices, quotes, and documents
          {:else}
            Read-only access to basic information
          {/if}
        </p>
      </div>

      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={onClose}>
          Cancel
        </Button>
        <Button type="submit" disabled={loading}>
          {#if loading}
            Sending...
          {:else}
            <Send class="mr-2 h-4 w-4" />
            Send Invitation
          {/if}
        </Button>
      </Dialog.Footer>
    </form>
  </Dialog.Content>
</Dialog.Root>
