<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import { Info, Copy, RefreshCw, Plus, SkipForward } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ImportOptions } from '$lib/api/imports';

	interface Props {
		moduleFields: Field[];
		options: ImportOptions;
	}

	let {
		moduleFields,
		options = $bindable()
	}: Props = $props();

	// Get fields that could be used for duplicate detection
	const duplicateCheckFields = $derived(
		moduleFields.filter((f) =>
			f.is_unique || ['email', 'text', 'number'].includes(f.type)
		)
	);

	function handleDuplicateHandlingChange(value: string) {
		options = {
			...options,
			duplicate_handling: value as 'skip' | 'update' | 'create'
		};
	}

	function handleDuplicateFieldChange(value: string | undefined) {
		options = {
			...options,
			duplicate_check_field: value || undefined
		};
	}

	function handleSkipEmptyChange(checked: boolean) {
		options = {
			...options,
			skip_empty_rows: checked
		};
	}
</script>

<div class="space-y-6">
	<div class="space-y-1">
		<h4 class="font-medium">Import Options</h4>
		<p class="text-sm text-muted-foreground">
			Configure how records should be imported
		</p>
	</div>

	<!-- Duplicate Handling -->
	<Card.Root>
		<Card.Header class="pb-3">
			<Card.Title class="text-base">Duplicate Handling</Card.Title>
			<Card.Description>
				Choose how to handle records that already exist in the system
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<!-- Duplicate Check Field -->
			<div class="space-y-2">
				<Label>Check duplicates by</Label>
				<Select.Root
					type="single"
					value={options.duplicate_check_field}
					onValueChange={handleDuplicateFieldChange}
				>
					<Select.Trigger class="w-full">
						{#if options.duplicate_check_field}
							{duplicateCheckFields.find((f) => f.api_name === options.duplicate_check_field)?.label || 'Select field'}
						{:else}
							<span class="text-muted-foreground">No duplicate checking</span>
						{/if}
					</Select.Trigger>
					<Select.Content>
						<Select.Item value="">
							<span class="text-muted-foreground">No duplicate checking</span>
						</Select.Item>
						<Select.Separator />
						{#each duplicateCheckFields as field}
							<Select.Item value={field.api_name}>
								{field.label}
								{#if field.is_unique}
									<span class="text-xs text-muted-foreground ml-2">(unique)</span>
								{/if}
							</Select.Item>
						{/each}
					</Select.Content>
				</Select.Root>
				<p class="text-xs text-muted-foreground">
					Select a field to identify existing records. Unique fields work best.
				</p>
			</div>

			{#if options.duplicate_check_field}
				<!-- Duplicate Action -->
				<div class="space-y-3">
					<Label>When a duplicate is found</Label>
					<RadioGroup.Root
						value={options.duplicate_handling || 'skip'}
						onValueChange={handleDuplicateHandlingChange}
						class="grid gap-3"
					>
						<Label
							class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer hover:bg-muted/50 transition-colors {options.duplicate_handling === 'skip' ? 'border-primary bg-primary/5' : ''}"
						>
							<RadioGroup.Item value="skip" class="mt-1" />
							<div class="flex-1">
								<div class="flex items-center gap-2">
									<SkipForward class="h-4 w-4 text-yellow-600" />
									<span class="font-medium">Skip duplicate</span>
								</div>
								<p class="text-sm text-muted-foreground mt-1">
									Don't import the row if a matching record exists
								</p>
							</div>
						</Label>

						<Label
							class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer hover:bg-muted/50 transition-colors {options.duplicate_handling === 'update' ? 'border-primary bg-primary/5' : ''}"
						>
							<RadioGroup.Item value="update" class="mt-1" />
							<div class="flex-1">
								<div class="flex items-center gap-2">
									<RefreshCw class="h-4 w-4 text-blue-600" />
									<span class="font-medium">Update existing</span>
								</div>
								<p class="text-sm text-muted-foreground mt-1">
									Update the existing record with new values from the import
								</p>
							</div>
						</Label>

						<Label
							class="flex items-start gap-3 rounded-lg border p-4 cursor-pointer hover:bg-muted/50 transition-colors {options.duplicate_handling === 'create' ? 'border-primary bg-primary/5' : ''}"
						>
							<RadioGroup.Item value="create" class="mt-1" />
							<div class="flex-1">
								<div class="flex items-center gap-2">
									<Plus class="h-4 w-4 text-green-600" />
									<span class="font-medium">Create anyway</span>
								</div>
								<p class="text-sm text-muted-foreground mt-1">
									Create a new record even if a duplicate exists
								</p>
							</div>
						</Label>
					</RadioGroup.Root>
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<!-- Data Processing Options -->
	<Card.Root>
		<Card.Header class="pb-3">
			<Card.Title class="text-base">Data Processing</Card.Title>
			<Card.Description>
				Additional options for processing import data
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<div class="flex items-center justify-between">
				<div class="space-y-0.5">
					<Label>Skip empty rows</Label>
					<p class="text-sm text-muted-foreground">
						Automatically skip rows that have no data
					</p>
				</div>
				<Switch
					checked={options.skip_empty_rows ?? true}
					onCheckedChange={handleSkipEmptyChange}
				/>
			</div>
		</Card.Content>
	</Card.Root>

	<!-- Info -->
	<div class="flex items-start gap-3 rounded-lg bg-muted/50 p-4">
		<Info class="h-5 w-5 text-muted-foreground mt-0.5" />
		<div class="text-sm text-muted-foreground">
			<p>
				After configuring options, click <strong>Validate</strong> to check your data for errors
				before importing. You can review and fix any issues before the final import.
			</p>
		</div>
	</div>
</div>
