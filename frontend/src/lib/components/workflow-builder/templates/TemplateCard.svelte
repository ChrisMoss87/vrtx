<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import {
		Users,
		TrendingUp,
		Heart,
		Database,
		Clock,
		MessageSquare,
		Zap,
		AlertCircle,
		Star
	} from 'lucide-svelte';
	import type { WorkflowTemplate, TemplateCategory, TemplateDifficulty } from '$lib/api/workflows';

	interface Props {
		template: WorkflowTemplate;
		onSelect?: (template: WorkflowTemplate) => void;
		onPreview?: (template: WorkflowTemplate) => void;
	}

	let { template, onSelect, onPreview }: Props = $props();

	const categoryIcons: Record<TemplateCategory, typeof Users> = {
		lead: Users,
		deal: TrendingUp,
		customer: Heart,
		data: Database,
		productivity: Clock,
		communication: MessageSquare
	};

	const categoryColors: Record<TemplateCategory, string> = {
		lead: 'bg-blue-100 text-blue-800',
		deal: 'bg-green-100 text-green-800',
		customer: 'bg-purple-100 text-purple-800',
		data: 'bg-orange-100 text-orange-800',
		productivity: 'bg-indigo-100 text-indigo-800',
		communication: 'bg-pink-100 text-pink-800'
	};

	const difficultyColors: Record<TemplateDifficulty, string> = {
		beginner: 'bg-emerald-100 text-emerald-800',
		intermediate: 'bg-amber-100 text-amber-800',
		advanced: 'bg-red-100 text-red-800'
	};

	let CategoryIcon = $derived(categoryIcons[template.category] || Zap);
</script>

<Card.Root
	class="group relative overflow-hidden transition-all hover:shadow-lg {!template.is_compatible
		? 'opacity-60'
		: ''}"
>
	{#if template.usage_count > 100}
		<div class="absolute right-2 top-2">
			<Badge variant="secondary" class="gap-1 bg-yellow-100 text-yellow-800">
				<Star class="h-3 w-3" />
				Popular
			</Badge>
		</div>
	{/if}

	<Card.Header class="pb-3">
		<div class="flex items-start gap-3">
			<div
				class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {categoryColors[
					template.category
				]}"
			>
				<CategoryIcon class="h-5 w-5" />
			</div>
			<div class="min-w-0 flex-1">
				<Card.Title class="line-clamp-1 text-base">{template.name}</Card.Title>
				<div class="mt-1 flex flex-wrap gap-1">
					<Badge variant="outline" class="text-xs {difficultyColors[template.difficulty]}">
						{template.difficulty}
					</Badge>
					{#if template.estimated_time_saved_hours}
						<Badge variant="outline" class="text-xs">
							Saves ~{template.estimated_time_saved_hours}h/mo
						</Badge>
					{/if}
				</div>
			</div>
		</div>
	</Card.Header>

	<Card.Content class="pb-3">
		<p class="line-clamp-2 text-sm text-muted-foreground">
			{template.description}
		</p>

		{#if !template.is_compatible && template.missing_modules?.length}
			<div class="mt-3 flex items-center gap-2 text-sm text-amber-600">
				<AlertCircle class="h-4 w-4" />
				<span>Requires: {template.missing_modules.join(', ')}</span>
			</div>
		{/if}
	</Card.Content>

	<Card.Footer class="flex gap-2 pt-0">
		<Button
			variant="outline"
			size="sm"
			class="flex-1"
			onclick={() => onPreview?.(template)}
		>
			Preview
		</Button>
		<Button
			size="sm"
			class="flex-1"
			disabled={!template.is_compatible}
			onclick={() => onSelect?.(template)}
		>
			Use Template
		</Button>
	</Card.Footer>
</Card.Root>
