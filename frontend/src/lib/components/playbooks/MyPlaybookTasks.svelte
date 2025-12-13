<script lang="ts">
  import { onMount } from 'svelte';
  import { playbookInstancesApi, type PlaybookTaskInstance } from '$lib/api/playbooks';
  import * as Card from '$lib/components/ui/card';
  import * as Tabs from '$lib/components/ui/tabs';
  import { Button } from '$lib/components/ui/button';
  import { Badge } from '$lib/components/ui/badge';
  import {
    AlertTriangle,
    Clock,
    PlayCircle,
    CheckCircle,
    Calendar,
    ArrowRight,
  } from 'lucide-svelte';

  interface Props {
    onSelectInstance?: (instanceId: number) => void;
  }

  let { onSelectInstance }: Props = $props();

  let overdueTasks = $state<PlaybookTaskInstance[]>([]);
  let inProgressTasks = $state<PlaybookTaskInstance[]>([]);
  let upcomingTasks = $state<PlaybookTaskInstance[]>([]);
  let loading = $state(true);
  let activeTab = $state('overdue');

  async function loadTasks() {
    loading = true;
    try {
      const response = await playbookInstancesApi.myTasks(14);
      overdueTasks = response.overdue;
      inProgressTasks = response.in_progress;
      upcomingTasks = response.upcoming;
    } catch (error) {
      console.error('Failed to load tasks:', error);
    } finally {
      loading = false;
    }
  }

  async function completeTask(task: PlaybookTaskInstance) {
    try {
      await playbookInstancesApi.completeTask(task.instance_id, task.id);
      await loadTasks();
    } catch (error) {
      console.error('Failed to complete task:', error);
    }
  }

  async function startTask(task: PlaybookTaskInstance) {
    try {
      await playbookInstancesApi.startTask(task.instance_id, task.id);
      await loadTasks();
    } catch (error) {
      console.error('Failed to start task:', error);
    }
  }

  function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }

  function getDaysOverdue(dueAt: string): number {
    const due = new Date(dueAt);
    const now = new Date();
    return Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24));
  }

  onMount(() => {
    loadTasks();
  });
</script>

<Card.Root>
  <Card.Header>
    <Card.Title>My Playbook Tasks</Card.Title>
    <Card.Description>Tasks assigned to you across all active playbooks</Card.Description>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else}
      <Tabs.Root bind:value={activeTab}>
        <Tabs.List class="grid w-full grid-cols-3">
          <Tabs.Trigger value="overdue" class="relative">
            <AlertTriangle class="mr-2 h-4 w-4" />
            Overdue
            {#if overdueTasks.length > 0}
              <Badge variant="destructive" class="ml-2">{overdueTasks.length}</Badge>
            {/if}
          </Tabs.Trigger>
          <Tabs.Trigger value="in_progress">
            <PlayCircle class="mr-2 h-4 w-4" />
            In Progress
            {#if inProgressTasks.length > 0}
              <Badge variant="secondary" class="ml-2">{inProgressTasks.length}</Badge>
            {/if}
          </Tabs.Trigger>
          <Tabs.Trigger value="upcoming">
            <Calendar class="mr-2 h-4 w-4" />
            Upcoming
            {#if upcomingTasks.length > 0}
              <Badge variant="outline" class="ml-2">{upcomingTasks.length}</Badge>
            {/if}
          </Tabs.Trigger>
        </Tabs.List>

        <div class="mt-4">
          <Tabs.Content value="overdue">
            {#if overdueTasks.length === 0}
              <div class="text-center py-8 text-muted-foreground">
                <CheckCircle class="h-12 w-12 mx-auto mb-2 text-green-500" />
                <p>No overdue tasks!</p>
              </div>
            {:else}
              <div class="space-y-3">
                {#each overdueTasks as task}
                  <div class="flex items-center gap-4 p-3 rounded-lg border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20">
                    <AlertTriangle class="h-5 w-5 text-red-500 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                      <div class="font-medium">{task.task?.title}</div>
                      <div class="text-sm text-muted-foreground">
                        {task.instance?.playbook?.name}
                      </div>
                      {#if task.due_at}
                        <div class="text-xs text-red-600">
                          {getDaysOverdue(task.due_at)} days overdue
                        </div>
                      {/if}
                    </div>
                    <div class="flex items-center gap-2">
                      <Button size="sm" onclick={() => completeTask(task)}>
                        <CheckCircle class="mr-1 h-3 w-3" />
                        Complete
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onclick={() => onSelectInstance?.(task.instance_id)}
                      >
                        <ArrowRight class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                {/each}
              </div>
            {/if}
          </Tabs.Content>

          <Tabs.Content value="in_progress">
            {#if inProgressTasks.length === 0}
              <div class="text-center py-8 text-muted-foreground">
                <Clock class="h-12 w-12 mx-auto mb-2" />
                <p>No tasks in progress</p>
              </div>
            {:else}
              <div class="space-y-3">
                {#each inProgressTasks as task}
                  <div class="flex items-center gap-4 p-3 rounded-lg border">
                    <PlayCircle class="h-5 w-5 text-blue-500 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                      <div class="font-medium">{task.task?.title}</div>
                      <div class="text-sm text-muted-foreground">
                        {task.instance?.playbook?.name}
                      </div>
                      {#if task.started_at}
                        <div class="text-xs text-muted-foreground">
                          Started {formatDate(task.started_at)}
                        </div>
                      {/if}
                    </div>
                    <div class="flex items-center gap-2">
                      <Button size="sm" onclick={() => completeTask(task)}>
                        <CheckCircle class="mr-1 h-3 w-3" />
                        Complete
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onclick={() => onSelectInstance?.(task.instance_id)}
                      >
                        <ArrowRight class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                {/each}
              </div>
            {/if}
          </Tabs.Content>

          <Tabs.Content value="upcoming">
            {#if upcomingTasks.length === 0}
              <div class="text-center py-8 text-muted-foreground">
                <Calendar class="h-12 w-12 mx-auto mb-2" />
                <p>No upcoming tasks in the next 14 days</p>
              </div>
            {:else}
              <div class="space-y-3">
                {#each upcomingTasks as task}
                  <div class="flex items-center gap-4 p-3 rounded-lg border">
                    <Clock class="h-5 w-5 text-muted-foreground flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                      <div class="font-medium">{task.task?.title}</div>
                      <div class="text-sm text-muted-foreground">
                        {task.instance?.playbook?.name}
                      </div>
                      {#if task.due_at}
                        <div class="text-xs text-muted-foreground">
                          Due {formatDate(task.due_at)}
                        </div>
                      {/if}
                    </div>
                    <div class="flex items-center gap-2">
                      <Button size="sm" variant="outline" onclick={() => startTask(task)}>
                        <PlayCircle class="mr-1 h-3 w-3" />
                        Start
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onclick={() => onSelectInstance?.(task.instance_id)}
                      >
                        <ArrowRight class="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                {/each}
              </div>
            {/if}
          </Tabs.Content>
        </div>
      </Tabs.Root>
    {/if}
  </Card.Content>
</Card.Root>
