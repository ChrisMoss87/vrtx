<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import * as Card from '$lib/components/ui/card';
	import { Badge } from '$lib/components/ui/badge';
	import { Edit2, CheckCircle } from 'lucide-svelte';
	import type { WizardStore } from '$lib/hooks/useWizard.svelte';

	interface ReviewSection {
		title: string;
		description?: string;
		stepId: string;
		fields: Array<{
			label: string;
			value: any;
			type?: string;
			isRequired?: boolean;
		}>;
	}

	interface Props {
		wizard: WizardStore;
		sections: ReviewSection[];
		onEdit?: (stepId: string) => void;
		showEditButtons?: boolean;
		title?: string;
		description?: string;
	}

	let {
		wizard,
		sections,
		onEdit,
		showEditButtons = true,
		title = 'Review Your Information',
		description = 'Please review your information before submitting'
	}: Props = $props();

	function handleEdit(stepId: string) {
		if (onEdit) {
			onEdit(stepId);
		} else {
			// Default behavior: navigate to that step
			const stepIndex = wizard.steps.findIndex((s) => s.id === stepId);
			if (stepIndex >= 0) {
				wizard.goToStep(stepIndex);
			}
		}
	}

	function formatValue(value: any, type?: string): string {
		if (value === null || value === undefined || value === '') {
			return '—';
		}

		switch (type) {
			case 'checkbox':
			case 'boolean':
				return value ? 'Yes' : 'No';
			case 'currency':
				return typeof value === 'number' ? `$${value.toFixed(2)}` : value;
			case 'percentage':
				return typeof value === 'number' ? `${value}%` : value;
			case 'date':
				return value instanceof Date ? value.toLocaleDateString() : value;
			case 'array':
				return Array.isArray(value) ? value.join(', ') : value;
			default:
				return String(value);
		}
	}

	const hasData = $derived(sections.some((s) => s.fields.some((f) => f.value)));
</script>

<div class="review-step space-y-6">
	<!-- Header -->
	{#if title || description}
		<div class="mb-6 text-center">
			{#if title}
				<h2 class="text-2xl font-semibold tracking-tight">{title}</h2>
			{/if}
			{#if description}
				<p class="mt-2 text-muted-foreground">{description}</p>
			{/if}
		</div>
	{/if}

	<!-- Review Sections -->
	{#if hasData}
		<div class="space-y-4">
			{#each sections as section}
				{#if section.fields.some((f) => f.value)}
					<Card.Root>
						<Card.Header>
							<div class="flex items-center justify-between">
								<div class="flex-1">
									<Card.Title class="flex items-center gap-2">
										<CheckCircle class="h-5 w-5 text-green-600" />
										{section.title}
									</Card.Title>
									{#if section.description}
										<Card.Description>{section.description}</Card.Description>
									{/if}
								</div>
								{#if showEditButtons && onEdit !== undefined}
									<Button variant="outline" size="sm" onclick={() => handleEdit(section.stepId)}>
										<Edit2 class="mr-2 h-3 w-3" />
										Edit
									</Button>
								{/if}
							</div>
						</Card.Header>
						<Card.Content>
							<dl class="space-y-3">
								{#each section.fields as field}
									{#if field.value}
										<div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-4">
											<dt class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
												{field.label}
												{#if field.isRequired}
													<Badge variant="secondary" class="text-xs">Required</Badge>
												{/if}
											</dt>
											<dd class="text-right text-sm font-medium break-words sm:text-right">
												{formatValue(field.value, field.type)}
											</dd>
										</div>
									{/if}
								{/each}
							</dl>
						</Card.Content>
					</Card.Root>
				{/if}
			{/each}
		</div>
	{:else}
		<!-- Empty State -->
		<Card.Root>
			<Card.Content class="py-12 text-center">
				<div class="text-muted-foreground">
					<p class="mb-2 text-lg">No information to review yet</p>
					<p class="text-sm">Complete the previous steps to see a summary here</p>
				</div>
			</Card.Content>
		</Card.Root>
	{/if}

	<!-- Summary Stats (if applicable) -->
	{#if hasData}
		<div class="flex items-center justify-center gap-6 border-t pt-4">
			<div class="text-center">
				<div class="text-2xl font-bold">{sections.length}</div>
				<div class="text-sm text-muted-foreground">Section{sections.length !== 1 ? 's' : ''}</div>
			</div>
			<div class="text-center">
				<div class="text-2xl font-bold">
					{sections.reduce((sum, s) => sum + s.fields.filter((f) => f.value).length, 0)}
				</div>
				<div class="text-sm text-muted-foreground">
					Field{sections.reduce((sum, s) => sum + s.fields.filter((f) => f.value).length, 0) !== 1
						? 's'
						: ''} Completed
				</div>
			</div>
		</div>
	{/if}
</div>

<style>
	.review-step {
		animation: fadeIn 0.3s ease-in;
	}

	@keyframes fadeIn {
		from {
			opacity: 0;
			transform: translateY(10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
</style>
