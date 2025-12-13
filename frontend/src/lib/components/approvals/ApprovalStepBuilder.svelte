<script lang="ts">
  import { createEventDispatcher } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';

  export let step: {
    order: number;
    approver_type: 'user' | 'role' | 'manager' | 'record_owner';
    approver_id: number | null;
    approver_role_id: number | null;
    can_delegate: boolean;
    require_all: boolean;
  };
  export let index: number;
  export let total: number;
  export let users: Array<{ id: number; name: string }> = [];
  export let roles: Array<{ id: number; name: string }> = [];

  const dispatch = createEventDispatcher<{
    remove: void;
  }>();

  const approverTypes = [
    { value: 'user', label: 'Specific User' },
    { value: 'role', label: 'Role' },
    { value: 'manager', label: 'Manager of Requester' },
    { value: 'record_owner', label: 'Record Owner' },
  ];
</script>

<Card.Root class="border-l-4 border-l-primary">
  <div class="flex items-center gap-4 p-4">
    <div class="w-8 h-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-medium">
      {index + 1}
    </div>

    <div class="flex-1 grid grid-cols-3 gap-4">
      <div class="space-y-1">
        <Label class="text-xs text-muted-foreground">Approver Type</Label>
        <Select.Root
          selected={{ value: step.approver_type, label: approverTypes.find(t => t.value === step.approver_type)?.label || 'Specific User' }}
          onSelectedChange={(v) => {
            step.approver_type = (v?.value as typeof step.approver_type) || 'user';
            if (step.approver_type !== 'user') step.approver_id = null;
            if (step.approver_type !== 'role') step.approver_role_id = null;
          }}
        >
          <Select.Trigger>
            <Select.Value />
          </Select.Trigger>
          <Select.Content>
            {#each approverTypes as type}
              <Select.Item value={type.value}>{type.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      {#if step.approver_type === 'user'}
        <div class="space-y-1">
          <Label class="text-xs text-muted-foreground">Select User</Label>
          <Select.Root
            selected={step.approver_id ? { value: step.approver_id.toString(), label: users.find(u => u.id === step.approver_id)?.name || 'Select user' } : null}
            onSelectedChange={(v) => step.approver_id = v ? parseInt(v.value) : null}
          >
            <Select.Trigger>
              <Select.Value placeholder="Select user" />
            </Select.Trigger>
            <Select.Content>
              {#each users as user}
                <Select.Item value={user.id.toString()}>{user.name}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      {:else if step.approver_type === 'role'}
        <div class="space-y-1">
          <Label class="text-xs text-muted-foreground">Select Role</Label>
          <Select.Root
            selected={step.approver_role_id ? { value: step.approver_role_id.toString(), label: roles.find(r => r.id === step.approver_role_id)?.name || 'Select role' } : null}
            onSelectedChange={(v) => step.approver_role_id = v ? parseInt(v.value) : null}
          >
            <Select.Trigger>
              <Select.Value placeholder="Select role" />
            </Select.Trigger>
            <Select.Content>
              {#each roles as role}
                <Select.Item value={role.id.toString()}>{role.name}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      {:else}
        <div class="flex items-center text-sm text-muted-foreground">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          Auto-determined at runtime
        </div>
      {/if}

      <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" bind:checked={step.can_delegate} class="rounded" />
          Can Delegate
        </label>
        {#if step.approver_type === 'role'}
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" bind:checked={step.require_all} class="rounded" />
            Require All
          </label>
        {/if}
      </div>
    </div>

    <Button variant="ghost" size="sm" on:click={() => dispatch('remove')}>
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-destructive" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="3 6 5 6 21 6" />
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
      </svg>
    </Button>
  </div>

  {#if index < total - 1}
    <div class="flex justify-center -mb-3">
      <div class="px-3 py-1 bg-muted text-xs text-muted-foreground rounded-full">
        then
      </div>
    </div>
  {/if}
</Card.Root>
