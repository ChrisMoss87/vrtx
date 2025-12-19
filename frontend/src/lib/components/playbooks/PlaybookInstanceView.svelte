<script lang="ts">
  import { onMount } from 'svelte';
  import {
    playbookInstancesApi,
    type PlaybookInstance,
    type PlaybookTaskInstance,
  } from '$lib/api/playbooks';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import { Progress } from '$lib/components/ui/progress';
  import { Separator } from '$lib/components/ui/separator';
  import * as Timeline from '$lib/components/ui/timeline';
  import {
    ArrowLeft,
    Play,
    Pause,
    X,
    CheckCircle,
    Circle,
    Clock,
    AlertTriangle,
    User,
    SkipForward,
    RefreshCw,
  } from 'lucide-svelte';

  interface Props {
    instanceId: number;
    onBack?: () => void;
  }

  let { instanceId, onBack }: Props = $props();

  let instance = $state<PlaybookInstance | null>(null);
  let loading = $state(true);
  let actionLoading = $state<number | null>(null);

  async function loadInstance() {
    loading = true;
    try {
      const response = await playbookInstancesApi.get(instanceId);
      instance = response.instance;
    } catch (error) {
      console.error('Failed to load instance:', error);
    } finally {
      loading = false;
    }
  }

  async function startTask(taskInstance: PlaybookTaskInstance) {
    actionLoading = taskInstance.id;
    try {
      await playbookInstancesApi.startTask(instanceId, taskInstance.id);
      await loadInstance();
    } catch (error) {
      console.error('Failed to start task:', error);
    } finally {
      actionLoading = null;
    }
  }

  async function completeTask(taskInstance: PlaybookTaskInstance) {
    actionLoading = taskInstance.id;
    try {
      await playbookInstancesApi.completeTask(instanceId, taskInstance.id);
      await loadInstance();
    } catch (error) {
      console.error('Failed to complete task:', error);
    } finally {
      actionLoading = null;
    }
  }

  async function skipTask(taskInstance: PlaybookTaskInstance) {
    if (!confirm('Skip this task? This cannot be undone.')) return;

    actionLoading = taskInstance.id;
    try {
      await playbookInstancesApi.skipTask(instanceId, taskInstance.id);
      await loadInstance();
    } catch (error) {
      console.error('Failed to skip task:', error);
    } finally {
      actionLoading = null;
    }
  }

  async function pausePlaybook() {
    try {
      await playbookInstancesApi.pause(instanceId);
      await loadInstance();
    } catch (error) {
      console.error('Failed to pause:', error);
    }
  }

  async function resumePlaybook() {
    try {
      await playbookInstancesApi.resume(instanceId);
      await loadInstance();
    } catch (error) {
      console.error('Failed to resume:', error);
    }
  }

  async function cancelPlaybook() {
    if (!confirm('Cancel this playbook? This cannot be undone.')) return;

    try {
      await playbookInstancesApi.cancel(instanceId);
      await loadInstance();
    } catch (error) {
      console.error('Failed to cancel:', error);
    }
  }

  function getStatusIcon(status: string) {
    switch (status) {
      case 'completed':
        return CheckCircle;
      case 'in_progress':
        return RefreshCw;
      case 'skipped':
        return SkipForward;
      case 'blocked':
        return AlertTriangle;
      default:
        return Circle;
    }
  }

  function getStatusColor(status: string): string {
    switch (status) {
      case 'completed':
        return 'text-green-500';
      case 'in_progress':
        return 'text-blue-500';
      case 'skipped':
        return 'text-gray-400';
      case 'blocked':
        return 'text-red-500';
      default:
        return 'text-muted-foreground';
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function isOverdue(taskInstance: PlaybookTaskInstance): boolean {
    if (taskInstance.status !== 'pending' || !taskInstance.due_at) return false;
    return new Date(taskInstance.due_at) < new Date();
  }

  onMount(() => {
    loadInstance();
  });
</script>

{#if loading}
  <div class="flex items-center justify-center py-8">
    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
  </div>
{:else if instance}
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
          <h1 class="text-2xl font-semibold">{instance.playbook?.name}</h1>
          <p class="text-muted-foreground">
            Started {formatDate(instance.started_at)}
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Badge
          variant={instance.status === 'active' ? 'default' : instance.status === 'completed' ? 'outline' : 'secondary'}
          class={instance.status === 'completed' ? 'bg-green-500 text-white' : ''}
        >
          {instance.status}
        </Badge>
        {#if instance.status === 'active'}
          <Button variant="outline" size="sm" onclick={pausePlaybook}>
            <Pause class="mr-1 h-4 w-4" />
            Pause
          </Button>
          <Button variant="destructive" size="sm" onclick={cancelPlaybook}>
            <X class="mr-1 h-4 w-4" />
            Cancel
          </Button>
        {:else if instance.status === 'paused'}
          <Button variant="outline" size="sm" onclick={resumePlaybook}>
            <Play class="mr-1 h-4 w-4" />
            Resume
          </Button>
        {/if}
      </div>
    </div>

    <!-- Progress Card -->
    <Card.Root>
      <Card.Content class="pt-6">
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium">Progress</span>
            <span class="text-sm text-muted-foreground">{instance.progress_percent}%</span>
          </div>
          <Progress value={instance.progress_percent} class="h-2" />
          <div class="grid grid-cols-4 gap-4 text-center">
            <div>
              <div class="text-2xl font-bold">
                {instance.task_instances?.filter(t => t.status === 'completed').length || 0}
              </div>
              <div class="text-xs text-muted-foreground">Completed</div>
            </div>
            <div>
              <div class="text-2xl font-bold">
                {instance.task_instances?.filter(t => t.status === 'in_progress').length || 0}
              </div>
              <div class="text-xs text-muted-foreground">In Progress</div>
            </div>
            <div>
              <div class="text-2xl font-bold">
                {instance.task_instances?.filter(t => t.status === 'pending').length || 0}
              </div>
              <div class="text-xs text-muted-foreground">Pending</div>
            </div>
            <div>
              <div class="text-2xl font-bold text-red-500">
                {instance.task_instances?.filter(t => isOverdue(t)).length || 0}
              </div>
              <div class="text-xs text-muted-foreground">Overdue</div>
            </div>
          </div>
        </div>
      </Card.Content>
    </Card.Root>

    <!-- Tasks -->
    <Card.Root>
      <Card.Header>
        <Card.Title>Tasks</Card.Title>
      </Card.Header>
      <Card.Content>
        {#if !instance.task_instances || instance.task_instances.length === 0}
          <div class="text-center py-4 text-muted-foreground">
            No tasks in this playbook
          </div>
        {:else}
          <div class="space-y-4">
            {#each instance.task_instances as taskInstance}
              {@const StatusIcon = getStatusIcon(taskInstance.status)}
              {@const overdue = isOverdue(taskInstance)}
              <div class="flex items-start gap-4 p-4 rounded-lg border {overdue ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : ''}">
                <div class={getStatusColor(taskInstance.status)}>
                  <StatusIcon class="h-5 w-5" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{taskInstance.task?.title}</span>
                    {#if taskInstance.task?.is_required}
                      <Badge variant="destructive" class="text-xs">Required</Badge>
                    {/if}
                    {#if taskInstance.task?.is_milestone}
                      <Badge class="text-xs">Milestone</Badge>
                    {/if}
                    {#if overdue}
                      <Badge variant="destructive" class="text-xs">
                        <AlertTriangle class="mr-1 h-3 w-3" />
                        Overdue
                      </Badge>
                    {/if}
                  </div>
                  {#if taskInstance.task?.description}
                    <p class="text-sm text-muted-foreground mt-1">
                      {taskInstance.task.description}
                    </p>
                  {/if}
                  <div class="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                    {#if taskInstance.due_at}
                      <span class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        Due: {formatDate(taskInstance.due_at)}
                      </span>
                    {/if}
                    {#if taskInstance.assignee}
                      <span class="flex items-center gap-1">
                        <User class="h-3 w-3" />
                        {taskInstance.assignee.name}
                      </span>
                    {/if}
                    {#if taskInstance.completed_at}
                      <span class="flex items-center gap-1">
                        <CheckCircle class="h-3 w-3" />
                        Completed: {formatDate(taskInstance.completed_at)}
                      </span>
                    {/if}
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  {#if taskInstance.status === 'pending' && instance.status === 'active'}
                    <Button
                      size="sm"
                      variant="outline"
                      onclick={() => startTask(taskInstance)}
                      disabled={actionLoading === taskInstance.id}
                    >
                      <Play class="mr-1 h-3 w-3" />
                      Start
                    </Button>
                    {#if !taskInstance.task?.is_required}
                      <Button
                        size="sm"
                        variant="ghost"
                        onclick={() => skipTask(taskInstance)}
                        disabled={actionLoading === taskInstance.id}
                      >
                        <SkipForward class="h-4 w-4" />
                      </Button>
                    {/if}
                  {:else if taskInstance.status === 'in_progress' && instance.status === 'active'}
                    <Button
                      size="sm"
                      onclick={() => completeTask(taskInstance)}
                      disabled={actionLoading === taskInstance.id}
                    >
                      <CheckCircle class="mr-1 h-3 w-3" />
                      Complete
                    </Button>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Owner Info -->
    {#if instance.owner}
      <Card.Root>
        <Card.Header>
          <Card.Title class="text-sm">Owner</Card.Title>
        </Card.Header>
        <Card.Content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
              <User class="h-5 w-5 text-primary" />
            </div>
            <div>
              <div class="font-medium">{instance.owner.name}</div>
              <div class="text-sm text-muted-foreground">Playbook Owner</div>
            </div>
          </div>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>
{:else}
  <div class="text-center py-8 text-muted-foreground">
    Playbook instance not found
  </div>
{/if}
