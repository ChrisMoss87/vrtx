<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import ApprovalStepBuilder from './ApprovalStepBuilder.svelte';

  interface RuleStep {
    order: number;
    approver_type: 'user' | 'role' | 'manager' | 'record_owner';
    approver_id: number | null;
    approver_role_id: number | null;
    can_delegate: boolean;
    require_all: boolean;
  }

  interface RuleCondition {
    field: string;
    operator: string;
    value: string;
  }

  interface RuleData {
    id?: number;
    name: string;
    description: string | null;
    entity_type: string;
    conditions: RuleCondition[] | null;
    approver_chain: Array<{ type: string; user_id?: number; role_id?: number }>;
    is_active: boolean;
  }

  interface Props {
    rule?: Partial<RuleData>;
    users?: Array<{ id: number; name: string }>;
    roles?: Array<{ id: number; name: string }>;
    loading?: boolean;
    onSave?: (data: Partial<RuleData>) => void;
    onCancel?: () => void;
  }

  let {
    rule = {},
    users = [],
    roles = [],
    loading = false,
    onSave = undefined,
    onCancel = undefined
  }: Props = $props();

  let name = $state(rule.name || '');
  let description = $state(rule.description || '');
  let entityType = $state(rule.entity_type || 'quote');
  let conditions = $state<RuleCondition[]>(rule.conditions || []);
  let steps = $state<RuleStep[]>([]);
  let isActive = $state(rule.is_active ?? true);
  let slaHours = $state(72);
  let escalationUserId = $state<string>('');
  let autoApproveOnTimeout = $state(false);

  const entityTypes = [
    { value: 'quote', label: 'Quote' },
    { value: 'discount', label: 'Discount Request' },
    { value: 'contract', label: 'Contract' },
    { value: 'deal', label: 'Deal Stage Change' },
    { value: 'expense', label: 'Expense Report' },
    { value: 'custom', label: 'Custom' },
  ];

  const operators = [
    { value: '=', label: 'Equals' },
    { value: '!=', label: 'Not Equals' },
    { value: '>', label: 'Greater Than' },
    { value: '>=', label: 'Greater Than or Equal' },
    { value: '<', label: 'Less Than' },
    { value: '<=', label: 'Less Than or Equal' },
    { value: 'in', label: 'In List' },
    { value: 'not_in', label: 'Not In List' },
  ];

  function addCondition() {
    conditions = [
      ...conditions,
      { field: '', operator: '=', value: '' },
    ];
  }

  function removeCondition(index: number) {
    conditions = conditions.filter((_, i) => i !== index);
  }

  function addStep() {
    steps = [
      ...steps,
      {
        order: steps.length + 1,
        approver_type: 'user',
        approver_id: null,
        approver_role_id: null,
        can_delegate: true,
        require_all: false,
      },
    ];
  }

  function removeStep(index: number) {
    steps = steps.filter((_, i) => i !== index);
    steps = steps.map((s, i) => ({ ...s, order: i + 1 }));
  }

  function handleSave() {
    // Convert steps to approver_chain format
    const approverChain = steps.map((s) => ({
      type: s.approver_type,
      user_id: s.approver_id || undefined,
      role_id: s.approver_role_id || undefined,
    }));

    onSave?.({
      ...rule,
      name,
      description: description || null,
      entity_type: entityType,
      conditions: conditions.length > 0 ? conditions : null,
      approver_chain: approverChain,
      is_active: isActive,
    });
  }

  function handleEntityTypeChange(value: string | undefined) {
    if (value) {
      entityType = value;
    }
  }

  function handleConditionOperatorChange(index: number, value: string | undefined) {
    if (value) {
      conditions[index].operator = value;
      conditions = [...conditions];
    }
  }

  function handleEscalationUserChange(value: string | undefined) {
    if (value) {
      escalationUserId = value;
    }
  }

  const entityTypeLabel = $derived(entityTypes.find(e => e.value === entityType)?.label || 'Select type');
  const escalationUserLabel = $derived(users.find(u => u.id.toString() === escalationUserId)?.name || 'Select user');
</script>

