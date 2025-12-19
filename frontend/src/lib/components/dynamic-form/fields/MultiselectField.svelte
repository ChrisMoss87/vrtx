<script lang="ts">
	import { Checkbox } from '$lib/components/ui/checkbox';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import * as Popover from '$lib/components/ui/popover';
	import { ChevronDown, X } from 'lucide-svelte';
	import type { FieldSettings, FieldOption } from '$lib/api/modules';

	interface Props {
		value: string[];
		error?: string;
		disabled?: boolean;
		placeholder?: string;
		required?: boolean;
		settings?: FieldSettings;
		options?: FieldOption[];
		onchange: (value: string[]) => void;
	}

	let {
		value = $bindable([]),
		error,
		disabled = false,
		placeholder = 'Select options...',
		required,
		settings,
		options = [],
		onchange
	}: Props = $props();

	let open = $state(false);

	function toggleOption(optionValue: string) {
		if (value.includes(optionValue)) {
			value = value.filter((v) => v !== optionValue);
		} else {
			value = [...value, optionValue];
		}
		onchange(value);
	}

	function removeOption(optionValue: string) {
		value = value.filter((v) => v !== optionValue);
		onchange(value);
	}

	function getSelectedLabels() {
		return value.map((v) => options.find((o) => o.value === v)?.label || v);
	}
</script>

<Popover.Root bind:open>
	<Popover.Trigger>
		{#snippet child({ props })}
			<Button
				{...props}
				variant="outline"
				role="combobox"
				aria-expanded={open}
				{disabled}
				class={`w-full justify-between ${error ? 'border-destructive' : ''}`}
			>
				<div class="flex flex-wrap gap-1 overflow-hidden">
					{#if value.length === 0}
						<span class="text-muted-foreground">{placeholder}</span>
					{:else}
						{#each getSelectedLabels() as label, i}
							<Badge variant="secondary" class="text-xs">
								{label}
								<button
									type="button"
									onclick={(e) => {
										e.stopPropagation();
										removeOption(value[i]);
									}}
									class="ml-1 hover:text-destructive"
								>
									<X class="h-3 w-3" />
								</button>
							</Badge>
						{/each}
					{/if}
				</div>
				<ChevronDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
			</Button>
		{/snippet}
	</Popover.Trigger>
	<Popover.Content class="w-[var(--bits-popover-trigger-width)] p-0">
		<div class="max-h-64 overflow-y-auto p-4">
			<div class="space-y-3">
				{#each options as option}
					<div class="flex items-center space-x-2">
						<Checkbox
							id={`multiselect-${option.value}`}
							checked={value.includes(option.value)}
							onCheckedChange={() => toggleOption(option.value)}
							{disabled}
						/>
						<Label
							for={`multiselect-${option.value}`}
							class="text-sm leading-none font-normal peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
						>
							{option.label}
						</Label>
					</div>
				{/each}
				{#if options.length === 0}
					<div class="text-sm text-muted-foreground">No options available</div>
				{/if}
			</div>
		</div>
	</Popover.Content>
</Popover.Root>
