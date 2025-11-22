<script lang="ts">
	import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { Monitor, Tablet, Smartphone } from 'lucide-svelte';
	import DynamicForm from '$lib/components/modules/DynamicForm.svelte';

	interface Block {
		id?: number;
		type: string;
		label: string;
		order: number;
		settings: Record<string, any>;
		fields: Field[];
	}

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
		options?: FieldOption[];
	}

	interface Module {
		id?: number;
		name: string;
		singular_name: string;
		api_name?: string;
		icon: string | null;
		description: string | null;
		is_active: boolean;
		is_system: boolean;
		settings: Record<string, any>;
		blocks: Block[];
	}

	interface Props {
		module: Module;
		blocks: Block[];
	}

	let { module, blocks }: Props = $props();

	type ViewportSize = 'desktop' | 'tablet' | 'mobile';
	let viewportSize = $state<ViewportSize>('desktop');

	// Create a preview module object
	const previewModule = $derived({
		...module,
		blocks: blocks || []
	});

	// Viewport width classes
	const viewportClass = $derived.by(() => {
		switch (viewportSize) {
			case 'mobile':
				return 'max-w-[375px]';
			case 'tablet':
				return 'max-w-[768px]';
			case 'desktop':
			default:
				return 'max-w-full';
		}
	});

	function handlePreviewSubmit(data: Record<string, any>) {
		// Preview mode - just log the data
		console.log('Preview form data:', data);
	}

	function handlePreviewCancel() {
		// Preview mode - do nothing
	}
</script>

<Card class="h-full flex flex-col">
	<CardHeader class="border-b">
		<div class="flex items-center justify-between">
			<CardTitle>Live Preview</CardTitle>
			<div class="flex items-center gap-2">
				<Button
					variant={viewportSize === 'desktop' ? 'secondary' : 'ghost'}
					size="sm"
					onclick={() => (viewportSize = 'desktop')}
					title="Desktop view"
				>
					<Monitor class="h-4 w-4" />
				</Button>
				<Button
					variant={viewportSize === 'tablet' ? 'secondary' : 'ghost'}
					size="sm"
					onclick={() => (viewportSize = 'tablet')}
					title="Tablet view"
				>
					<Tablet class="h-4 w-4" />
				</Button>
				<Button
					variant={viewportSize === 'mobile' ? 'secondary' : 'ghost'}
					size="sm"
					onclick={() => (viewportSize = 'mobile')}
					title="Mobile view"
				>
					<Smartphone class="h-4 w-4" />
				</Button>
			</div>
		</div>
	</CardHeader>

	<CardContent class="flex-1 overflow-auto p-6">
		{#if blocks.length === 0}
			<div class="flex items-center justify-center h-full text-center">
				<div class="text-muted-foreground">
					<p class="text-sm">No blocks or fields to preview</p>
					<p class="text-xs mt-1">Add blocks and fields to see a live preview</p>
				</div>
			</div>
		{:else}
			<div class="mx-auto transition-all duration-200 {viewportClass}">
				<DynamicForm
					module={previewModule}
					onSubmit={handlePreviewSubmit}
					onCancel={handlePreviewCancel}
					isSubmitting={false}
				/>
			</div>
		{/if}
	</CardContent>
</Card>
