<script lang="ts">
	import type {
		Campaign,
		CampaignType,
		CreateCampaignRequest,
		UpdateCampaignRequest
	} from '$lib/api/campaigns';
	import { createCampaign, updateCampaign, getCampaignTypes } from '$lib/api/campaigns';
	import { getActiveModules, type Module } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { toast } from 'svelte-sonner';
	import { Loader2, Mail, Megaphone, Calendar, Rocket, Newspaper, UserPlus } from 'lucide-svelte';

	interface Props {
		campaign?: Campaign;
		onSave?: (campaign: Campaign) => void;
		onCancel?: () => void;
	}

	let { campaign, onSave, onCancel }: Props = $props();

	let loading = $state(false);
	let loadingModules = $state(true);
	let modules = $state<Module[]>([]);
	let campaignTypes = $state<Record<CampaignType, string>>({} as Record<CampaignType, string>);

	// Form state
	let name = $state(campaign?.name ?? '');
	let description = $state(campaign?.description ?? '');
	let type = $state<CampaignType>(campaign?.type ?? 'email');
	let moduleId = $state<number | null>(campaign?.module_id ?? null);
	let startDate = $state(campaign?.start_date?.split('T')[0] ?? '');
	let endDate = $state(campaign?.end_date?.split('T')[0] ?? '');
	let budget = $state(campaign?.budget?.toString() ?? '');

	const isEditing = $derived(!!campaign?.id);

	const typeIcons: Record<CampaignType, typeof Mail> = {
		email: Mail,
		drip: Megaphone,
		event: Calendar,
		product_launch: Rocket,
		newsletter: Newspaper,
		re_engagement: UserPlus
	};

	async function loadData() {
		loadingModules = true;
		try {
			const [modulesData, typesData] = await Promise.all([
				getActiveModules(),
				getCampaignTypes()
			]);
			modules = modulesData;
			campaignTypes = typesData;
		} catch (error) {
			console.error('Failed to load data:', error);
			toast.error('Failed to load form data');
		} finally {
			loadingModules = false;
		}
	}

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Campaign name is required');
			return;
		}

		loading = true;
		try {
			const data: CreateCampaignRequest | UpdateCampaignRequest = {
				name: name.trim(),
				description: description.trim() || undefined,
				type,
				module_id: moduleId ?? undefined,
				start_date: startDate || undefined,
				end_date: endDate || undefined,
				budget: budget ? parseFloat(budget) : undefined
			};

			let savedCampaign: Campaign;

			if (isEditing && campaign) {
				savedCampaign = await updateCampaign(campaign.id, data);
				toast.success('Campaign updated successfully');
			} else {
				savedCampaign = await createCampaign(data as CreateCampaignRequest);
				toast.success('Campaign created successfully');
			}

			onSave?.(savedCampaign);
		} catch (error) {
			console.error('Failed to save campaign:', error);
			toast.error('Failed to save campaign');
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		loadData();
	});
</script>

<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-6">
	<!-- Campaign Type Selection -->
	{#if !isEditing}
		<div class="space-y-3">
			<Label>Campaign Type</Label>
			<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
				{#each Object.entries(campaignTypes) as [typeKey, typeLabel]}
					{@const Icon = typeIcons[typeKey as CampaignType]}
					<button
						type="button"
						onclick={() => (type = typeKey as CampaignType)}
						class="flex flex-col items-center gap-2 rounded-lg border p-4 text-center transition-colors hover:bg-muted {type ===
						typeKey
							? 'border-primary bg-primary/5'
							: 'border-border'}"
					>
						<Icon class="h-6 w-6 {type === typeKey ? 'text-primary' : 'text-muted-foreground'}" />
						<span class="text-sm font-medium">{typeLabel}</span>
					</button>
				{/each}
			</div>
		</div>
	{/if}

	<!-- Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Campaign Details</Card.Title>
			<Card.Description>Basic information about your campaign</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Campaign Name *</Label>
				<Input
					id="name"
					bind:value={name}
					placeholder="Enter campaign name"
					required
				/>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Describe the purpose of this campaign"
					rows={3}
				/>
			</div>

			<div class="space-y-2">
				<Label for="module">Target Module</Label>
				<Select.Root
					type="single"
					value={moduleId?.toString()}
					onValueChange={(v) => (moduleId = v ? parseInt(v) : null)}
				>
					<Select.Trigger id="module">
						{#if loadingModules}
							<span class="text-muted-foreground">Loading...</span>
						{:else}
							<span>
								{modules.find((m) => m.id === moduleId)?.name ?? 'Select module'}
							</span>
						{/if}
					</Select.Trigger>
					<Select.Content>
						{#each modules as mod}
							<Select.Item value={mod.id.toString()}>{mod.name}</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
				<p class="text-xs text-muted-foreground">
					Select the module containing your target audience (e.g., Contacts, Leads)
				</p>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Schedule & Budget -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Schedule & Budget</Card.Title>
			<Card.Description>Set timing and budget constraints</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 sm:grid-cols-2">
				<div class="space-y-2">
					<Label for="startDate">Start Date</Label>
					<Input
						id="startDate"
						type="date"
						bind:value={startDate}
					/>
				</div>
				<div class="space-y-2">
					<Label for="endDate">End Date</Label>
					<Input
						id="endDate"
						type="date"
						bind:value={endDate}
						min={startDate || undefined}
					/>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="budget">Budget ($)</Label>
				<Input
					id="budget"
					type="number"
					bind:value={budget}
					placeholder="0.00"
					min="0"
					step="0.01"
				/>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Actions -->
	<div class="flex justify-end gap-3">
		{#if onCancel}
			<Button type="button" variant="outline" onclick={onCancel}>
				Cancel
			</Button>
		{/if}
		<Button type="submit" disabled={loading || !name.trim()}>
			{#if loading}
				<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			{/if}
			{isEditing ? 'Update Campaign' : 'Create Campaign'}
		</Button>
	</div>
</form>
