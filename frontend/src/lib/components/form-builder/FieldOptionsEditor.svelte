<script lang="ts">
	import { Plus, Trash2, GripVertical, Palette } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import type { CreateFieldOptionRequest } from '$lib/api/modules';

	interface Props {
		options: CreateFieldOptionRequest[];
		onOptionsChange: (options: CreateFieldOptionRequest[]) => void;
	}

	let { options = $bindable([]), onOptionsChange }: Props = $props();

	const predefinedColors = [
		{ name: 'Gray', value: '#9CA3AF' },
		{ name: 'Blue', value: '#3B82F6' },
		{ name: 'Green', value: '#10B981' },
		{ name: 'Yellow', value: '#F59E0B' },
		{ name: 'Red', value: '#EF4444' },
		{ name: 'Purple', value: '#8B5CF6' },
		{ name: 'Pink', value: '#EC4899' },
		{ name: 'Indigo', value: '#6366F1' }
	];

	function addOption() {
		const newOption: CreateFieldOptionRequest = {
			label: `Option ${options.length + 1}`,
			value: `option_${options.length + 1}`,
			display_order: options.length
		};
		onOptionsChange([...options, newOption]);
	}

	function removeOption(index: number) {
		onOptionsChange(options.filter((_, i) => i !== index));
	}

	function updateOption(index: number, updates: Partial<CreateFieldOptionRequest>) {
		const updatedOptions = [...options];
		updatedOptions[index] = { ...updatedOptions[index], ...updates };
		onOptionsChange(updatedOptions);
	}

	function generateValue(label: string): string {
		return label
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '');
	}
</script>

<Card.Root class="shadow-sm">
	<Card.Header class="pb-3">
		<div class="flex items-center justify-between gap-4">
			<div>
				<Card.Title class="flex items-center gap-2 text-base">
					<div class="h-4 w-1 rounded-full bg-pink-500"></div>
					Field Options
				</Card.Title>
				<Card.Description class="mt-1 text-xs">Choices users can select from</Card.Description>
			</div>
			<Button
				variant="outline"
				size="sm"
				onclick={addOption}
				data-testid="add-option"
				class="shrink-0"
			>
				<Plus class="mr-1.5 h-4 w-4" />
				Add
			</Button>
		</div>
	</Card.Header>
	<Card.Content>
		{#if options.length === 0}
			<div class="px-4 py-8 text-center">
				<div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
					<Plus class="h-8 w-8 text-muted-foreground" />
				</div>
				<p class="mb-1 text-sm font-medium">No options yet</p>
				<p class="mb-4 text-xs text-muted-foreground">Add options for users to choose from</p>
				<Button variant="outline" size="sm" onclick={addOption}>
					<Plus class="mr-1.5 h-4 w-4" />
					Add First Option
				</Button>
			</div>
		{:else}
			<div class="space-y-3">
				{#each options as option, index (index)}
					<div
						class="group flex items-start gap-2 rounded-lg border-2 p-3.5 transition-all hover:border-primary/30 hover:bg-accent/30"
						data-testid="option-{index}"
					>
						<!-- Drag Handle -->
						<button
							class="mt-5 shrink-0 cursor-grab rounded p-1.5 transition-colors hover:bg-accent"
							title="Drag to reorder"
						>
							<GripVertical
								class="h-4 w-4 text-muted-foreground transition-colors group-hover:text-foreground"
							/>
						</button>

						<!-- Option Fields -->
						<div class="min-w-0 flex-1 space-y-3">
							<div class="grid grid-cols-2 gap-3">
								<div class="space-y-1.5">
									<Label class="text-xs font-medium">Label</Label>
									<Input
										value={option.label}
										oninput={(e) => {
											const label = e.currentTarget.value;
											updateOption(index, {
												label,
												value: generateValue(label)
											});
										}}
										placeholder="Option label"
										data-testid="option-label-{index}"
										class="transition-all focus:ring-2 focus:ring-primary/20"
									/>
								</div>
								<div class="space-y-1.5">
									<Label class="text-xs font-medium">Value</Label>
									<Input
										value={option.value}
										oninput={(e) => updateOption(index, { value: e.currentTarget.value })}
										placeholder="option_value"
										data-testid="option-value-{index}"
										class="font-mono text-xs transition-all focus:ring-2 focus:ring-primary/20"
									/>
								</div>
							</div>

							<!-- Color Picker -->
							<div class="space-y-2">
								<Label class="text-xs font-medium">Color Badge (Optional)</Label>
								<div class="flex flex-wrap gap-2">
									{#each predefinedColors as color}
										<button
											class="h-9 w-9 rounded-md border-2 {option.color === color.value
												? 'scale-110 border-primary ring-2 ring-primary/20'
												: 'border-border hover:border-border/60'} shadow-sm transition-all hover:scale-105 active:scale-95"
											style="background-color: {color.value}"
											onclick={() => updateOption(index, { color: color.value })}
											title={color.name}
											data-testid="option-color-{index}-{color.name.toLowerCase()}"
										/>
									{/each}
									<button
										class="h-9 w-9 rounded-md border-2 {!option.color
											? 'border-primary ring-2 ring-primary/20'
											: 'border-border hover:border-border/60'} flex items-center justify-center shadow-sm transition-all hover:scale-105 hover:bg-accent active:scale-95"
										onclick={() => updateOption(index, { color: undefined })}
										title="No color"
									>
										<Palette class="h-4 w-4 text-muted-foreground" />
									</button>
								</div>
								{#if option.color}
									<p class="text-xs text-muted-foreground">
										Selected: {predefinedColors.find((c) => c.value === option.color)?.name ||
											'Custom'}
									</p>
								{/if}
							</div>
						</div>

						<!-- Remove Button -->
						<Button
							variant="ghost"
							size="icon"
							onclick={() => removeOption(index)}
							class="mt-5 shrink-0 transition-colors hover:bg-destructive/10 hover:text-destructive"
							data-testid="remove-option-{index}"
						>
							<Trash2 class="h-4 w-4" />
						</Button>
					</div>
				{/each}
			</div>
		{/if}

		{#if options.length > 0}
			<div class="mt-4 border-t pt-4">
				<div class="flex items-start gap-2 text-xs text-muted-foreground">
					<span class="text-base">💡</span>
					<div>
						<p class="mb-0.5 font-medium text-foreground">Auto-generated values</p>
						<p>Values are automatically created from labels for database storage</p>
					</div>
				</div>
			</div>
		{/if}
	</Card.Content>
</Card.Root>
