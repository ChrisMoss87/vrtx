<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { Badge } from '$lib/components/ui/badge';
  import { Users, Plus, Settings, Trash2, UserPlus, Clock, Phone } from 'lucide-svelte';
  import { callQueueApi, callProviderApi, getMemberStatusColor, type CallQueue, type CallProvider } from '$lib/api/calls';

  let queues = $state<CallQueue[]>([]);
  let providers = $state<CallProvider[]>([]);
  let isLoading = $state(true);
  let showAddForm = $state(false);
  let editingQueue = $state<CallQueue | null>(null);
  let selectedQueue = $state<CallQueue | null>(null);

  // Form state
  let formData = $state<{
    name: string;
    description: string;
    provider_id: number;
    phone_number: string;
    routing_strategy: 'round_robin' | 'longest_idle' | 'skills_based' | 'random';
    max_wait_time_seconds: number;
    max_queue_size: number;
    welcome_message: string;
    voicemail_enabled: boolean;
    voicemail_greeting: string;
    after_hours_message: string;
  }>({
    name: '',
    description: '',
    provider_id: 0,
    phone_number: '',
    routing_strategy: 'round_robin',
    max_wait_time_seconds: 300,
    max_queue_size: 20,
    welcome_message: '',
    voicemail_enabled: true,
    voicemail_greeting: '',
    after_hours_message: '',
  });

  const routingOptions = [
    { value: 'round_robin', label: 'Round Robin', description: 'Distribute calls evenly among agents' },
    { value: 'longest_idle', label: 'Longest Idle', description: 'Route to agent idle longest' },
    { value: 'skills_based', label: 'Skills Based', description: 'Route based on agent priority/skill' },
    { value: 'random', label: 'Random', description: 'Randomly select available agent' },
  ];

  async function loadData() {
    try {
      isLoading = true;
      [queues, providers] = await Promise.all([callQueueApi.list(), callProviderApi.list()]);
    } catch (error) {
      console.error('Failed to load data:', error);
    } finally {
      isLoading = false;
    }
  }

  async function handleSubmit() {
    try {
      if (editingQueue) {
        await callQueueApi.update(editingQueue.id, formData);
      } else {
        await callQueueApi.create(formData);
      }
      await loadData();
      resetForm();
    } catch (error) {
      console.error('Failed to save queue:', error);
    }
  }

  async function deleteQueue(queue: CallQueue) {
    if (!confirm(`Are you sure you want to delete "${queue.name}"?`)) return;
    try {
      await callQueueApi.delete(queue.id);
      await loadData();
      if (selectedQueue?.id === queue.id) {
        selectedQueue = null;
      }
    } catch (error) {
      console.error('Failed to delete queue:', error);
    }
  }

  async function toggleQueueActive(queue: CallQueue) {
    try {
      await callQueueApi.toggleActive(queue.id);
      await loadData();
    } catch (error) {
      console.error('Failed to toggle queue:', error);
    }
  }

  async function removeMember(queueId: number, userId: number) {
    try {
      await callQueueApi.removeMember(queueId, userId);
      await loadData();
      if (selectedQueue) {
        selectedQueue = queues.find((q) => q.id === selectedQueue!.id) || null;
      }
    } catch (error) {
      console.error('Failed to remove member:', error);
    }
  }

  function editQueue(queue: CallQueue) {
    editingQueue = queue;
    formData = {
      name: queue.name,
      description: queue.description || '',
      provider_id: queue.provider_id,
      phone_number: queue.phone_number || '',
      routing_strategy: queue.routing_strategy,
      max_wait_time_seconds: queue.max_wait_time_seconds || 300,
      max_queue_size: queue.max_queue_size || 20,
      welcome_message: queue.welcome_message || '',
      voicemail_enabled: queue.voicemail_enabled,
      voicemail_greeting: queue.voicemail_greeting || '',
      after_hours_message: queue.after_hours_message || '',
    };
    showAddForm = true;
  }

  function resetForm() {
    showAddForm = false;
    editingQueue = null;
    formData = {
      name: '',
      description: '',
      provider_id: 0,
      phone_number: '',
      routing_strategy: 'round_robin',
      max_wait_time_seconds: 300,
      max_queue_size: 20,
      welcome_message: '',
      voicemail_enabled: true,
      voicemail_greeting: '',
      after_hours_message: '',
    };
  }

  $effect(() => {
    loadData();
  });
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-lg font-semibold">Call Queues</h2>
      <p class="text-sm text-muted-foreground">Manage call queues and agent assignments.</p>
    </div>
    <Button onclick={() => (showAddForm = true)}>
      <Plus class="mr-2 h-4 w-4" />
      Create Queue
    </Button>
  </div>

  {#if showAddForm}
    <Card.Root>
      <Card.Header>
        <Card.Title>{editingQueue ? 'Edit Queue' : 'Create New Queue'}</Card.Title>
      </Card.Header>
      <Card.Content>
        <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="name">Queue Name</Label>
              <Input id="name" bind:value={formData.name} placeholder="Sales Queue" required />
            </div>
            <div class="space-y-2">
              <Label for="provider">Provider</Label>
              <Select.Root type="single" name="provider" value={String(formData.provider_id)} onValueChange={(v) => formData.provider_id = Number(v)}>
                <Select.Trigger>
                  <span>{providers.find((p) => p.id === formData.provider_id)?.name || 'Select provider'}</span>
                </Select.Trigger>
                <Select.Content>
                  {#each providers.filter((p) => p.is_active) as provider}
                    <Select.Item value={String(provider.id)}>{provider.name}</Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea id="description" bind:value={formData.description} placeholder="Queue description..." rows={2} />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="phone_number">Phone Number</Label>
              <Input id="phone_number" bind:value={formData.phone_number} placeholder="+1234567890" />
            </div>
            <div class="space-y-2">
              <Label for="routing">Routing Strategy</Label>
              <Select.Root type="single" name="routing" bind:value={formData.routing_strategy}>
                <Select.Trigger>
                  <span>{routingOptions.find((r) => r.value === formData.routing_strategy)?.label || 'Select strategy'}</span>
                </Select.Trigger>
                <Select.Content>
                  {#each routingOptions as option}
                    <Select.Item value={option.value}>
                      <div>
                        <div class="font-medium">{option.label}</div>
                        <div class="text-xs text-muted-foreground">{option.description}</div>
                      </div>
                    </Select.Item>
                  {/each}
                </Select.Content>
              </Select.Root>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="max_wait">Max Wait Time (seconds)</Label>
              <Input id="max_wait" type="number" bind:value={formData.max_wait_time_seconds} min={30} max={3600} />
            </div>
            <div class="space-y-2">
              <Label for="max_size">Max Queue Size</Label>
              <Input id="max_size" type="number" bind:value={formData.max_queue_size} min={1} max={100} />
            </div>
          </div>

          <div class="space-y-2">
            <Label for="welcome">Welcome Message</Label>
            <Textarea id="welcome" bind:value={formData.welcome_message} placeholder="Thank you for calling..." rows={2} />
          </div>

          <div class="flex items-center gap-2">
            <Switch id="voicemail" bind:checked={formData.voicemail_enabled} />
            <Label for="voicemail">Enable Voicemail</Label>
          </div>

          {#if formData.voicemail_enabled}
            <div class="space-y-2">
              <Label for="voicemail_greeting">Voicemail Greeting</Label>
              <Textarea id="voicemail_greeting" bind:value={formData.voicemail_greeting} placeholder="Please leave a message..." rows={2} />
            </div>
          {/if}

          <div class="space-y-2">
            <Label for="after_hours">After Hours Message</Label>
            <Textarea id="after_hours" bind:value={formData.after_hours_message} placeholder="We are currently closed..." rows={2} />
          </div>

          <div class="flex justify-end gap-2">
            <Button variant="outline" type="button" onclick={resetForm}>Cancel</Button>
            <Button type="submit">{editingQueue ? 'Update' : 'Create'} Queue</Button>
          </div>
        </form>
      </Card.Content>
    </Card.Root>
  {/if}

  <div class="grid grid-cols-3 gap-6">
    <div class="col-span-1 space-y-4">
      {#if isLoading}
        <div class="text-center py-8 text-muted-foreground">Loading queues...</div>
      {:else if queues.length === 0}
        <Card.Root>
          <Card.Content class="py-8 text-center">
            <Users class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
            <h3 class="font-medium mb-2">No queues configured</h3>
            <p class="text-sm text-muted-foreground">Create a queue to manage call routing.</p>
          </Card.Content>
        </Card.Root>
      {:else}
        {#each queues as queue}
          <Card.Root
            class={`cursor-pointer transition-colors ${selectedQueue?.id === queue.id ? 'border-primary' : ''}`}
          >
            <button type="button" class="w-full text-left" onclick={() => (selectedQueue = queue)}>
              <Card.Content class="py-4">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                      <Users class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="font-medium">{queue.name}</h3>
                        {#if !queue.is_active}
                          <Badge variant="secondary">Inactive</Badge>
                        {/if}
                      </div>
                      <p class="text-sm text-muted-foreground">
                        {queue.online_agent_count} online • {queue.members?.length || 0} members
                      </p>
                    </div>
                  </div>
                  <div class={`h-3 w-3 rounded-full ${queue.is_within_business_hours ? 'bg-green-500' : 'bg-gray-400'}`}></div>
                </div>
              </Card.Content>
            </button>
          </Card.Root>
        {/each}
      {/if}
    </div>

    <div class="col-span-2">
      {#if selectedQueue}
        <Card.Root>
          <Card.Header>
            <div class="flex items-center justify-between">
              <div>
                <Card.Title>{selectedQueue.name}</Card.Title>
                <Card.Description>{selectedQueue.description || 'No description'}</Card.Description>
              </div>
              <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" onclick={() => editQueue(selectedQueue!)}>
                  <Settings class="h-4 w-4 mr-1" />
                  Edit
                </Button>
                <Button variant="outline" size="sm" onclick={() => toggleQueueActive(selectedQueue!)}>
                  {selectedQueue.is_active ? 'Deactivate' : 'Activate'}
                </Button>
                <Button variant="ghost" size="icon" onclick={() => deleteQueue(selectedQueue!)}>
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </Card.Header>
          <Card.Content class="space-y-6">
            <div class="grid grid-cols-4 gap-4">
              <div class="text-center p-4 bg-muted rounded-lg">
                <div class="text-2xl font-bold">{selectedQueue.stats?.today_calls || 0}</div>
                <div class="text-sm text-muted-foreground">Today's Calls</div>
              </div>
              <div class="text-center p-4 bg-muted rounded-lg">
                <div class="text-2xl font-bold">{selectedQueue.stats?.today_answered || 0}</div>
                <div class="text-sm text-muted-foreground">Answered</div>
              </div>
              <div class="text-center p-4 bg-muted rounded-lg">
                <div class="text-2xl font-bold">{selectedQueue.stats?.today_missed || 0}</div>
                <div class="text-sm text-muted-foreground">Missed</div>
              </div>
              <div class="text-center p-4 bg-muted rounded-lg">
                <div class="text-2xl font-bold">{Math.round(selectedQueue.stats?.avg_wait_time || 0)}s</div>
                <div class="text-sm text-muted-foreground">Avg Wait</div>
              </div>
            </div>

            <div>
              <div class="flex items-center justify-between mb-4">
                <h4 class="font-medium">Queue Members</h4>
                <Button variant="outline" size="sm">
                  <UserPlus class="h-4 w-4 mr-1" />
                  Add Member
                </Button>
              </div>
              {#if selectedQueue.members && selectedQueue.members.length > 0}
                <div class="space-y-2">
                  {#each selectedQueue.members as member}
                    <div class="flex items-center justify-between p-3 border rounded-lg">
                      <div class="flex items-center gap-3">
                        <div class={`h-3 w-3 rounded-full ${getMemberStatusColor(member.status)}`}></div>
                        <div>
                          <div class="font-medium">{member.user?.name || `User ${member.user_id}`}</div>
                          <div class="text-sm text-muted-foreground">
                            Priority: {member.priority} • {member.calls_handled_today} calls today
                          </div>
                        </div>
                      </div>
                      <div class="flex items-center gap-2">
                        <Badge variant="outline">{member.status}</Badge>
                        <Button variant="ghost" size="icon" onclick={() => selectedQueue && removeMember(selectedQueue.id, member.user_id)}>
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  {/each}
                </div>
              {:else}
                <div class="text-center py-8 text-muted-foreground">
                  <Users class="mx-auto h-8 w-8 mb-2" />
                  <p>No members in this queue yet.</p>
                </div>
              {/if}
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-muted-foreground">Routing Strategy:</span>
                <span class="ml-2 font-medium">{routingOptions.find((r) => r.value === selectedQueue?.routing_strategy)?.label}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Phone:</span>
                <span class="ml-2 font-medium">{selectedQueue?.phone_number || 'Not configured'}</span>
              </div>
              <div>
                <span class="text-muted-foreground">Max Wait:</span>
                <span class="ml-2 font-medium">{selectedQueue?.max_wait_time_seconds}s</span>
              </div>
              <div>
                <span class="text-muted-foreground">Voicemail:</span>
                <span class="ml-2 font-medium">{selectedQueue?.voicemail_enabled ? 'Enabled' : 'Disabled'}</span>
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      {:else}
        <Card.Root>
          <Card.Content class="py-16 text-center">
            <Phone class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
            <h3 class="font-medium mb-2">Select a Queue</h3>
            <p class="text-sm text-muted-foreground">Click on a queue to view details and manage members.</p>
          </Card.Content>
        </Card.Root>
      {/if}
    </div>
  </div>
</div>
