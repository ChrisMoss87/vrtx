<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import * as Select from '$lib/components/ui/select';
	import * as Table from '$lib/components/ui/table';
	import { Check, X, AlertCircle, ArrowRight } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';

	interface Props {
		headers: string[];
		previewRows: unknown[][];
		moduleFields: Field[];
		mapping: Record<string, string | null>;
	}

	let {
		headers,
		previewRows,
		moduleFields,
		mapping = $bindable()
	}: Props = $props();

	// Group fields by required status
	const requiredFields = $derived(moduleFields.filter((f) => f.is_required));
	const optionalFields = $derived(moduleFields.filter((f) => !f.is_required));

	// Check which required fields are mapped
	const mappedRequiredFields = $derived(
		requiredFields.filter((f) =>
			Object.values(mapping).includes(f.api_name)
		)
	);

	const unmappedRequiredFields = $derived(
		requiredFields.filter(
			(f) => !Object.values(mapping).includes(f.api_name)
		)
	);

	// Get available fields (not yet mapped)
	function getAvailableFields(currentHeader: string): Field[] {
		const currentMapping = mapping[currentHeader];
		const usedFields = Object.entries(mapping)
			.filter(([header, field]) => header !== currentHeader && field !== null)
			.map(([, field]) => field);

		return moduleFields.filter(
			(f) => !usedFields.includes(f.api_name) || f.api_name === currentMapping
		);
	}

	function getFieldLabel(apiName: string | null): string {
		if (!apiName) return 'Skip this column';
		const field = moduleFields.find((f) => f.api_name === apiName);
		return field?.label || apiName;
	}

	function isFieldRequired(apiName: string | null): boolean {
		if (!apiName) return false;
		const field = moduleFields.find((f) => f.api_name === apiName);
		return field?.is_required || false;
	}

	function handleMappingChange(header: string, value: string | null) {
		mapping = { ...mapping, [header]: value };
	}

	function autoMapAll() {
		const newMapping: Record<string, string | null> = {};

		for (const header of headers) {
			const normalizedHeader = header.toLowerCase().replace(/[^a-z0-9]/g, '');

			// Try to find a matching field
			const match = moduleFields.find((f) => {
				const normalizedLabel = f.label.toLowerCase().replace(/[^a-z0-9]/g, '');
				const normalizedApiName = f.api_name.toLowerCase().replace(/[^a-z0-9]/g, '');
				return normalizedHeader === normalizedLabel || normalizedHeader === normalizedApiName;
			});

			newMapping[header] = match?.api_name || null;
		}

		mapping = newMapping;
	}

	function clearAllMappings() {
		mapping = Object.fromEntries(headers.map((h) => [h, null]));
	}
</script>

