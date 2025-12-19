<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as Card from '$lib/components/ui/card';
	import * as Select from '$lib/components/ui/select';
	import { Switch } from '$lib/components/ui/switch';
	import { Slider } from '$lib/components/ui/slider';
	import { Badge } from '$lib/components/ui/badge';
	import { Plus, Trash2, FlaskConical, Target, BarChart3, Calendar } from 'lucide-svelte';
	import type { AbTest, AbTestType, AbTestEntityType, AbTestGoal } from '$lib/api/ab-tests';

	interface VariantData {
		id?: number;
		name: string;
		content: Record<string, unknown>;
		traffic_percentage: number;
		is_control: boolean;
	}

	interface Props {
		test?: AbTest;
		onSave: (data: Partial<Omit<AbTest, 'variants'>> & { variants?: VariantData[] }) => void;
		onCancel: () => void;
		entityOptions?: { type: AbTestEntityType; id: number; name: string }[];
	}

	let { test, onSave, onCancel, entityOptions = [] }: Props = $props();

	let name = $state(test?.name || '');
	let description = $state(test?.description || '');
	let type = $state<AbTestType>(test?.type || 'email_subject');
	let entityType = $state<AbTestEntityType>(test?.entity_type || 'email_template');
	let entityId = $state(test?.entity_id || 0);
	let goal = $state<AbTestGoal>(test?.goal || 'conversion');
	let minSampleSize = $state(test?.min_sample_size || 100);
	let confidenceLevel = $state(test?.confidence_level || 95);
	let autoSelectWinner = $state(test?.auto_select_winner ?? true);
	let scheduledEndAt = $state(test?.scheduled_end_at?.split('T')[0] || '');

	let variants = $state<VariantData[]>(
		test?.variants?.map((v) => ({
			id: v.id,
			name: v.name,
			content: v.content,
			traffic_percentage: v.traffic_percentage,
			is_control: v.is_control
		})) || [
			{ name: 'Control (A)', content: {}, traffic_percentage: 50, is_control: true },
			{ name: 'Variant B', content: {}, traffic_percentage: 50, is_control: false }
		]
	);

	const testTypes: { value: AbTestType; label: string }[] = [
		{ value: 'email_subject', label: 'Email Subject' },
		{ value: 'email_content', label: 'Email Content' },
		{ value: 'cta_button', label: 'CTA Button' },
		{ value: 'send_time', label: 'Send Time' },
		{ value: 'form_layout', label: 'Form Layout' }
	];

	const entityTypes: { value: AbTestEntityType; label: string }[] = [
		{ value: 'email_template', label: 'Email Template' },
		{ value: 'campaign', label: 'Campaign' },
		{ value: 'web_form', label: 'Web Form' }
	];

	const goals: { value: AbTestGoal; label: string }[] = [
		{ value: 'conversion', label: 'Conversion Rate' },
		{ value: 'click_rate', label: 'Click Rate' },
		{ value: 'open_rate', label: 'Open Rate' }
	];

	const filteredEntities = $derived(entityOptions.filter((e) => e.type === entityType));

	function addVariant() {
		const letter = String.fromCharCode(65 + variants.length);
		const newPercentage = Math.floor(100 / (variants.length + 1));

		// Redistribute traffic
		variants = variants.map((v) => ({ ...v, traffic_percentage: newPercentage }));
		variants = [...variants, {
			name: `Variant ${letter}`,
			content: {},
			traffic_percentage: newPercentage,
			is_control: false
		}];

		// Adjust to ensure total is 100
		redistributeTraffic();
	}

	function removeVariant(index: number) {
		if (variants.length <= 2) return;
		if (variants[index].is_control) return;

		variants = variants.filter((_, i) => i !== index);
		redistributeTraffic();
	}

	function redistributeTraffic() {
		const count = variants.length;
		const base = Math.floor(100 / count);
		const remainder = 100 - base * count;

		variants = variants.map((v, i) => ({
			...v,
			traffic_percentage: base + (i < remainder ? 1 : 0)
		}));
	}

	function updateTraffic(index: number, value: number) {
		const diff = value - variants[index].traffic_percentage;
		const others = variants.filter((_, i) => i !== index);
		const totalOthers = others.reduce((sum, v) => sum + v.traffic_percentage, 0);

		if (totalOthers - diff < others.length) return;

		variants = variants.map((v, i) => {
			if (i === index) {
				return { ...v, traffic_percentage: value };
			}
			const share = v.traffic_percentage / totalOthers;
			return { ...v, traffic_percentage: Math.max(1, Math.round(v.traffic_percentage - diff * share)) };
		});

		// Ensure total is 100
		const total = variants.reduce((sum, v) => sum + v.traffic_percentage, 0);
		if (total !== 100) {
			const nonControlIndex = variants.findIndex((v) => !v.is_control);
			if (nonControlIndex >= 0) {
				variants[nonControlIndex].traffic_percentage += 100 - total;
			}
		}
	}

	function handleSave() {
		onSave({
			name,
			description: description || undefined,
			type,
			entity_type: entityType,
			entity_id: entityId,
			goal,
			min_sample_size: minSampleSize,
			confidence_level: confidenceLevel,
			auto_select_winner: autoSelectWinner,
			scheduled_end_at: scheduledEndAt || undefined,
			variants: variants.map((v) => ({
				id: v.id,
				name: v.name,
				content: v.content,
				traffic_percentage: v.traffic_percentage,
				is_control: v.is_control
			}))
		});
	}

	const isValid = $derived(
		name.trim() !== '' &&
		entityId > 0 &&
		variants.length >= 2 &&
		variants.reduce((sum, v) => sum + v.traffic_percentage, 0) === 100
	);
