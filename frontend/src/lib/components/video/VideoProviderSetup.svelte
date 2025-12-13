<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import * as Dialog from '$lib/components/ui/dialog';
  import { Badge } from '$lib/components/ui/badge';
  import { videoProviderApi, type VideoProvider, getProviderIcon } from '$lib/api/video';
  import CheckCircle from '@lucide/svelte/icons/check-circle';
  import XCircle from '@lucide/svelte/icons/x-circle';
  import Settings from '@lucide/svelte/icons/settings';
  import Trash2 from '@lucide/svelte/icons/trash-2';
  import Plus from '@lucide/svelte/icons/plus';
  import ExternalLink from '@lucide/svelte/icons/external-link';
  import RefreshCw from '@lucide/svelte/icons/refresh-cw';

  let providers = $state<VideoProvider[]>([]);
  let loading = $state(true);
  let showAddDialog = $state(false);
  let showEditDialog = $state(false);
  let selectedProvider = $state<VideoProvider | null>(null);
  let verifying = $state<number | null>(null);

  interface FormData {
    name: string;
    provider: 'zoom' | 'teams' | 'google_meet' | 'webex';
    client_id: string;
    client_secret: string;
    api_key: string;
    api_secret: string;
    webhook_secret: string;
  }

  let formData = $state<FormData>({
    name: '',
    provider: 'zoom',
    client_id: '',
    client_secret: '',
    api_key: '',
    api_secret: '',
    webhook_secret: '',
  });

  const providerOptions = [
    { value: 'zoom', label: 'Zoom' },
    { value: 'teams', label: 'Microsoft Teams' },
    { value: 'google_meet', label: 'Google Meet' },
    { value: 'webex', label: 'Cisco Webex' },
  ];

  async function loadProviders() {
    loading = true;
    try {
      providers = await videoProviderApi.list();
    } catch (error) {
      console.error('Failed to load providers:', error);
    } finally {
      loading = false;
    }
  }

  async function handleAdd() {
    try {
      await videoProviderApi.create(formData);
      showAddDialog = false;
      resetForm();
      await loadProviders();
    } catch (error) {
      console.error('Failed to add provider:', error);
    }
  }

  async function handleUpdate() {
    if (!selectedProvider) return;

    try {
      await videoProviderApi.update(selectedProvider.id, formData);
      showEditDialog = false;
      selectedProvider = null;
      resetForm();
      await loadProviders();
    } catch (error) {
      console.error('Failed to update provider:', error);
    }
  }

  async function handleDelete(provider: VideoProvider) {
    if (!confirm(`Are you sure you want to delete ${provider.name}?`)) return;

    try {
      await videoProviderApi.delete(provider.id);
      await loadProviders();
    } catch (error) {
      console.error('Failed to delete provider:', error);
    }
  }

  async function handleVerify(provider: VideoProvider) {
    verifying = provider.id;
    try {
      const result = await videoProviderApi.verify(provider.id);
      alert(result.message);
      await loadProviders();
    } catch (error) {
      console.error('Failed to verify provider:', error);
    } finally {
      verifying = null;
    }
  }

  async function handleToggleActive(provider: VideoProvider) {
    try {
      await videoProviderApi.toggleActive(provider.id);
      await loadProviders();
    } catch (error) {
      console.error('Failed to toggle provider:', error);
    }
  }

  async function handleOAuth(provider: VideoProvider) {
    try {
      const url = await videoProviderApi.getOAuthUrl(provider.id);
      window.open(url, '_blank');
    } catch (error) {
      console.error('Failed to get OAuth URL:', error);
    }
  }

  function openEditDialog(provider: VideoProvider) {
    selectedProvider = provider;
    formData = {
      name: provider.name,
      provider: provider.provider,
      client_id: '',
      client_secret: '',
      api_key: '',
      api_secret: '',
      webhook_secret: '',
    };
    showEditDialog = true;
  }

  function resetForm() {
    formData = {
      name: '',
      provider: 'zoom',
      client_id: '',
      client_secret: '',
      api_key: '',
      api_secret: '',
      webhook_secret: '',
    };
  }

  $effect(() => {
    loadProviders();
  });
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-lg font-semibold">Video Providers</h2>
      <p class="text-sm text-muted-foreground">
        Connect your video conferencing platforms to schedule and manage meetings
      </p>
    </div>
    <Button onclick={() => (showAddDialog = true)}>
      <Plus class="mr-2 h-4 w-4" />
      Add Provider
    </Button>
  </div>

  {#if loading}
    <div class="flex items-center justify-center py-8">
      <RefreshCw class="h-6 w-6 animate-spin text-muted-foreground" />
    </div>
  {:else if providers.length === 0}
    <Card.Root>
      <Card.Content class="flex flex-col items-center justify-center py-12">
        <p class="text-muted-foreground">No video providers configured</p>
        <Button variant="outline" class="mt-4" onclick={() => (showAddDialog = true)}>
          <Plus class="mr-2 h-4 w-4" />
          Add Your First Provider
        </Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="grid gap-4 md:grid-cols-2">
      {#each providers as provider}
        <Card.Root>
          <Card.Header class="flex flex-row items-start justify-between space-y-0">
            <div class="flex items-center gap-3">
              <span class="text-2xl">{getProviderIcon(provider.provider)}</span>
              <div>
                <Card.Title class="text-base">{provider.name}</Card.Title>
                <p class="text-sm text-muted-foreground capitalize">{provider.provider.replace('_', ' ')}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              {#if provider.is_verified}
                <Badge variant="outline" class="text-green-600 border-green-600">
                  <CheckCircle class="mr-1 h-3 w-3" />
                  Verified
                </Badge>
              {:else}
                <Badge variant="outline" class="text-yellow-600 border-yellow-600">
                  <XCircle class="mr-1 h-3 w-3" />
                  Not Verified
                </Badge>
              {/if}
              {#if provider.is_active}
                <Badge>Active</Badge>
              {:else}
                <Badge variant="secondary">Inactive</Badge>
              {/if}
            </div>
          </Card.Header>
          <Card.Content>
            <div class="flex flex-wrap gap-2">
              <Button
                variant="outline"
                size="sm"
                onclick={() => handleVerify(provider)}
                disabled={verifying === provider.id}
              >
                {#if verifying === provider.id}
                  <RefreshCw class="mr-2 h-4 w-4 animate-spin" />
                {:else}
                  <CheckCircle class="mr-2 h-4 w-4" />
                {/if}
                Verify
              </Button>
              <Button
                variant="outline"
                size="sm"
                onclick={() => handleToggleActive(provider)}
              >
                {provider.is_active ? 'Deactivate' : 'Activate'}
              </Button>
              {#if provider.provider === 'zoom' || provider.provider === 'google_meet'}
                <Button variant="outline" size="sm" onclick={() => handleOAuth(provider)}>
                  <ExternalLink class="mr-2 h-4 w-4" />
                  Connect OAuth
                </Button>
              {/if}
              <Button variant="outline" size="sm" onclick={() => openEditDialog(provider)}>
                <Settings class="mr-2 h-4 w-4" />
                Configure
              </Button>
              <Button variant="ghost" size="sm" onclick={() => handleDelete(provider)}>
                <Trash2 class="h-4 w-4 text-destructive" />
              </Button>
            </div>
          </Card.Content>
        </Card.Root>
      {/each}
    </div>
  {/if}
</div>

<!-- Add Provider Dialog -->
<Dialog.Root bind:open={showAddDialog}>
  <Dialog.Content class="max-w-md">
    <Dialog.Header>
      <Dialog.Title>Add Video Provider</Dialog.Title>
      <Dialog.Description>
        Connect a video conferencing platform to your CRM
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-4">
      <div class="space-y-2">
        <Label>Name</Label>
        <Input bind:value={formData.name} placeholder="e.g., Company Zoom" />
      </div>

      <div class="space-y-2">
        <Label>Provider</Label>
        <Select.Root
          type="single"
          value={formData.provider}
          onValueChange={(v) => { if (v) formData.provider = v as FormData['provider']; }}
        >
          <Select.Trigger>
            <span>{providerOptions.find((p) => p.value === formData.provider)?.label || 'Select provider'}</span>
          </Select.Trigger>
          <Select.Content>
            {#each providerOptions as option}
              <Select.Item value={option.value}>{option.label}</Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>

      {#if formData.provider === 'zoom'}
        <div class="space-y-4 rounded-lg border p-4">
          <p class="text-sm font-medium">Zoom Configuration</p>
          <div class="space-y-2">
            <Label>Account ID (for Server-to-Server)</Label>
            <Input bind:value={formData.api_key} placeholder="Account ID" />
          </div>
          <div class="space-y-2">
            <Label>Client ID</Label>
            <Input bind:value={formData.client_id} placeholder="Client ID" />
          </div>
          <div class="space-y-2">
            <Label>Client Secret</Label>
            <Input type="password" bind:value={formData.client_secret} placeholder="Client Secret" />
          </div>
          <div class="space-y-2">
            <Label>Webhook Secret (optional)</Label>
            <Input type="password" bind:value={formData.webhook_secret} placeholder="Webhook Secret" />
          </div>
        </div>
      {:else if formData.provider === 'google_meet'}
        <div class="space-y-4 rounded-lg border p-4">
          <p class="text-sm font-medium">Google Meet Configuration</p>
          <div class="space-y-2">
            <Label>Client ID</Label>
            <Input bind:value={formData.client_id} placeholder="Client ID" />
          </div>
          <div class="space-y-2">
            <Label>Client Secret</Label>
            <Input type="password" bind:value={formData.client_secret} placeholder="Client Secret" />
          </div>
        </div>
      {:else}
        <div class="rounded-lg border p-4">
          <p class="text-sm text-muted-foreground">
            {formData.provider === 'teams' ? 'Microsoft Teams' : 'Cisco Webex'} integration coming soon.
          </p>
        </div>
      {/if}
    </div>

    <Dialog.Footer>
      <Button variant="outline" onclick={() => (showAddDialog = false)}>Cancel</Button>
      <Button onclick={handleAdd} disabled={!formData.name}>Add Provider</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<!-- Edit Provider Dialog -->
<Dialog.Root bind:open={showEditDialog}>
  <Dialog.Content class="max-w-md">
    <Dialog.Header>
      <Dialog.Title>Configure Provider</Dialog.Title>
      <Dialog.Description>
        Update your {selectedProvider?.name} configuration
      </Dialog.Description>
    </Dialog.Header>

    <div class="space-y-4">
      <div class="space-y-2">
        <Label>Name</Label>
        <Input bind:value={formData.name} placeholder="Provider name" />
      </div>

      {#if selectedProvider?.provider === 'zoom'}
        <div class="space-y-4 rounded-lg border p-4">
          <p class="text-sm font-medium">Update Credentials (leave blank to keep existing)</p>
          <div class="space-y-2">
            <Label>Account ID</Label>
            <Input bind:value={formData.api_key} placeholder="Account ID" />
          </div>
          <div class="space-y-2">
            <Label>Client ID</Label>
            <Input bind:value={formData.client_id} placeholder="Client ID" />
          </div>
          <div class="space-y-2">
            <Label>Client Secret</Label>
            <Input type="password" bind:value={formData.client_secret} placeholder="Client Secret" />
          </div>
          <div class="space-y-2">
            <Label>Webhook Secret</Label>
            <Input type="password" bind:value={formData.webhook_secret} placeholder="Webhook Secret" />
          </div>
        </div>
      {/if}
    </div>

    <Dialog.Footer>
      <Button variant="outline" onclick={() => (showEditDialog = false)}>Cancel</Button>
      <Button onclick={handleUpdate}>Save Changes</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
