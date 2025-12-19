<script lang="ts">
	import type {
		CampaignAudience,
		SegmentRule,
		CreateAudienceRequest,
		UpdateAudienceRequest
	} from '$lib/api/campaigns';
	import { addAudience, updateAudience, previewAudience, refreshAudience } from '$lib/api/campaigns';
	import { getActiveModules, getModuleByApiName, type Module, type Field } from '$lib/api/modules';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Switch } from '$lib/components/ui/switch';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Separator } from '$lib/components/ui/separator';
	import { toast } from 'svelte-sonner';
	import { Loader2, Plus, Trash2, RefreshCw, Users, Eye } from 'lucide-svelte';

	interface Props {
		campaignId: number;
		audience?: CampaignAudience;
		onSave?: (audience: CampaignAudience) => void;
		onCancel?: () => void;
	}

	let { campaignId, audience, onSave, onCancel }: Props = $props();

	let loading = $state(false);
	let loadingModules = $state(true);
	let loadingFields = $state(false);
	let loadingPreview = $state(false);
	let refreshing = $state(false);

	let modules = $state<Module[]>([]);
	let fields = $state<Field[]>([]);
	let previewRecords = $state<unknown[]>([]);
	let previewCount = $state(0);
	let showPreview = $state(false);

	// Form state
	let name = $state(audience?.name ?? '');
	let description = $state(audience?.description ?? '');
	let moduleId = $state<number | null>(audience?.module_id ?? null);
	let isDynamic = $state(audience?.is_dynamic ?? true);
	let segmentRules = $state<SegmentRule[]>(audience?.segment_rules ?? []);

	const isEditing = $derived(!!audience?.id);

	const operators = [
		{ value: 'equals', label: 'Equals' },
		{ value: 'not_equals', label: 'Does not equal' },
		{ value: 'contains', label: 'Contains' },
		{ value: 'starts_with', label: 'Starts with' },
		{ value: 'ends_with', label: 'Ends with' },
		{ value: 'is_empty', label: 'Is empty' },
		{ value: 'is_not_empty', label: 'Is not empty' },
		{ value: 'greater_than', label: 'Greater than' },
		{ value: 'less_than', label: 'Less than' }
	];

	async function loadModules() {
		loadingModules = true;
		try {
			modules = await getActiveModules();
		} catch (error) {
			console.error('Failed to load modules:', error);
			toast.error('Failed to load modules');
		} finally {
			loadingModules = false;
		}
	}

	async function loadFields(modId: number) {
		loadingFields = true;
		fields = [];
		try {
			const module = modules.find((m) => m.id === modId);
			if (module) {
				const fullModule = await getModuleByApiName(module.api_name);
				fields = fullModule.fields ?? [];
			}
		} catch (error) {
			console.error('Failed to load fields:', error);
		} finally {
			loadingFields = false;
		}
	}

	function handleModuleChange(modId: string | undefined) {
		moduleId = modId ? parseInt(modId) : null;
		segmentRules = [];
		if (moduleId) {
			loadFields(moduleId);
		}
	}

	function addRule() {
		segmentRules = [...segmentRules, { field: '', operator: 'equals', value: '' }];
	}

	function removeRule(index: number) {
		segmentRules = segmentRules.filter((_, i) => i !== index);
	}

	function updateRule(index: number, updates: Partial<SegmentRule>) {
		segmentRules = segmentRules.map((rule, i) => (i === index ? { ...rule, ...updates } : rule));
	}

	async function handlePreview() {
		if (!audience?.id) return;

		loadingPreview = true;
		showPreview = true;
		try {
			const result = await previewAudience(campaignId, audience.id);
			previewRecords = result.records;
			previewCount = result.total_count;
		} catch (error) {
			console.error('Failed to preview audience:', error);
			toast.error('Failed to load preview');
		} finally {
			loadingPreview = false;
		}
	}

	async function handleRefresh() {
		if (!audience?.id) return;

		refreshing = true;
		try {
			const count = await refreshAudience(campaignId, audience.id);
			toast.success(`Audience refreshed: ${count} contacts`);
		} catch (error) {
			console.error('Failed to refresh audience:', error);
			toast.error('Failed to refresh audience');
		} finally {
			refreshing = false;
		}
	}

	async function handleSubmit() {
		if (!name.trim()) {
			toast.error('Audience name is required');
			return;
		}

		if (!moduleId) {
			toast.error('Please select a target module');
			return;
		}

		// Filter out empty rules
		const validRules = segmentRules.filter((r) => r.field && r.operator);

		loading = true;
		try {
			let savedAudience: CampaignAudience;

			if (isEditing && audience) {
				const data: UpdateAudienceRequest = {
					name: name.trim(),
					description: description.trim() || undefined,
					segment_rules: validRules,
					is_dynamic: isDynamic
				};
				savedAudience = await updateAudience(campaignId, audience.id, data);
				toast.success('Audience updated successfully');
			} else {
				const data: CreateAudienceRequest = {
					name: name.trim(),
					description: description.trim() || undefined,
					module_id: moduleId,
					segment_rules: validRules,
					is_dynamic: isDynamic
				};
				savedAudience = await addAudience(campaignId, data);
				toast.success('Audience created successfully');
			}

			onSave?.(savedAudience);
		} catch (error) {
			console.error('Failed to save audience:', error);
			toast.error('Failed to save audience');
		} finally {
			loading = false;
		}
	}

	$effect(() => {
		loadModules();
	});

	$effect(() => {
		if (audience?.module_id) {
			loadFields(audience.module_id);
		}
	});
</script>

