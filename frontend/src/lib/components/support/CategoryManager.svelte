<script lang="ts">
  import { onMount } from 'svelte';
  import { ticketCategoriesApi, type TicketCategory } from '$lib/api/support';
  import * as Card from '$lib/components/ui/card';
  import * as Table from '$lib/components/ui/table';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Switch } from '$lib/components/ui/switch';
  import * as Select from '$lib/components/ui/select';
  import { Badge } from '$lib/components/ui/badge';
  import { Plus, Edit2, Trash2, GripVertical } from 'lucide-svelte';

  let categories = $state<TicketCategory[]>([]);
  let loading = $state(true);
  let showDialog = $state(false);
  let editingCategory = $state<TicketCategory | null>(null);

  // Form state
  let name = $state('');
  let description = $state('');
  let color = $state('#3b82f6');
  let defaultPriority = $state<string>('2');
  let slaResponseHours = $state('');
  let slaResolutionHours = $state('');
  let isActive = $state(true);
  let saving = $state(false);

  const priorityOptions = [
    { value: '1', label: 'Low' },
    { value: '2', label: 'Medium' },
    { value: '3', label: 'High' },
    { value: '4', label: 'Urgent' },
  ];

  async function loadCategories() {
    loading = true;
    try {
      const response = await ticketCategoriesApi.list();
      categories = response.categories;
    } catch (error) {
      console.error('Failed to load categories:', error);
    } finally {
      loading = false;
    }
  }

  function openCreateDialog() {
    editingCategory = null;
    name = '';
    description = '';
    color = '#3b82f6';
    defaultPriority = '2';
    slaResponseHours = '';
    slaResolutionHours = '';
    isActive = true;
    showDialog = true;
  }

  function openEditDialog(category: TicketCategory) {
    editingCategory = category;
    name = category.name;
    description = category.description || '';
    color = category.color;
    defaultPriority = String(category.default_priority);
    slaResponseHours = category.sla_response_hours?.toString() || '';
    slaResolutionHours = category.sla_resolution_hours?.toString() || '';
    isActive = category.is_active;
    showDialog = true;
  }

  async function handleSubmit() {
    if (!name.trim()) return;

    saving = true;
    try {
      const data = {
        name: name.trim(),
        description: description.trim() || undefined,
        color,
        default_priority: parseInt(defaultPriority),
        sla_response_hours: slaResponseHours ? parseInt(slaResponseHours) : undefined,
        sla_resolution_hours: slaResolutionHours ? parseInt(slaResolutionHours) : undefined,
        is_active: isActive,
      };

      if (editingCategory) {
        await ticketCategoriesApi.update(editingCategory.id, data);
      } else {
        await ticketCategoriesApi.create(data);
      }

      showDialog = false;
      await loadCategories();
    } catch (error) {
      console.error('Failed to save category:', error);
    } finally {
      saving = false;
    }
  }

  async function deleteCategory(category: TicketCategory) {
    if (!confirm(`Delete category "${category.name}"? This cannot be undone.`)) return;

    try {
      await ticketCategoriesApi.delete(category.id);
      await loadCategories();
    } catch (error) {
      console.error('Failed to delete category:', error);
      alert('Cannot delete category. It may have associated tickets.');
    }
  }

  function getPriorityLabel(priority: number): string {
    return priorityOptions.find(p => p.value === String(priority))?.label || 'Unknown';
  }

  onMount(() => {
    loadCategories();
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <Card.Title>Ticket Categories</Card.Title>
      <Button onclick={openCreateDialog}>
        <Plus class="mr-2 h-4 w-4" />
        Add Category
      </Button>
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if categories.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        No categories found. Create one to get started.
      </div>
    {:else}
      <Table.Root>
        <Table.Header>
          <Table.Row>
            <Table.Head class="w-[50px]"></Table.Head>
            <Table.Head>Name</Table.Head>
            <Table.Head>Default Priority</Table.Head>
            <Table.Head>SLA Response</Table.Head>
            <Table.Head>SLA Resolution</Table.Head>
            <Table.Head>Tickets</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="w-[100px]"></Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each categories as category}
            <Table.Row>
              <Table.Cell>
                <GripVertical class="h-4 w-4 text-muted-foreground cursor-move" />
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-2">
                  <div
                    class="w-4 h-4 rounded"
                    style="background-color: {category.color}"
                  ></div>
                  <span class="font-medium">{category.name}</span>
                </div>
                {#if category.description}
                  <p class="text-sm text-muted-foreground truncate max-w-[200px]">
                    {category.description}
                  </p>
                {/if}
              </Table.Cell>
              <Table.Cell>
                {getPriorityLabel(category.default_priority)}
              </Table.Cell>
              <Table.Cell>
                {category.sla_response_hours ? `${category.sla_response_hours}h` : '-'}
              </Table.Cell>
              <Table.Cell>
                {category.sla_resolution_hours ? `${category.sla_resolution_hours}h` : '-'}
              </Table.Cell>
              <Table.Cell>
                {category.tickets_count || 0}
              </Table.Cell>
              <Table.Cell>
                <Badge variant={category.is_active ? 'default' : 'secondary'}>
                  {category.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </Table.Cell>
              <Table.Cell>
                <div class="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="icon"
                    onclick={() => openEditDialog(category)}
                  >
                    <Edit2 class="h-4 w-4" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="icon"
                    onclick={() => deleteCategory(category)}
                  >
                    <Trash2 class="h-4 w-4 text-red-500" />
                  </Button>
                </div>
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    {/if}
  </Card.Content>
</Card.Root>

<Dialog.Root bind:open={showDialog}>
  <Dialog.Content class="max-w-md">
    <Dialog.Header>
      <Dialog.Title>
        {editingCategory ? 'Edit Category' : 'Create Category'}
      </Dialog.Title>
    </Dialog.Header>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
      <div class="space-y-2">
        <Label for="name">Name *</Label>
        <Input id="name" bind:value={name} placeholder="Category name" required />
      </div>

      <div class="space-y-2">
        <Label for="description">Description</Label>
        <Textarea id="description" bind:value={description} placeholder="Category description" rows={2} />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label for="color">Color</Label>
          <div class="flex items-center gap-2">
            <input
              type="color"
              id="color"
              bind:value={color}
              class="w-10 h-10 rounded border cursor-pointer"
            />
            <Input bind:value={color} class="flex-1" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Default Priority</Label>
          <Select.Root type="single" value={defaultPriority} onValueChange={(val) => { if (val) defaultPriority = val; }}>
            <Select.Trigger>
              {priorityOptions.find(o => o.value === defaultPriority)?.label || 'Select priority'}
            </Select.Trigger>
            <Select.Content>
              {#each priorityOptions as option}
                <Select.Item value={option.value}>
                  {option.label}
                </Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label for="slaResponse">SLA Response (hours)</Label>
          <Input
            id="slaResponse"
            type="number"
            min="1"
            bind:value={slaResponseHours}
            placeholder="e.g., 4"
          />
        </div>

        <div class="space-y-2">
          <Label for="slaResolution">SLA Resolution (hours)</Label>
          <Input
            id="slaResolution"
            type="number"
            min="1"
            bind:value={slaResolutionHours}
            placeholder="e.g., 24"
          />
        </div>
      </div>

      <div class="flex items-center gap-2">
        <Switch id="isActive" bind:checked={isActive} />
        <Label for="isActive">Active</Label>
      </div>

      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => showDialog = false} disabled={saving}>
          Cancel
        </Button>
        <Button type="submit" disabled={saving || !name.trim()}>
          {saving ? 'Saving...' : editingCategory ? 'Update' : 'Create'}
        </Button>
      </Dialog.Footer>
    </form>
  </Dialog.Content>
</Dialog.Root>
