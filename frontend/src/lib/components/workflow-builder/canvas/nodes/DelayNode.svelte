<script lang="ts">
	import { Handle, Position } from '@xyflow/svelte';
	import { Clock } from 'lucide-svelte';
	import type { DelayNodeData } from '../types';

	interface Props {
		data: DelayNodeData;
		selected?: boolean;
	}

	let { data, selected = false }: Props = $props();

	const delayText = $derived(() => {
		if (!data.delayValue) return 'Configure delay';

		const unit = data.delayType || 'minutes';
		const value = data.delayValue;

		switch (unit) {
			case 'seconds':
				return `${value} second${value !== 1 ? 's' : ''}`;
			case 'minutes':
				return `${value} minute${value !== 1 ? 's' : ''}`;
			case 'hours':
				return `${value} hour${value !== 1 ? 's' : ''}`;
			case 'days':
				return `${value} day${value !== 1 ? 's' : ''}`;
			case 'until_time':
				return `Until ${data.delayTime || 'specified time'}`;
			case 'until_date':
				return `Until specific date`;
			default:
				return `${value} ${unit}`;
		}
	});
</script>

<div
	class="workflow-node delay-node"
	class:selected
>
	<Handle type="target" position={Position.Top} class="handle-target" />
	<div class="node-header">
		<div class="node-icon">
			<Clock class="h-4 w-4" />
		</div>
		<span class="node-type">Delay</span>
	</div>
	<div class="node-content">
		<div class="node-title">{data.label}</div>
		<div class="delay-value">
			<Clock class="h-3 w-3" />
			<span>{delayText()}</span>
		</div>
	</div>
	<Handle type="source" position={Position.Bottom} class="handle-source" />
</div>

<style>
	.workflow-node {
		background: white;
		border: 2px solid #6366f1;
		border-radius: 12px;
		min-width: 180px;
		max-width: 240px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		transition: all 0.2s ease;
	}

	.workflow-node:hover {
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
	}

	.workflow-node.selected {
		box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3);
	}

	.node-header {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		border-radius: 10px 10px 0 0;
		background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
		margin-bottom: 8px;
	}

	.delay-value {
		display: flex;
		align-items: center;
		gap: 6px;
		font-size: 13px;
		color: #6366f1;
		font-weight: 500;
		padding: 6px 10px;
		background: #eef2ff;
		border-radius: 6px;
	}

	:global(.delay-node .handle-target) {
		width: 12px !important;
		height: 12px !important;
		background: #6366f1 !important;
		border: 2px solid white !important;
		top: -6px !important;
	}

	:global(.delay-node .handle-source) {
		width: 12px !important;
		height: 12px !important;
		background: #6366f1 !important;
		border: 2px solid white !important;
		bottom: -6px !important;
	}
</style>
