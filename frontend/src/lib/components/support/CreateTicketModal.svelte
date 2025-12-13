<script lang="ts">
  import { ticketsApi, ticketCategoriesApi, type TicketCategory } from '$lib/api/support';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import * as Select from '$lib/components/ui/select';
  import { onMount } from 'svelte';

  interface Props {
    open: boolean;
    onClose: () => void;
    onCreated?: () => void;
  }

  let { open = $bindable(), onClose, onCreated }: Props = $props();

  let subject = $state('');
  let description = $state('');
  let priority = $state<string>('2');
  let categoryId = $state<string | undefined>();
  let tags = $state('');
  let saving = $state(false);
  let error = $state('');
  let categories = $state<TicketCategory[]>([]);

  const priorityOptions = [
    { value: '1', label: 'Low' },
    { value: '2', label: 'Medium' },
    { value: '3', label: 'High' },
    { value: '4', label: 'Urgent' },
  ];

  async function loadCategories() {
    try {
      const response = await ticketCategoriesApi.list({ active_only: true });
      categories = response.categories;
    } catch (err) {
      console.error('Failed to load categories:', err);
    }
  }

  async function handleSubmit() {
    if (!subject.trim() || !description.trim()) {
      error = 'Subject and description are required';
      return;
    }

    saving = true;
    error = '';

    try {
      await ticketsApi.create({
        subject: subject.trim(),
        description: description.trim(),
        priority: parseInt(priority),
        category_id: categoryId ? parseInt(categoryId) : undefined,
        tags: tags ? tags.split(',').map(t => t.trim()).filter(Boolean) : undefined,
      });

      // Reset form
      subject = '';
      description = '';
      priority = '2';
      categoryId = undefined;
      tags = '';

      onCreated?.();
      onClose();
    } catch (err) {
      error = 'Failed to create ticket';
      console.error(err);
    } finally {
      saving = false;
    }
  }

  onMount(() => {
    loadCategories();
  });
</script>

<Dialog.Root bind:open onOpenChange={(isOpen) => !isOpen && onClose()}>
  <Dialog.Content class="max-w-lg">
    <Dialog.Header>
      <Dialog.Title>Create Support Ticket</Dialog.Title>
      <Dialog.Description>
        Create a new support ticket to track and resolve customer issues.
      </Dialog.Description>
    </Dialog.Header>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
      {#if error}
        <div class="text-sm text-red-500 bg-red-50 dark:bg-red-950/20 p-3 rounded">
          {error}
        </div>
      {/if}

      <div class="space-y-2">
        <Label for="subject">Subject *</Label>
        <Input
          id="subject"
          bind:value={subject}
          placeholder="Brief summary of the issue"
          required
        />
      </div>

      <div class="space-y-2">
        <Label for="description">Description *</Label>
        <Textarea
          id="description"
          bind:value={description}
          placeholder="Detailed description of the issue..."
          rows={5}
          required
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label>Priority</Label>
          <Select.Root type="single" value={priority} onValueChange={(val) => { if (val) priority = val; }}>
            <Select.Trigger>
              {priorityOptions.find(o => o.value === priority)?.label || 'Select priority'}
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

        <div class="space-y-2">
          <Label>Category</Label>
          <Select.Root type="single" value={categoryId} onValueChange={(val) => { categoryId = val; }}>
            <Select.Trigger>
              {categories.find(c => String(c.id) === categoryId)?.name || 'Select category'}
            </Select.Trigger>
            <Select.Content>
              {#each categories as category}
                <Select.Item value={String(category.id)}>
                  <div class="flex items-center gap-2">
                    {#if category.color}
                      <div
                        class="w-3 h-3 rounded-full"
                        style="background-color: {category.color}"
                      ></div>
                    {/if}
                    {category.name}
                  </div>
                </Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>
      </div>

      <div class="space-y-2">
        <Label for="tags">Tags</Label>
        <Input
          id="tags"
          bind:value={tags}
          placeholder="Enter tags separated by commas"
        />
        <p class="text-xs text-muted-foreground">Separate multiple tags with commas</p>
      </div>

      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={onClose} disabled={saving}>
          Cancel
        </Button>
        <Button type="submit" disabled={saving}>
          {saving ? 'Creating...' : 'Create Ticket'}
        </Button>
      </Dialog.Footer>
    </form>
  </Dialog.Content>
</Dialog.Root>
