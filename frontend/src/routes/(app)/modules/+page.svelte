<script lang="ts">
	import { onMount } from 'svelte';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card/index.ts';
	import { Badge } from '$lib/components/ui/badge/index.ts';
	import { goto } from '$app/navigation';
	import { Plus, Database, Edit, Trash2, Power } from 'lucide-svelte';
	import Page from '$lib/components/layout/Page.svelte';

	let modules = $state<Module[]>([]);
	let loading = $state(true);
	let error = $state<string | null>(null);

	onMount(async () => {
		try {
			modules = await modulesApi.getAll();
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load modules';
		} finally {
			loading = false;
		}
	});

	async function toggleModuleStatus(moduleId: number) {
		try {
			const updated = await modulesApi.toggleStatus(moduleId);
			modules = modules.map((m) => (m.id === moduleId ? updated : m));
		} catch (err) {
			alert(err instanceof Error ? err.message : 'Failed to toggle module status');
		}
	}

	async function deleteModule(moduleId: number, moduleName: string) {
		if (!confirm(`Are you sure you want to delete the "${moduleName}" module?`)) {
			return;
		}

		try {
			await modulesApi.delete(moduleId);
			modules = modules.filter((m) => m.id !== moduleId);
		} catch (err) {
			alert(err instanceof Error ? err.message : 'Failed to delete module');
		}
	}

	function createModule() {
		goto('/modules/create');
	}

	function viewModule(apiName: string) {
		goto(`/records/${apiName}`);
	}
</script>

<Page>
	<div class="mb-8 flex items-center justify-between">
		<div>
			<h1 class="text-3xl font-bold">Modules</h1>
			<p class="mt-2 text-muted-foreground">Manage your custom modules and data structures</p>
		</div>
		<Button onclick={createModule}>
			<Plus class="mr-2 h-4 w-4" />
			Create Module
		</Button>
	</div>

	{#if loading}
		<div class="flex items-center justify-center py-12">
			<div class="text-center">
				<div class="mx-auto h-12 w-12 animate-spin rounded-full border-b-2 border-primary"></div>
				<p class="mt-4 text-muted-foreground">Loading modules...</p>
			</div>
		</div>
	{:else if error}
		<Card.Root class="border-destructive">
			<Card.CardContent class="pt-6">
				<p class="text-destructive">{error}</p>
			</Card.CardContent>
		</Card.Root>
	{:else if modules.length === 0}
		<Card.Root>
			<Card.CardContent class="py-12 pt-6 text-center">
				<Database class="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
				<h3 class="mb-2 text-lg font-semibold">No modules yet</h3>
				<p class="mb-4 text-muted-foreground">
					Create your first custom module to start managing your data
				</p>
				<Button onclick={createModule}>
					<Plus class="mr-2 h-4 w-4" />
					Create Your First Module
				</Button>
			</Card.CardContent>
		</Card.Root>
	{:else}
		<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
			{#each modules as module (module.id)}
				<Card.Root class="transition-shadow hover:shadow-lg">
					<Card.CardHeader>
						<div class="flex items-start justify-between">
							<div class="flex items-center gap-3">
								{#if module.icon}
									<div class="rounded-lg bg-primary/10 p-2">
										<Database class="h-5 w-5 text-primary" />
									</div>
								{/if}
								<div>
									<Card.CardTitle class="text-xl">{module.name}</Card.CardTitle>
									<Card.CardDescription class="mt-1">
										{module.api_name}
									</Card.CardDescription>
								</div>
							</div>
							<Badge variant={module.is_active ? 'default' : 'secondary'}>
								{module.is_active ? 'Active' : 'Inactive'}
							</Badge>
						</div>
					</Card.CardHeader>
					<Card.CardContent>
						{#if module.description}
							<p class="mb-4 line-clamp-2 text-sm text-muted-foreground">
								{module.description}
							</p>
						{/if}

						{#if module.blocks}
							<div class="mb-4 text-sm text-muted-foreground">
								<div class="flex items-center gap-4">
									<span>{module.blocks.length} blocks</span>
									<span>
										{module.blocks.reduce((sum, block) => sum + block.fields.length, 0)} fields
									</span>
								</div>
							</div>
						{/if}

						<div class="flex items-center gap-2">
							<Button variant="default" size="sm" onclick={() => viewModule(module.api_name)}>
								<Database class="mr-2 h-4 w-4" />
								View Records
							</Button>

							<Button
								variant="outline"
								size="icon"
								onclick={() => goto(`/modules/edit/${module.id}`)}
								title="Edit module"
							>
								<Edit class="h-4 w-4" />
							</Button>

							<Button
								variant="outline"
								size="icon"
								onclick={() => toggleModuleStatus(module.id)}
								title={module.is_active ? 'Deactivate' : 'Activate'}
							>
								<Power class="h-4 w-4" />
							</Button>

							<Button
								variant="outline"
								size="icon"
								onclick={() => deleteModule(module.id, module.name)}
								title="Delete module"
							>
								<Trash2 class="h-4 w-4" />
							</Button>
						</div>
					</Card.CardContent>
				</Card.Root>
			{/each}
		</div>
	{/if}
</Page>
