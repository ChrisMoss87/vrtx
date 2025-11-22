<script lang="ts">
	import { Badge } from '$lib/components/ui/badge';
	import type { Field } from '$lib/types/modules';
	import { Check, X, Mail, Phone, ExternalLink } from 'lucide-svelte';

	interface Props {
		value: any;
		field: Field;
	}

	let { value, field }: Props = $props();
</script>

{#if value === null || value === undefined || value === ''}
	<span class="text-muted-foreground italic">Not set</span>
{:else if field.type === 'email'}
	<a
		href={`mailto:${value}`}
		class="inline-flex items-center gap-1 text-primary hover:underline"
	>
		<Mail class="h-3 w-3" />
		{value}
	</a>
{:else if field.type === 'phone'}
	<a
		href={`tel:${value}`}
		class="inline-flex items-center gap-1 text-primary hover:underline"
	>
		<Phone class="h-3 w-3" />
		{value}
	</a>
{:else if field.type === 'url'}
	<a
		href={value}
		target="_blank"
		rel="noopener noreferrer"
		class="inline-flex items-center gap-1 text-primary hover:underline"
	>
		{value}
		<ExternalLink class="h-3 w-3" />
	</a>
{:else if field.type === 'date'}
	{new Date(value).toLocaleDateString()}
{:else if field.type === 'datetime'}
	{new Date(value).toLocaleString()}
{:else if field.type === 'time'}
	{new Date(`2000-01-01T${value}`).toLocaleTimeString([], {
		hour: '2-digit',
		minute: '2-digit',
	})}
{:else if field.type === 'currency'}
	{new Intl.NumberFormat('en-US', {
		style: 'currency',
		currency: field.settings?.currency || 'USD',
	}).format(value)}
{:else if field.type === 'percent'}
	{value}%
{:else if field.type === 'number' || field.type === 'decimal'}
	{new Intl.NumberFormat('en-US').format(value)}
{:else if field.type === 'checkbox' || field.type === 'toggle'}
	{#if value}
		<Badge variant="default" class="gap-1">
			<Check class="h-3 w-3" />
			Yes
		</Badge>
	{:else}
		<Badge variant="secondary" class="gap-1">
			<X class="h-3 w-3" />
			No
		</Badge>
	{/if}
{:else if field.type === 'select' || field.type === 'radio'}
	{@const option = field.options?.find((opt) => opt.value === value)}
	{#if option}
		<Badge style={option.color ? `background-color: ${option.color}` : ''}>
			{option.label}
		</Badge>
	{:else}
		{value}
	{/if}
{:else if field.type === 'multiselect'}
	{#if Array.isArray(value)}
		<div class="flex flex-wrap gap-1">
			{#each value as val}
				{@const opt = field.options?.find((o) => o.value === val)}
				<Badge style={opt?.color ? `background-color: ${opt.color}` : ''}>
					{opt?.label || val}
				</Badge>
			{/each}
		</div>
	{:else}
		{value}
	{/if}
{:else if field.type === 'textarea' || field.type === 'rich_text'}
	<div class="whitespace-pre-wrap rounded-md bg-muted p-3 text-sm">
		{value}
	</div>
{:else}
	{String(value)}
{/if}