<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-6">
	<!-- Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Users class="h-5 w-5" />
				Audience Details
			</Card.Title>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="space-y-2">
				<Label for="name">Audience Name *</Label>
				<Input id="name" bind:value={name} placeholder="e.g., Active Customers" required />
			</div>

			<div class="space-y-2">
				<Label for="description">Description</Label>
				<Textarea
					id="description"
					bind:value={description}
					placeholder="Describe this audience segment"
					rows={2}
				/>
			</div>

			<div class="space-y-2">
				<Label for="module">Target Module *</Label>
				<Select.Root
					type="single"
					value={moduleId?.toString()}
					onValueChange={handleModuleChange}
					disabled={isEditing}
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
			</div>

			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Dynamic Audience</Label>
					<p class="text-xs text-muted-foreground">
						Automatically include new contacts matching the rules
					</p>
				</div>
				<Switch bind:checked={isDynamic} />
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Segment Rules -->
	<Card.Root>
		<Card.Header>
			<Card.Title>Segment Rules</Card.Title>
			<Card.Description>Define criteria to select contacts for this audience</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			{#if segmentRules.length === 0}
				<div class="rounded-lg border border-dashed p-6 text-center">
					<p class="text-sm text-muted-foreground mb-3">
						No rules defined. All records from the selected module will be included.
					</p>
					<Button type="button" variant="outline" size="sm" onclick={addRule} disabled={!moduleId}>
						<Plus class="mr-2 h-4 w-4" />
						Add Rule
					</Button>
				</div>
			{:else}
				<div class="space-y-3">
					{#each segmentRules as rule, index}
						<div class="flex items-center gap-2 rounded-lg border bg-muted/30 p-3">
							{#if index > 0}
								<Badge variant="secondary" class="mr-2">AND</Badge>
							{/if}

							<!-- Field selector -->
							<Select.Root
								type="single"
								value={rule.field}
								onValueChange={(v) => updateRule(index, { field: v ?? '' })}
							>
								<Select.Trigger class="w-[180px]">
									<span>
										{fields.find((f) => f.api_name === rule.field)?.label ?? 'Select field'}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each fields as field}
										<Select.Item value={field.api_name}>{field.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

							<!-- Operator selector -->
							<Select.Root
								type="single"
								value={rule.operator}
								onValueChange={(v) => updateRule(index, { operator: v ?? 'equals' })}
							>
								<Select.Trigger class="w-[160px]">
									<span>
										{operators.find((o) => o.value === rule.operator)?.label ?? 'Equals'}
									</span>
								</Select.Trigger>
								<Select.Content>
									{#each operators as op}
										<Select.Item value={op.value}>{op.label}</Select.Item>
									{/each}
								</Select.Content>
							</Select.Root>

							<!-- Value input (hidden for is_empty/is_not_empty) -->
							{#if !['is_empty', 'is_not_empty'].includes(rule.operator)}
								<Input
									value={rule.value as string}
									oninput={(e) => updateRule(index, { value: e.currentTarget.value })}
									placeholder="Value"
									class="flex-1"
								/>
							{/if}

							<Button
								type="button"
								variant="ghost"
								size="icon"
								onclick={() => removeRule(index)}
							>
								<Trash2 class="h-4 w-4 text-destructive" />
							</Button>
						</div>
					{/each}
				</div>

				<Button type="button" variant="outline" size="sm" onclick={addRule}>
					<Plus class="mr-2 h-4 w-4" />
					Add Rule
				</Button>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Preview (only for existing audiences) -->
	{#if isEditing && audience}
		<Card.Root>
			<Card.Header>
				<div class="flex items-center justify-between">
					<div>
						<Card.Title>Audience Preview</Card.Title>
						<Card.Description>
							{audience.contact_count} contacts match this audience
						</Card.Description>
					</div>
					<div class="flex gap-2">
						<Button type="button" variant="outline" size="sm" onclick={handleRefresh} disabled={refreshing}>
							{#if refreshing}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							{:else}
								<RefreshCw class="mr-2 h-4 w-4" />
							{/if}
							Refresh Count
						</Button>
						<Button type="button" variant="outline" size="sm" onclick={handlePreview} disabled={loadingPreview}>
							{#if loadingPreview}
								<Loader2 class="mr-2 h-4 w-4 animate-spin" />
							{:else}
								<Eye class="mr-2 h-4 w-4" />
							{/if}
							Preview
						</Button>
					</div>
				</div>
			</Card.Header>
			{#if showPreview}
				<Card.Content>
					<ScrollArea class="h-[200px]">
						{#if previewRecords.length === 0}
							<p class="text-sm text-muted-foreground text-center py-4">
								No matching records found
							</p>
						{:else}
							<div class="space-y-2">
								{#each previewRecords as record}
									<div class="rounded border p-2 text-sm">
										<pre class="text-xs overflow-hidden">{JSON.stringify(record, null, 2)}</pre>
									</div>
								{/each}
							</div>
							{#if previewCount > previewRecords.length}
								<p class="text-xs text-muted-foreground mt-2 text-center">
									Showing {previewRecords.length} of {previewCount} records
								</p>
							{/if}
						{/if}
					</ScrollArea>
				</Card.Content>
			{/if}
		</Card.Root>
	{/if}

	<!-- Actions -->
	<div class="flex justify-end gap-3">
		{#if onCancel}
			<Button type="button" variant="outline" onclick={onCancel}>Cancel</Button>
		{/if}
		<Button type="submit" disabled={loading || !name.trim() || !moduleId}>
			{#if loading}
				<Loader2 class="mr-2 h-4 w-4 animate-spin" />
			{/if}
			{isEditing ? 'Update Audience' : 'Create Audience'}
		</Button>
	</div>
</form>
