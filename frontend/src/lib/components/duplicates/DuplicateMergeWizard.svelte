<script lang="ts">
	import type { MergePreview } from '$lib/api/duplicates';
	import { previewMerge, mergeRecords, getRecordDisplayName } from '$lib/api/duplicates';
	import { Button } from '$lib/components/ui/button';
	import { Label } from '$lib/components/ui/label';
	import * as RadioGroup from '$lib/components/ui/radio-group';
	import * as Dialog from '$lib/components/ui/dialog';
	import { Loader2, GitMerge, CheckCircle, ArrowRight, AlertCircle } from 'lucide-svelte';
	import { toast } from 'svelte-sonner';

	interface Props {
		open?: boolean;
		recordAId: number;
		recordBId: number;
		primaryField?: string;
		onClose?: () => void;
		onMerged?: (survivingRecordId: number) => void;
	}

	let {
		open = $bindable(false),
		recordAId,
		recordBId,
		primaryField,
		onClose,
		onMerged
	}: Props = $props();

	let step = $state<'select' | 'review' | 'confirm'>('select');
	let loading = $state(true);
	let merging = $state(false);
	let preview = $state<MergePreview | null>(null);
	let survivingRecordId = $state<number>(recordAId);
	let fieldSelections = $state<Record<string, string>>({});

	async function loadPreview() {
		loading = true;
		try {
			preview = await previewMerge(recordAId, recordBId, fieldSelections);
			// Initialize field selections - default to surviving record's values
			const selections: Record<string, string> = {};
			for (const [field, info] of Object.entries(preview.preview)) {
				if (info.differs) {
					selections[field] = survivingRecordId === recordAId ? 'a' : 'b';
				}
			}
			fieldSelections = selections;
		} catch {
			toast.error('Failed to load merge preview');
		} finally {
			loading = false;
		}
	}

	async function handleMerge() {
		if (!preview) return;

		merging = true;
		try {
			const mergeRecordIds = [survivingRecordId === recordAId ? recordBId : recordAId];
			const result = await mergeRecords(survivingRecordId, mergeRecordIds, fieldSelections);
			toast.success('Records merged successfully');
			onMerged?.(result.id);
			open = false;
		} catch {
			toast.error('Failed to merge records');
		} finally {
			merging = false;
		}
	}

	function handleOpenChange(isOpen: boolean) {
		open = isOpen;
		if (!isOpen) {
			step = 'select';
			onClose?.();
		}
	}

	function getRecordName(recordId: number): string {
		if (!preview) return `Record ${recordId}`;
		const data = recordId === recordAId ? preview.record_a.data : preview.record_b.data;
		return getRecordDisplayName(data, primaryField);
	}

	function goToReview() {
		step = 'review';
	}

	function goToConfirm() {
		step = 'confirm';
	}

	function goBack() {
		if (step === 'confirm') step = 'review';
		else if (step === 'review') step = 'select';
	}

	$effect(() => {
		if (open && recordAId && recordBId) {
			loadPreview();
		}
	});
</script>

