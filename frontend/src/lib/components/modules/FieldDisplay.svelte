<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import { GripVertical, Edit, Trash2 } from 'lucide-svelte';
	import { Button } from '$lib/components/ui/button';

	interface FieldOption {
		id?: number;
		label: string;
		value: string;
		color: string | null;
		order: number;
		is_default: boolean;
	}

	interface Field {
		id?: number;
		type: string;
		api_name: string;
		label: string;
		description: string | null;
		help_text: string | null;
		is_required: boolean;
		is_unique: boolean;
		is_searchable: boolean;
		order: number;
		default_value: string | null;
		validation_rules: Record<string, any>;
		settings: Record<string, any>;
		width: number;
		options: FieldOption[];
	}

	interface Props {
		field: Field;
		onEdit: () => void;
		onDelete: () => void;
		disabled?: boolean;
	}

	let { field, onEdit, onDelete, disabled = false }: Props = $props();

	// Get field type label
	const fieldTypeLabel = $derived(() => {
		const type = field.type;
		return type.charAt(0).toUpperCase() + type.slice(1);
	});

	// Get width label
	const widthLabel = $derived(() => {
		switch (field.width) {
			case 25:
				return '1/4';
			case 33:
				return '1/3';
			case 50:
				return '1/2';
			case 66:
				return '2/3';
			case 75:
				return '3/4';
			case 100:
				return 'Full';
			default:
				return `${field.width}%`;
		}
	});
</script>

<div
	class="group flex items-start gap-3 rounded-lg border bg-card p-4 transition-colors hover:bg-accent/50"
	data-testid="field-item"
>
	<!-- Drag handle -->
	<GripVertical class="mt-1 h-5 w-5 flex-shrink-0 cursor-move text-muted-foreground" />

	<!-- Field info -->
	<div class="min-w-0 flex-1 space-y-2">
		<div class="flex items-start justify-between gap-2">
			<div class="min-w-0 flex-1">
				<div class="flex flex-wrap items-center gap-2">
					<h4 class="truncate text-sm font-medium">{field.label}</h4>
					{#if field.is_required}
						<Badge variant="destructive" class="text-xs">Required</Badge>
					{/if}
					{#if field.is_unique}
						<Badge variant="secondary" class="text-xs">Unique</Badge>
					{/if}
					{#if field.is_searchable}
						<Badge variant="outline" class="text-xs">Searchable</Badge>
					{/if}
				</div>

				<div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
					<code class="rounded bg-muted px-1.5 py-0.5">{field.api_name}</code>
					<span>•</span>
					<span>{fieldTypeLabel}</span>
					<span>•</span>
					<span>{widthLabel} width</span>
				</div>

				{#if field.description}
					<p class="mt-2 text-sm text-muted-foreground">{field.description}</p>
				{/if}

				{#if field.help_text}
					<p class="mt-1 text-xs text-muted-foreground italic">{field.help_text}</p>
				{/if}

				<!-- Show options for select/radio/multiselect -->
				{#if ['select', 'radio', 'multiselect'].includes(field.type) && field.options?.length > 0}
					<div class="mt-2 flex flex-wrap items-center gap-1.5">
						<span class="text-xs text-muted-foreground">Options:</span>
						{#each field.options.slice(0, 5) as option}
							<Badge variant="outline" class="text-xs">
								{#if option.color}
									<span class="mr-1.5 h-2 w-2 rounded-full" style="background-color: {option.color}"
									></span>
								{/if}
								{option.label}
								{#if option.is_default}
									<span class="ml-1 text-muted-foreground">(default)</span>
								{/if}
							</Badge>
						{/each}
						{#if field.options.length > 5}
							<span class="text-xs text-muted-foreground">
								+{field.options.length - 5} more
							</span>
						{/if}
					</div>
				{/if}

				<!-- Show validation rules -->
				{#if Object.keys(field.validation_rules || {}).length > 0}
					<div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
						<span>Validation:</span>
						{#if field.validation_rules.min_length}
							<Badge variant="outline" class="text-xs">
								Min: {field.validation_rules.min_length} chars
							</Badge>
						{/if}
						{#if field.validation_rules.max_length}
							<Badge variant="outline" class="text-xs">
								Max: {field.validation_rules.max_length} chars
							</Badge>
						{/if}
						{#if field.validation_rules.min !== undefined}
							<Badge variant="outline" class="text-xs">Min: {field.validation_rules.min}</Badge>
						{/if}
						{#if field.validation_rules.max !== undefined}
							<Badge variant="outline" class="text-xs">Max: {field.validation_rules.max}</Badge>
						{/if}
						{#if field.validation_rules.min_date}
							<Badge variant="outline" class="text-xs">
								From: {field.validation_rules.min_date}
							</Badge>
						{/if}
						{#if field.validation_rules.max_date}
							<Badge variant="outline" class="text-xs">
								To: {field.validation_rules.max_date}
							</Badge>
						{/if}
					</div>
				{/if}
			</div>

			<!-- Action buttons -->
			<div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
				<Button
					variant="ghost"
					size="icon"
					onclick={onEdit}
					{disabled}
					title="Edit field"
					class="h-8 w-8"
				>
					<Edit class="h-4 w-4" />
				</Button>
				<Button
					variant="ghost"
					size="icon"
					onclick={onDelete}
					{disabled}
					title="Delete field"
					class="h-8 w-8"
				>
					<Trash2 class="h-4 w-4 text-destructive" />
				</Button>
			</div>
		</div>
	</div>
</div>
