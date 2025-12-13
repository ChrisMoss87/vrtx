<script lang="ts">
	import type { ForecastCategory, ForecastDeal } from '$lib/api/forecasts';
	import { updateDealForecast, formatCurrency, getCategoryLabel } from '$lib/api/forecasts';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Loader2, DollarSign, Calendar, MessageSquare } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';

	interface Props {
		deal: ForecastDeal;
		open?: boolean;
		onClose?: () => void;
		onUpdate?: (deal: ForecastDeal) => void;
	}

	let { deal, open = $bindable(false), onClose, onUpdate }: Props = $props();

	let saving = $state(false);
	let category = $state<ForecastCategory | null>(deal.forecast_category);
	let override = $state<string>(deal.forecast_override?.toString() ?? '');
	let closeDate = $state<string>(deal.expected_close_date ?? '');
	let reason = $state<string>('');

	const categories: { value: ForecastCategory; label: string; description: string }[] = [
		{ value: 'commit', label: 'Commit', description: 'Will close this period' },
		{ value: 'best_case', label: 'Best Case', description: 'Likely to close' },
		{ value: 'pipeline', label: 'Pipeline', description: 'In progress' },
		{ value: 'omitted', label: 'Omitted', description: 'Exclude from forecast' }
	];

	async function save() {
		saving = true;
		try {
			const result = await updateDealForecast(deal.id, {
				forecast_category: category ?? undefined,
				forecast_override: override ? parseFloat(override) : undefined,
				expected_close_date: closeDate || undefined,
				reason: reason || undefined
			});

			// Update local deal with new values
			const updatedDeal: ForecastDeal = {
				...deal,
				forecast_category: result.forecast_category,
				forecast_override: result.forecast_override,
				expected_close_date: result.expected_close_date
			};

			onUpdate?.(updatedDeal);
			toast.success('Forecast updated');
			open = false;
			onClose?.();
		} catch (e) {
			toast.error('Failed to update forecast');
		} finally {
			saving = false;
		}
	}

	function handleOpenChange(isOpen: boolean) {
		open = isOpen;
		if (!isOpen) {
			onClose?.();
		}
	}
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-md">
		<Dialog.Header>
			<Dialog.Title>Edit Forecast</Dialog.Title>
			<Dialog.Description>
				Update forecast settings for {deal.name}
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-6 py-4">
			<!-- Current Deal Info -->
			<div class="rounded-lg bg-muted p-3">
				<p class="font-medium">{deal.name}</p>
				<p class="text-sm text-muted-foreground">
					{formatCurrency(deal.amount)}
					{#if deal.stage_field_value}
						&middot; {deal.stage_field_value} ({deal.probability}%)
					{/if}
				</p>
			</div>

			<!-- Forecast Category -->
			<div class="space-y-3">
				<Label>Forecast Category</Label>
				<RadioGroup.Root value={category ?? undefined} onValueChange={(v) => category = v as ForecastCategory} class="grid grid-cols-2 gap-2">
					{#each categories as cat}
						<Label
							class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-accent [&:has([data-state=checked])]:border-primary"
						>
							<RadioGroup.Item value={cat.value} id={cat.value} class="mt-0.5" />
							<div>
								<span class="text-sm font-medium">{cat.label}</span>
								<p class="text-xs text-muted-foreground">{cat.description}</p>
							</div>
						</Label>
					{/each}
				</RadioGroup.Root>
			</div>

			<!-- Amount Override -->
			<div class="space-y-2">
				<Label for="override" class="flex items-center gap-2">
					<DollarSign class="h-4 w-4" />
					Override Amount
				</Label>
				<Input
					id="override"
					type="number"
					min="0"
					step="0.01"
					placeholder={deal.amount.toString()}
					bind:value={override}
				/>
				<p class="text-xs text-muted-foreground">
					Leave empty to use the deal amount ({formatCurrency(deal.amount)})
				</p>
			</div>

			<!-- Expected Close Date -->
			<div class="space-y-2">
				<Label for="close-date" class="flex items-center gap-2">
					<Calendar class="h-4 w-4" />
					Expected Close Date
				</Label>
				<Input id="close-date" type="date" bind:value={closeDate} />
			</div>

			<!-- Reason -->
			<div class="space-y-2">
				<Label for="reason" class="flex items-center gap-2">
					<MessageSquare class="h-4 w-4" />
					Reason (optional)
				</Label>
				<Textarea
					id="reason"
					placeholder="Why are you making this change?"
					rows={2}
					bind:value={reason}
				/>
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => handleOpenChange(false)}>Cancel</Button>
			<Button onclick={save} disabled={saving}>
				{#if saving}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
				{/if}
				Save Changes
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
