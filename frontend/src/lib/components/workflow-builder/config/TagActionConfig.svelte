<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import * as Select from '$lib/components/ui/select';
	import { Badge } from '$lib/components/ui/badge';
	import { Button } from '$lib/components/ui/button';
	import { Plus, X, Info } from 'lucide-svelte';
	import type { Field } from '$lib/api/modules';
	import type { ActionType } from '$lib/api/workflows';

	interface Props {
		config: Record<string, unknown>;
		actionType: ActionType;
		moduleFields?: Field[];
		onConfigChange?: (config: Record<string, unknown>) => void;
	}

	let { config = {}, actionType, moduleFields = [], onConfigChange }: Props = $props();

	const isRemove = actionType === 'remove_tag';

	// Local state
	let tagSource = $state<string>((config.tag_source as string) || 'static');
	let staticTags = $state<string[]>((config.tags as string[]) || []);
	let tagField = $state<string>((config.tag_field as string) || '');
	let newTag = $state('');

	function emitChange() {
		onConfigChange?.({
			tag_source: tagSource,
			tags: staticTags,
			tag_field: tagField
		});
	}

	function addTag() {
		if (newTag && !staticTags.includes(newTag)) {
			staticTags = [...staticTags, newTag];
			newTag = '';
			emitChange();
		}
	}

	function removeTag(tag: string) {
		staticTags = staticTags.filter((t) => t !== tag);
		emitChange();
	}

	// Tag fields from module
	const tagFields = $derived(
		moduleFields.filter((f) => f.type === 'tag' || f.type === 'multiselect')
	);
</script>

<div class="space-y-4">
	<h4 class="font-medium">{isRemove ? 'Remove Tag' : 'Add Tag'} Configuration</h4>

	<!-- Tag Source -->
	<div class="space-y-2">
		<Label>Tag Source</Label>
		<Select.Root
			type="single"
			value={tagSource}
			onValueChange={(v) => {
				if (v) {
					tagSource = v;
					emitChange();
				}
			}}
		>
			<Select.Trigger>
				{tagSource === 'static'
					? 'Specific Tags'
					: tagSource === 'field'
						? 'From Field Value'
						: 'Select source'}
			</Select.Trigger>
			<Select.Content>
				<Select.Item value="static">Specific Tags</Select.Item>
				<Select.Item value="field">From Field Value</Select.Item>
			</Select.Content>
		</Select.Root>
	</div>

	<!-- Static Tags -->
	{#if tagSource === 'static'}
		<div class="space-y-2">
			<Label>Tags to {isRemove ? 'Remove' : 'Add'}</Label>
			{#if staticTags.length > 0}
				<div class="flex flex-wrap gap-2">
					{#each staticTags as tag}
						<Badge variant="secondary" class="gap-1">
							{tag}
							<button type="button" onclick={() => removeTag(tag)} class="hover:text-destructive">
								<X class="h-3 w-3" />
							</button>
						</Badge>
					{/each}
				</div>
			{/if}
			<div class="flex gap-2">
				<Input
					bind:value={newTag}
					placeholder="Enter tag name"
					onkeydown={(e) => e.key === 'Enter' && addTag()}
				/>
				<Button type="button" variant="outline" size="icon" onclick={addTag}>
					<Plus class="h-4 w-4" />
				</Button>
			</div>
		</div>
	{/if}

	<!-- Field Source -->
	{#if tagSource === 'field'}
		<div class="space-y-2">
			<Label>Tag Field</Label>
			<Select.Root
				type="single"
				value={tagField}
				onValueChange={(v) => {
					if (v) {
						tagField = v;
						emitChange();
					}
				}}
			>
				<Select.Trigger>
					{tagFields.find((f) => f.api_name === tagField)?.label || 'Select field'}
				</Select.Trigger>
				<Select.Content>
					{#each tagFields as field}
						<Select.Item value={field.api_name}>{field.label}</Select.Item>
					{/each}
				</Select.Content>
			</Select.Root>
			{#if tagFields.length === 0}
				<p class="text-xs text-muted-foreground">
					No tag or multiselect fields found in this module
				</p>
			{/if}
		</div>
	{/if}

	<!-- Info -->
	<div class="flex items-start gap-2 rounded-lg bg-muted/50 p-3">
		<Info class="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
		<p class="text-xs text-muted-foreground">
			{#if isRemove}
				Tags will be removed from the record. If a tag doesn't exist on the record, it will be ignored.
			{:else}
				Tags will be added to the record. Duplicate tags will be ignored.
			{/if}
		</p>
	</div>
</div>
