<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import type { Blueprint, BlueprintSla, BlueprintSlaEscalation } from '$lib/api/blueprints';
	import type { Field } from '$lib/api/modules';
	import * as blueprintApi from '$lib/api/blueprints';
	import { getModuleById } from '$lib/api/modules';
	import { BlueprintDesigner, SLABuilder } from '$lib/components/blueprint/designer';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Dialog from '$lib/components/ui/dialog';
	import * as Tabs from '$lib/components/ui/tabs';
	import { toast } from 'svelte-sonner';
	import ArrowLeftIcon from '@lucide/svelte/icons/arrow-left';
	import SettingsIcon from '@lucide/svelte/icons/settings';
	import ClockIcon from '@lucide/svelte/icons/clock';

	const blueprintId = $derived(parseInt($page.params.id || '0'));

	let blueprint = $state<Blueprint | null>(null);
	let moduleFields = $state<Field[]>([]);
	let slas = $state<BlueprintSla[]>([]);
	let loading = $state(true);
	let settingsOpen = $state(false);
	let activeTab = $state('designer');

	// Settings form state
	let name = $state('');
	let description = $state('');
	let isActive = $state(false);
	let saving = $state(false);

	async function loadBlueprint() {
		loading = true;
		try {
			blueprint = await blueprintApi.getBlueprint(blueprintId);
			name = blueprint.name;
			description = blueprint.description || '';
			isActive = blueprint.is_active;

			// Load module fields for conditions/requirements
			if (blueprint.module_id) {
				const module = await getModuleById(blueprint.module_id);
				// Flatten fields from all blocks
				moduleFields = module.blocks?.flatMap(block => block.fields) || [];
			}

			// Load SLAs
			slas = await blueprintApi.getSlas(blueprintId);
		} catch (error) {
			console.error('Failed to load blueprint:', error);
			toast.error('Failed to load blueprint');
			goto('/admin/blueprints');
		} finally {
			loading = false;
		}
	}

	async function saveSettings() {
		saving = true;
		try {
			blueprint = await blueprintApi.updateBlueprint(blueprintId, {
				name,
				description: description || undefined,
				is_active: isActive
			});
			toast.success('Settings saved');
			settingsOpen = false;
		} catch (error) {
			console.error('Failed to save settings:', error);
			toast.error('Failed to save settings');
		} finally {
			saving = false;
		}
	}

	// SLA handlers
	async function handleAddSla(sla: Partial<BlueprintSla>) {
		try {
			const created = await blueprintApi.createSla(blueprintId, {
				state_id: sla.state_id!,
				name: sla.name!,
				duration_hours: sla.duration_hours!,
				business_hours_only: sla.business_hours_only,
				exclude_weekends: sla.exclude_weekends,
				is_active: sla.is_active
			});
			slas = [...slas, created];
			toast.success('SLA created');
		} catch (error) {
			console.error('Failed to create SLA:', error);
			toast.error('Failed to create SLA');
		}
	}

	async function handleUpdateSla(id: number, data: Partial<BlueprintSla>) {
		try {
			const updated = await blueprintApi.updateSla(blueprintId, id, data);
			slas = slas.map((s) => (s.id === id ? updated : s));
			toast.success('SLA updated');
		} catch (error) {
			console.error('Failed to update SLA:', error);
			toast.error('Failed to update SLA');
		}
	}

	async function handleDeleteSla(id: number) {
		try {
			await blueprintApi.deleteSla(blueprintId, id);
			slas = slas.filter((s) => s.id !== id);
			toast.success('SLA deleted');
		} catch (error) {
			console.error('Failed to delete SLA:', error);
			toast.error('Failed to delete SLA');
		}
	}

	async function handleAddEscalation(slaId: number, escalation: Partial<BlueprintSlaEscalation>) {
		try {
			const created = await blueprintApi.createSlaEscalation(slaId, {
				trigger_type: escalation.trigger_type!,
				trigger_value: escalation.trigger_value ?? undefined,
				action_type: escalation.action_type!,
				config: escalation.config!,
				display_order: escalation.display_order
			});
			// Update the SLA with the new escalation
			slas = slas.map((s) => {
				if (s.id === slaId) {
					return { ...s, escalations: [...(s.escalations || []), created] };
				}
				return s;
			});
			toast.success('Escalation added');
		} catch (error) {
			console.error('Failed to add escalation:', error);
			toast.error('Failed to add escalation');
		}
	}

	async function handleDeleteEscalation(slaId: number, escalationId: number) {
		try {
			await blueprintApi.deleteSlaEscalation(slaId, escalationId);
			// Remove the escalation from the SLA
			slas = slas.map((s) => {
				if (s.id === slaId) {
					return { ...s, escalations: (s.escalations || []).filter((e) => e.id !== escalationId) };
				}
				return s;
			});
			toast.success('Escalation deleted');
		} catch (error) {
			console.error('Failed to delete escalation:', error);
			toast.error('Failed to delete escalation');
		}
	}

	onMount(() => {
		loadBlueprint();
	});
