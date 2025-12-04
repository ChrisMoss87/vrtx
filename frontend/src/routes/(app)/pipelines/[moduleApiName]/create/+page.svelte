<script lang="ts">
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { modulesApi, type Module } from '$lib/api/modules';
	import { createPipeline, type StageInput } from '$lib/api/pipelines';
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import * as Card from '$lib/components/ui/card';
	import { Switch } from '$lib/components/ui/switch';
	import { ArrowLeft, Plus, Trash2, GripVertical, Save } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';
	import { Skeleton } from '$lib/components/ui/skeleton';

	const moduleApiName = $derived($page.params.moduleApiName as string);

	let module = $state<Module | null>(null);
	let loading = $state(true);
	let saving = $state(false);
	let error = $state<string | null>(null);

	let pipelineName = $state('');
	let isActive = $state(true);
	let stages = $state<StageInput[]>([
		{ name: 'Lead', color: '#3b82f6', probability: 10 },
		{ name: 'Qualified', color: '#8b5cf6', probability: 25 },
		{ name: 'Proposal', color: '#f59e0b', probability: 50 },
		{ name: 'Negotiation', color: '#f97316', probability: 75 },
		{ name: 'Closed Won', color: '#22c55e', probability: 100, is_won_stage: true },
		{ name: 'Closed Lost', color: '#ef4444', probability: 0, is_lost_stage: true }
	]);

	const colors = [
		'#3b82f6', // blue
		'#8b5cf6', // violet
		'#ec4899', // pink
		'#f59e0b', // amber
		'#f97316', // orange
		'#22c55e', // green
		'#14b8a6', // teal
		'#06b6d4', // cyan
		'#ef4444', // red
		'#6b7280' // gray
	];

	onMount(async () => {
		try {
			module = await modulesApi.getByApiName(moduleApiName);
			pipelineName = `${module.name} Pipeline`;
		} catch (err) {
			error = err instanceof Error ? err.message : 'Failed to load module';
		} finally {
			loading = false;
		}
	});

	function addStage() {
		const newStage: StageInput = {
			name: `Stage ${stages.length + 1}`,
			color: colors[stages.length % colors.length],
			probability: Math.min((stages.length + 1) * 15, 100)
		};
		stages = [...stages, newStage];
	}

	function removeStage(index: number) {
		if (stages.length <= 2) {
			toast.error('Pipeline must have at least 2 stages');
			return;
		}
		stages = stages.filter((_, i) => i !== index);
	}

	function updateStage(index: number, field: keyof StageInput, value: any) {
		stages = stages.map((stage, i) => {
			if (i === index) {
				return { ...stage, [field]: value };
			}
			return stage;
		});
	}

	function moveStage(fromIndex: number, direction: 'up' | 'down') {
		const toIndex = direction === 'up' ? fromIndex - 1 : fromIndex + 1;
		if (toIndex < 0 || toIndex >= stages.length) return;

		const newStages = [...stages];
		[newStages[fromIndex], newStages[toIndex]] = [newStages[toIndex], newStages[fromIndex]];
		stages = newStages;
	}

	async function handleSubmit() {
		if (!pipelineName.trim()) {
			toast.error('Pipeline name is required');
			return;
		}

		if (!module) {
			toast.error('Module not loaded');
			return;
		}

		saving = true;
		try {
			const stagesWithOrder = stages.map((stage, index) => ({
				...stage,
				display_order: index
			}));

			await createPipeline({
				name: pipelineName,
				module_id: module.id,
				is_active: isActive,
				stages: stagesWithOrder
			});

			toast.success('Pipeline created successfully');
			goto(`/pipelines/${moduleApiName}`);
		} catch (err) {
			toast.error(err instanceof Error ? err.message : 'Failed to create pipeline');
		} finally {
			saving = false;
		}
	}

	function goBack() {
		goto(`/pipelines/${moduleApiName}`);
	}
</script>