<Dialog.Root {open} onOpenChange={handleOpenChange}>
	<Dialog.Content class="sm:max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
		<Dialog.Header>
			<Dialog.Title class="flex items-center gap-2">
				<GitMerge class="h-5 w-5" />
				Merge Records
			</Dialog.Title>
			<Dialog.Description>
				{#if step === 'select'}
					Step 1: Select which record to keep as the primary
				{:else if step === 'review'}
					Step 2: Choose which values to keep for each field
				{:else}
					Step 3: Review and confirm the merge
				{/if}
			</Dialog.Description>
		</Dialog.Header>

		<div class="flex-1 overflow-y-auto py-4">
			{#if loading}
				<div class="flex items-center justify-center py-12">
					<Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
				</div>
			{:else if !preview}
				<div class="text-center py-8 text-muted-foreground">
					Failed to load preview
				</div>
			{:else if step === 'select'}
				<!-- Step 1: Select Primary Record -->
				<div class="space-y-4">
					<p class="text-sm text-muted-foreground">
						Choose which record will be kept. The other record will be merged into it and then deleted.
					</p>

					<RadioGroup.Root
						value={String(survivingRecordId)}
						onValueChange={(v) => {
							if (v) survivingRecordId = Number(v);
						}}
						class="space-y-3"
					>
						{#each [preview.record_a, preview.record_b] as record}
							<Label
								class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 hover:bg-accent [&:has([data-state=checked])]:border-primary"
							>
								<RadioGroup.Item value={String(record.id)} class="mt-1" />
								<div class="flex-1">
									<p class="font-medium">
										{getRecordDisplayName(record.data, primaryField)}
									</p>
									<p class="text-sm text-muted-foreground">ID: {record.id}</p>
									<div class="mt-2 grid grid-cols-2 gap-2 text-sm">
										{#each Object.entries(record.data).slice(0, 4) as [key, value]}
											<div class="truncate">
												<span class="text-muted-foreground">{key}:</span> {value ?? '-'}
											</div>
										{/each}
									</div>
								</div>
							</Label>
						{/each}
					</RadioGroup.Root>
				</div>
			{:else if step === 'review'}
				<!-- Step 2: Field Selection -->
				<div class="space-y-2">
					<p class="text-sm text-muted-foreground mb-4">
						{preview.differing_fields} of {preview.field_count} fields have different values.
						Choose which value to keep for each field.
					</p>

					<div class="border rounded-lg overflow-hidden">
						<table class="w-full text-sm">
							<thead class="bg-muted">
								<tr>
									<th class="text-left p-2 font-medium">Field</th>
									<th class="text-left p-2 font-medium">Record A</th>
									<th class="text-left p-2 font-medium">Record B</th>
									<th class="text-center p-2 font-medium w-24">Keep</th>
								</tr>
							</thead>
							<tbody>
								{#each Object.entries(preview.preview) as [field, info]}
									{#if info.differs}
										<tr class="border-t">
											<td class="p-2 font-medium">{info.label}</td>
											<td class="p-2 truncate max-w-[150px]" title={String(info.value_a ?? '')}>
												{#if info.value_a != null}{info.value_a}{:else}<span class="text-muted-foreground">-</span>{/if}
											</td>
											<td class="p-2 truncate max-w-[150px]" title={String(info.value_b ?? '')}>
												{#if info.value_b != null}{info.value_b}{:else}<span class="text-muted-foreground">-</span>{/if}
											</td>
											<td class="p-2 text-center">
												<RadioGroup.Root
													value={fieldSelections[field] ?? 'a'}
													onValueChange={(v) => {
														if (v) fieldSelections[field] = v;
													}}
													class="flex justify-center gap-4"
												>
													<RadioGroup.Item value="a" />
													<RadioGroup.Item value="b" />
												</RadioGroup.Root>
											</td>
										</tr>
									{/if}
								{/each}
							</tbody>
						</table>
					</div>
				</div>
			{:else}
				<!-- Step 3: Confirmation -->
				<div class="space-y-4">
					<div class="rounded-lg bg-yellow-50 dark:bg-yellow-950 border border-yellow-200 dark:border-yellow-800 p-4">
						<div class="flex items-start gap-3">
							<AlertCircle class="h-5 w-5 text-yellow-600 mt-0.5" />
							<div>
								<p class="font-medium text-yellow-800 dark:text-yellow-200">This action cannot be undone</p>
								<p class="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
									The merged record will be permanently deleted. All relationships, activities,
									and history will be transferred to the surviving record.
								</p>
							</div>
						</div>
					</div>

					<div class="rounded-lg border p-4 space-y-3">
						<div class="flex items-center gap-3">
							<div class="flex-1 p-3 rounded bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800">
								<p class="text-xs text-green-600 font-medium uppercase">Keeping</p>
								<p class="font-medium">{getRecordName(survivingRecordId)}</p>
								<p class="text-sm text-muted-foreground">ID: {survivingRecordId}</p>
							</div>
							<ArrowRight class="h-5 w-5 text-muted-foreground flex-shrink-0" />
							<div class="flex-1 p-3 rounded bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800">
								<p class="text-xs text-red-600 font-medium uppercase">Deleting</p>
								<p class="font-medium">{getRecordName(survivingRecordId === recordAId ? recordBId : recordAId)}</p>
								<p class="text-sm text-muted-foreground">ID: {survivingRecordId === recordAId ? recordBId : recordAId}</p>
							</div>
						</div>

						<div class="pt-3 border-t">
							<p class="text-sm font-medium mb-2">Field values to update:</p>
							<div class="grid grid-cols-2 gap-2 text-sm">
								{#each Object.entries(fieldSelections) as [field, selection]}
									{#if preview.preview[field]?.differs}
										<div class="flex items-center gap-2">
											<CheckCircle class="h-3 w-3 text-green-500" />
											<span class="text-muted-foreground">{field}:</span>
											<span class="truncate">
												{selection === 'a' ? preview.preview[field].value_a : preview.preview[field].value_b}
											</span>
										</div>
									{/if}
								{/each}
							</div>
						</div>
					</div>
				</div>
			{/if}
		</div>

		<Dialog.Footer class="flex items-center justify-between border-t pt-4">
			<div>
				{#if step !== 'select'}
					<Button variant="outline" onclick={goBack}>
						Back
					</Button>
				{/if}
			</div>
			<div class="flex items-center gap-2">
				<Button variant="outline" onclick={() => handleOpenChange(false)}>
					Cancel
				</Button>
				{#if step === 'select'}
					<Button onclick={goToReview}>
						Next: Review Fields
					</Button>
				{:else if step === 'review'}
					<Button onclick={goToConfirm}>
						Next: Confirm
					</Button>
				{:else}
					<Button onclick={handleMerge} disabled={merging}>
						{#if merging}
							<Loader2 class="mr-2 h-4 w-4 animate-spin" />
						{/if}
						Merge Records
					</Button>
				{/if}
			</div>
		</Dialog.Footer>
	</Dialog.Content>
</Dialog.Root>
