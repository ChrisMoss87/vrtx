<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';

  interface StepData {
    order: number;
    approver_type: 'user' | 'role' | 'manager' | 'record_owner';
    approver_id: number | null;
    approver_role_id: number | null;
    can_delegate: boolean;
    require_all: boolean;
  }

  interface Props {
    step: StepData;
    index: number;
    total: number;
    users?: Array<{ id: number; name: string }>;
    roles?: Array<{ id: number; name: string }>;
    onRemove?: () => void;
    onStepChange?: (step: StepData) => void;
  }

  let {
    step = $bindable(),
    index,
    total,
    users = [],
    roles = [],
    onRemove = undefined,
    onStepChange = undefined
  }: Props = $props();

  const approverTypes = [
    { value: 'user', label: 'Specific User' },
    { value: 'role', label: 'Role' },
    { value: 'manager', label: 'Manager of Requester' },
    { value: 'record_owner', label: 'Record Owner' },
  ];

  function handleApproverTypeChange(value: string | undefined) {
    if (!value) return;
    step.approver_type = value as StepData['approver_type'];
    if (step.approver_type !== 'user') step.approver_id = null;
    if (step.approver_type !== 'role') step.approver_role_id = null;
    onStepChange?.(step);
  }

  function handleApproverIdChange(value: string | undefined) {
    step.approver_id = value ? parseInt(value) : null;
    onStepChange?.(step);
  }

  function handleApproverRoleIdChange(value: string | undefined) {
    step.approver_role_id = value ? parseInt(value) : null;
    onStepChange?.(step);
  }

  function handleCanDelegateChange(e: Event) {
    step.can_delegate = (e.target as HTMLInputElement).checked;
    onStepChange?.(step);
  }

  function handleRequireAllChange(e: Event) {
    step.require_all = (e.target as HTMLInputElement).checked;
    onStepChange?.(step);
  }

  const approverTypeLabel = $derived(approverTypes.find(t => t.value === step.approver_type)?.label || 'Select type');
  const selectedUserLabel = $derived(users.find(u => u.id === step.approver_id)?.name || 'Select user');
  const selectedRoleLabel = $derived(roles.find(r => r.id === step.approver_role_id)?.name || 'Select role');
</script>

<Card.Root class="border-l-4 border-l-primary">
  <div class="flex items-center gap-4 p-4">
    <div class="w-8 h-8 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-sm font-medium">
      {index + 1}
    </div>

    <div class="flex-1 grid grid-cols-3 gap-4">
      <div class="space-y-1">
        <Label class="text-xs text-muted-foreground">Approver Type</Label>
        <Select.Root type="single" value={step.approver_type} onValueChange={handleApproverTypeChange}>
          <Select.Trigger>
            <span>{approverTypeLabel}</span>
          </Select.Trigger>
          <Select.Content>
            {#each approverTypes as type}
              <Select.Item value={type.value} label={type.label}>{type.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      {#if step.approver_type === 'user'}
        <div class="space-y-1">
          <Label class="text-xs text-muted-foreground">Select User</Label>
          <Select.Root type="single" value={step.approver_id?.toString() || ''} onValueChange={handleApproverIdChange}>
            <Select.Trigger>
              <span>{selectedUserLabel}</span>
            </Select.Trigger>
            <Select.Content>
              {#each users as user}
                <Select.Item value={user.id.toString()} label={user.name}>{user.name}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      {:else if step.approver_type === 'role'}
        <div class="space-y-1">
          <Label class="text-xs text-muted-foreground">Select Role</Label>
          <Select.Root type="single" value={step.approver_role_id?.toString() || ''} onValueChange={handleApproverRoleIdChange}>
            <Select.Trigger>
              <span>{selectedRoleLabel}</span>
            </Select.Trigger>
            <Select.Content>
              {#each roles as role}
                <Select.Item value={role.id.toString()} label={role.name}>{role.name}</Select.Item>
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
          <input type="checkbox" checked={step.can_delegate} onchange={handleCanDelegateChange} class="rounded" />
          Can Delegate
        </label>
        {#if step.approver_type === 'role'}
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={step.require_all} onchange={handleRequireAllChange} class="rounded" />
            Require All
          </label>
        {/if}
      </div>
    </div>

    <Button variant="ghost" size="sm" onclick={() => onRemove?.()}>
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
