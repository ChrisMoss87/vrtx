<script lang="ts">
	import { onMount } from 'svelte';
	import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Label } from '$lib/components/ui/label';
	import { RadioGroup, RadioGroupItem } from '$lib/components/ui/radio-group';
	import { sidebarStyle, type SidebarStyle } from '$lib/stores/sidebar';
	import {
		PanelLeft,
		PanelLeftOpen,
		Check
	} from 'lucide-svelte';

	let selectedSidebar = $state<SidebarStyle>('collapsible');
	let saving = $state(false);

	onMount(() => {
		// Get current value from store
		const unsubscribe = sidebarStyle.subscribe((value) => {
			selectedSidebar = value;
		});
		return unsubscribe;
	});

	async function handleSidebarChange(value: string) {
		if (value === 'rail' || value === 'collapsible') {
			saving = true;
			selectedSidebar = value;
			await sidebarStyle.setStyle(value);
			saving = false;
		}
	}
</script>

<svelte:head>
	<title>Preferences | VRTX</title>
</svelte:head>

<div class="max-w-4xl space-y-6">
	<div>
		<h1 class="text-2xl font-bold">Preferences</h1>
		<p class="text-muted-foreground">Customize your experience</p>
	</div>

	<!-- Sidebar Style -->
	<Card>
		<CardHeader>
			<CardTitle class="flex items-center gap-2">
				<PanelLeft class="h-5 w-5" />
				Sidebar Style
			</CardTitle>
			<CardDescription>
				Choose how you want the navigation sidebar to appear
			</CardDescription>
		</CardHeader>
		<CardContent>
			<RadioGroup value={selectedSidebar} onValueChange={handleSidebarChange}>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<!-- Rail Style -->
					<label
						class="relative flex cursor-pointer flex-col rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {selectedSidebar === 'rail' ? 'border-primary bg-primary/5' : 'border-muted'}"
					>
						<RadioGroupItem value="rail" class="sr-only" />
						<div class="flex items-start justify-between">
							<div class="space-y-1">
								<div class="font-medium">Rail</div>
								<div class="text-sm text-muted-foreground">
									Hover over icons to reveal flyout menus
								</div>
							</div>
							{#if selectedSidebar === 'rail'}
								<Check class="h-5 w-5 text-primary" />
							{/if}
						</div>
						<!-- Preview -->
						<div class="mt-4 flex h-24 rounded border bg-muted/30">
							<div class="w-10 bg-slate-900 flex flex-col items-center py-2 gap-1 rounded-l">
								<div class="w-5 h-5 rounded bg-violet-500"></div>
								<div class="w-4 h-4 rounded bg-slate-700"></div>
								<div class="w-4 h-4 rounded bg-slate-700"></div>
								<div class="w-4 h-4 rounded bg-slate-700"></div>
							</div>
							<div class="w-32 bg-slate-800 p-2 hidden group-hover:block">
								<div class="h-2 w-16 rounded bg-slate-700 mb-1"></div>
								<div class="h-2 w-12 rounded bg-slate-700"></div>
							</div>
							<div class="flex-1 bg-background p-2">
								<div class="h-2 w-24 rounded bg-muted mb-1"></div>
								<div class="h-2 w-16 rounded bg-muted"></div>
							</div>
						</div>
						<div class="mt-2 text-xs text-muted-foreground">
							Compact • Dark rail • Hover menus
						</div>
					</label>

					<!-- Collapsible Style -->
					<label
						class="relative flex cursor-pointer flex-col rounded-lg border-2 p-4 transition-colors hover:bg-muted/50 {selectedSidebar === 'collapsible' ? 'border-primary bg-primary/5' : 'border-muted'}"
					>
						<RadioGroupItem value="collapsible" class="sr-only" />
						<div class="flex items-start justify-between">
							<div class="space-y-1">
								<div class="font-medium">Collapsible</div>
								<div class="text-sm text-muted-foreground">
									Click icons to expand full navigation panel
								</div>
							</div>
							{#if selectedSidebar === 'collapsible'}
								<Check class="h-5 w-5 text-primary" />
							{/if}
						</div>
						<!-- Preview -->
						<div class="mt-4 flex h-24 rounded border bg-muted/30">
							<div class="w-8 bg-zinc-100 dark:bg-zinc-900 border-r flex flex-col items-center py-2 gap-1 rounded-l">
								<div class="w-4 h-4 rounded bg-violet-500"></div>
								<div class="w-3 h-3 rounded bg-zinc-300 dark:bg-zinc-700"></div>
								<div class="w-3 h-3 rounded bg-zinc-300 dark:bg-zinc-700"></div>
								<div class="w-3 h-3 rounded bg-zinc-300 dark:bg-zinc-700"></div>
							</div>
							<div class="w-36 bg-background border-r p-2">
								<div class="h-2 w-16 rounded bg-muted mb-1"></div>
								<div class="h-2 w-20 rounded bg-muted mb-1"></div>
								<div class="h-2 w-14 rounded bg-muted"></div>
							</div>
							<div class="flex-1 bg-muted/30 p-2">
								<div class="h-2 w-24 rounded bg-muted mb-1"></div>
								<div class="h-2 w-16 rounded bg-muted"></div>
							</div>
						</div>
						<div class="mt-2 text-xs text-muted-foreground">
							Light rail • Click to expand • Search & favorites
						</div>
					</label>
				</div>
			</RadioGroup>
			{#if saving}
				<p class="mt-4 text-sm text-muted-foreground">Saving...</p>
			{/if}
		</CardContent>
	</Card>
</div>
