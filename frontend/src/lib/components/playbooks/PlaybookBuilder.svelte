<script lang="ts">
  import { playbooksApi, type Playbook, type PlaybookPhase, type PlaybookTask } from '$lib/api/playbooks';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Switch } from '$lib/components/ui/switch';
  import { Badge } from '$lib/components/ui/badge';
  import { Separator } from '$lib/components/ui/separator';
  import * as Tabs from '$lib/components/ui/tabs';
  import {
    Plus,
    GripVertical,
    Trash2,
    Edit2,
    CheckCircle,
    Clock,
    AlertCircle,
    ArrowLeft,
    Save,
    FolderPlus,
  } from 'lucide-svelte';

  interface Props {
    playbook?: Playbook;
    onSave?: (playbook: Playbook) => void;
    onBack?: () => void;
  }

  let { playbook, onSave, onBack }: Props = $props();

  // Form state
  let name = $state(playbook?.name || '');
  let description = $state(playbook?.description || '');
  let triggerModule = $state(playbook?.trigger_module || '');
  let triggerCondition = $state(playbook?.trigger_condition || '');
  let estimatedDays = $state(playbook?.estimated_days?.toString() || '');
  let isActive = $state(playbook?.is_active ?? true);
  let autoAssign = $state(playbook?.auto_assign ?? false);
  let phases = $state<PlaybookPhase[]>(playbook?.phases || []);
  let tasks = $state<PlaybookTask[]>(playbook?.tasks || []);

  let saving = $state(false);
  let activeTab = $state('settings');

  // Task editing state
  let editingTask = $state<PlaybookTask | null>(null);
  let newTaskPhaseId = $state<number | null>(null);
  let showTaskForm = $state(false);

  // New phase form
  let newPhaseName = $state('');
  let showPhaseForm = $state(false);

  async function handleSave() {
    if (!name.trim()) return;

    saving = true;
    try {
      const data = {
        name: name.trim(),
        description: description.trim() || undefined,
        trigger_module: triggerModule || undefined,
        trigger_condition: triggerCondition || undefined,
        estimated_days: estimatedDays ? parseInt(estimatedDays) : undefined,
        is_active: isActive,
        auto_assign: autoAssign,
      };

      let savedPlaybook: Playbook;
      if (playbook?.id) {
        const response = await playbooksApi.update(playbook.id, data);
        savedPlaybook = response.playbook;
      } else {
        const response = await playbooksApi.create(data);
        savedPlaybook = response.playbook;
      }

      onSave?.(savedPlaybook);
    } catch (error) {
      console.error('Failed to save playbook:', error);
    } finally {
      saving = false;
    }
  }

  async function addPhase() {
    if (!newPhaseName.trim() || !playbook?.id) return;

    try {
      const response = await playbooksApi.addPhase(playbook.id, {
        name: newPhaseName.trim(),
      });
      phases = [...phases, response.phase];
      newPhaseName = '';
      showPhaseForm = false;
    } catch (error) {
      console.error('Failed to add phase:', error);
    }
  }

  async function deletePhase(phase: PlaybookPhase) {
    if (!confirm(`Delete phase "${phase.name}"? Tasks will be moved to uncategorized.`)) return;

    try {
      await playbooksApi.deletePhase(playbook!.id, phase.id);
      phases = phases.filter(p => p.id !== phase.id);
      // Move tasks to uncategorized
      tasks = tasks.map(t => t.phase_id === phase.id ? { ...t, phase_id: undefined } : t);
    } catch (error) {
      console.error('Failed to delete phase:', error);
    }
  }

  async function addTask(phaseId?: number) {
    newTaskPhaseId = phaseId ?? null;
    editingTask = null;
    showTaskForm = true;
  }

  async function saveTask(taskData: Partial<PlaybookTask>) {
    if (!playbook?.id) return;

    try {
      if (editingTask?.id) {
        const response = await playbooksApi.updateTask(playbook.id, editingTask.id, taskData);
        tasks = tasks.map(t => t.id === editingTask!.id ? response.task : t);
      } else {
        const response = await playbooksApi.addTask(playbook.id, {
          ...taskData,
          title: taskData.title!,
          phase_id: newTaskPhaseId ?? undefined,
        });
        tasks = [...tasks, response.task];
      }
      showTaskForm = false;
      editingTask = null;
    } catch (error) {
      console.error('Failed to save task:', error);
    }
  }

  async function deleteTask(task: PlaybookTask) {
    if (!confirm(`Delete task "${task.title}"?`)) return;

    try {
      await playbooksApi.deleteTask(playbook!.id, task.id);
      tasks = tasks.filter(t => t.id !== task.id);
    } catch (error) {
      console.error('Failed to delete task:', error);
    }
  }

  function getTasksForPhase(phaseId: number | undefined): PlaybookTask[] {
    return tasks.filter(t => t.phase_id === phaseId).sort((a, b) => a.display_order - b.display_order);
  }

  const triggerModuleOptions = [
    { value: '', label: 'Manual Start' },
    { value: 'deals', label: 'Deals' },
    { value: 'accounts', label: 'Accounts' },
    { value: 'contacts', label: 'Contacts' },
  ];

  const triggerConditionOptions = [
    { value: 'created', label: 'When Created' },
    { value: 'stage_change', label: 'Stage Change' },
    { value: 'field_update', label: 'Field Update' },
  ];