<div class="space-y-6">
  <Card.Root>
    <Card.Header>
      <Card.Title>{rule.id ? 'Edit Approval Rule' : 'Create Approval Rule'}</Card.Title>
      <Card.Description>
        Define when approvals are required and who needs to approve
      </Card.Description>
    </Card.Header>
    <Card.Content class="space-y-6">
      <!-- Basic Info -->
      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label for="name">Rule Name</Label>
          <Input id="name" bind:value={name} placeholder="e.g., Large Discount Approval" />
        </div>
        <div class="space-y-2">
          <Label for="entityType">Applies To</Label>
          <Select.Root type="single" value={entityType} onValueChange={handleEntityTypeChange}>
            <Select.Trigger>
              <span>{entityTypeLabel}</span>
            </Select.Trigger>
            <Select.Content>
              {#each entityTypes as type}
                <Select.Item value={type.value} label={type.label}>{type.label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <div class="space-y-2">
        <Label for="description">Description (Optional)</Label>
        <Textarea
          id="description"
          bind:value={description}
          placeholder="Describe when this rule applies..."
        />
      </div>

      <!-- Conditions -->
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div>
            <h4 class="font-medium">Trigger Conditions</h4>
            <p class="text-sm text-muted-foreground">When should this approval be required?</p>
          </div>
          <Button variant="outline" size="sm" onclick={addCondition}>
            Add Condition
          </Button>
        </div>

        {#if conditions.length === 0}
          <div class="p-4 border rounded-lg bg-muted/50 text-center text-sm text-muted-foreground">
            No conditions - this rule will apply to all {entityTypes.find(e => e.value === entityType)?.label || 'items'}
          </div>
        {:else}
          <div class="space-y-2">
            {#each conditions as condition, index}
              <div class="flex gap-2 items-center p-3 border rounded-lg">
                {#if index > 0}
                  <span class="text-xs text-muted-foreground w-10">AND</span>
                {/if}
                <Input
                  bind:value={condition.field}
                  placeholder="Field (e.g., amount)"
                  class="w-40"
                />
                <Select.Root type="single" value={condition.operator} onValueChange={(v) => handleConditionOperatorChange(index, v)}>
                  <Select.Trigger class="w-40">
                    <span>{operators.find(o => o.value === condition.operator)?.label || 'Equals'}</span>
                  </Select.Trigger>
                  <Select.Content>
                    {#each operators as op}
                      <Select.Item value={op.value} label={op.label}>{op.label}</Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
                <Input
                  bind:value={condition.value}
                  placeholder="Value"
                  class="flex-1"
                />
                <Button variant="ghost" size="sm" onclick={() => removeCondition(index)}>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                </Button>
              </div>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Approval Steps -->
      <div class="space-y-4">
        <div class="flex justify-between items-center">
          <div>
            <h4 class="font-medium">Approval Steps</h4>
            <p class="text-sm text-muted-foreground">Define the approval chain</p>
          </div>
          <Button variant="outline" size="sm" onclick={addStep}>
            Add Step
          </Button>
        </div>

        {#if steps.length === 0}
          <Card.Root>
            <Card.Content class="py-8 text-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-muted-foreground mb-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
              <p class="text-muted-foreground">No approval steps defined</p>
              <Button variant="outline" class="mt-4" onclick={addStep}>
                Add first approver
              </Button>
            </Card.Content>
          </Card.Root>
        {:else}
          <div class="space-y-3">
            {#each steps as _, index}
              <ApprovalStepBuilder
                bind:step={steps[index]}
                {index}
                total={steps.length}
                {users}
                {roles}
                onRemove={() => removeStep(index)}
              />
            {/each}
          </div>
        {/if}
      </div>

      <!-- SLA Settings -->
      <div class="space-y-4 pt-4 border-t">
        <h4 class="font-medium">SLA & Escalation</h4>

        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label for="slaHours">SLA (Hours)</Label>
            <Input id="slaHours" type="number" bind:value={slaHours} min="1" max="720" />
            <p class="text-xs text-muted-foreground">Time before escalation triggers</p>
          </div>
          <div class="space-y-2">
            <Label for="escalation">Escalation User</Label>
            <Select.Root type="single" value={escalationUserId} onValueChange={handleEscalationUserChange}>
              <Select.Trigger>
                <span>{escalationUserLabel}</span>
              </Select.Trigger>
              <Select.Content>
                {#each users as user}
                  <Select.Item value={user.id.toString()} label={user.name}>{user.name}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" id="autoApprove" bind:checked={autoApproveOnTimeout} class="rounded" />
          <Label for="autoApprove">Auto-approve if SLA expires without action</Label>
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" id="isActive" bind:checked={isActive} class="rounded" />
          <Label for="isActive">Rule is active</Label>
        </div>
      </div>
    </Card.Content>
    <Card.Footer class="flex justify-between">
      <Button variant="outline" onclick={() => onCancel?.()}>Cancel</Button>
      <Button onclick={handleSave} disabled={loading || !name || steps.length === 0}>
        {loading ? 'Saving...' : 'Save Rule'}
      </Button>
    </Card.Footer>
  </Card.Root>
</div>