<div class="container mx-auto max-w-4xl py-8">
	{#if loading}
		<div class="space-y-6">
			<Skeleton class="h-12 w-64" />
			<Skeleton class="h-96 w-full" />
		</div>
	{:else if error}
		<div class="rounded-lg border border-destructive p-6">
			<p class="text-destructive">{error}</p>
			<Button variant="outline" class="mt-4" onclick={goBack}>Go Back</Button>
		</div>
	{:else}
		<div class="mb-6 flex items-center gap-4">
			<Button variant="ghost" size="icon" onclick={goBack}>
				<ArrowLeft class="h-4 w-4" />
			</Button>
			<div>
				<h1 class="text-3xl font-bold">Create Pipeline</h1>
				<p class="text-muted-foreground mt-1">
					Create a new sales pipeline for {module?.name?.toLowerCase()}
				</p>
			</div>
		</div>

		<form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }}>
			<!-- Pipeline Settings -->
			<Card.Root class="mb-6">
				<Card.Header>
					<Card.Title>Pipeline Settings</Card.Title>
				</Card.Header>
				<Card.Content class="space-y-4">
					<div class="space-y-2">
						<Label for="pipeline-name">Pipeline Name</Label>
						<Input
							id="pipeline-name"
							bind:value={pipelineName}
							placeholder="e.g., Sales Pipeline, Recruitment Pipeline"
						/>
					</div>
					<div class="flex items-center gap-2">
						<Switch id="is-active" bind:checked={isActive} />
						<Label for="is-active">Active</Label>
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Stages -->
			<Card.Root class="mb-6">
				<Card.Header>
					<div class="flex items-center justify-between">
						<Card.Title>Stages</Card.Title>
						<Button type="button" variant="outline" size="sm" onclick={addStage}>
							<Plus class="mr-2 h-4 w-4" />
							Add Stage
						</Button>
					</div>
					<Card.Description>
						Define the stages records will move through in this pipeline
					</Card.Description>
				</Card.Header>
				<Card.Content>
					<div class="space-y-3">
						{#each stages as stage, index (index)}
							<div
								class="flex items-center gap-3 rounded-lg border bg-card p-3"
							>
								<div class="flex flex-col gap-1">
									<button
										type="button"
										class="text-muted-foreground hover:text-foreground disabled:opacity-30"
										disabled={index === 0}
										onclick={() => moveStage(index, 'up')}
									>
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
									</button>
									<button
										type="button"
										class="text-muted-foreground hover:text-foreground disabled:opacity-30"
										disabled={index === stages.length - 1}
										onclick={() => moveStage(index, 'down')}
									>
										<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
									</button>
								</div>

								<div
									class="h-8 w-8 shrink-0 rounded-full"
									style="background-color: {stage.color}"
								></div>

								<div class="flex-1 space-y-2">
									<div class="flex gap-3">
										<Input
											value={stage.name}
											oninput={(e) => updateStage(index, 'name', e.currentTarget.value)}
											placeholder="Stage name"
											class="flex-1"
										/>
										<div class="flex items-center gap-2">
											<Input
												type="number"
												value={stage.probability}
												oninput={(e) => updateStage(index, 'probability', Number(e.currentTarget.value))}
												min="0"
												max="100"
												class="w-20"
											/>
											<span class="text-muted-foreground text-sm">%</span>
										</div>
									</div>
									<div class="flex items-center gap-4">
										<div class="flex items-center gap-2">
											<Label class="text-xs">Color:</Label>
											<div class="flex gap-1">
												{#each colors as color}
													<button
														type="button"
														class="h-5 w-5 rounded-full border-2 transition-transform hover:scale-110"
														class:border-foreground={stage.color === color}
														class:border-transparent={stage.color !== color}
														style="background-color: {color}"
														onclick={() => updateStage(index, 'color', color)}
													></button>
												{/each}
											</div>
										</div>
										<div class="flex items-center gap-2">
											<input
												type="checkbox"
												id="won-{index}"
												checked={stage.is_won_stage}
												onchange={(e) => updateStage(index, 'is_won_stage', e.currentTarget.checked)}
												class="h-4 w-4"
											/>
											<Label for="won-{index}" class="text-xs text-green-600">Won</Label>
										</div>
										<div class="flex items-center gap-2">
											<input
												type="checkbox"
												id="lost-{index}"
												checked={stage.is_lost_stage}
												onchange={(e) => updateStage(index, 'is_lost_stage', e.currentTarget.checked)}
												class="h-4 w-4"
											/>
											<Label for="lost-{index}" class="text-xs text-red-600">Lost</Label>
										</div>
									</div>
								</div>

								<Button
									type="button"
									variant="ghost"
									size="icon"
									class="text-muted-foreground hover:text-destructive"
									onclick={() => removeStage(index)}
								>
									<Trash2 class="h-4 w-4" />
								</Button>
							</div>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Preview -->
			<Card.Root class="mb-6">
				<Card.Header>
					<Card.Title>Preview</Card.Title>
				</Card.Header>
				<Card.Content>
					<div class="flex gap-1">
						{#each stages as stage}
							<div
								class="flex-1 rounded-md py-2 text-center text-xs font-medium text-white"
								style="background-color: {stage.color}"
								title="{stage.name} ({stage.probability}%)"
							>
								{stage.name}
							</div>
						{/each}
					</div>
				</Card.Content>
			</Card.Root>

			<!-- Actions -->
			<div class="flex justify-end gap-3">
				<Button type="button" variant="outline" onclick={goBack}>
					Cancel
				</Button>
				<Button type="submit" disabled={saving}>
					{#if saving}
						<div class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></div>
					{:else}
						<Save class="mr-2 h-4 w-4" />
					{/if}
					Create Pipeline
				</Button>
			</div>
		</form>
	{/if}
</div>
