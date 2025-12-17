<script lang="ts">
  import { onMount } from 'svelte';
  import { portalAdminApi, type PortalAnnouncement } from '$lib/api/portal';
  import * as Card from '$lib/components/ui/card';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import { Textarea } from '$lib/components/ui/textarea';
  import { Badge } from '$lib/components/ui/badge';
  import * as Select from '$lib/components/ui/select';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Switch } from '$lib/components/ui/switch';
  import { Megaphone, Plus, Edit, Trash2, Info, AlertTriangle, CheckCircle, XCircle } from 'lucide-svelte';

  let announcements = $state<PortalAnnouncement[]>([]);
  let loading = $state(true);
  let showCreateModal = $state(false);
  let editingAnnouncement = $state<PortalAnnouncement | null>(null);

  // Form state
  let formTitle = $state('');
  let formContent = $state('');
  let formType = $state('info');
  let formIsActive = $state(true);
  let formIsDismissible = $state(true);
  let formStartsAt = $state('');
  let formEndsAt = $state('');
  let formError = $state('');
  let formLoading = $state(false);

  const announcementTypes = [
    { value: 'info', label: 'Information' },
    { value: 'warning', label: 'Warning' },
    { value: 'success', label: 'Success' },
    { value: 'error', label: 'Error' },
  ];

  const getTypeLabel = (value: string) => announcementTypes.find(t => t.value === value)?.label ?? value;

  async function loadAnnouncements() {
    loading = true;
    try {
      const response = await portalAdminApi.getAnnouncements();
      announcements = response.data;
    } catch (error) {
      console.error('Failed to load announcements:', error);
    } finally {
      loading = false;
    }
  }

  function resetForm() {
    formTitle = '';
    formContent = '';
    formType = 'info';
    formIsActive = true;
    formIsDismissible = true;
    formStartsAt = '';
    formEndsAt = '';
    formError = '';
    editingAnnouncement = null;
  }

  function openCreateModal() {
    resetForm();
    showCreateModal = true;
  }

  function openEditModal(announcement: PortalAnnouncement) {
    editingAnnouncement = announcement;
    formTitle = announcement.title;
    formContent = announcement.content;
    formType = announcement.type;
    formIsActive = announcement.is_active;
    formIsDismissible = announcement.is_dismissible;
    formStartsAt = announcement.starts_at?.split('T')[0] || '';
    formEndsAt = announcement.ends_at?.split('T')[0] || '';
    showCreateModal = true;
  }

  async function handleSubmit() {
    if (!formTitle || !formContent) {
      formError = 'Title and content are required';
      return;
    }

    formLoading = true;
    formError = '';

    try {
      const data = {
        title: formTitle,
        content: formContent,
        type: formType,
        is_active: formIsActive,
        is_dismissible: formIsDismissible,
        starts_at: formStartsAt || undefined,
        ends_at: formEndsAt || undefined,
      };

      if (editingAnnouncement) {
        await portalAdminApi.updateAnnouncement(editingAnnouncement.id, data);
      } else {
        await portalAdminApi.createAnnouncement(data);
      }

      showCreateModal = false;
      resetForm();
      await loadAnnouncements();
    } catch (err: unknown) {
      const apiError = err as { response?: { data?: { message?: string } } };
      formError = apiError.response?.data?.message || 'Failed to save announcement';
    } finally {
      formLoading = false;
    }
  }

  async function deleteAnnouncement(id: number) {
    if (!confirm('Are you sure you want to delete this announcement?')) return;

    try {
      await portalAdminApi.deleteAnnouncement(id);
      await loadAnnouncements();
    } catch (error) {
      console.error('Failed to delete announcement:', error);
    }
  }

  function getTypeIcon(type: string) {
    switch (type) {
      case 'warning':
        return AlertTriangle;
      case 'success':
        return CheckCircle;
      case 'error':
        return XCircle;
      default:
        return Info;
    }
  }

  function getTypeBadgeClass(type: string): string {
    switch (type) {
      case 'warning':
        return 'bg-yellow-500';
      case 'success':
        return 'bg-green-500';
      case 'error':
        return 'bg-red-500';
      default:
        return 'bg-blue-500';
    }
  }

  onMount(() => {
    loadAnnouncements();
  });
</script>

