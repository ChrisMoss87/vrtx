<script lang="ts">
	import { Handle, Position } from '@xyflow/svelte';
	import {
		Mail,
		Bell,
		FilePlus,
		FileEdit,
		PenLine,
		Trash2,
		Link,
		UserPlus,
		CheckSquare,
		ArrowRight,
		Tag,
		Webhook,
		Cog
	} from 'lucide-svelte';
	import type { ActionNodeData } from '../types';
	import { getActionInfo } from '../nodeConfig';

	interface Props {
		data: ActionNodeData;
		selected?: boolean;
	}

	let { data, selected = false }: Props = $props();

	const actionInfo = $derived(getActionInfo(data.actionType));

	const iconMap: Record<string, typeof Cog> = {
		Mail,
		Bell,
		FilePlus,
		FileEdit,
		PenLine,
		Trash2,
		Link,
		UserPlus,
		CheckSquare,
		ArrowRight,
		Tag,
		TagOff: Tag,
		Webhook
	};

	const Icon = $derived(iconMap[actionInfo?.icon || 'Cog'] || Cog);
	const nodeColor = $derived(actionInfo?.color || '#3b82f6');
</script>

<div
	class="workflow-node action-node"
	class:selected
	style="--node-color: {nodeColor}"
>
	<Handle type="target" position={Position.Top} class="handle-target" />
	<div class="node-header">
		<div class="node-icon">
			<Icon class="h-4 w-4" />
		</div>
		<span class="node-type">Action</span>
	</div>
	<div class="node-content">
		<div class="node-title">{data.label}</div>
		{#if data.description}
			<div class="node-description">{data.description}</div>
		{:else if actionInfo}
			<div class="node-description">{actionInfo.description}</div>
		{/if}
		{#if data.continueOnError || data.retryCount}
			<div class="node-badges">
				{#if data.continueOnError}
					<span class="badge">Continue on error</span>
				{/if}
				{#if data.retryCount && data.retryCount > 0}
					<span class="badge">Retry: {data.retryCount}x</span>
				{/if}
			</div>
		{/if}
	</div>
	<Handle type="source" position={Position.Bottom} class="handle-source" />
</div>

<style>
	.workflow-node {
		background: white;
		border: 2px solid var(--node-color, #3b82f6);
		border-radius: 12px;
		min-width: 200px;
		max-width: 280px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		transition: all 0.2s ease;
	}

	.workflow-node:hover {
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	}

	.workflow-node.selected {
		box-shadow: 0 0 0 2px color-mix(in srgb, var(--node-color) 30%, transparent);
	}

	.node-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		border-radius: 10px 10px 0 0;
		background: var(--node-color);
		color: white;
	}

	.node-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		background: rgba(255, 255, 255, 0.2);
		border-radius: 6px;
	}

	.node-type {
		font-size: 11px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		opacity: 0.9;
	}

	.node-content {
		padding: 12px;
	}

	.node-title {
		font-size: 14px;
		font-weight: 600;
		color: #1e293b;
		margin-bottom: 4px;
	}

	.node-description {
		font-size: 12px;
		color: #64748b;
		line-height: 1.4;
	}

	.node-badges {
		display: flex;
		flex-wrap: wrap;
		gap: 4px;
		margin-top: 8px;
	}

	.badge {
		font-size: 10px;
		padding: 2px 6px;
		background: #f1f5f9;
		color: #64748b;
		border-radius: 4px;
	}

	:global(.handle-target) {
		width: 12px !important;
		height: 12px !important;
		background: var(--node-color, #3b82f6) !important;
		border: 2px solid white !important;
		top: -6px !important;
	}

	:global(.handle-source) {
		width: 12px !important;
		height: 12px !important;
		background: var(--node-color, #3b82f6) !important;
		border: 2px solid white !important;
		bottom: -6px !important;
	}
</style>
