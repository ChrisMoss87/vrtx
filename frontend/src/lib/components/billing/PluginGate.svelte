<script lang="ts">
	import { license } from '$lib/stores/license';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Lock, Sparkles } from 'lucide-svelte';

	export let plugin: string | undefined = undefined;
	export let feature: string | undefined = undefined;
	export let plan: string | undefined = undefined;
	export let showUpgrade: boolean = true;
	export let title: string = 'Premium Feature';
	export let description: string | undefined = undefined;
	export let compact: boolean = false;

	$: hasAccess = (() => {
		if (plugin) return $license.plugins.includes(plugin);
		if (feature) return $license.features.includes(feature);
		if (plan) return license.hasPlan(plan);
		return true;
	})();

	$: upgradeLabel = plugin ? `Requires ${plugin}` : plan ? `Requires ${plan} plan` : 'Upgrade to unlock';
</script>

{#if hasAccess}
	<slot />
{:else if showUpgrade}
	{#if compact}
		<div class="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground bg-muted/50 rounded-md border border-dashed">
			<Lock class="h-4 w-4" />
			<span>{upgradeLabel}</span>
			<Button variant="link" size="sm" href="/settings/billing/plugins" class="h-auto p-0 ml-auto">
				Upgrade
			</Button>
		</div>
	{:else}
		<Card.Root class="border-dashed">
			<Card.Content class="flex flex-col items-center justify-center py-10">
				<div class="rounded-full bg-muted p-3 mb-4">
					<Lock class="h-8 w-8 text-muted-foreground" />
				</div>
				<h3 class="text-lg font-semibold mb-2">{title}</h3>
				<p class="text-muted-foreground text-center mb-4 max-w-md">
					{#if description}
						{description}
					{:else if plugin}
						This feature requires the <strong>{plugin}</strong> plugin.
						Upgrade your plan to unlock this functionality.
					{:else if plan}
						This feature requires the <strong>{plan}</strong> plan or higher.
					{:else}
						This feature requires an upgrade to access.
					{/if}
				</p>
				<Button href="/settings/billing/plugins{plugin ? `?highlight=${plugin}` : ''}">
					<Sparkles class="h-4 w-4 mr-2" />
					View Upgrade Options
				</Button>
			</Card.Content>
		</Card.Root>
	{/if}
{/if}
