<script lang="ts">
	import { page } from '$app/stores';
	import type { Campaign, CampaignAudience, CampaignAsset, CampaignAnalytics as Analytics } from '$lib/api/campaigns';
	import {
		getCampaign,
		getCampaignTypes,
		getCampaignStatuses,
		startCampaign,
		pauseCampaign,
		completeCampaign,
		cancelCampaign,
		deleteAudience,
		deleteAsset
	} from '$lib/api/campaigns';
	import { CampaignAnalytics, AudienceBuilder } from '$lib/components/campaigns';
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import * as Tabs from '$lib/components/ui/tabs';
	import * as AlertDialog from '$lib/components/ui/alert-dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { Spinner } from '$lib/components/ui/spinner';
	import { toast } from 'svelte-sonner';
	import { goto } from '$app/navigation';
	import {
		ArrowLeft,
		Edit,
		Play,
		Pause,
		CheckCircle,
		XCircle,
		Users,
		FileText,
		BarChart3,
		Plus,
		Trash2,
		Mail,
		Megaphone,
		Calendar,
		Rocket,
		Newspaper,
		UserPlus,
		DollarSign
	} from 'lucide-svelte';

	const campaignId = $derived(parseInt($page.params.id ?? '0'));

	let loading = $state(true);
	let campaign = $state<Campaign | null>(null);
	let analytics = $state<Analytics | null>(null);
	let campaignTypes = $state<Record<string, string>>({});
	let campaignStatuses = $state<Record<string, string>>({});

	let activeTab = $state('overview');
	let showAudienceBuilder = $state(false);
	let editingAudience = $state<CampaignAudience | undefined>(undefined);

	let deleteAudienceDialog = $state(false);
	let audienceToDelete = $state<CampaignAudience | null>(null);
	let deletingAudience = $state(false);

	const typeIcons: Record<string, typeof Mail> = {
		email: Mail,
		drip: Megaphone,
		event: Calendar,
		product_launch: Rocket,
		newsletter: Newspaper,
		re_engagement: UserPlus
	};

	const statusColors: Record<string, string> = {
		draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
		scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
		active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
		paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
		completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
		cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
	};

	async function loadData() {
		loading = true;
		try {
			const [result, typesData, statusesData] = await Promise.all([
				getCampaign(campaignId),
				getCampaignTypes(),
				getCampaignStatuses()
			]);
			campaign = result.campaign;
			analytics = result.analytics;
			campaignTypes = typesData;
			campaignStatuses = statusesData;
		} catch (error) {
			console.error('Failed to load campaign:', error);
			toast.error('Failed to load campaign');
		} finally {
			loading = false;
		}
	}

	async function handleStart() {
		try {
			campaign = await startCampaign(campaignId);
			toast.success('Campaign started');
		} catch (error) {
			console.error('Failed to start:', error);
			toast.error('Failed to start campaign');
		}
	}

	async function handlePause() {
		try {
			campaign = await pauseCampaign(campaignId);
			toast.success('Campaign paused');
		} catch (error) {
			console.error('Failed to pause:', error);
			toast.error('Failed to pause campaign');
		}
	}

	async function handleComplete() {
		try {
			campaign = await completeCampaign(campaignId);
			toast.success('Campaign completed');
		} catch (error) {
			console.error('Failed to complete:', error);
			toast.error('Failed to complete campaign');
		}
	}

	async function handleCancel() {
		try {
			campaign = await cancelCampaign(campaignId);
			toast.success('Campaign cancelled');
		} catch (error) {
			console.error('Failed to cancel:', error);
			toast.error('Failed to cancel campaign');
		}
	}

	function handleAddAudience() {
		editingAudience = undefined;
		showAudienceBuilder = true;
	}

	function handleEditAudience(audience: CampaignAudience) {
		editingAudience = audience;
		showAudienceBuilder = true;
	}

	function handleAudienceSaved() {
		showAudienceBuilder = false;
		editingAudience = undefined;
		loadData();
	}

	function confirmDeleteAudience(audience: CampaignAudience) {
		audienceToDelete = audience;
		deleteAudienceDialog = true;
	}

	async function handleDeleteAudience() {
		if (!audienceToDelete) return;

		deletingAudience = true;
		try {
			await deleteAudience(campaignId, audienceToDelete.id);
			toast.success('Audience deleted');
			deleteAudienceDialog = false;
			audienceToDelete = null;
			loadData();
		} catch (error) {
			console.error('Failed to delete audience:', error);
			toast.error('Failed to delete audience');
		} finally {
			deletingAudience = false;
		}
	}

	function formatDate(dateString: string | null): string {
		if (!dateString) return '-';
		return new Date(dateString).toLocaleDateString();
	}

	function formatCurrency(value: number | null): string {
		if (value === null) return '-';
		return new Intl.NumberFormat('en-US', {
			style: 'currency',
			currency: 'USD',
			minimumFractionDigits: 0,
			maximumFractionDigits: 0
		}).format(value);
	}

	$effect(() => {
		loadData();
	});