<Card.Root>
  <Card.Header>
    <div class="flex items-center justify-between">
      <div>
        <Card.Title class="flex items-center gap-2">
          <Megaphone class="h-5 w-5" />
          Portal Announcements
        </Card.Title>
        <Card.Description>Manage announcements shown to portal users</Card.Description>
      </div>
      <Button onclick={openCreateModal}>
        <Plus class="mr-2 h-4 w-4" />
        New Announcement
      </Button>
    </div>
  </Card.Header>
  <Card.Content>
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
      </div>
    {:else if announcements.length === 0}
      <div class="text-center py-8 text-muted-foreground">
        <Megaphone class="mx-auto h-12 w-12 mb-4 opacity-50" />
        <p>No announcements yet</p>
        <p class="text-sm">Create an announcement to communicate with portal users</p>
      </div>
    {:else}
      <div class="space-y-4">
        {#each announcements as announcement}
          {@const TypeIcon = getTypeIcon(announcement.type)}
          <div class="border rounded-lg p-4">
            <div class="flex items-start justify-between">
              <div class="flex items-start gap-3">
                <div class="mt-0.5">
                  <TypeIcon class="h-5 w-5" />
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h4 class="font-medium">{announcement.title}</h4>
                    <Badge variant="default" class={getTypeBadgeClass(announcement.type)}>
                      {announcement.type}
                    </Badge>
                    {#if !announcement.is_active}
                      <Badge variant="secondary">Inactive</Badge>
                    {/if}
                  </div>
                  <p class="text-sm text-muted-foreground mt-1">{announcement.content}</p>
                  <div class="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                    {#if announcement.starts_at}
                      <span>Starts: {new Date(announcement.starts_at).toLocaleDateString()}</span>
                    {/if}
                    {#if announcement.ends_at}
                      <span>Ends: {new Date(announcement.ends_at).toLocaleDateString()}</span>
                    {/if}
                    <span>Created by: {announcement.creator?.name || 'Unknown'}</span>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" onclick={() => openEditModal(announcement)}>
                  <Edit class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" onclick={() => deleteAnnouncement(announcement.id)}>
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </Card.Content>
</Card.Root>

<Dialog.Root bind:open={showCreateModal}>
  <Dialog.Content class="sm:max-w-lg">
    <Dialog.Header>
      <Dialog.Title>
        {editingAnnouncement ? 'Edit Announcement' : 'New Announcement'}
      </Dialog.Title>
      <Dialog.Description>
        Create an announcement to display in the customer portal.
      </Dialog.Description>
    </Dialog.Header>

    <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
      {#if formError}
        <div class="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
          {formError}
        </div>
      {/if}

      <div class="space-y-2">
        <Label for="title">Title</Label>
        <Input id="title" bind:value={formTitle} placeholder="Announcement title" />
      </div>

      <div class="space-y-2">
        <Label for="content">Content</Label>
        <Textarea id="content" bind:value={formContent} placeholder="Announcement content..." rows={3} />
      </div>

      <div class="space-y-2">
        <Label>Type</Label>
        <Select.Root type="single" bind:value={formType}>
          <Select.Trigger>
            {getTypeLabel(formType)}
          </Select.Trigger>
          <Select.Content>
            {#each announcementTypes as t}
              <Select.Item value={t.value} label={t.label}>
                {t.label}
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label for="starts_at">Start Date (optional)</Label>
          <Input id="starts_at" type="date" bind:value={formStartsAt} />
        </div>
        <div class="space-y-2">
          <Label for="ends_at">End Date (optional)</Label>
          <Input id="ends_at" type="date" bind:value={formEndsAt} />
        </div>
      </div>

      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Switch id="is_active" bind:checked={formIsActive} />
          <Label for="is_active">Active</Label>
        </div>
        <div class="flex items-center gap-2">
          <Switch id="is_dismissible" bind:checked={formIsDismissible} />
          <Label for="is_dismissible">Dismissible</Label>
        </div>
      </div>

      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (showCreateModal = false)}>
          Cancel
        </Button>
        <Button type="submit" disabled={formLoading}>
          {formLoading ? 'Saving...' : editingAnnouncement ? 'Update' : 'Create'}
        </Button>
      </Dialog.Footer>
    </form>
  </Dialog.Content>
</Dialog.Root>
