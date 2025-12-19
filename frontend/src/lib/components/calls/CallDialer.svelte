<script lang="ts">
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Badge } from '$lib/components/ui/badge';
  import {
    Phone, PhoneOff, Mic, MicOff, Pause, Play,
    ArrowRight, User, X, Clock
  } from 'lucide-svelte';
  import { callApi, callProviderApi, formatDuration, type CallProvider, type Call } from '$lib/api/calls';

  interface Props {
    contactId?: number;
    contactModule?: string;
    defaultNumber?: string;
    onCallStart?: (call: { call_id: number; external_call_id: string }) => void;
    onCallEnd?: () => void;
  }

  let { contactId, contactModule, defaultNumber = '', onCallStart, onCallEnd }: Props = $props();

  let providers = $state<CallProvider[]>([]);
  let selectedProviderId = $state<number | undefined>(undefined);
  let phoneNumber = $state(defaultNumber);
  let isLoading = $state(true);
  let isCalling = $state(false);
  let activeCall = $state<{ call_id: number; external_call_id: string } | null>(null);
  let callDuration = $state(0);
  let callInterval = $state<ReturnType<typeof setInterval> | null>(null);
  let isMuted = $state(false);
  let isOnHold = $state(false);
  let transferNumber = $state('');
  let showTransfer = $state(false);

  const dialPad = [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    ['*', '0', '#'],
  ];

  async function loadProviders() {
    try {
      providers = await callProviderApi.list();
      const activeProvider = providers.find((p) => p.is_active && p.is_verified);
      if (activeProvider) {
        selectedProviderId = activeProvider.id;
      }
    } catch (error) {
      console.error('Failed to load providers:', error);
    } finally {
      isLoading = false;
    }
  }

  async function initiateCall() {
    if (!selectedProviderId || !phoneNumber.trim()) return;

    try {
      isCalling = true;
      const result = await callApi.initiate({
        provider_id: selectedProviderId,
        to_number: phoneNumber,
        contact_id: contactId,
        contact_module: contactModule,
      });

      activeCall = result;
      onCallStart?.(result);

      // Start call timer
      callInterval = setInterval(() => {
        callDuration++;
      }, 1000);
    } catch (error) {
      console.error('Failed to initiate call:', error);
      isCalling = false;
    }
  }

  async function endCall() {
    if (!activeCall) return;

    try {
      await callApi.end(activeCall.call_id);
    } catch (error) {
      console.error('Failed to end call:', error);
    } finally {
      cleanupCall();
    }
  }

  async function toggleMute() {
    if (!activeCall) return;

    try {
      await callApi.mute(activeCall.call_id, !isMuted);
      isMuted = !isMuted;
    } catch (error) {
      console.error('Failed to toggle mute:', error);
    }
  }

  async function toggleHold() {
    if (!activeCall) return;

    try {
      await callApi.hold(activeCall.call_id);
      isOnHold = !isOnHold;
    } catch (error) {
      console.error('Failed to toggle hold:', error);
    }
  }

  async function transferCall() {
    if (!activeCall || !transferNumber.trim()) return;

    try {
      await callApi.transfer(activeCall.call_id, transferNumber);
      showTransfer = false;
      cleanupCall();
    } catch (error) {
      console.error('Failed to transfer call:', error);
    }
  }

  function cleanupCall() {
    if (callInterval) {
      clearInterval(callInterval);
      callInterval = null;
    }
    activeCall = null;
    isCalling = false;
    callDuration = 0;
    isMuted = false;
    isOnHold = false;
    showTransfer = false;
    onCallEnd?.();
  }

  function appendDigit(digit: string) {
    phoneNumber += digit;
  }

  function clearNumber() {
    phoneNumber = '';
  }

  function backspace() {
    phoneNumber = phoneNumber.slice(0, -1);
  }

  $effect(() => {
    loadProviders();
    return () => {
      if (callInterval) {
        clearInterval(callInterval);
      }
    };
  });
</script>

