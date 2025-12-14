<script lang="ts">
	import { Clock, AlertCircle } from 'lucide-svelte';
	import * as Alert from '$lib/components/ui/alert';

	interface Props {
		data: Record<string, unknown>;
		fields: Record<string, { label: string; type: string }>;
		timestamp: string;
		currentData?: Record<string, unknown> | null;
	}

	let {
		data,
		fields,
		timestamp,
		currentData = null,
	}: Props = $props();

	function formatTimestamp(ts: string): string {
		return new Date(ts).toLocaleString('en-US', {
			weekday: 'long',
			month: 'long',
			day: 'numeric',
			year: 'numeric',
			hour: 'numeric',
			minute: '2-digit'
		});
	}

	function formatValue(value: unknown, fieldType: string): string {
		if (value === null || value === undefined) {
			return '-';
		}

		if (Array.isArray(value)) {
			return value.join(', ');
		}

		if (typeof value === 'boolean') {
			return value ? 'Yes' : 'No';
		}

		switch (fieldType) {
			case 'currency':
				return `$${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
			case 'percent':
				return `${Number(value).toFixed(1)}%`;
			case 'date':
				return new Date(String(value)).toLocaleDateString();
			case 'datetime':
				return new Date(String(value)).toLocaleString();
			default:
				return String(value);
		}
	}

	function hasChanged(fieldKey: string): boolean {
		if (!currentData) return false;
		const oldVal = JSON.stringify(data[fieldKey]);
		const newVal = JSON.stringify(currentData[fieldKey]);
		return oldVal !== newVal;
	}

	const sortedFields = $derived(Object.entries(fields).sort((a, b) =>
		a[1].label.localeCompare(b[1].label)
	));
</script>

<div class="space-y-4">
	<!-- Historical indicator -->
	<Alert.Root variant="default" class="bg-amber-50 dark:bg-amber-950 border-amber-200 dark:border-amber-800">
		<Clock class="h-4 w-4 text-amber-600" />
		<Alert.Title class="text-amber-800 dark:text-amber-200">Viewing Historical State</Alert.Title>
		<Alert.Description class="text-amber-700 dark:text-amber-300">
			This is how the record appeared on {formatTimestamp(timestamp)}
		</Alert.Description>
	</Alert.Root>

	<!-- Field values -->
	<div class="grid gap-4 md:grid-cols-2">
		{#each sortedFields as [fieldKey, fieldInfo]}
			{@const changed = hasChanged(fieldKey)}
			<div
				class="p-3 rounded-lg border {changed
					? 'bg-yellow-50 dark:bg-yellow-950 border-yellow-200 dark:border-yellow-700'
					: 'bg-background border-border'}"
			>
				<div class="flex items-center justify-between mb-1">
					<span class="text-sm font-medium text-muted-foreground">
						{fieldInfo.label}
					</span>
					{#if changed}
						<span
							class="text-xs px-1.5 py-0.5 rounded bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200"
						>
							Changed since
						</span>
					{/if}
				</div>
				<div class="text-foreground">
					{formatValue(data[fieldKey], fieldInfo.type)}
				</div>
				{#if changed && currentData}
					<div class="mt-1 text-xs text-muted-foreground">
						Current: {formatValue(currentData[fieldKey], fieldInfo.type)}
					</div>
				{/if}
			</div>
		{/each}
	</div>
</div>