</script>

<svelte:head>
	<title>{blueprint?.name || 'Blueprint'} | Admin</title>
</svelte:head>

<div class="flex h-[calc(100vh-4rem)] flex-col">
	<!-- Header -->
	<div class="flex items-center justify-between border-b px-4 py-3">
		<div class="flex items-center gap-4">
			<Button variant="ghost" size="icon" href="/admin/blueprints">
				<ArrowLeftIcon class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-lg font-semibold">{blueprint?.name || 'Loading...'}</h1>
				<p class="text-sm text-muted-foreground">
					{blueprint?.module?.name} &middot; {blueprint?.field?.label}
				</p>
			</div>
		</div>

		<div class="flex items-center gap-2">
			<!-- Tab navigation -->
			<Tabs.Root bind:value={activeTab} class="mr-4">
				<Tabs.List>
					<Tabs.Trigger value="designer">Designer</Tabs.Trigger>
					<Tabs.Trigger value="sla">
						<ClockIcon class="mr-1 h-4 w-4" />
						SLAs
					</Tabs.Trigger>
				</Tabs.List>
			</Tabs.Root>

			<Button variant="outline" size="sm" onclick={() => (settingsOpen = true)}>
				<SettingsIcon class="mr-2 h-4 w-4" />
				Settings
			</Button>
		</div>
	</div>

	<!-- Main content -->
	<div class="flex-1 overflow-hidden p-4">
		{#if loading}
			<div class="flex h-full items-center justify-center">
				<div class="text-muted-foreground">Loading blueprint...</div>
			</div>
		{:else if activeTab === 'designer'}
			<BlueprintDesigner {blueprintId} fields={moduleFields} />
		{:else if activeTab === 'sla'}
			<div class="mx-auto max-w-2xl">
				<SLABuilder
					{slas}
					states={blueprint?.states || []}
					onAddSla={handleAddSla}
					onUpdateSla={handleUpdateSla}
					onDeleteSla={handleDeleteSla}
					onAddEscalation={handleAddEscalation}
					onDeleteEscalation={handleDeleteEscalation}
				/>
			</div>
		{/if}
	</div>
</div>

<!-- Settings Dialog -->
<Dialog.Root bind:open={settingsOpen}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Blueprint Settings</Dialog.Title>
			<Dialog.Description>
				Configure general settings for this blueprint.
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4 py-4">
			<div class="space-y-2">
				<Label for="settings-name">Name</Label>
				<Input id="settings-name" bind:value={name} placeholder="Blueprint name" />
			</div>

			<div class="space-y-2">
				<Label for="settings-description">Description</Label>
				<Input
					id="settings-description"
					bind:value={description}
					placeholder="Optional description"
				/>
			</div>

			<div class="flex items-center justify-between">
				<div>
					<Label for="settings-active">Active</Label>
					<p class="text-xs text-muted-foreground">
						When active, this blueprint will control transitions for the module.
					</p>
				</div>
				<Switch
					id="settings-active"
					checked={isActive}
					onCheckedChange={(checked) => (isActive = checked)}
				/>
			</div>

			{#if blueprint}
				<div class="rounded-lg bg-muted p-3 text-sm">
					<p><strong>Module:</strong> {blueprint.module?.name}</p>
					<p><strong>Field:</strong> {blueprint.field?.label}</p>
					<p><strong>States:</strong> {blueprint.states?.length || 0}</p>
					<p><strong>Transitions:</strong> {blueprint.transitions?.length || 0}</p>
				</div>
			{/if}
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (settingsOpen = false)}>Cancel</Button>
			<Button onclick={saveSettings} disabled={saving}>
				{saving ? 'Saving...' : 'Save Changes'}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