</script>

<svelte:head>
	<title>{campaign?.name ?? 'Campaign'} | VRTX</title>
</svelte:head>

{#if loading}
	<div class="flex items-center justify-center py-12">
		<Spinner class="h-8 w-8" />
	</div>
{:else if !campaign}
	<div class="container mx-auto p-6">
		<Card.Root>
			<Card.Content class="flex flex-col items-center justify-center py-12">
				<XCircle class="h-12 w-12 text-muted-foreground" />
				<h3 class="mt-4 text-lg font-medium">Campaign not found</h3>
				<Button class="mt-4" href="/marketing/campaigns">Back to Campaigns</Button>
			</Card.Content>
		</Card.Root>
	</div>
{:else}
	<div class="container mx-auto space-y-6 p-6">
		<!-- Header -->
		<div class="flex items-start justify-between">
			<div class="flex items-start gap-4">
				<Button variant="ghost" size="icon" href="/marketing/campaigns">
					<ArrowLeft class="h-4 w-4" />
				</Button>
				{@const Icon = typeIcons[campaign.type] ?? Mail}
				<div class="flex items-start gap-4">
					<div class="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
						<Icon class="h-6 w-6 text-muted-foreground" />
					</div>
					<div>
						<div class="flex items-center gap-3">
							<h1 class="text-2xl font-bold tracking-tight">{campaign.name}</h1>
							<Badge class={statusColors[campaign.status]}>
								{campaignStatuses[campaign.status]}
							</Badge>
						</div>
						{#if campaign.description}
							<p class="mt-1 text-muted-foreground">{campaign.description}</p>
						{/if}
						<div class="mt-2 flex items-center gap-4 text-sm text-muted-foreground">
							<span>{campaignTypes[campaign.type]}</span>
							{#if campaign.start_date}
								<span>Started: {formatDate(campaign.start_date)}</span>
							{/if}
							{#if campaign.budget}
								<span class="flex items-center gap-1">
									<DollarSign class="h-3 w-3" />
									{formatCurrency(campaign.budget)} budget
								</span>
							{/if}
						</div>
					</div>
				</div>
			</div>

			<div class="flex items-center gap-2">
				{#if campaign.status === 'draft' || campaign.status === 'paused'}
					<Button onclick={handleStart}>
						<Play class="mr-2 h-4 w-4" />
						Start
					</Button>
				{/if}
				{#if campaign.status === 'active'}
					<Button variant="outline" onclick={handlePause}>
						<Pause class="mr-2 h-4 w-4" />
						Pause
					</Button>
					<Button variant="outline" onclick={handleComplete}>
						<CheckCircle class="mr-2 h-4 w-4" />
						Complete
					</Button>
				{/if}
				{#if campaign.status !== 'completed' && campaign.status !== 'cancelled'}
					<Button variant="outline" onclick={handleCancel}>
						<XCircle class="mr-2 h-4 w-4" />
						Cancel
					</Button>
				{/if}
				<Button variant="outline" href={`/marketing/campaigns/${campaign.id}/edit`}>
					<Edit class="mr-2 h-4 w-4" />
					Edit
				</Button>
			</div>
		</div>

		<!-- Tabs -->
		<Tabs.Root bind:value={activeTab}>
			<Tabs.List>
				<Tabs.Trigger value="overview">
					<BarChart3 class="mr-2 h-4 w-4" />
					Overview
				</Tabs.Trigger>
				<Tabs.Trigger value="audiences">
					<Users class="mr-2 h-4 w-4" />
					Audiences ({campaign.audiences?.length ?? 0})
				</Tabs.Trigger>
				<Tabs.Trigger value="assets">
					<FileText class="mr-2 h-4 w-4" />
					Assets ({campaign.assets?.length ?? 0})
				</Tabs.Trigger>
			</Tabs.List>

			<Tabs.Content value="overview" class="mt-6">
				<CampaignAnalytics {campaign} />
			</Tabs.Content>

			<Tabs.Content value="audiences" class="mt-6">
				{#if showAudienceBuilder}
					<Card.Root>
						<Card.Header>
							<Card.Title>{editingAudience ? 'Edit Audience' : 'Add Audience'}</Card.Title>
						</Card.Header>
						<Card.Content>
							<AudienceBuilder
								campaignId={campaign.id}
								audience={editingAudience}
								onSave={handleAudienceSaved}
								onCancel={() => (showAudienceBuilder = false)}
							/>
						</Card.Content>
					</Card.Root>
				{:else}
					<div class="space-y-4">
						<div class="flex justify-end">
							<Button onclick={handleAddAudience}>
								<Plus class="mr-2 h-4 w-4" />
								Add Audience
							</Button>
						</div>

						{#if !campaign.audiences || campaign.audiences.length === 0}
							<Card.Root>
								<Card.Content class="flex flex-col items-center justify-center py-12">
									<Users class="h-12 w-12 text-muted-foreground" />
									<h3 class="mt-4 text-lg font-medium">No audiences</h3>
									<p class="mt-1 text-sm text-muted-foreground">
										Add an audience to target contacts for this campaign
									</p>
									<Button class="mt-4" onclick={handleAddAudience}>
										<Plus class="mr-2 h-4 w-4" />
										Add Audience
									</Button>
								</Card.Content>
							</Card.Root>
						{:else}
							<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
								{#each campaign.audiences as audience}
									<Card.Root>
										<Card.Header>
											<div class="flex items-start justify-between">
												<div>
													<Card.Title class="text-base">{audience.name}</Card.Title>
													{#if audience.description}
														<Card.Description class="line-clamp-2">
															{audience.description}
														</Card.Description>
													{/if}
												</div>
												<Badge variant={audience.is_dynamic ? 'default' : 'secondary'}>
													{audience.is_dynamic ? 'Dynamic' : 'Static'}
												</Badge>
											</div>
										</Card.Header>
										<Card.Content>
											<div class="flex items-center justify-between">
												<div class="flex items-center gap-2 text-2xl font-bold">
													<Users class="h-5 w-5 text-muted-foreground" />
													{audience.contact_count}
												</div>
												<div class="flex gap-1">
													<Button
														variant="ghost"
														size="sm"
														onclick={() => handleEditAudience(audience)}
													>
														<Edit class="h-4 w-4" />
													</Button>
													<Button
														variant="ghost"
														size="sm"
														onclick={() => confirmDeleteAudience(audience)}
													>
														<Trash2 class="h-4 w-4 text-destructive" />
													</Button>
												</div>
											</div>
											{#if audience.module}
												<p class="mt-2 text-sm text-muted-foreground">
													From: {audience.module.name}
												</p>
											{/if}
										</Card.Content>
									</Card.Root>
								{/each}
							</div>
						{/if}
					</div>
				{/if}
			</Tabs.Content>

			<Tabs.Content value="assets" class="mt-6">
				{#if !campaign.assets || campaign.assets.length === 0}
					<Card.Root>
						<Card.Content class="flex flex-col items-center justify-center py-12">
							<FileText class="h-12 w-12 text-muted-foreground" />
							<h3 class="mt-4 text-lg font-medium">No assets</h3>
							<p class="mt-1 text-sm text-muted-foreground">
								Add email templates, images, or documents to this campaign
							</p>
							<Button class="mt-4">
								<Plus class="mr-2 h-4 w-4" />
								Add Asset
							</Button>
						</Card.Content>
					</Card.Root>
				{:else}
					<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
						{#each campaign.assets as asset}
							<Card.Root>
								<Card.Header>
									<div class="flex items-start justify-between">
										<div>
											<Card.Title class="text-base">{asset.name}</Card.Title>
											{#if asset.description}
												<Card.Description class="line-clamp-2">
													{asset.description}
												</Card.Description>
											{/if}
										</div>
										<Badge variant="outline">{asset.type}</Badge>
									</div>
								</Card.Header>
								<Card.Content>
									{#if asset.subject}
										<p class="text-sm text-muted-foreground">Subject: {asset.subject}</p>
									{/if}
									<p class="mt-2 text-xs text-muted-foreground">Version {asset.version}</p>
								</Card.Content>
							</Card.Root>
						{/each}
					</div>
				{/if}
			</Tabs.Content>
		</Tabs.Root>
	</div>
{/if}

<!-- Delete Audience Confirmation -->
<AlertDialog.Root bind:open={deleteAudienceDialog}>
	<AlertDialog.Content>
		<AlertDialog.Header>
			<AlertDialog.Title>Delete Audience</AlertDialog.Title>
			<AlertDialog.Description>
				Are you sure you want to delete "{audienceToDelete?.name}"? This action cannot be undone.
			</AlertDialog.Description>
		</AlertDialog.Header>
		<AlertDialog.Footer>
			<AlertDialog.Cancel>Cancel</AlertDialog.Cancel>
			<AlertDialog.Action
				class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
				onclick={handleDeleteAudience}
				disabled={deletingAudience}
			>
				{#if deletingAudience}
					<Spinner class="mr-2 h-4 w-4" />
				{/if}
				Delete
			</AlertDialog.Action>
		</AlertDialog.Footer>
	</AlertDialog.Content>
</AlertDialog.Root>
