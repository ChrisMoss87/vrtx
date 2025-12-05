<script lang="ts">
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import { ArrowLeft, Save } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { dashboardsApi } from '$lib/api/dashboards';

	let name = $state('');
	let description = $state('');
	let isPublic = $state(false);
	let isDefault = $state(false);
	let saving = $state(false);

	async function handleCreate() {
		if (!name.trim()) {
			toast.error('Please enter a dashboard name');
			return;
		}

		saving = true;
		try {
			const dashboard = await dashboardsApi.create({
				name: name.trim(),
				description: description.trim() || undefined,
				is_public: isPublic,
				is_default: isDefault
			});

			toast.success('Dashboard created');
			goto(`/dashboards/${dashboard.id}?edit=true`);
		} catch (error) {
			console.error('Failed to create dashboard:', error);
			toast.error('Failed to create dashboard');
		} finally {
			saving = false;
		}
	}
</script>

<svelte:head>
	<title>Create Dashboard | VRTX CRM</title>
</svelte:head>

<div class="container mx-auto max-w-2xl p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center gap-4">
		<Button variant="ghost" size="icon" onclick={() => goto('/dashboards')}>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold">Create Dashboard</h1>
			<p class="text-muted-foreground">Set up a new dashboard</p>
		</div>
	</div>

	<Card.Root>
		<Card.Header>
			<Card.Title>Dashboard Details</Card.Title>
			<Card.Description>Configure the basic settings for your dashboard</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-6">
			<div class="space-y-2">
				<Label for="name">Name *</Label>
				<Input
					id="name"
					placeholder="Enter dashboard name"
					bind:value={name}
					onkeydown={(e) => e.key === 'Enter' && handleCreate()}
				/>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					placeholder="Describe what this dashboard shows..."
					bind:value={description}
					rows={3}
				/>
			</div>

			<div class="flex items-center justify-between rounded-lg border p-4">
				<div>
					<Label>Public Dashboard</Label>
					<p class="text-sm text-muted-foreground">
						Allow other users to view this dashboard
					</p>
				</div>
				<Switch bind:checked={isPublic} />
			</div>

			<div class="flex items-center justify-between rounded-lg border p-4">
				<div>
					<Label>Default Dashboard</Label>
					<p class="text-sm text-muted-foreground">
						Show this dashboard by default when you log in
					</p>
				</div>
				<Switch bind:checked={isDefault} />
			</div>
		</Card.Content>
		<Card.Footer class="flex justify-end gap-2">
			<Button variant="outline" onclick={() => goto('/dashboards')}>Cancel</Button>
			<Button onclick={handleCreate} disabled={saving || !name.trim()}>
				<Save class="mr-2 h-4 w-4" />
				Create Dashboard
			</Button>
		</Card.Footer>
	</Card.Root>
</div>
