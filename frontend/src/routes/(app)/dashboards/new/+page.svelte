<script lang="ts">
	import { goto } from '$app/navigation';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Card from '$lib/components/ui/card';
	import { ArrowLeft, ArrowRight, Save, LayoutTemplate } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import {
		dashboardsApi,
		dashboardTemplatesApi,
		type DashboardTemplate
	} from '$lib/api/dashboards';
	import TemplateSelector from '$lib/components/dashboard/TemplateSelector.svelte';

	// Step state: 'template' or 'details'
	let step = $state<'template' | 'details'>('template');

	// Template selection
	let selectedTemplate = $state<DashboardTemplate | null>(null);

	// Form fields
	let name = $state('');
	let description = $state('');
	let isPublic = $state(false);
	let isDefault = $state(false);
	let saving = $state(false);

	// Pre-fill name when template is selected
	$effect(() => {
		if (selectedTemplate && !name) {
			name = selectedTemplate.name;
			description = selectedTemplate.description || '';
		}
	});

	function handleTemplateSelect(template: DashboardTemplate | null) {
		selectedTemplate = template;
	}

	function goToDetails() {
		step = 'details';
	}

	function goToTemplate() {
		step = 'template';
	}

	async function handleCreate() {
		if (!name.trim()) {
			toast.error('Please enter a dashboard name');
			return;
		}

		saving = true;
		try {
			let dashboardId: number;

			if (selectedTemplate) {
				// Create from template
				const result = await dashboardTemplatesApi.createDashboard(selectedTemplate.id, {
					name: name.trim(),
					description: description.trim() || undefined
				});
				dashboardId = result.id;

				// Update public/default if needed
				if (isPublic || isDefault) {
					await dashboardsApi.update(dashboardId, {
						is_public: isPublic,
						is_default: isDefault
					});
				}

				toast.success(`Dashboard created from "${selectedTemplate.name}" template`);
			} else {
				// Create blank dashboard
				const dashboard = await dashboardsApi.create({
					name: name.trim(),
					description: description.trim() || undefined,
					is_public: isPublic,
					is_default: isDefault
				});
				dashboardId = dashboard.id;
				toast.success('Dashboard created');
			}

			goto(`/dashboards/${dashboardId}?edit=true`);
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

<div class="container mx-auto max-w-4xl p-6">
	<!-- Header -->
	<div class="mb-6 flex items-center gap-4">
		<Button
			variant="ghost"
			size="icon"
			onclick={() => (step === 'details' ? goToTemplate() : goto('/dashboards'))}
		>
			<ArrowLeft class="h-4 w-4" />
		</Button>
		<div>
			<h1 class="text-2xl font-bold">Create Dashboard</h1>
			<p class="text-muted-foreground">
				{step === 'template' ? 'Choose a template to get started' : 'Configure your dashboard'}
			</p>
		</div>
	</div>

	<!-- Step Indicator -->
	<div class="mb-8 flex items-center gap-4">
		<div
			class="flex items-center gap-2 {step === 'template'
				? 'text-primary'
				: 'text-muted-foreground'}"
		>
			<div
				class="flex h-8 w-8 items-center justify-center rounded-full {step === 'template'
					? 'bg-primary text-primary-foreground'
					: 'bg-muted'}"
			>
				1
			</div>
			<span class="font-medium">Choose Template</span>
		</div>
		<div class="h-px flex-1 bg-border"></div>
		<div
			class="flex items-center gap-2 {step === 'details'
				? 'text-primary'
				: 'text-muted-foreground'}"
		>
			<div
				class="flex h-8 w-8 items-center justify-center rounded-full {step === 'details'
					? 'bg-primary text-primary-foreground'
					: 'bg-muted'}"
			>
				2
			</div>
			<span class="font-medium">Configure</span>
		</div>
	</div>

	{#if step === 'template'}
		<!-- Step 1: Template Selection -->
		<Card.Root>
			<Card.Header>
				<Card.Title class="flex items-center gap-2">
					<LayoutTemplate class="h-5 w-5" />
					Select a Template
				</Card.Title>
				<Card.Description>
					Choose a pre-built template or start with a blank dashboard
				</Card.Description>
			</Card.Header>
			<Card.Content>
				<TemplateSelector bind:selected={selectedTemplate} onSelect={handleTemplateSelect} />
			</Card.Content>
			<Card.Footer class="flex justify-end">
				<Button onclick={goToDetails}>
					Continue
					<ArrowRight class="ml-2 h-4 w-4" />
				</Button>
			</Card.Footer>
		</Card.Root>
	{:else}
		<!-- Step 2: Dashboard Details -->
		<Card.Root>
			<Card.Header>
				<Card.Title>Dashboard Details</Card.Title>
				<Card.Description>
					{#if selectedTemplate}
						Creating from "<span class="font-medium">{selectedTemplate.name}</span>" template
					{:else}
						Configure the basic settings for your blank dashboard
					{/if}
				</Card.Description>
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
			<Card.Footer class="flex justify-between">
				<Button variant="outline" onclick={goToTemplate}>
					<ArrowLeft class="mr-2 h-4 w-4" />
					Back
				</Button>
				<div class="flex gap-2">
					<Button variant="outline" onclick={() => goto('/dashboards')}>Cancel</Button>
					<Button onclick={handleCreate} disabled={saving || !name.trim()}>
						<Save class="mr-2 h-4 w-4" />
						Create Dashboard
					</Button>
				</div>
			</Card.Footer>
		</Card.Root>
	{/if}
</div>
