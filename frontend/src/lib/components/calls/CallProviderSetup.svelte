<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { Badge } from '$lib/components/ui/badge';
  import { Phone, Check, X, RefreshCw, Settings, Trash2 } from 'lucide-svelte';
  import { callProviderApi, type CallProvider, type PhoneNumber } from '$lib/api/calls';

  let providers = $state<CallProvider[]>([]);
  let isLoading = $state(true);
  let showAddForm = $state(false);
  let editingProvider = $state<CallProvider | null>(null);
  let phoneNumbers = $state<PhoneNumber[]>([]);
  let loadingPhoneNumbers = $state(false);

  // Form state
  let formData = $state<{
    name: string;
    provider: 'twilio' | 'vonage' | 'ringcentral' | 'aircall';
    account_sid: string;
    auth_token: string;
    api_key: string;
    api_secret: string;
    phone_number: string;
    recording_enabled: boolean;
    transcription_enabled: boolean;
  }>({
    name: '',
    provider: 'twilio',
    account_sid: '',
    auth_token: '',
    api_key: '',
    api_secret: '',
    phone_number: '',
    recording_enabled: true,
    transcription_enabled: true,
  });

  const providerOptions = [
    { value: 'twilio', label: 'Twilio' },
    { value: 'vonage', label: 'Vonage' },
    { value: 'ringcentral', label: 'RingCentral' },
    { value: 'aircall', label: 'Aircall' },
  ];

  async function loadProviders() {
    try {
      isLoading = true;
      providers = await callProviderApi.list();
    } catch (error) {
      console.error('Failed to load providers:', error);
    } finally {
      isLoading = false;
    }
  }

  async function handleSubmit() {
    try {
      if (editingProvider) {
        await callProviderApi.update(editingProvider.id, formData);
      } else {
        await callProviderApi.create(formData);
      }
      await loadProviders();
      resetForm();
    } catch (error) {
      console.error('Failed to save provider:', error);
    }
  }

  async function verifyProvider(provider: CallProvider) {
    try {
      const result = await callProviderApi.verify(provider.id);
      await loadProviders();
      alert(`Verified! Balance: ${result.balance} ${result.currency}`);
    } catch (error) {
      console.error('Verification failed:', error);
      alert('Verification failed. Please check your credentials.');
    }
  }

  async function toggleActive(provider: CallProvider) {
    try {
      await callProviderApi.toggleActive(provider.id);
      await loadProviders();
    } catch (error) {
      console.error('Failed to toggle active:', error);
    }
  }

  async function deleteProvider(provider: CallProvider) {
    if (!confirm(`Are you sure you want to delete "${provider.name}"?`)) return;
    try {
      await callProviderApi.delete(provider.id);
      await loadProviders();
    } catch (error) {
      console.error('Failed to delete provider:', error);
    }
  }

  async function loadPhoneNumbers(provider: CallProvider) {
    try {
      loadingPhoneNumbers = true;
      phoneNumbers = await callProviderApi.listPhoneNumbers(provider.id);
    } catch (error) {
      console.error('Failed to load phone numbers:', error);
    } finally {
      loadingPhoneNumbers = false;
    }
  }

  async function selectPhoneNumber(provider: CallProvider, phoneNumber: string) {
    try {
      await callProviderApi.syncPhoneNumber(provider.id, phoneNumber);
      await loadProviders();
      phoneNumbers = [];
    } catch (error) {
      console.error('Failed to sync phone number:', error);
    }
  }

  function editProvider(provider: CallProvider) {
    editingProvider = provider;
    formData = {
      name: provider.name,
      provider: provider.provider,
      account_sid: '',
      auth_token: '',
      api_key: '',
      api_secret: '',
      phone_number: provider.phone_number || '',
      recording_enabled: provider.recording_enabled,
      transcription_enabled: provider.transcription_enabled,
    };
    showAddForm = true;
  }

  function resetForm() {
    showAddForm = false;
    editingProvider = null;
    formData = {
      name: '',
      provider: 'twilio',
      account_sid: '',
      auth_token: '',
      api_key: '',
      api_secret: '',
      phone_number: '',
      recording_enabled: true,
      transcription_enabled: true,
    };
  }

  $effect(() => {
    loadProviders();
  });
</script>

