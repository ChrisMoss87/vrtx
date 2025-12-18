<script lang="ts">
	import { Button } from '$lib/components/ui/button';
	import { Input } from '$lib/components/ui/input';
	import { Label } from '$lib/components/ui/label';
	import { Textarea } from '$lib/components/ui/textarea';
	import { Switch } from '$lib/components/ui/switch';
	import * as Select from '$lib/components/ui/select';
	import * as Card from '$lib/components/ui/card';
	import { X, Trash2 } from 'lucide-svelte';
	import type { WorkflowNode, ActionNodeData, ConditionNodeData, DelayNodeData, EndNodeData } from './types';
	import type { Field } from '$lib/api/modules';

	// Import action config components
	import EmailActionConfig from '../config/EmailActionConfig.svelte';
	import CreateRecordConfig from '../config/CreateRecordConfig.svelte';
	import UpdateFieldConfig from '../config/UpdateFieldConfig.svelte';
	import WebhookConfig from '../config/WebhookConfig.svelte';
	import AssignUserConfig from '../config/AssignUserConfig.svelte';
	import NotificationConfig from '../config/NotificationConfig.svelte';
	import DelayConfig from '../config/DelayConfig.svelte';
	import TagActionConfig from '../config/TagActionConfig.svelte';
	import CreateTaskConfig from '../config/CreateTaskConfig.svelte';
	import MoveStageConfig from '../config/MoveStageConfig.svelte';
	import UpdateRelatedRecordConfig from '../config/UpdateRelatedRecordConfig.svelte';
	import ConditionBuilder from '../ConditionBuilder.svelte';

	interface Props {
		node: WorkflowNode | null;
		moduleFields?: Field[];
		onClose?: () => void;
		onUpdate?: (nodeId: string, data: Partial<WorkflowNode['data']>) => void;
		onDelete?: (nodeId: string) => void;
	}

	let { node, moduleFields = [], onClose, onUpdate, onDelete }: Props = $props();

	function updateNodeData(updates: Record<string, unknown>) {
		if (!node) return;
		onUpdate?.(node.id, { ...node.data, ...updates });
	}

	function handleDelete() {
		if (!node) return;
		onDelete?.(node.id);
	}
</script>

