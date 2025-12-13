<script lang="ts">
  import { onMount } from 'svelte';
  import { Button } from '$lib/components/ui/button';
  import { Input } from '$lib/components/ui/input';
  import { Label } from '$lib/components/ui/label';
  import * as Card from '$lib/components/ui/card';
  import * as Select from '$lib/components/ui/select';
  import { Switch } from '$lib/components/ui/switch';
  import { Badge } from '$lib/components/ui/badge';
  import { getAiSettings, updateAiSettings, testAiConnection, getAiUsage, type AiSettings, type AiUsage } from '$lib/api/ai';
  import { Loader2, Zap, CheckCircle, XCircle } from 'lucide-svelte';

  let settings = $state<AiSettings | null>(null);
  let usage = $state<AiUsage | null>(null);
  let availableProviders = $state<Record<string, string>>({});
  let availableModels = $state<Record<string, Record<string, { name: string; input_cost: number; output_cost: number }>>>({});
  let isConfigured = $state(false);

  let loading = $state(true);
  let saving = $state(false);
  let testing = $state(false);
  let testResult = $state<{ success: boolean; message: string } | null>(null);

  // Form state
  let isEnabled = $state(false);
  let provider = $state('openai');
  let model = $state('');
  let apiKey = $state('');
  let maxTokens = $state(1000);
  let temperature = $state(0.7);
  let monthlyBudgetDollars = $state<number | null>(null);
  let budgetResetDay = $state(1);

  onMount(async () => {
    await loadSettings();
  });

  async function loadSettings() {
    loading = true;
    try {
      const [settingsRes, usageRes] = await Promise.all([
        getAiSettings(),
        getAiUsage().catch(() => null)
      ]);

      settings = settingsRes.settings;
      isConfigured = settingsRes.is_configured;
      availableProviders = settingsRes.available_providers;
      availableModels = settingsRes.available_models;
      usage = usageRes;

      if (settings) {
        isEnabled = settings.is_enabled;
        provider = settings.provider;
        model = settings.model;
        maxTokens = settings.max_tokens;
        temperature = settings.temperature;
        monthlyBudgetDollars = settings.monthly_budget_cents ? settings.monthly_budget_cents / 100 : null;
        budgetResetDay = settings.budget_reset_day;
      }
    } catch (error) {
      console.error('Failed to load AI settings:', error);
    } finally {
      loading = false;
    }
  }

  async function handleSave() {
    saving = true;
    testResult = null;
    try {
      const data: Record<string, unknown> = {
        is_enabled: isEnabled,
        provider,
        model,
        max_tokens: maxTokens,
        temperature,
        monthly_budget_cents: monthlyBudgetDollars ? Math.round(monthlyBudgetDollars * 100) : null,
        budget_reset_day: budgetResetDay,
      };

      if (apiKey) {
        data.api_key = apiKey;
      }

      await updateAiSettings(data);
      apiKey = ''; // Clear API key field after save
      await loadSettings();
    } catch (error) {
      console.error('Failed to save AI settings:', error);
    } finally {
      saving = false;
    }
  }

  async function handleTestConnection() {
    testing = true;
    testResult = null;
    try {
      const result = await testAiConnection();
      testResult = result;
    } catch (error: unknown) {
      testResult = { success: false, message: error instanceof Error ? error.message : 'Connection test failed' };
    } finally {
      testing = false;
    }
  }

  function handleProviderChange(newValue: string | undefined) {
    if (newValue) {
      provider = newValue;
      model = Object.keys(getModelsForProvider(newValue))[0] || '';
    }
  }

  function handleModelChange(newValue: string | undefined) {
    if (newValue) {
      model = newValue;
    }
  }

  function getModelsForProvider(p: string) {
    return availableModels[p] || {};
  }

  function formatCurrency(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
  }

  const selectedProviderLabel = $derived(availableProviders[provider] || provider);
  const selectedModelLabel = $derived(getModelsForProvider(provider)[model]?.name || model);
</script>