<Card.Root class="w-full max-w-sm">
  <Card.Header>
    <Card.Title class="flex items-center gap-2">
      <Phone class="h-5 w-5" />
      {activeCall ? 'Active Call' : 'Phone Dialer'}
    </Card.Title>
    {#if activeCall}
      <div class="flex items-center gap-2 text-lg font-mono">
        <Clock class="h-4 w-4 text-muted-foreground" />
        {formatDuration(callDuration)}
      </div>
    {/if}
  </Card.Header>
  <Card.Content class="space-y-4">
    {#if activeCall}
      <!-- Active Call UI -->
      <div class="text-center py-4">
        <div class="text-2xl font-bold mb-2">{phoneNumber}</div>
        <Badge variant="default" class="bg-green-500">In Call</Badge>
        {#if isOnHold}
          <Badge variant="secondary" class="ml-2">On Hold</Badge>
        {/if}
        {#if isMuted}
          <Badge variant="secondary" class="ml-2">Muted</Badge>
        {/if}
      </div>

      <div class="grid grid-cols-3 gap-2">
        <Button
          variant={isMuted ? 'destructive' : 'outline'}
          class="flex-col h-16"
          onclick={toggleMute}
        >
          {#if isMuted}
            <MicOff class="h-5 w-5 mb-1" />
          {:else}
            <Mic class="h-5 w-5 mb-1" />
          {/if}
          <span class="text-xs">{isMuted ? 'Unmute' : 'Mute'}</span>
        </Button>
        <Button
          variant={isOnHold ? 'secondary' : 'outline'}
          class="flex-col h-16"
          onclick={toggleHold}
        >
          {#if isOnHold}
            <Play class="h-5 w-5 mb-1" />
          {:else}
            <Pause class="h-5 w-5 mb-1" />
          {/if}
          <span class="text-xs">{isOnHold ? 'Resume' : 'Hold'}</span>
        </Button>
        <Button
          variant="outline"
          class="flex-col h-16"
          onclick={() => (showTransfer = !showTransfer)}
        >
          <ArrowRight class="h-5 w-5 mb-1" />
          <span class="text-xs">Transfer</span>
        </Button>
      </div>

      {#if showTransfer}
        <div class="flex gap-2">
          <Input
            bind:value={transferNumber}
            placeholder="Transfer to..."
            class="flex-1"
          />
          <Button onclick={transferCall} disabled={!transferNumber.trim()}>
            Transfer
          </Button>
        </div>
      {/if}

      <Button variant="destructive" class="w-full" size="lg" onclick={endCall}>
        <PhoneOff class="h-5 w-5 mr-2" />
        End Call
      </Button>
    {:else}
      <!-- Dialer UI -->
      {#if isLoading}
        <div class="text-center py-4 text-muted-foreground">Loading...</div>
      {:else}
        <div class="space-y-4">
          <Select.Root type="single" name="provider" value={selectedProviderId ? String(selectedProviderId) : undefined} onValueChange={(v) => selectedProviderId = v ? Number(v) : undefined}>
            <Select.Trigger>
              <span>{providers.find((p) => p.id === selectedProviderId)?.name || 'Select provider'}</span>
            </Select.Trigger>
            <Select.Content>
              {#each providers.filter((p) => p.is_active && p.is_verified) as provider}
                <Select.Item value={String(provider.id)}>
                  <div class="flex items-center gap-2">
                    <span>{provider.name}</span>
                    <span class="text-xs text-muted-foreground">{provider.phone_number}</span>
                  </div>
                </Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>

          <div class="relative">
            <Input
              bind:value={phoneNumber}
              placeholder="Enter phone number"
              class="text-center text-lg font-mono pr-10"
            />
            {#if phoneNumber}
              <Button
                variant="ghost"
                size="icon"
                class="absolute right-1 top-1/2 -translate-y-1/2"
                onclick={clearNumber}
              >
                <X class="h-4 w-4" />
              </Button>
            {/if}
          </div>

          <div class="grid grid-cols-3 gap-2">
            {#each dialPad as row}
              {#each row as digit}
                <Button
                  variant="outline"
                  class="h-14 text-xl font-medium"
                  onclick={() => appendDigit(digit)}
                >
                  {digit}
                </Button>
              {/each}
            {/each}
          </div>

          <div class="flex gap-2">
            <Button
              variant="outline"
              class="flex-1"
              onclick={backspace}
              disabled={!phoneNumber}
            >
              Delete
            </Button>
            <Button
              class="flex-1 bg-green-600 hover:bg-green-700"
              size="lg"
              onclick={initiateCall}
              disabled={!selectedProviderId || !phoneNumber.trim() || isCalling}
            >
              {#if isCalling}
                Calling...
              {:else}
                <Phone class="h-5 w-5 mr-2" />
                Call
              {/if}
            </Button>
          </div>

          {#if contactId}
            <div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
              <User class="h-4 w-4" />
              Linked to {contactModule} #{contactId}
            </div>
          {/if}
        </div>
      {/if}
    {/if}
  </Card.Content>
</Card.Root>
