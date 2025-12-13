<script lang="ts">
	import { Check } from 'lucide-svelte';
	import { fade, fly } from 'svelte/transition';
	import type { RecordingStep } from '$lib/api/recordings';

	export let step: RecordingStep;
	export let onDismiss: () => void;

	// Auto-dismiss after 3 seconds
	setTimeout(() => {
		onDismiss();
	}, 3000);
</script>

<div
	class="fixed bottom-20 right-4 z-40"
	in:fly={{ y: 20, duration: 200 }}
	out:fade={{ duration: 150 }}
>
	<div class="flex items-center gap-3 bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-800 rounded-lg shadow-lg p-3 max-w-xs">
		<div class="flex-shrink-0 w-6 h-6 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center">
			<Check class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
		</div>
		<div class="flex-1 min-w-0">
			<p class="text-sm font-medium text-green-800 dark:text-green-200">
				Captured: {step.action_label}
			</p>
			<p class="text-xs text-green-600 dark:text-green-400 truncate">
				{step.description}
			</p>
		</div>
	</div>
</div>
