<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import {
		LayoutTemplate,
		Type,
		Image,
		Video,
		MousePointer,
		FileText,
		Star,
		Grid3X3,
		DollarSign,
		HelpCircle,
		Minus,
		MoveVertical,
		Square,
		Megaphone
	} from 'lucide-svelte';
	import type { PageElementType } from '$lib/api/landing-pages';
	import { createDefaultElement } from '$lib/api/landing-pages';

	interface Props {
		onAddElement: (element: ReturnType<typeof createDefaultElement>) => void;
	}

	let { onAddElement }: Props = $props();

	const elementCategories = [
		{
			name: 'Layout',
			elements: [
				{ type: 'section' as PageElementType, label: 'Section', icon: LayoutTemplate },
				{ type: 'container' as PageElementType, label: 'Container', icon: Square },
				{ type: 'divider' as PageElementType, label: 'Divider', icon: Minus },
				{ type: 'spacer' as PageElementType, label: 'Spacer', icon: MoveVertical }
			]
		},
		{
			name: 'Content',
			elements: [
				{ type: 'hero' as PageElementType, label: 'Hero', icon: Megaphone },
				{ type: 'heading' as PageElementType, label: 'Heading', icon: Type },
				{ type: 'text' as PageElementType, label: 'Text', icon: FileText },
				{ type: 'image' as PageElementType, label: 'Image', icon: Image },
				{ type: 'video' as PageElementType, label: 'Video', icon: Video },
				{ type: 'button' as PageElementType, label: 'Button', icon: MousePointer }
			]
		},
		{
			name: 'Blocks',
			elements: [
				{ type: 'cta' as PageElementType, label: 'Call to Action', icon: Megaphone },
				{ type: 'form' as PageElementType, label: 'Form', icon: FileText },
				{ type: 'testimonials' as PageElementType, label: 'Testimonials', icon: Star },
				{ type: 'features' as PageElementType, label: 'Features', icon: Grid3X3 },
				{ type: 'pricing' as PageElementType, label: 'Pricing', icon: DollarSign },
				{ type: 'faq' as PageElementType, label: 'FAQ', icon: HelpCircle },
				{ type: 'footer' as PageElementType, label: 'Footer', icon: LayoutTemplate }
			]
		}
	];

	function handleAddElement(type: PageElementType) {
		const element = createDefaultElement(type);
		onAddElement(element);
	}
</script>

<div class="flex h-full flex-col">
	<div class="border-b p-3">
		<h3 class="text-sm font-medium">Elements</h3>
		<p class="text-muted-foreground text-xs">Drag or click to add</p>
	</div>

	<ScrollArea class="flex-1">
		<div class="space-y-4 p-3">
			{#each elementCategories as category}
				<div>
					<h4 class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">
						{category.name}
					</h4>
					<div class="grid grid-cols-2 gap-2">
						{#each category.elements as element}
							<Button
								variant="outline"
								size="sm"
								class="h-auto flex-col gap-1 py-3"
								onclick={() => handleAddElement(element.type)}
							>
								<element.icon class="h-4 w-4" />
								<span class="text-xs">{element.label}</span>
							</Button>
						{/each}
					</div>
				</div>
			{/each}
		</div>
	</ScrollArea>
</div>
