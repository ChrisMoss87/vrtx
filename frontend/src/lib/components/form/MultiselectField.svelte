<script lang="ts">
	import FieldBase from './FieldBase.svelte';
	import { Button } from '$lib/components/ui/button';
	import * as Command from '$lib/components/ui/command';
	import * as Popover from '$lib/components/ui/popover';
	import { Badge } from '$lib/components/ui/badge';
	import { Check, ChevronsUpDown, X } from 'lucide-svelte';
	import { cn } from '$lib/utils';

	interface Option {
		label: string;
		value: string;
	}

	interface Props {
		label?: string;
		name: string;
		value?: string[];
		options: Option[];
		description?: string;
		error?: string;
		required?: boolean;
		disabled?: boolean;
		placeholder?: string;
		width?: 25 | 50 | 75 | 100;
		class?: string;
		maxSelected?: number;
		onchange?: (value: string[]) => void;
	}

	let {
		label,
		name,
		value = $bindable([]),
		options,
		description,
		error,
		required = false,
		disabled = false,
		placeholder = 'Select options...',
		width = 100,
		class: className,
		maxSelected,
		onchange
	}: Props = $props();

	let open = $state(false);
	let searchValue = $state('');

	// Filter options based on search
	const filteredOptions = $derived(() => {
		if (!searchValue) return options;
		return options.filter((opt) => opt.label.toLowerCase().includes(searchValue.toLowerCase()));
	});

	// Get selected options for display
	const selectedOptions = $derived(() => {
		return options.filter((opt) => value.includes(opt.value));
	});

	function toggleOption(optionValue: string) {
		if (disabled) return;

		const index = value.indexOf(optionValue);

		if (index > -1) {
			// Remove from selection
			value = value.filter((v) => v !== optionValue);
		} else {
			// Add to selection (if not at max)
			if (!maxSelected || value.length < maxSelected) {
				value = [...value, optionValue];
			}
		}

		onchange?.(value);
	}

	function removeOption(optionValue: string) {
		value = value.filter((v) => v !== optionValue);
		onchange?.(value);
	}

	function clearAll() {
		value = [];
		onchange?.(value);
	}

	function isSelected(optionValue: string): boolean {
		return value.includes(optionValue);
	}
</script>

<FieldBase {label} {name} {description} {error} {required} {disabled} class={className}>
	{#snippet children(props)}
		<div class="w-full space-y-2">
			<!-- Selected items badges -->
			{#if selectedOptions().length > 0}
				<div class="flex flex-wrap gap-1">
					{#each selectedOptions() as option (option.value)}
						<Badge variant="secondary" class="gap-1">
							{option.label}
							{#if !disabled}
								<button
									type="button"
									onclick={(e) => {
										e.stopPropagation();
										removeOption(option.value);
									}}
									class="ml-1 rounded-sm hover:bg-destructive/20"
								>
									<X class="h-3 w-3" />
								</button>
							{/if}
						</Badge>
					{/each}
				</div>
			{/if}

			<!-- Combobox trigger -->
			<Popover.Root bind:open>
				<Popover.Trigger>
					<Button
						{...props}
						variant="outline"
						role="combobox"
						aria-expanded={open}
						class={cn(
							'w-full justify-between',
							selectedOptions().length === 0 && 'text-muted-foreground',
							error && 'border-destructive'
						)}
						{disabled}
					>
						<span class="truncate">
							{#if selectedOptions().length > 0}
								{selectedOptions().length} selected
							{:else}
								{placeholder}
							{/if}
						</span>
						<div class="flex items-center gap-1">
							{#if value.length > 0 && !disabled}
								<button
									type="button"
									onclick={(e) => {
										e.stopPropagation();
										clearAll();
									}}
									class="hover:text-foreground"
								>
									<X class="h-4 w-4" />
								</button>
							{/if}
							<ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
						</div>
					</Button>
				</Popover.Trigger>
				<Popover.Content class="w-[400px] p-0" align="start">
					<Command.Root shouldFilter={false}>
						<Command.Input placeholder="Search options..." bind:value={searchValue} />
						<Command.List>
							{#if filteredOptions().length === 0}
								<Command.Empty>No options found.</Command.Empty>
							{:else}
								<Command.Group>
									{#each filteredOptions() as option (option.value)}
										<Command.Item
											value={option.value}
											onSelect={() => toggleOption(option.value)}
											disabled={maxSelected &&
												value.length >= maxSelected &&
												!isSelected(option.value)}
										>
											<Check
												class={cn(
													'mr-2 h-4 w-4',
													isSelected(option.value) ? 'opacity-100' : 'opacity-0'
												)}
											/>
											{option.label}
										</Command.Item>
									{/each}
								</Command.Group>
							{/if}
						</Command.List>
					</Command.Root>
				</Popover.Content>
			</Popover.Root>
		</div>
	{/snippet}
</FieldBase>
