<script lang="ts">
	import { Label } from '$lib/components/ui/label';
	import { Input } from '$lib/components/ui/input';
	import { Switch } from '$lib/components/ui/switch';
	import * as Collapsible from '$lib/components/ui/collapsible';
	import { ChevronDown } from 'lucide-svelte';
	import type { WorkflowStepInput } from '$lib/api/workflows';
	import type { Field } from '$lib/api/modules';

	// Action config components
	import EmailActionConfig from './config/EmailActionConfig.svelte';
	import CreateRecordConfig from './config/CreateRecordConfig.svelte';
	import UpdateFieldConfig from './config/UpdateFieldConfig.svelte';
	import WebhookConfig from './config/WebhookConfig.svelte';
	import AssignUserConfig from './config/AssignUserConfig.svelte';
	import NotificationConfig from './config/NotificationConfig.svelte';
	import DelayConfig from './config/DelayConfig.svelte';
	import CreateTaskConfig from './config/CreateTaskConfig.svelte';
	import TagActionConfig from './config/TagActionConfig.svelte';
	import MoveStageConfig from './config/MoveStageConfig.svelte';
	import ConditionBranchConfig from './config/ConditionBranchConfig.svelte';
	import UpdateRelatedRecordConfig from './config/UpdateRelatedRecordConfig.svelte';

	interface Props {
		step: WorkflowStepInput;
		moduleFields?: Field[];
		onStepChange?: (updates: Partial<WorkflowStepInput>) => void;
	}

	let { step, moduleFields = [], onStepChange }: Props = $props();

	let advancedOpen = $state(false);

	function updateConfig(config: Record<string, unknown>) {
		onStepChange?.({ action_config: config });
	}

	function updateName(name: string) {
		onStepChange?.({ name });
	}

	function updateContinueOnError(value: boolean) {
		onStepChange?.({ continue_on_error: value });
	}

	function updateRetryCount(count: number) {
		onStepChange?.({ retry_count: count });
	}

	function updateRetryDelay(seconds: number) {
		onStepChange?.({ retry_delay_seconds: seconds });
	}
</script>

<div class="space-y-4">
	<!-- Step Name -->
	<div class="space-y-2">
		<Label>Step Name</Label>
		<Input
			value={step.name || ''}
			oninput={(e) => updateName(e.currentTarget.value)}
			placeholder="Give this step a name"
		/>
	</div>

	<!-- Action-specific Configuration -->
	<div class="rounded-lg border bg-muted/30 p-4">
		{#if step.action_type === 'send_email'}
			<EmailActionConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'create_record' || step.action_type === 'update_record'}
			<CreateRecordConfig
				config={step.action_config}
				actionType={step.action_type}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'update_field' || step.action_type === 'delete_record'}
			<UpdateFieldConfig
				config={step.action_config}
				actionType={step.action_type}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'webhook'}
			<WebhookConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'assign_user'}
			<AssignUserConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'send_notification'}
			<NotificationConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'delay'}
			<DelayConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'create_task'}
			<CreateTaskConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'add_tag' || step.action_type === 'remove_tag'}
			<TagActionConfig
				config={step.action_config}
				actionType={step.action_type}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'move_stage'}
			<MoveStageConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'condition'}
			<ConditionBranchConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else if step.action_type === 'update_related_record'}
			<UpdateRelatedRecordConfig
				config={step.action_config}
				{moduleFields}
				onConfigChange={updateConfig}
			/>
		{:else}
			<div class="text-center text-sm text-muted-foreground">
				Configuration not available for this action type
			</div>
		{/if}
	</div>

	<!-- Advanced Options -->
	<Collapsible.Root bind:open={advancedOpen}>
		<Collapsible.Trigger class="flex w-full items-center justify-between rounded-lg border bg-muted/30 px-4 py-2 text-sm font-medium hover:bg-muted/50">
			Advanced Options
			<ChevronDown class="h-4 w-4 transition-transform {advancedOpen ? 'rotate-180' : ''}" />
		</Collapsible.Trigger>
		<Collapsible.Content>
			<div class="mt-2 space-y-4 rounded-lg border bg-muted/30 p-4">
				<!-- Continue on Error -->
				<div class="flex items-center justify-between">
					<div>
						<Label>Continue on Error</Label>
						<p class="text-xs text-muted-foreground">
							Continue to next step even if this one fails
						</p>
					</div>
					<Switch
						checked={step.continue_on_error || false}
						onCheckedChange={updateContinueOnError}
					/>
				</div>

				<!-- Retry Configuration -->
				<div class="grid gap-4 sm:grid-cols-2">
					<div class="space-y-2">
						<Label>Retry Count</Label>
						<Input
							type="number"
							min="0"
							max="5"
							value={String(step.retry_count || 0)}
							oninput={(e) => updateRetryCount(parseInt(e.currentTarget.value) || 0)}
						/>
						<p class="text-xs text-muted-foreground">
							Number of times to retry on failure (0-5)
						</p>
					</div>
					<div class="space-y-2">
						<Label>Retry Delay (seconds)</Label>
						<Input
							type="number"
							min="0"
							value={String(step.retry_delay_seconds || 60)}
							oninput={(e) => updateRetryDelay(parseInt(e.currentTarget.value) || 60)}
							disabled={!step.retry_count}
						/>
						<p class="text-xs text-muted-foreground">
							Seconds to wait between retries
						</p>
					</div>
				</div>
			</div>
		</Collapsible.Content>
	</Collapsible.Root>
</div>