</script>

<div class="space-y-6">
	<!-- Basic Info -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<FlaskConical class="h-5 w-5" />
				Test Configuration
			</Card.Title>
			<Card.Description>Set up the basic parameters for your A/B test</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label for="name">Test Name</Label>
					<Input id="name" bind:value={name} placeholder="e.g., Homepage CTA Test" />
				</div>

				<div class="space-y-2">
					<Label for="type">Test Type</Label>
					<Select.Root type="single" value={type} onValueChange={(v) => (type = v as AbTestType)}>
						<Select.Trigger>
							{testTypes.find((t) => t.value === type)?.label || 'Select type'}
						</Select.Trigger>
						<Select.Content>
							{#each testTypes as t}
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
					placeholder="Describe what you're testing and your hypothesis..."
					rows={3}
				/>
			</div>

			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label for="entityType">Entity Type</Label>
					<Select.Root
						type="single"
						value={entityType}
						onValueChange={(v) => {
							entityType = v as AbTestEntityType;
							entityId = 0;
						}}
					>
						<Select.Trigger>
							{entityTypes.find((t) => t.value === entityType)?.label || 'Select entity type'}
						</Select.Trigger>
						<Select.Content>
							{#each entityTypes as t}
								<Select.Item value={t.value}>{t.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="entity">Entity</Label>
					<Select.Root
						type="single"
						value={entityId.toString()}
						onValueChange={(v) => (entityId = parseInt(v || '0'))}
					>
						<Select.Trigger>
							{filteredEntities.find((e) => e.id === entityId)?.name || 'Select entity'}
						</Select.Trigger>
						<Select.Content>
							{#each filteredEntities as entity}
								<Select.Item value={entity.id.toString()}>{entity.name}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Goal & Statistical Settings -->
	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				<Target class="h-5 w-5" />
				Goal & Statistics
			</Card.Title>
			<Card.Description>Define your success metric and statistical requirements</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label for="goal">Primary Goal</Label>
					<Select.Root type="single" value={goal} onValueChange={(v) => (goal = v as AbTestGoal)}>
						<Select.Trigger>
							{goals.find((g) => g.value === goal)?.label || 'Select goal'}
						</Select.Trigger>
						<Select.Content>
							{#each goals as g}
								<Select.Item value={g.value}>{g.label}</Select.Item>
							{/each}
						</Select.Content>
					</Select.Root>
				</div>

				<div class="space-y-2">
					<Label for="scheduledEnd">Scheduled End Date</Label>
					<Input id="scheduledEnd" type="date" bind:value={scheduledEndAt} />
				</div>
			</div>

			<div class="grid gap-4 md:grid-cols-2">
				<div class="space-y-2">
					<Label>Minimum Sample Size per Variant: {minSampleSize}</Label>
					<Slider
						type="single"
						bind:value={minSampleSize}
						min={10}
						max={10000}
						step={10}
					/>
					<p class="text-xs text-muted-foreground">
						Minimum visitors needed before declaring significance
					</p>
				</div>

				<div class="space-y-2">
					<Label>Confidence Level: {confidenceLevel}%</Label>
					<Slider
						type="single"
						bind:value={confidenceLevel}
						min={80}
						max={99}
						step={1}
					/>
					<p class="text-xs text-muted-foreground">
						Statistical confidence required for results
					</p>
				</div>
			</div>

			<div class="flex items-center space-x-2">
				<Switch id="autoWinner" bind:checked={autoSelectWinner} />
				<Label for="autoWinner">Automatically select winner when statistically significant</Label>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Variants -->
	<Card.Root>
		<Card.Header>
			<div class="flex items-center justify-between">
				<div>
					<Card.Title class="flex items-center gap-2">
						<BarChart3 class="h-5 w-5" />
						Test Variants
					</Card.Title>
					<Card.Description>Configure the variants to test</Card.Description>
				</div>
				<Button variant="outline" size="sm" onclick={addVariant} disabled={variants.length >= 4}>
					<Plus class="mr-2 h-4 w-4" />
					Add Variant
				</Button>
			</div>
		</Card.Header>
		<Card.Content class="space-y-4">
			{#each variants as variant, index}
				<div class="rounded-lg border p-4 space-y-3">
					<div class="flex items-center justify-between">
						<div class="flex items-center gap-2">
							<Input
								value={variant.name}
								oninput={(e) => {
									variants[index].name = e.currentTarget.value;
								}}
								class="w-48"
							/>
							{#if variant.is_control}
								<Badge variant="secondary">Control</Badge>
							{/if}
						</div>
						{#if !variant.is_control && variants.length > 2}
							<Button
								variant="ghost"
								size="icon"
								onclick={() => removeVariant(index)}
							>
								<Trash2 class="h-4 w-4 text-destructive" />
							</Button>
						{/if}
					</div>

					<div class="space-y-2">
						<div class="flex items-center justify-between">
							<Label>Traffic: {variant.traffic_percentage}%</Label>
						</div>
						<Slider
							type="single"
							value={variant.traffic_percentage}
							onValueChange={(v: number) => updateTraffic(index, v)}
							min={1}
							max={99}
							step={1}
						/>
					</div>

					<div class="text-xs text-muted-foreground">
						Variant Code: {String.fromCharCode(65 + index)}
					</div>
				</div>
			{/each}

			<div class="flex items-center justify-between pt-2 border-t">
				<span class="text-sm text-muted-foreground">Total Traffic Distribution</span>
				<span class="font-medium">
					{variants.reduce((sum, v) => sum + v.traffic_percentage, 0)}%
					{#if variants.reduce((sum, v) => sum + v.traffic_percentage, 0) !== 100}
						<span class="text-destructive">(must be 100%)</span>
					{/if}
				</span>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Actions -->
	<div class="flex justify-end gap-3">
		<Button variant="outline" onclick={onCancel}>Cancel</Button>
		<Button onclick={handleSave} disabled={!isValid}>
			{test ? 'Update Test' : 'Create Test'}
		</Button>
	</div>
</div>
