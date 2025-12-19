<script lang="ts">
	import { Handle, Position } from '@xyflow/svelte';
	import {
		FilePlus,
		FileEdit,
		FileX,
		FileInput,
		Calendar,
		Webhook,
		Hand,
		Zap
	} from 'lucide-svelte';
	import type { TriggerNodeData } from '../types';
	import { getTriggerInfo } from '../nodeConfig';

	interface Props {
		data: TriggerNodeData;
		selected?: boolean;
	}

	let { data, selected = false }: Props = $props();

	const triggerInfo = $derived(getTriggerInfo(data.triggerType));

	const iconMap: Record<string, typeof Zap> = {
		FilePlus,
		FileEdit,
		FileX,
		FileInput,
		Calendar,
		Webhook,
		Hand
	};

	const Icon = $derived(iconMap[triggerInfo?.icon || 'Zap'] || Zap);
</script>

<div
	class="workflow-node trigger-node"
	class:selected
>
	<div class="node-header">
		<div class="node-icon">
			<Icon class="h-4 w-4" />
		</div>
		<span class="node-type">Trigger</span>
	</div>
	<div class="node-content">
		<div class="node-title">{data.label}</div>
		{#if data.description}
			<div class="node-description">{data.description}</div>
		{:else if triggerInfo}
			<div class="node-description">{triggerInfo.description}</div>
		{/if}
		{#if data.moduleName}
			<div class="node-meta">
				<span class="meta-label">Module:</span>
				<span class="meta-value">{data.moduleName}</span>
			</div>
		{/if}
	</div>
	<Handle type="source" position={Position.Bottom} class="handle-source" />
</div>

<style>
	.workflow-node {
		background: white;
		border: 2px solid #e2e8f0;
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
		border-color: #10b981;
		box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
	}

	.trigger-node {
		border-color: #10b981;
	}

	.trigger-node .node-header {
		background: linear-gradient(135deg, #10b981 0%, #059669 100%);
	}

	.node-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		border-radius: 10px 10px 0 0;
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

	.node-meta {
		margin-top: 8px;
		padding-top: 8px;
		border-top: 1px solid #e2e8f0;
		font-size: 11px;
	}

	.meta-label {
		color: #94a3b8;
		margin-right: 4px;
	}

	.meta-value {
		color: #475569;
		font-weight: 500;
	}

	:global(.handle-source) {
		width: 12px !important;
		height: 12px !important;
		background: #10b981 !important;
		border: 2px solid white !important;
		bottom: -6px !important;
	}
</style>
