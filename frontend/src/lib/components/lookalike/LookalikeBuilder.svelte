<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Slider } from '$lib/components/ui/slider';
	import { Users, Target, Settings, RefreshCcw, Download } from 'lucide-svelte';
	import type { LookalikeAudience, SourceType, RefreshFrequency, CriteriaType } from '$lib/api/lookalike';
	import { getCriteriaLabel } from '$lib/api/lookalike';

	interface Props {
		audience?: LookalikeAudience;
		onSave: (data: Partial<LookalikeAudience>) => void;
		onCancel: () => void;
		savedSearches?: { id: number; name: string }[];
		segments?: { id: number; name: string }[];
	}

	let { audience, onSave, onCancel, savedSearches = [], segments = [] }: Props = $props();

	let name = $state(audience?.name || '');
	let description = $state(audience?.description || '');
	let sourceType = $state<SourceType>(audience?.source_type || 'manual');
	let sourceId = $state(audience?.source_id || 0);
	let minSimilarityScore = $state(audience?.min_similarity_score || 70);
	let sizeLimit = $state<number | null>(audience?.size_limit || null);
	let autoRefresh = $state(audience?.auto_refresh || false);
	let refreshFrequency = $state<RefreshFrequency | null>(audience?.refresh_frequency || null);

	const criteriaTypes: CriteriaType[] = ['industry', 'company_size', 'location', 'behavior', 'technology', 'engagement', 'purchase'];

	let matchCriteria = $state<Record<string, boolean>>(
		audience?.match_criteria || {
			industry: true,
			company_size: true,
			location: true,
			behavior: false,
			technology: false,
			engagement: true,
			purchase: false
		}
	);

	let weights = $state<Record<string, number>>(
		audience?.weights || {
			industry: 25,
			company_size: 20,
			location: 15,
			behavior: 10,
			technology: 10,
			engagement: 15,
			purchase: 5
		}
	);

	const sourceTypes: { value: SourceType; label: string }[] = [
		{ value: 'saved_search', label: 'Saved Search' },
		{ value: 'manual', label: 'Manual Selection' },
		{ value: 'segment', label: 'Segment' }
	];

	const refreshOptions: { value: RefreshFrequency; label: string }[] = [
		{ value: 'daily', label: 'Daily' },
		{ value: 'weekly', label: 'Weekly' },
		{ value: 'monthly', label: 'Monthly' }
	];

	const sourceOptions = $derived(
		sourceType === 'saved_search'
			? savedSearches
			: sourceType === 'segment'
				? segments
				: []
	);

	function toggleCriteria(criterion: string) {
		matchCriteria = { ...matchCriteria, [criterion]: !matchCriteria[criterion] };
	}

	function updateWeight(criterion: string, value: number) {
		weights = { ...weights, [criterion]: value };
	}

	const totalWeight = $derived(
		Object.entries(weights)
			.filter(([key]) => matchCriteria[key])
			.reduce((sum, [, value]) => sum + value, 0)
	);

	function handleSave() {
		onSave({
			name,
			description: description || undefined,
			source_type: sourceType,
			source_id: sourceId || undefined,
			match_criteria: matchCriteria,
			weights,
			min_similarity_score: minSimilarityScore,
			size_limit: sizeLimit || undefined,
			auto_refresh: autoRefresh,
			refresh_frequency: autoRefresh ? refreshFrequency : undefined
		});
	}

	const isValid = $derived(
		name.trim() !== '' &&
		(sourceType === 'manual' || sourceId > 0) &&
		Object.values(matchCriteria).some(Boolean)
	);
</script>

