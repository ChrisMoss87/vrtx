<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { X } from 'lucide-svelte';
	import type { FilterConfig } from '../types';
	import { cn } from '$lib/utils';

	interface Props {
		field: string;
		initialValue?: FilterConfig;
		onApply: (filter: FilterConfig | null) => void;
		onClose: () => void;
	}

	let { field, initialValue, onApply, onClose }: Props = $props();

	// null = no selection, true, false
	let selectedValue = $state<boolean | null>(
		initialValue?.value === true ? true : initialValue?.value === false ? false : null
	);

	function handleSelect(value: boolean) {
		selectedValue = selectedValue === value ? null : value;
	}

	function handleApply() {
		if (selectedValue === null) {
			onApply(null);
		} else {
			onApply({
				field,
				operator: 'equals',
				value: selectedValue
			});
		}
		onClose();
	}

	function handleClear() {
		onApply(null);
		onClose();
	}
</script>

<div class="w-[200px] space-y-3 p-3">
	<div class="space-y-2">
		<label class="text-xs font-medium">Value</label>
		<div class="flex gap-2">
			<button
				type="button"
				class={cn(
					'flex-1 rounded-md border px-3 py-2 text-sm transition-colors',
					selectedValue === true
						? 'border-primary bg-primary/10 text-primary'
						: 'border-input hover:bg-accent'
				)}
				onclick={() => handleSelect(true)}
			>
				Yes / True
			</button>
			<button
				type="button"
				class={cn(
					'flex-1 rounded-md border px-3 py-2 text-sm transition-colors',
					selectedValue === false
						? 'border-primary bg-primary/10 text-primary'
						: 'border-input hover:bg-accent'
				)}
				onclick={() => handleSelect(false)}
			>
				No / False
			</button>
		</div>
	</div>

	<div class="flex gap-2">
		<Button size="sm" onclick={handleApply} class="flex-1">Apply</Button>
		<Button size="sm" variant="outline" onclick={handleClear}>
			<X class="h-3 w-3" />
		</Button>
	</div>
</div>
