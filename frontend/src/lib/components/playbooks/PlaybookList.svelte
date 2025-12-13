<script lang="ts">
  import { onMount } from 'svelte';
  import { playbooksApi, type Playbook } from '$lib/api/playbooks';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Badge } from '$lib/components/ui/badge';
  import {
    Search,
    Plus,
    PlayCircle,
    Pause,
    CheckCircle,
    ListTodo,
    Users,
    Clock,
    MoreHorizontal,
    Copy,
    Edit,
    Trash2,
  } from 'lucide-svelte';
  import * as DropdownMenu from '$lib/components/ui/dropdown-menu';

  interface Props {
    onSelect?: (playbook: Playbook) => void;
    onCreate?: () => void;
  }

  let { onSelect, onCreate }: Props = $props();

  let playbooks = $state<Playbook[]>([]);
  let loading = $state(true);
  let searchQuery = $state('');
  let currentPage = $state(1);
  let totalPages = $state(1);

  async function loadPlaybooks() {
    loading = true;
    try {
      const response = await playbooksApi.list({
        search: searchQuery || undefined,
        page: currentPage,
      });
      playbooks = response.data;
      totalPages = response.last_page;
    } catch (error) {
      console.error('Failed to load playbooks:', error);
    } finally {
      loading = false;
    }
  }

  async function duplicatePlaybook(playbook: Playbook) {
    try {
      await playbooksApi.duplicate(playbook.id);
      await loadPlaybooks();
    } catch (error) {
      console.error('Failed to duplicate playbook:', error);
    }
  }

  async function deletePlaybook(playbook: Playbook) {
    if (!confirm(`Delete playbook "${playbook.name}"? This cannot be undone.`)) return;

    try {
      await playbooksApi.delete(playbook.id);
      await loadPlaybooks();
    } catch (error) {
      console.error('Failed to delete playbook:', error);
      alert('Cannot delete playbook with active instances');
    }
  }

  onMount(() => {
    loadPlaybooks();
  });

  $effect(() => {
    if (searchQuery !== undefined) {
      const timeout = setTimeout(() => {
        currentPage = 1;
        loadPlaybooks();
      }, 300);
      return () => clearTimeout(timeout);
    }
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between gap-4">
      <div class="relative flex-1 max-w-md">
        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Search playbooks..."
          bind:value={searchQuery}
          class="pl-9"
        />
      </div>
      {#if onCreate}
        <Button onclick={onCreate}>
          <Plus class="mr-2 h-4 w-4" />
          New Playbook
        </Button>
      {/if}
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if playbooks.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        {searchQuery ? 'No playbooks found' : 'No playbooks yet. Create your first one!'}
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head>Name</Table.Head>
            <Table.Head>Trigger</Table.Head>
            <Table.Head>Tasks</Table.Head>
            <Table.Head>Active Instances</Table.Head>
            <Table.Head>Est. Duration</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="w-[50px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each playbooks as playbook}
            <Table.Row
              class="cursor-pointer hover:bg-muted/50"
              onclick={() => onSelect?.(playbook)}
            >
              <Table.Cell>
                <div>
                  <div class="font-medium">{playbook.name}</div>
                  {#if playbook.description}
                    <div class="text-sm text-muted-foreground truncate max-w-[250px]">
                      {playbook.description}
                    </div>
                  {/if}
                </div>
              </Table.Cell>
              <Table.Cell>
                {#if playbook.trigger_module}
                  <Badge variant="outline">
                    {playbook.trigger_module}
                  </Badge>
                {:else}
                  <span class="text-muted-foreground">Manual</span>
                {/if}
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  <ListTodo class="h-4 w-4 text-muted-foreground" />
                  <span>{playbook.tasks_count || 0}</span>
                </div>
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  <Users class="h-4 w-4 text-muted-foreground" />
                  <span>{playbook.instances_count || 0}</span>
                </div>
              </Table.Cell>
              <Table.Cell>
                {#if playbook.estimated_days}
                  <div class="flex items-center gap-1">
                    <Clock class="h-4 w-4 text-muted-foreground" />
                    <span>{playbook.estimated_days} days</span>
                  </div>
                {:else}
                  <span class="text-muted-foreground">-</span>
                {/if}
              </Table.Cell>
              <Table.Cell>
                <Badge variant={playbook.is_active ? 'default' : 'secondary'}>
                  {playbook.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <DropdownMenu.Root>
                  <DropdownMenu.Trigger>
                    {#snippet child({ props })}
                      <Button variant="ghost" size="icon" {...props} onclick={(e) => e.stopPropagation()}>
                        <MoreHorizontal class="h-4 w-4" />
                      </Button>
                    {/snippet}
                  </DropdownMenu.Trigger>
                  <DropdownMenu.Content align="end">
                    <DropdownMenu.Item onclick={() => onSelect?.(playbook)}>
                      <Edit class="mr-2 h-4 w-4" />
                      Edit
                    </DropdownMenu.Item>
                    <DropdownMenu.Item onclick={() => duplicatePlaybook(playbook)}>
                      <Copy class="mr-2 h-4 w-4" />
                      Duplicate
                    </DropdownMenu.Item>
                    <DropdownMenu.Separator />
                    <DropdownMenu.Item onclick={() => deletePlaybook(playbook)} class="text-red-600">
                      <Trash2 class="mr-2 h-4 w-4" />
                      Delete
                    </DropdownMenu.Item>
                  </DropdownMenu.Content>
                </DropdownMenu.Root>
              </Table.Cell>
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
            onclick={() => { currentPage--; loadPlaybooks(); }}
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
            onclick={() => { currentPage++; loadPlaybooks(); }}
          >
            Next
          </Button>
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
