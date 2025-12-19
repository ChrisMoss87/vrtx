<script lang="ts">
	import { Handle, Position } from '@xyflow/svelte';
	import { CircleStop, CheckCircle2, XCircle } from 'lucide-svelte';
	import type { EndNodeData } from '../types';

	interface Props {
		data: EndNodeData;
		selected?: boolean;
	}

	let { data, selected = false }: Props = $props();

	const endConfig = $derived(() => {
		switch (data.endType) {
			case 'success':
				return {
					icon: CheckCircle2,
					color: '#22c55e',
					bgColor: '#dcfce7',
					label: 'Success'
				};
			case 'failure':
				return {
					icon: XCircle,
					color: '#ef4444',
					bgColor: '#fee2e2',
					label: 'Failure'
				};
			default:
				return {
					icon: CircleStop,
					color: '#64748b',
					bgColor: '#f1f5f9',
					label: 'End'
				};
		}
	});

	const config = $derived(endConfig());
	const Icon = $derived(config.icon);
</script>

<div
	class="workflow-node end-node"
	class:selected
	style="--end-color: {config.color}; --end-bg: {config.bgColor}"
>
	<Handle type="target" position={Position.Top} class="handle-target" />
	<div class="end-circle">
		<Icon class="h-6 w-6" />
	</div>
	<div class="node-label">{data.label || config.label}</div>
</div>

<style>
	.workflow-node {
		display: flex;
		flex-direction: column;
		align-items: center;
		transition: all 0.2s ease;
	}

	.workflow-node.selected .end-circle {
		box-shadow: 0 0 0 3px color-mix(in srgb, var(--end-color) 30%, transparent);
	}

	.end-circle {
		width: 56px;
		height: 56px;
		border-radius: 50%;
		background: var(--end-bg);
		border: 3px solid var(--end-color);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--end-color);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}

	.node-label {
		margin-top: 8px;
		font-size: 12px;
		font-weight: 600;
		color: var(--end-color);
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	:global(.end-node .handle-target) {
		width: 12px !important;
		height: 12px !important;
		background: var(--end-color, #64748b) !important;
		border: 2px solid white !important;
		top: -6px !important;
	}
</style>