<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h2 class="text-lg font-semibold">Call Providers</h2>
      <p class="text-sm text-muted-foreground">Configure your telephony providers for making and receiving calls.</p>
    </div>
    <Button onclick={() => (showAddForm = true)}>
      <Phone class="mr-2 h-4 w-4" />
      Add Provider
    </Button>
  </div>

  {#if showAddForm}
    <Card.Root>
      <Card.Header>
        <Card.Title>{editingProvider ? 'Edit Provider' : 'Add New Provider'}</Card.Title>
      </Card.Header>
      <Card.Content>
        <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label for="name">Provider Name</Label>
              <Input id="name" bind:value={formData.name} placeholder="My Twilio Account" required />
            </div>
            <div class="space-y-2">
              <Label for="provider">Provider Type</Label>
              <Select.Root type="single" name="provider" bind:value={formData.provider}>
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
          </div>

          {#if formData.provider === 'twilio'}
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="account_sid">Account SID</Label>
                <Input id="account_sid" bind:value={formData.account_sid} placeholder="ACxxxxxxxx" />
              </div>
              <div class="space-y-2">
                <Label for="auth_token">Auth Token</Label>
                <Input id="auth_token" type="password" bind:value={formData.auth_token} placeholder="Your auth token" />
              </div>
            </div>
          {:else}
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <Label for="api_key">API Key</Label>
                <Input id="api_key" bind:value={formData.api_key} placeholder="Your API key" />
              </div>
              <div class="space-y-2">
                <Label for="api_secret">API Secret</Label>
                <Input id="api_secret" type="password" bind:value={formData.api_secret} placeholder="Your API secret" />
              </div>
            </div>
          {/if}

          <div class="space-y-2">
            <Label for="phone_number">Phone Number</Label>
            <Input id="phone_number" bind:value={formData.phone_number} placeholder="+1234567890" />
          </div>

          <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
              <Switch id="recording" bind:checked={formData.recording_enabled} />
              <Label for="recording">Enable Call Recording</Label>
            </div>
            <div class="flex items-center gap-2">
              <Switch id="transcription" bind:checked={formData.transcription_enabled} />
              <Label for="transcription">Enable Transcription</Label>
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <Button variant="outline" type="button" onclick={resetForm}>Cancel</Button>
            <Button type="submit">{editingProvider ? 'Update' : 'Add'} Provider</Button>
          </div>
        </form>
      </Card.Content>
    </Card.Root>
  {/if}

  {#if isLoading}
    <div class="text-center py-8 text-muted-foreground">Loading providers...</div>
  {:else if providers.length === 0}
    <Card.Root>
      <Card.Content class="py-8 text-center">
        <Phone class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
        <h3 class="font-medium mb-2">No providers configured</h3>
        <p class="text-sm text-muted-foreground mb-4">Add a telephony provider to start making and receiving calls.</p>
        <Button onclick={() => (showAddForm = true)}>Add Your First Provider</Button>
      </Card.Content>
    </Card.Root>
  {:else}
    <div class="grid gap-4">
      {#each providers as provider}
        <Card.Root>
          <Card.Content class="py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                  <Phone class="h-5 w-5 text-primary" />
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="font-medium">{provider.name}</h3>
                    <Badge variant="outline">{provider.provider}</Badge>
                    {#if provider.is_verified}
                      <Badge variant="default" class="bg-green-500">
                        <Check class="h-3 w-3 mr-1" />
                        Verified
                      </Badge>
                    {:else}
                      <Badge variant="secondary">Not Verified</Badge>
                    {/if}
                    {#if !provider.is_active}
                      <Badge variant="destructive">Inactive</Badge>
                    {/if}
                  </div>
                  <p class="text-sm text-muted-foreground">
                    {provider.phone_number || 'No phone number configured'}
                    {#if provider.recording_enabled}
                      <span class="mx-1">•</span> Recording enabled
                    {/if}
                    {#if provider.transcription_enabled}
                      <span class="mx-1">•</span> Transcription enabled
                    {/if}
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                {#if provider.is_verified}
                  <Button variant="outline" size="sm" onclick={() => loadPhoneNumbers(provider)}>
                    <RefreshCw class="h-4 w-4 mr-1" />
                    Phone Numbers
                  </Button>
                {:else}
                  <Button variant="outline" size="sm" onclick={() => verifyProvider(provider)}>
                    <Check class="h-4 w-4 mr-1" />
                    Verify
                  </Button>
                {/if}
                <Button variant="ghost" size="icon" onclick={() => editProvider(provider)}>
                  <Settings class="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" onclick={() => toggleActive(provider)}>
                  {#if provider.is_active}
                    <X class="h-4 w-4" />
                  {:else}
                    <Check class="h-4 w-4" />
                  {/if}
                </Button>
                <Button variant="ghost" size="icon" onclick={() => deleteProvider(provider)}>
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </div>

            {#if phoneNumbers.length > 0 && !loadingPhoneNumbers}
              <div class="mt-4 border-t pt-4">
                <h4 class="text-sm font-medium mb-2">Available Phone Numbers</h4>
                <div class="grid grid-cols-3 gap-2">
                  {#each phoneNumbers as number}
                    <Button
                      variant="outline"
                      size="sm"
                      onclick={() => selectPhoneNumber(provider, number.phone_number)}
                      class="justify-start"
                    >
                      {number.phone_number}
                      {#if number.capabilities.voice}
                        <Badge variant="secondary" class="ml-2 text-xs">Voice</Badge>
                      {/if}
                    </Button>
                  {/each}
                </div>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>
      {/each}
    </div>
  {/if}
</div>