</script>

<div class="space-y-6">
  <!-- Header -->
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
      {#if onBack}
        <Button variant="ghost" size="icon" onclick={onBack}>
          <ArrowLeft class="h-5 w-5" />
        </Button>
      {/if}
      <div>
        <h1 class="text-2xl font-semibold">
          {playbook?.id ? 'Edit Playbook' : 'Create Playbook'}
        </h1>
        <p class="text-muted-foreground">Design your customer onboarding workflow</p>
      </div>
    </div>
    <Button onclick={handleSave} disabled={saving || !name.trim()}>
      <Save class="mr-2 h-4 w-4" />
      {saving ? 'Saving...' : 'Save Playbook'}
    </Button>
  </div>

  <Tabs.Root bind:value={activeTab}>
    <Tabs.List>
      <Tabs.Trigger value="settings">Settings</Tabs.Trigger>
      <Tabs.Trigger value="tasks" disabled={!playbook?.id}>Tasks & Phases</Tabs.Trigger>
    </Tabs.List>

    <div class="mt-6">
      <!-- Settings Tab -->
      <Tabs.Content value="settings">
        <Card.Root>
          <Card.Header>
            <Card.Title>Playbook Settings</Card.Title>
          </Card.Header>
          <Card.Content class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <Label for="name">Name *</Label>
                <Input
                  id="name"
                  bind:value={name}
                  placeholder="e.g., Customer Onboarding"
                  required
                />
              </div>

              <div class="space-y-2">
                <Label for="estimatedDays">Estimated Duration (days)</Label>
                <Input
                  id="estimatedDays"
                  type="number"
                  min="1"
                  bind:value={estimatedDays}
                  placeholder="e.g., 30"
                />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="description">Description</Label>
              <Textarea
                id="description"
                bind:value={description}
                placeholder="Describe the purpose of this playbook..."
                rows={3}
              />
            </div>

            <Separator />

            <div class="space-y-4">
              <h3 class="font-medium">Trigger Settings</h3>

              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                  <Label>Trigger Module</Label>
                  <select
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    bind:value={triggerModule}
                  >
                    {#each triggerModuleOptions as option}
                      <option value={option.value}>{option.label}</option>
                    {/each}
                  </select>
                </div>

                {#if triggerModule}
                  <div class="space-y-2">
                    <Label>Trigger Condition</Label>
                    <select
                      class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                      bind:value={triggerCondition}
                    >
                      {#each triggerConditionOptions as option}
                        <option value={option.value}>{option.label}</option>
                      {/each}
                    </select>
                  </div>
                {/if}
              </div>
            </div>

            <Separator />

            <div class="space-y-4">
              <h3 class="font-medium">Options</h3>

              <div class="flex items-center justify-between">
                <div>
                  <Label>Active</Label>
                  <p class="text-sm text-muted-foreground">
                    Allow this playbook to be started
                  </p>
                </div>
                <Switch bind:checked={isActive} />
              </div>

              <div class="flex items-center justify-between">
                <div>
                  <Label>Auto-assign</Label>
                  <p class="text-sm text-muted-foreground">
                    Automatically start when trigger conditions are met
                  </p>
                </div>
                <Switch bind:checked={autoAssign} disabled={!triggerModule} />
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      </Tabs.Content>

      <!-- Tasks Tab -->
      <Tabs.Content value="tasks">
        <div class="space-y-6">
          {#if !playbook?.id}
            <Card.Root>
              <Card.Content class="py-8 text-center text-muted-foreground">
                Save the playbook first to add tasks and phases
              </Card.Content>
            </Card.Root>
          {:else}
            <!-- Add Phase -->
            <div class="flex items-center gap-2">
              {#if showPhaseForm}
                <Input
                  bind:value={newPhaseName}
                  placeholder="Phase name"
                  class="max-w-xs"
                />
                <Button onclick={addPhase} disabled={!newPhaseName.trim()}>
                  Add
                </Button>
                <Button variant="outline" onclick={() => { showPhaseForm = false; newPhaseName = ''; }}>
                  Cancel
                </Button>
              {:else}
                <Button variant="outline" onclick={() => showPhaseForm = true}>
                  <FolderPlus class="mr-2 h-4 w-4" />
                  Add Phase
                </Button>
              {/if}
            </div>

            <!-- Phases and Tasks -->
            {#each phases as phase}
              <Card.Root>
                <Card.Header>
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                      <Card.Title class="text-lg">{phase.name}</Card.Title>
                      {#if phase.target_days}
                        <Badge variant="outline">
                          <Clock class="mr-1 h-3 w-3" />
                          Day {phase.target_days}
                        </Badge>
                      {/if}
                    </div>
                    <div class="flex items-center gap-2">
                      <Button variant="ghost" size="sm" onclick={() => addTask(phase.id)}>
                        <Plus class="mr-1 h-3 w-3" />
                        Add Task
                      </Button>
                      <Button variant="ghost" size="icon" onclick={() => deletePhase(phase)}>
                        <Trash2 class="h-4 w-4 text-red-500" />
                      </Button>
                    </div>
                  </div>
                </Card.Header>
                <Card.Content>
                  {#if getTasksForPhase(phase.id).length === 0}
                    <div class="text-center py-4 text-muted-foreground text-sm">
                      No tasks in this phase
                    </div>
                  {:else}
                    <div class="space-y-2">
                      {#each getTasksForPhase(phase.id) as task}
                        <div class="flex items-center gap-3 p-3 rounded-lg border bg-muted/30">
                          <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                          <div class="flex-1">
                            <div class="flex items-center gap-2">
                              <span class="font-medium">{task.title}</span>
                              {#if task.is_required}
                                <Badge variant="destructive" class="text-xs">Required</Badge>
                              {/if}
                              {#if task.is_milestone}
                                <Badge variant="default" class="text-xs">Milestone</Badge>
                              {/if}
                            </div>
                            {#if task.due_days}
                              <span class="text-sm text-muted-foreground">
                                Due: Day {task.due_days}
                              </span>
                            {/if}
                          </div>
                          <Button
                            variant="ghost"
                            size="icon"
                            onclick={() => { editingTask = task; showTaskForm = true; }}
                          >
                            <Edit2 class="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onclick={() => deleteTask(task)}>
                            <Trash2 class="h-4 w-4 text-red-500" />
                          </Button>
                        </div>
                      {/each}
                    </div>
                  {/if}
                </Card.Content>
              </Card.Root>
            {/each}

            <!-- Uncategorized Tasks -->
            {#if getTasksForPhase(undefined).length > 0 || phases.length === 0}
              <Card.Root>
                <Card.Header>
                  <div class="flex items-center justify-between">
                    <Card.Title class="text-lg">
                      {phases.length > 0 ? 'Uncategorized Tasks' : 'Tasks'}
                    </Card.Title>
                    <Button variant="ghost" size="sm" onclick={() => addTask()}>
                      <Plus class="mr-1 h-3 w-3" />
                      Add Task
                    </Button>
                  </div>
                </Card.Header>
                <Card.Content>
                  {#if getTasksForPhase(undefined).length === 0}
                    <div class="text-center py-4 text-muted-foreground text-sm">
                      No tasks yet. Add your first task!
                    </div>
                  {:else}
                    <div class="space-y-2">
                      {#each getTasksForPhase(undefined) as task}
                        <div class="flex items-center gap-3 p-3 rounded-lg border bg-muted/30">
                          <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
                          <div class="flex-1">
                            <div class="flex items-center gap-2">
                              <span class="font-medium">{task.title}</span>
                              {#if task.is_required}
                                <Badge variant="destructive" class="text-xs">Required</Badge>
                              {/if}
                              {#if task.is_milestone}
                                <Badge variant="default" class="text-xs">Milestone</Badge>
                              {/if}
                            </div>
                            {#if task.due_days}
                              <span class="text-sm text-muted-foreground">
                                Due: Day {task.due_days}
                              </span>
                            {/if}
                          </div>
                          <Button
                            variant="ghost"
                            size="icon"
                            onclick={() => { editingTask = task; showTaskForm = true; }}
                          >
                            <Edit2 class="h-4 w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onclick={() => deleteTask(task)}>
                            <Trash2 class="h-4 w-4 text-red-500" />
                          </Button>
                        </div>
                      {/each}
                    </div>
                  {/if}
                </Card.Content>
              </Card.Root>
            {/if}
          {/if}
        </div>
      </Tabs.Content>
    </div>
  </Tabs.Root>
</div>

<!-- Task Edit Modal would go here -->
{#if showTaskForm}
  {@const formTask = editingTask || {} as Partial<PlaybookTask>}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <Card.Root class="w-full max-w-lg">
      <Card.Header>
        <Card.Title>{editingTask ? 'Edit Task' : 'Add Task'}</Card.Title>
      </Card.Header>
      <Card.Content>
        <form
          class="space-y-4"
          onsubmit={(e) => {
            e.preventDefault();
            const form = e.currentTarget as HTMLFormElement;
            const formData = new FormData(form);
            saveTask({
              title: formData.get('title') as string,
              description: formData.get('description') as string || undefined,
              due_days: formData.get('due_days') ? parseInt(formData.get('due_days') as string) : undefined,
              is_required: formData.get('is_required') === 'on',
              is_milestone: formData.get('is_milestone') === 'on',
            });
          }}
        >
          <div class="space-y-2">
            <Label for="title">Task Title *</Label>
            <Input
              id="title"
              name="title"
              value={formTask.title || ''}
              placeholder="What needs to be done?"
              required
            />
          </div>

          <div class="space-y-2">
            <Label for="description">Description</Label>
            <Textarea
              id="description"
              name="description"
              value={formTask.description || ''}
              placeholder="Additional details..."
              rows={3}
            />
          </div>

          <div class="space-y-2">
            <Label for="due_days">Due (days from start)</Label>
            <Input
              id="due_days"
              name="due_days"
              type="number"
              min="0"
              value={formTask.due_days?.toString() || ''}
              placeholder="e.g., 7"
            />
          </div>

          <div class="flex items-center gap-6">
            <label class="flex items-center gap-2">
              <input type="checkbox" name="is_required" checked={formTask.is_required} />
              <span class="text-sm">Required</span>
            </label>
            <label class="flex items-center gap-2">
              <input type="checkbox" name="is_milestone" checked={formTask.is_milestone} />
              <span class="text-sm">Milestone</span>
            </label>
          </div>

          <div class="flex justify-end gap-2 pt-4">
            <Button type="button" variant="outline" onclick={() => { showTaskForm = false; editingTask = null; }}>
              Cancel
            </Button>
            <Button type="submit">
              {editingTask ? 'Update Task' : 'Add Task'}
            </Button>
          </div>
        </form>
      </Card.Content>
    </Card.Root>
  </div>
{/if}
