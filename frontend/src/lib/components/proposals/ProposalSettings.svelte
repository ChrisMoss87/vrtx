<script lang="ts">
  import { Label } from '$lib/components/ui/label';
  import { Input } from '$lib/components/ui/input';
  import * as Card from '$lib/components/ui/card';

  interface Props {
    settings?: {
      allow_comments?: boolean;
      allow_e_signature?: boolean;
      show_pricing_breakdown?: boolean;
      require_acceptance?: boolean;
      custom_css?: string;
      branding?: {
        logo_url?: string;
        primary_color?: string;
        accent_color?: string;
      };
    };
  }

  let {
    settings = $bindable({
      allow_comments: true,
      allow_e_signature: true,
      show_pricing_breakdown: true,
      require_acceptance: true,
      branding: {
        primary_color: '#3b82f6',
        accent_color: '#10b981',
      },
    }),
  }: Props = $props();

  // Initialize defaults
  if (!settings.branding) {
    settings.branding = {
      primary_color: '#3b82f6',
      accent_color: '#10b981',
    };
  }
</script>

<div class="space-y-6">
  <Card.Root>
    <Card.Header>
      <Card.Title class="text-lg">Client Interaction</Card.Title>
    </Card.Header>
    <Card.Content class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <Label>Allow Comments</Label>
          <p class="text-sm text-muted-foreground">Let clients add comments to sections</p>
        </div>
        <input type="checkbox" bind:checked={settings.allow_comments} class="rounded h-5 w-5" />
      </div>

      <div class="flex items-center justify-between">
        <div>
          <Label>Enable E-Signature</Label>
          <p class="text-sm text-muted-foreground">Allow clients to sign electronically</p>
        </div>
        <input type="checkbox" bind:checked={settings.allow_e_signature} class="rounded h-5 w-5" />
      </div>

      <div class="flex items-center justify-between">
        <div>
          <Label>Require Formal Acceptance</Label>
          <p class="text-sm text-muted-foreground">Client must explicitly accept the proposal</p>
        </div>
        <input type="checkbox" bind:checked={settings.require_acceptance} class="rounded h-5 w-5" />
      </div>
    </Card.Content>
  </Card.Root>

  <Card.Root>
    <Card.Header>
      <Card.Title class="text-lg">Pricing Display</Card.Title>
    </Card.Header>
    <Card.Content class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <Label>Show Pricing Breakdown</Label>
          <p class="text-sm text-muted-foreground">Display itemized pricing to client</p>
        </div>
        <input type="checkbox" bind:checked={settings.show_pricing_breakdown} class="rounded h-5 w-5" />
      </div>
    </Card.Content>
  </Card.Root>

  <Card.Root>
    <Card.Header>
      <Card.Title class="text-lg">Branding</Card.Title>
    </Card.Header>
    <Card.Content class="space-y-4">
      <div class="space-y-2">
        <Label>Company Logo URL</Label>
        <Input
          value={settings.branding?.logo_url ?? ''}
          oninput={(e) => { if (settings.branding) settings.branding.logo_url = e.currentTarget.value; }}
          placeholder="https://example.com/logo.png"
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <Label>Primary Color</Label>
          <div class="flex gap-2">
            <input
              type="color"
              value={settings.branding?.primary_color ?? '#3b82f6'}
              oninput={(e) => { if (settings.branding) settings.branding.primary_color = e.currentTarget.value; }}
              class="w-10 h-10 rounded border cursor-pointer"
            />
            <Input value={settings.branding?.primary_color ?? '#3b82f6'} oninput={(e) => { if (settings.branding) settings.branding.primary_color = e.currentTarget.value; }} class="font-mono" />
          </div>
        </div>

        <div class="space-y-2">
          <Label>Accent Color</Label>
          <div class="flex gap-2">
            <input
              type="color"
              value={settings.branding?.accent_color ?? '#10b981'}
              oninput={(e) => { if (settings.branding) settings.branding.accent_color = e.currentTarget.value; }}
              class="w-10 h-10 rounded border cursor-pointer"
            />
            <Input value={settings.branding?.accent_color ?? '#10b981'} oninput={(e) => { if (settings.branding) settings.branding.accent_color = e.currentTarget.value; }} class="font-mono" />
          </div>
        </div>
      </div>

      {#if settings.branding?.logo_url}
        <div class="p-4 border rounded-lg bg-muted/50">
          <p class="text-sm text-muted-foreground mb-2">Logo Preview:</p>
          <img
            src={settings.branding.logo_url}
            alt="Logo preview"
            class="max-h-16 object-contain"
          />
        </div>
      {/if}
    </Card.Content>
  </Card.Root>

  <Card.Root>
    <Card.Header>
      <Card.Title class="text-lg">Advanced</Card.Title>
    </Card.Header>
    <Card.Content class="space-y-4">
      <div class="space-y-2">
        <Label>Custom CSS (Advanced)</Label>
        <p class="text-sm text-muted-foreground mb-2">
          Add custom styling to your proposal. Use with caution.
        </p>
        <textarea
          bind:value={settings.custom_css}
          placeholder={".proposal-header { ... }"}
          class="w-full h-32 px-3 py-2 text-sm font-mono bg-background border rounded-md resize-none focus:outline-none focus:ring-2 focus:ring-ring"
        ></textarea>
      </div>
    </Card.Content>
  </Card.Root>
</div>
