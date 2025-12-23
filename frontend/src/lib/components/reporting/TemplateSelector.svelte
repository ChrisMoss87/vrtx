<script lang="ts">
	import { onMount } from 'svelte';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import * as Card from '$lib/components/ui/card';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import { FileText, Search, Check, Users, Loader2 } from 'lucide-svelte';
	import { reportTemplatesApi, type ReportTemplate } from '$lib/api/report-templates';

	interface Props {
		open?: boolean;
		onOpenChange?: (open: boolean) => void;
		onSelect?: (template: ReportTemplate) => void;
		moduleId?: number;
	}

	let { open = $bindable(false), onOpenChange, onSelect, moduleId }: Props = $props();

	let templates = $state<ReportTemplate[]>([]);
	let loading = $state(true);
	let search = $state('');
	let selectedTemplate = $state<ReportTemplate | null>(null);
	let applying = $state(false);

	const filteredTemplates = $derived(
		templates.filter(t =>
			t.name.toLowerCase().includes(search.toLowerCase()) ||
			(t.description?.toLowerCase().includes(search.toLowerCase()) ?? false)
		)
	);

	onMount(async () => {
		await loadTemplates();
	});

	async function loadTemplates() {
		loading = true;
		try {
			const response = await reportTemplatesApi.list({
				module_id: moduleId,
				per_page: 100
			});
			templates = response.data;
		} catch (error) {
			console.error('Failed to load templates:', error);
		} finally {
			loading = false;
		}
	}

	function handleSelect(template: ReportTemplate) {
		selectedTemplate = template;
	}

	function handleApply() {
		if (selectedTemplate) {
			applying = true;
			onSelect?.(selectedTemplate);
			open = false;
		}
	}

	function handleOpenChange(value: boolean) {
		open = value;
		if (!value) {
			selectedTemplate = null;
		}
		onOpenChange?.(value);
	}

	function getTypeLabel(type: string): string {
		const labels: Record<string, string> = {
			table: 'Table',
			chart: 'Chart',
			summary: 'Summary',
			matrix: 'Matrix',
			pivot: 'Pivot'
		};
		return labels[type] || type;
	}
</script>

<Dialog.Root bind:open onOpenChange={handleOpenChange}>
	<Dialog.Content class="max-w-2xl max-h-[80vh] flex flex-col">
		<Dialog.Header>
			<Dialog.Title>Load Template</Dialog.Title>
			<Dialog.Description>
				Select a template to pre-fill your report configuration
			</Dialog.Description>
		</Dialog.Header>

		<div class="py-4 flex-1 overflow-hidden flex flex-col">
			<!-- Search -->
			<div class="relative mb-4">
				<Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
				<Input
					placeholder="Search templates..."
					class="pl-9"
					bind:value={search}
				/>
			</div>

			<!-- Templates List -->
			<div class="flex-1 overflow-auto space-y-2">
				{#if loading}
					{#each Array(3) as _}
						<div class="p-4 border rounded-lg">
							<Skeleton class="h-5 w-48 mb-2" />
							<Skeleton class="h-4 w-full" />
						</div>
					{/each}
				{:else if filteredTemplates.length === 0}
					<div class="flex flex-col items-center justify-center py-8 text-center">
						<FileText class="mb-4 h-12 w-12 text-muted-foreground" />
						<h3 class="mb-2 text-lg font-medium">No templates found</h3>
						<p class="text-muted-foreground">
							{search ? 'Try a different search term' : 'Create your first template by saving a report'}
						</p>
					</div>
				{:else}
					{#each filteredTemplates as template}
						<button
							type="button"
							class="w-full text-left p-4 border rounded-lg transition-colors hover:bg-muted/50 {selectedTemplate?.id === template.id ? 'border-primary bg-primary/5' : ''}"
							onclick={() => handleSelect(template)}
						>
							<div class="flex items-start justify-between">
								<div class="flex-1">
									<div class="flex items-center gap-2 mb-1">
										<span class="font-medium">{template.name}</span>
										{#if template.is_public}
											<Badge variant="secondary" class="text-xs">
												<Users class="mr-1 h-3 w-3" />
												Public
											</Badge>
										{/if}
									</div>
									{#if template.description}
										<p class="text-sm text-muted-foreground line-clamp-1">
											{template.description}
										</p>
									{/if}
									<div class="flex items-center gap-2 mt-2">
										<Badge variant="outline">{getTypeLabel(template.type)}</Badge>
										{#if template.chart_type}
											<Badge variant="outline">{template.chart_type}</Badge>
										{/if}
										{#if template.module}
											<Badge variant="outline">{template.module.name}</Badge>
										{/if}
									</div>
								</div>
								{#if selectedTemplate?.id === template.id}
									<div class="flex-shrink-0 ml-4">
										<div class="rounded-full bg-primary p-1">
											<Check class="h-4 w-4 text-primary-foreground" />
										</div>
									</div>
								{/if}
							</div>
						</button>
					{/each}
				{/if}
			</div>
		</div>

		<Dialog.Footer>
			<Button variant="outline" onclick={() => (open = false)} disabled={applying}>
				Cancel
			</Button>
			<Button onclick={handleApply} disabled={!selectedTemplate || applying}>
				{#if applying}
					<Loader2 class="mr-2 h-4 w-4 animate-spin" />
					Loading...
				{:else}
					Use Template
				{/if}
			</Button>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
