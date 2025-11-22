<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Input } from '$lib/components/ui/input';
	import { ScrollArea } from '$lib/components/ui/scroll-area';
	import { Badge } from '$lib/components/ui/badge';
	import {
		fieldTemplates,
		templateCategories,
		getTemplatesByCategory,
		type FieldTemplate,
	} from '$lib/lib/field-templates';
	import { Search } from 'lucide-svelte';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
		onSelect: (template: FieldTemplate) => void;
	}

	let { open = $bindable(false), onOpenChange, onSelect }: Props = $props();

	let searchQuery = $state('');
	let selectedCategory = $state<string | null>(null);

	const filteredTemplates = $derived.by(() => {
		let templates = selectedCategory
			? getTemplatesByCategory(selectedCategory)
			: fieldTemplates;

		if (searchQuery.trim()) {
			const query = searchQuery.toLowerCase();
			templates = templates.filter(
				(t) =>
					t.name.toLowerCase().includes(query) ||
					t.description.toLowerCase().includes(query) ||
					t.field.label.toLowerCase().includes(query)
			);
		}

		return templates;
	});

	function handleSelect(template: FieldTemplate) {
		onSelect(template);
		open = false;
		searchQuery = '';
		selectedCategory = null;
	}

	function handleOpenChange(newOpen: boolean) {
		open = newOpen;
		onOpenChange?.(newOpen);
		if (!newOpen) {
			searchQuery = '';
			selectedCategory = null;
		}
	}
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-3xl max-h-[80vh]">
		<Dialog.Header>
			<Dialog.Title>Choose a Field Template</Dialog.Title>
			<Dialog.Description>
				Select from pre-built field templates or build your own from scratch
			</Dialog.Description>
		</Dialog.Header>

		<div class="space-y-4">
			<!-- Search -->
			<div class="relative">
				<Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
				<Input
					bind:value={searchQuery}
					placeholder="Search templates..."
					class="pl-9"
				/>
			</div>

			<!-- Category Filter -->
			<div class="flex flex-wrap gap-2">
				<Button
					variant={selectedCategory === null ? 'secondary' : 'outline'}
					size="sm"
					onclick={() => (selectedCategory = null)}
				>
					All
				</Button>
				{#each templateCategories as category}
					<Button
						variant={selectedCategory === category.value ? 'secondary' : 'outline'}
						size="sm"
						onclick={() => (selectedCategory = category.value)}
					>
						{category.label}
					</Button>
				{/each}
			</div>

			<!-- Templates Grid -->
			<ScrollArea class="h-[400px] rounded-md border p-4">
				{#if filteredTemplates.length === 0}
					<div class="flex items-center justify-center h-full text-center py-12">
						<div class="text-muted-foreground">
							<p class="text-sm">No templates found</p>
							<p class="text-xs mt-1">Try a different search or category</p>
						</div>
					</div>
				{:else}
					<div class="grid grid-cols-2 gap-3">
						{#each filteredTemplates as template}
							<button
								onclick={() => handleSelect(template)}
								class="group text-left p-4 rounded-lg border border-border hover:border-primary hover:bg-accent transition-colors"
							>
								<div class="flex items-start justify-between gap-2 mb-2">
									<h4 class="font-medium group-hover:text-primary transition-colors">
										{template.name}
									</h4>
									<Badge variant="secondary" class="text-xs shrink-0">
										{template.field.type}
									</Badge>
								</div>
								<p class="text-xs text-muted-foreground line-clamp-2">
									{template.description}
								</p>
								<div class="mt-3 flex flex-wrap gap-1">
									{#if template.field.is_required}
										<Badge variant="outline" class="text-xs">Required</Badge>
									{/if}
									{#if template.field.is_unique}
										<Badge variant="outline" class="text-xs">Unique</Badge>
									{/if}
									{#if template.field.is_searchable}
										<Badge variant="outline" class="text-xs">Searchable</Badge>
									{/if}
								</div>
							</button>
						{/each}
					</div>
				{/if}
			</ScrollArea>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => handleOpenChange(false)}>
				Cancel
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