<div class="space-y-6">
	<!-- Summary -->
	<div class="flex items-center justify-between">
		<div class="space-y-1">
			<h4 class="font-medium">Map Columns to Fields</h4>
			<p class="text-sm text-muted-foreground">
				Match your file columns to {moduleFields.length} available fields
			</p>
		</div>
		<div class="flex gap-2">
			<button
				type="button"
				class="text-sm text-primary hover:underline"
				onclick={autoMapAll}
			>
				Auto-map all
			</button>
			<span class="text-muted-foreground">|</span>
			<button
				type="button"
				class="text-sm text-muted-foreground hover:underline"
				onclick={clearAllMappings}
			>
				Clear all
			</button>
		</div>
	</div>

	<!-- Required Fields Warning -->
	{#if unmappedRequiredFields.length > 0}
		<div class="flex items-start gap-3 rounded-lg border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950">
			<AlertCircle class="h-5 w-5 text-yellow-600 dark:text-yellow-400 mt-0.5" />
			<div>
				<p class="font-medium text-yellow-800 dark:text-yellow-200">
					Required fields not mapped
				</p>
				<p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
					The following required fields need to be mapped:
					{#each unmappedRequiredFields as field, i}
						<span class="font-medium">{field.label}</span>{#if i < unmappedRequiredFields.length - 1}, {/if}
					{/each}
				</p>
			</div>
		</div>
	{/if}

	<!-- Mapping Table -->
	<div class="border rounded-lg overflow-hidden">
		<Table.Root>
			<Table.Header>
				<Table.Row>
					<Table.Head class="w-1/3">File Column</Table.Head>
					<Table.Head class="w-12 text-center"></Table.Head>
					<Table.Head class="w-1/3">Module Field</Table.Head>
					<Table.Head>Preview</Table.Head>
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each headers as header, index}
					{@const currentMapping = mapping[header]}
					{@const previewValue = previewRows[0]?.[index]}
					{@const availableFields = getAvailableFields(header)}
					<Table.Row>
						<Table.Cell>
							<div class="flex items-center gap-2">
								<span class="font-medium">{header}</span>
							</div>
						</Table.Cell>
						<Table.Cell class="text-center">
							<ArrowRight class="h-4 w-4 text-muted-foreground mx-auto" />
						</Table.Cell>
						<Table.Cell>
							<Select.Root
								type="single"
								value={currentMapping || '__skip__'}
								onValueChange={(v) => handleMappingChange(header, v === '__skip__' ? null : v)}
							>
								<Select.Trigger class="w-full">
									<div class="flex items-center gap-2">
										{#if currentMapping}
											<Check class="h-4 w-4 text-green-600" />
											<span>{getFieldLabel(currentMapping)}</span>
											{#if isFieldRequired(currentMapping)}
												<Badge variant="secondary" class="text-xs">Required</Badge>
											{/if}
										{:else}
											<X class="h-4 w-4 text-muted-foreground" />
											<span class="text-muted-foreground">Skip this column</span>
										{/if}
									</div>
								</Select.Trigger>
								<Select.Content>
									<Select.Item value="__skip__">
										<div class="flex items-center gap-2">
											<X class="h-4 w-4 text-muted-foreground" />
											<span class="text-muted-foreground">Skip this column</span>
										</div>
									</Select.Item>
									<Select.Separator />
									{#if requiredFields.length > 0}
										<Select.Group>
											<Select.GroupHeading>Required Fields</Select.GroupHeading>
											{#each availableFields.filter((f) => f.is_required) as field}
												<Select.Item value={field.api_name}>
													<div class="flex items-center gap-2">
														<span>{field.label}</span>
														<Badge variant="secondary" class="text-xs">
															{field.type}
														</Badge>
													</div>
												</Select.Item>
											{/each}
										</Select.Group>
									{/if}
									{#if availableFields.filter((f) => !f.is_required).length > 0}
										<Select.Group>
											<Select.GroupHeading>Optional Fields</Select.GroupHeading>
											{#each availableFields.filter((f) => !f.is_required) as field}
												<Select.Item value={field.api_name}>
													<div class="flex items-center gap-2">
														<span>{field.label}</span>
														<Badge variant="outline" class="text-xs">
															{field.type}
														</Badge>
													</div>
												</Select.Item>
											{/each}
										</Select.Group>
									{/if}
								</Select.Content>
							</Select.Root>
						</Table.Cell>
						<Table.Cell class="text-muted-foreground text-sm truncate max-w-32">
							{previewValue ?? '-'}
						</Table.Cell>
					</Table.Row>
				{/each}
			</Table.Body>
		</Table.Root>
	</div>

	<!-- Mapping Summary -->
	<div class="flex items-center justify-between text-sm">
		<span class="text-muted-foreground">
			{Object.values(mapping).filter((v) => v !== null).length} of {headers.length} columns mapped
		</span>
		<div class="flex items-center gap-4">
			<span class="text-green-600">
				{mappedRequiredFields.length}/{requiredFields.length} required
			</span>
		</div>
	</div>
</div>