{#if node}
	<div class="config-panel">
		<div class="panel-header">
			<h3 class="panel-title">Configure {node.type}</h3>
			<Button variant="ghost" size="icon" onclick={onClose}>
				<X class="h-4 w-4" />
			</Button>
		</div>

		<div class="panel-content">
			<!-- Common fields -->
			<div class="config-section">
				<Label>Name</Label>
				<Input
					value={node.data.label}
					oninput={(e) => updateNodeData({ label: e.currentTarget.value })}
					placeholder="Step name"
				/>
			</div>

			{#if 'description' in node.data}
				<div class="config-section">
					<Label>Description</Label>
					<Textarea
						value={node.data.description || ''}
						oninput={(e) => updateNodeData({ description: e.currentTarget.value })}
						placeholder="Optional description"
						rows={2}
					/>
				</div>
			{/if}

			<!-- Action-specific configuration -->
			{#if node.type === 'action'}
				{@const actionData = node.data as ActionNodeData}

				<div class="config-section">
					<Label>Action Type</Label>
					<div class="action-type-badge">
						{actionData.actionType.replace(/_/g, ' ')}
					</div>
				</div>

				<!-- Action config based on type -->
				<div class="action-config">
					{#if actionData.actionType === 'send_email'}
						<EmailActionConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'create_record' || actionData.actionType === 'update_record'}
						<CreateRecordConfig
							config={actionData.actionConfig}
							actionType={actionData.actionType}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'update_field'}
						<UpdateFieldConfig
							config={actionData.actionConfig}
							actionType={actionData.actionType}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'webhook'}
						<WebhookConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'assign_user'}
						<AssignUserConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'send_notification'}
						<NotificationConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'add_tag' || actionData.actionType === 'remove_tag'}
						<TagActionConfig
							config={actionData.actionConfig}
							actionType={actionData.actionType}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
						/>
					{:else if actionData.actionType === 'create_task'}
						<CreateTaskConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else if actionData.actionType === 'move_stage'}
						<MoveStageConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
						/>
					{:else if actionData.actionType === 'update_related_record'}
						<UpdateRelatedRecordConfig
							config={actionData.actionConfig}
							onConfigChange={(config) => updateNodeData({ actionConfig: config })}
							{moduleFields}
						/>
					{:else}
						<p class="text-sm text-muted-foreground">
							Configuration for this action type is not yet available in the visual builder.
						</p>
					{/if}
				</div>

				<!-- Error handling -->
				<Card.Root class="mt-4">
					<Card.Header class="pb-2">
						<Card.Title class="text-sm">Error Handling</Card.Title>
					</Card.Header>
					<Card.Content class="space-y-4">
						<div class="flex items-center justify-between">
							<div>
								<Label>Continue on Error</Label>
								<p class="text-xs text-muted-foreground">
									Continue workflow if this step fails
								</p>
							</div>
							<Switch
								checked={actionData.continueOnError ?? false}
								onCheckedChange={(checked) => updateNodeData({ continueOnError: checked })}
							/>
						</div>

						<div class="space-y-2">
							<Label>Retry Count</Label>
							<Input
								type="number"
								min="0"
								max="5"
								value={actionData.retryCount ?? 0}
								oninput={(e) => updateNodeData({ retryCount: parseInt(e.currentTarget.value) || 0 })}
							/>
						</div>

						{#if actionData.retryCount && actionData.retryCount > 0}
							<div class="space-y-2">
								<Label>Retry Delay (seconds)</Label>
								<Input
									type="number"
									min="1"
									value={actionData.retryDelaySeconds ?? 60}
									oninput={(e) => updateNodeData({ retryDelaySeconds: parseInt(e.currentTarget.value) || 60 })}
								/>
							</div>
						{/if}
					</Card.Content>
				</Card.Root>
			{/if}

			<!-- Condition configuration -->
			{#if node.type === 'condition'}
				{@const conditionData = node.data as ConditionNodeData}
				<div class="config-section">
					<Label>Conditions</Label>
					<ConditionBuilder
						conditions={conditionData.conditions}
						{moduleFields}
						onConditionsChange={(conditions) => updateNodeData({ conditions })}
					/>
				</div>
			{/if}

			<!-- Delay configuration -->
			{#if node.type === 'delay'}
				{@const delayData = node.data as DelayNodeData}
				<div class="config-section space-y-4">
					<div class="space-y-2">
						<Label>Delay Type</Label>
						<Select.Root
							type="single"
							value={delayData.delayType}
							onValueChange={(v) => updateNodeData({ delayType: v })}
						>
							<Select.Trigger>
								{delayData.delayType?.replace(/_/g, ' ') || 'Select type'}
							</Select.Trigger>
							<Select.Content>
								<Select.Item value="seconds">Seconds</Select.Item>
								<Select.Item value="minutes">Minutes</Select.Item>
								<Select.Item value="hours">Hours</Select.Item>
								<Select.Item value="days">Days</Select.Item>
								<Select.Item value="until_time">Until specific time</Select.Item>
							</Select.Content>
						</Select.Root>
					</div>

					{#if delayData.delayType !== 'until_time'}
						<div class="space-y-2">
							<Label>Duration</Label>
							<Input
								type="number"
								min="1"
								value={delayData.delayValue}
								oninput={(e) => updateNodeData({ delayValue: parseInt(e.currentTarget.value) || 1 })}
							/>
						</div>
					{:else}
						<div class="space-y-2">
							<Label>Time</Label>
							<Input
								type="time"
								value={delayData.delayTime || ''}
								oninput={(e) => updateNodeData({ delayTime: e.currentTarget.value })}
							/>
						</div>
					{/if}
				</div>
			{/if}

			<!-- End configuration -->
			{#if node.type === 'end'}
				{@const endData = node.data as EndNodeData}
				<div class="config-section">
					<Label>End Type</Label>
					<Select.Root
						type="single"
						value={endData.endType}
						onValueChange={(v) => updateNodeData({ endType: v })}
					>
						<Select.Trigger>
							{endData.endType || 'Select type'}
						</Select.Trigger>
						<Select.Content>
							<Select.Item value="success">Success</Select.Item>
							<Select.Item value="failure">Failure</Select.Item>
							<Select.Item value="stop">Stop</Select.Item>
						</Select.Content>
					</Select.Root>
				</div>
			{/if}
		</div>

		<!-- Delete button (not for trigger) -->
		{#if node.type !== 'trigger'}
			<div class="panel-footer">
				<Button variant="destructive" class="w-full" onclick={handleDelete}>
					<Trash2 class="mr-2 h-4 w-4" />
					Delete {node.type}
				</Button>
			</div>
		{/if}
	</div>
{:else}
	<div class="config-panel empty">
		<div class="empty-state">
			<p class="text-sm text-muted-foreground">
				Select a node to configure it
			</p>
		</div>
	</div>
{/if}

<style>
	.config-panel {
		display: flex;
		flex-direction: column;
		height: 100%;
		background: white;
		border-left: 1px solid #e2e8f0;
	}

	.panel-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 16px;
		border-bottom: 1px solid #e2e8f0;
	}

	.panel-title {
		font-size: 14px;
		font-weight: 600;
		color: #1e293b;
		margin: 0;
		text-transform: capitalize;
	}

	.panel-content {
		flex: 1;
		overflow-y: auto;
		padding: 16px;
	}

	.config-section {
		margin-bottom: 16px;
	}

	.config-section :global(label) {
		display: block;
		margin-bottom: 6px;
	}

	.action-type-badge {
		display: inline-block;
		padding: 4px 12px;
		background: #f1f5f9;
		color: #475569;
		border-radius: 6px;
		font-size: 13px;
		font-weight: 500;
		text-transform: capitalize;
	}

	.action-config {
		margin-top: 16px;
		padding-top: 16px;
		border-top: 1px solid #e2e8f0;
	}

	.panel-footer {
		padding: 16px;
		border-top: 1px solid #e2e8f0;
	}

	.config-panel.empty {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.empty-state {
		text-align: center;
		padding: 32px;
	}
</style>