<div class="space-y-6">
	<!-- Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Users class="h-5 w-5" />
				Audience Configuration
			</Card.Title>
			<Card.Description>Define your lookalike audience parameters</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label for="name">Audience Name</Label>
					<Input id="name" bind:value={name} placeholder="e.g., High-Value Customers Lookalike" />
				</div>

				<div class="space-y-2">
					<Label for="sourceType">Source Type</Label>
					<Select.Root
						type="single"
						value={sourceType}
						onValueChange={(v) => {
							sourceType = v as SourceType;
							sourceId = 0;
						}}
					>
						<Select.Trigger>
							{sourceTypes.find((t) => t.value === sourceType)?.label || 'Select source type'}
						</Select.Trigger>
						<Select.Content>
							{#each sourceTypes as t}
								<Select.Item value={t.value}>{t.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Describe the characteristics of your source audience..."
					rows={3}
				/>
			</div>

			{#if sourceType !== 'manual' && sourceOptions.length > 0}
				<div class="space-y-2">
					<Label for="source">Select Source</Label>
					<Select.Root
						type="single"
						value={sourceId.toString()}
						onValueChange={(v) => (sourceId = parseInt(v || '0'))}
					>
						<Select.Trigger>
							{sourceOptions.find((s) => s.id === sourceId)?.name || 'Select source'}
						</Select.Trigger>
						<Select.Content>
							{#each sourceOptions as source}
								<Select.Item value={source.id.toString()}>{source.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Match Criteria -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Target class="h-5 w-5" />
				Match Criteria
			</Card.Title>
			<Card.Description>Select which attributes to use for similarity matching</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 md:grid-cols-2">
				{#each criteriaTypes as criterion}
					<div class="flex items-start space-x-3 rounded-lg border p-4">
						<Checkbox
							id={criterion}
							checked={matchCriteria[criterion]}
							onCheckedChange={() => toggleCriteria(criterion)}
						/>
						<div class="flex-1">
							<Label for={criterion} class="font-medium cursor-pointer">
								{getCriteriaLabel(criterion)}
							</Label>
							{#if matchCriteria[criterion]}
								<div class="mt-2">
									<div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
										<span>Weight</span>
										<span>{weights[criterion]}%</span>
									</div>
									<Slider
										type="single"
										value={weights[criterion]}
										onValueChange={(v: number) => updateWeight(criterion, v)}
										min={1}
										max={50}
										step={1}
									/>
								</div>
							{/if}
						</div>
					</div>
				{/each}
			</div>

			<div class="flex items-center justify-between pt-2 border-t text-sm">
				<span class="text-muted-foreground">Total Weight (enabled criteria)</span>
				<span class="font-medium">
					{totalWeight}%
					{#if totalWeight !== 100}
						<span class="text-muted-foreground">(weights are normalized)</span>
					{/if}
				</span>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Settings -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Settings class="h-5 w-5" />
				Matching Settings
			</Card.Title>
			<Card.Description>Configure similarity thresholds and limits</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="space-y-2">
				<div class="flex items-center justify-between">
					<Label>Minimum Similarity Score</Label>
					<span class="font-medium">{minSimilarityScore}%</span>
				</div>
				<Slider
					type="single"
					bind:value={minSimilarityScore}
					min={50}
					max={95}
					step={5}
				/>
				<p class="text-xs text-muted-foreground">
					Only include contacts with a similarity score above this threshold
				</p>
			</div>

			<div class="space-y-2">
				<Label for="sizeLimit">Audience Size Limit (optional)</Label>
				<Input
					id="sizeLimit"
					type="number"
					value={sizeLimit || ''}
					oninput={(e) => {
						const val = e.currentTarget.value;
						sizeLimit = val ? parseInt(val) : null;
					}}
					placeholder="No limit"
					min={1}
				/>
				<p class="text-xs text-muted-foreground">
					Maximum number of matches to include in the audience
				</p>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Auto Refresh -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<RefreshCcw class="h-5 w-5" />
				Automatic Refresh
			</Card.Title>
			<Card.Description>Keep your audience up-to-date automatically</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="flex items-center space-x-2">
				<Switch id="autoRefresh" bind:checked={autoRefresh} />
				<Label for="autoRefresh">Enable automatic audience refresh</Label>
			</div>

			{#if autoRefresh}
				<div class="space-y-2">
					<Label for="frequency">Refresh Frequency</Label>
					<Select.Root
						type="single"
						value={refreshFrequency || ''}
						onValueChange={(v) => (refreshFrequency = v as RefreshFrequency)}
					>
						<Select.Trigger>
							{refreshOptions.find((r) => r.value === refreshFrequency)?.label || 'Select frequency'}
						</Select.Trigger>
						<Select.Content>
							{#each refreshOptions as opt}
								<Select.Item value={opt.value}>{opt.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Actions -->
	<div class="flex justify-end gap-3">
		<Button variant="outline" onclick={onCancel}>Cancel</Button>
		<Button onclick={handleSave} disabled={!isValid}>
			{audience ? 'Update Audience' : 'Create Audience'}
		</Button>
	</div>
</div>
