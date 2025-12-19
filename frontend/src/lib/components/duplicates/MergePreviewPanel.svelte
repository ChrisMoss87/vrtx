<script lang="ts">
	import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '$lib/components/ui/card';
	import { Button } from '$lib/components/ui/button';
	import { RadioGroup, RadioGroupItem } from '$lib/components/ui/radio-group';
	import { Label } from '$lib/components/ui/label';
	import { Badge } from '$lib/components/ui/badge';
	import { Skeleton } from '$lib/components/ui/skeleton';
	import type { MergePreview } from '$lib/api/duplicates';
	import { previewMerge, mergeRecords } from '$lib/api/duplicates';
	import { toast } from 'svelte-sonner';

	interface Props {
		recordAId: number;
		recordBId: number;
		onMerged?: (mergedRecordId: number) => void;
		onCancel?: () => void;
		class?: string;
	}

	let { recordAId, recordBId, onMerged, onCancel, class: className = '' }: Props = $props();

	let preview = $state<MergePreview | null>(null);
	let loading = $state(true);
	let merging = $state(false);
	let fieldSelections = $state<Record<string, string>>({});

	async function loadPreview() {
		loading = true;
		try {
			preview = await previewMerge(recordAId, recordBId, fieldSelections);
			// Set default selections
			if (preview) {
				for (const [fieldKey, fieldData] of Object.entries(preview.preview)) {
					if (!(fieldKey in fieldSelections)) {
						fieldSelections[fieldKey] = fieldData.selection;
					}
				}
			}
		} catch (e) {
			toast.error('Failed to load merge preview');
		} finally {
			loading = false;
		}
	}

	async function handleMerge() {
		if (!preview) return;
		merging = true;
		try {
			const result = await mergeRecords(recordAId, [recordBId], fieldSelections);
			toast.success('Records merged successfully');
			onMerged?.(result.id);
		} catch (e) {
			toast.error(e instanceof Error ? e.message : 'Failed to merge records');
		} finally {
			merging = false;
		}
	}

	$effect(() => {
		loadPreview();
	});
</script>

<Card class={className}>
	<CardHeader>
		<CardTitle>Merge Preview</CardTitle>
		<CardDescription>
			Select which value to keep for each differing field
		</CardDescription>
	</CardHeader>
	<CardContent>
		{#if loading}
			<div class="space-y-4">
				{#each Array(5) as _}
					<div class="space-y-2">
						<Skeleton class="h-4 w-1/4" />
						<div class="grid grid-cols-2 gap-4">
							<Skeleton class="h-10" />
							<Skeleton class="h-10" />
						</div>
					</div>
				{/each}
			</div>
		{:else if preview}
			<div class="space-y-6">
				<div class="flex items-center gap-2 text-sm text-muted-foreground">
					<Badge variant="outline">{preview.differing_fields}</Badge>
					<span>fields differ out of {preview.field_count} total</span>
				</div>

				<div class="space-y-4">
					{#each Object.entries(preview.preview) as [fieldKey, field]}
						{#if field.differs}
							<div class="space-y-2 p-3 border rounded-lg">
								<Label class="font-medium">{field.label}</Label>
								<RadioGroup bind:value={fieldSelections[fieldKey]}>
									<div class="grid grid-cols-2 gap-4">
										<div class="flex items-start gap-2">
											<RadioGroupItem value="a" id="{fieldKey}-a" />
											<div class="flex-1">
												<Label for="{fieldKey}-a" class="text-sm cursor-pointer">
													Record A
												</Label>
												<p class="text-sm text-muted-foreground break-words">
													{field.value_a ?? '(empty)'}
												</p>
											</div>
										</div>
										<div class="flex items-start gap-2">
											<RadioGroupItem value="b" id="{fieldKey}-b" />
											<div class="flex-1">
												<Label for="{fieldKey}-b" class="text-sm cursor-pointer">
													Record B
												</Label>
												<p class="text-sm text-muted-foreground break-words">
													{field.value_b ?? '(empty)'}
												</p>
											</div>
										</div>
									</div>
								</RadioGroup>
							</div>
						{/if}
					{/each}
				</div>

				<div class="flex gap-2 pt-4 border-t">
					<Button onclick={handleMerge} disabled={merging} class="flex-1">
						{merging ? 'Merging...' : 'Merge Records'}
					</Button>
					<Button variant="outline" onclick={onCancel}>
						Cancel
					</Button>
				</div>
			</div>
		{/if}
	</CardContent>
</Card>