<div class="space-y-6">
  {#if loading}
    <div class="flex items-center justify-center py-12">
      <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
    </div>
  {:else}
    <!-- Status Card -->
    <Card.Root>
      <Card.Header>
        <Card.Title class="flex items-center gap-2">
          <Zap class="h-5 w-5" />
          AI Status
        </Card.Title>
      </Card.Header>
      <Card.Content>
        <div class="flex items-center gap-4">
          <Badge variant={isConfigured ? 'default' : 'secondary'}>
            {isConfigured ? 'Configured' : 'Not Configured'}
          </Badge>
          {#if settings?.has_api_key}
            <Badge variant="outline">API Key Set</Badge>
          {/if}
          {#if usage?.current_month.is_exceeded}
            <Badge variant="destructive">Budget Exceeded</Badge>
          {/if}
        </div>

        {#if usage}
          <div class="mt-4 grid grid-cols-3 gap-4">
            <div>
              <p class="text-sm text-muted-foreground">This Month</p>
              <p class="text-2xl font-bold">{formatCurrency(usage.current_month.used_cents)}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Budget</p>
              <p class="text-2xl font-bold">
                {usage.current_month.budget_cents
                  ? formatCurrency(usage.current_month.budget_cents)
                  : 'Unlimited'}
              </p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Remaining</p>
              <p class="text-2xl font-bold">
                {usage.current_month.remaining_cents !== null
                  ? formatCurrency(usage.current_month.remaining_cents)
                  : '-'}
              </p>
            </div>
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Configuration Card -->
    <Card.Root>
      <Card.Header>
        <Card.Title>Configuration</Card.Title>
        <Card.Description>
          Configure your AI provider and model settings
        </Card.Description>
      </Card.Header>
      <Card.Content class="space-y-6">
        <!-- Enable/Disable -->
        <div class="flex items-center justify-between">
          <div>
            <Label>Enable AI Features</Label>
            <p class="text-sm text-muted-foreground">Turn AI features on or off for this workspace</p>
          </div>
          <Switch bind:checked={isEnabled} />
        </div>

        <!-- Provider Selection -->
        <div class="space-y-2">
          <Label>Provider</Label>
          <Select.Root type="single" value={provider} onValueChange={handleProviderChange}>
            <Select.Trigger class="w-full">
              <span>{selectedProviderLabel}</span>
            </Select.Trigger>
            <Select.Content>
              {#each Object.entries(availableProviders) as [value, label]}
                <Select.Item {value} {label}>{label}</Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>

        <!-- Model Selection -->
        <div class="space-y-2">
          <Label>Model</Label>
          <Select.Root type="single" value={model} onValueChange={handleModelChange}>
            <Select.Trigger class="w-full">
              <span>{selectedModelLabel || 'Select model'}</span>
            </Select.Trigger>
            <Select.Content>
              {#each Object.entries(getModelsForProvider(provider)) as [value, info]}
                <Select.Item {value} label={info.name}>
                  {info.name}
                  <span class="text-muted-foreground ml-2">
                    (${info.input_cost}/1M in, ${info.output_cost}/1M out)
                  </span>
                </Select.Item>
              {/each}
            </Select.Content>
          </Select.Root>
        </div>

        <!-- API Key -->
        <div class="space-y-2">
          <Label>API Key</Label>
          <Input
            type="password"
            bind:value={apiKey}
            placeholder={settings?.has_api_key ? '••••••••••••••••' : 'Enter your API key'}
          />
          <p class="text-xs text-muted-foreground">
            {settings?.has_api_key ? 'API key is set. Enter a new key to change it.' : 'Required to enable AI features.'}
          </p>
        </div>

        <!-- Max Tokens -->
        <div class="space-y-2">
          <Label>Max Tokens</Label>
          <Input
            type="number"
            bind:value={maxTokens}
            min={100}
            max={8000}
          />
          <p class="text-xs text-muted-foreground">Maximum tokens per request (100-8000)</p>
        </div>

        <!-- Temperature -->
        <div class="space-y-2">
          <Label>Temperature: {temperature}</Label>
          <input
            type="range"
            bind:value={temperature}
            min={0}
            max={2}
            step={0.1}
            class="w-full"
          />
          <p class="text-xs text-muted-foreground">
            Lower = more focused, Higher = more creative (0-2)
          </p>
        </div>

        <!-- Budget -->
        <div class="space-y-2">
          <Label>Monthly Budget ($)</Label>
          <Input
            type="number"
            bind:value={monthlyBudgetDollars}
            min={0}
            step={1}
            placeholder="Leave empty for unlimited"
          />
          <p class="text-xs text-muted-foreground">Set a monthly spending limit (optional)</p>
        </div>

        <!-- Budget Reset Day -->
        <div class="space-y-2">
          <Label>Budget Reset Day</Label>
          <Input
            type="number"
            bind:value={budgetResetDay}
            min={1}
            max={28}
          />
          <p class="text-xs text-muted-foreground">Day of month when budget resets (1-28)</p>
        </div>
      </Card.Content>
      <Card.Footer class="flex justify-between">
        <Button variant="outline" onclick={handleTestConnection} disabled={testing || !settings?.has_api_key}>
          {#if testing}
            <Loader2 class="mr-2 h-4 w-4 animate-spin" />
          {/if}
          Test Connection
        </Button>
        <Button onclick={handleSave} disabled={saving}>
          {#if saving}
            <Loader2 class="mr-2 h-4 w-4 animate-spin" />
          {/if}
          Save Settings
        </Button>
      </Card.Footer>
    </Card.Root>

    <!-- Test Result -->
    {#if testResult}
      <Card.Root class={testResult.success ? 'border-green-500' : 'border-red-500'}>
        <Card.Content class="pt-6">
          <div class="flex items-center gap-2">
            {#if testResult.success}
              <CheckCircle class="h-5 w-5 text-green-500" />
            {:else}
              <XCircle class="h-5 w-5 text-red-500" />
            {/if}
            <span>{testResult.message}</span>
          </div>
        </Card.Content>
      </Card.Root>
    {/if}

    <!-- Usage Statistics -->
    {#if usage?.statistics}
      <Card.Root>
        <Card.Header>
          <Card.Title>Usage Statistics</Card.Title>
        </Card.Header>
        <Card.Content>
          <div class="grid grid-cols-3 gap-4 mb-6">
            <div>
              <p class="text-sm text-muted-foreground">Total Requests</p>
              <p class="text-2xl font-bold">{usage.statistics.total_requests.toLocaleString()}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Total Tokens</p>
              <p class="text-2xl font-bold">{usage.statistics.total_tokens.toLocaleString()}</p>
            </div>
            <div>
              <p class="text-sm text-muted-foreground">Total Cost</p>
              <p class="text-2xl font-bold">{formatCurrency(usage.statistics.total_cost_cents)}</p>
            </div>
          </div>

          {#if Object.keys(usage.statistics.by_feature || {}).length > 0}
            <h4 class="font-medium mb-2">By Feature</h4>
            <div class="space-y-2">
              {#each Object.entries(usage.statistics.by_feature) as [feature, stats]}
                <div class="flex items-center justify-between text-sm">
                  <span class="capitalize">{feature.replace(/_/g, ' ')}</span>
                  <span class="text-muted-foreground">
                    {stats.requests} requests, {stats.tokens.toLocaleString()} tokens, {formatCurrency(stats.cost_cents)}
                  </span>
                </div>
              {/each}
            </div>
          {/if}
        </Card.Content>
      </Card.Root>
    {/if}
  {/if}
</div>
