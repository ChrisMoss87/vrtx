<script lang="ts">
	import { Handle, Position } from '@xyflow/svelte';
	import { GitBranch } from 'lucide-svelte';
	import type { ConditionNodeData } from '../types';

	interface Props {
		data: ConditionNodeData;
		selected?: boolean;
	}

	let { data, selected = false }: Props = $props();
</script>

<div
	class="workflow-node condition-node"
	class:selected
>
	<Handle type="target" position={Position.Top} class="handle-target" />
	<div class="node-diamond">
		<div class="diamond-inner">
			<div class="node-icon">
				<GitBranch class="h-5 w-5" />
			</div>
		</div>
	</div>
	<div class="node-label">{data.label}</div>
	{#if data.description}
		<div class="node-description">{data.description}</div>
	{/if}
	<div class="handles-container">
		<div class="branch-handle true-branch">
			<Handle
				type="source"
				position={Position.Bottom}
				id="true"
				class="handle-source handle-true"
			/>
			<span class="branch-label">Yes</span>
		</div>
		<div class="branch-handle false-branch">
			<Handle
				type="source"
				position={Position.Bottom}
				id="false"
				class="handle-source handle-false"
			/>
			<span class="branch-label">No</span>
		</div>
	</div>
</div>

<style>
	.workflow-node {
		display: flex;
		flex-direction: column;
		align-items: center;
		min-width: 160px;
		transition: all 0.2s ease;
	}

	.workflow-node.selected .node-diamond {
		box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
	}

	.node-diamond {
		width: 80px;
		height: 80px;
		transform: rotate(45deg);
		background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
	}

	.diamond-inner {
		transform: rotate(-45deg);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.node-icon {
		color: white;
	}

	.node-label {
		margin-top: 12px;
		font-size: 14px;
		font-weight: 600;
		color: #1e293b;
		text-align: center;
	}

	.node-description {
		font-size: 12px;
		color: #64748b;
		text-align: center;
		max-width: 180px;
		margin-top: 4px;
	}

	.handles-container {
		display: flex;
		justify-content: space-between;
		width: 120px;
		margin-top: 16px;
		position: relative;
	}

	.branch-handle {
		display: flex;
		flex-direction: column;
		align-items: center;
		position: relative;
	}

	.branch-label {
		font-size: 10px;
		font-weight: 600;
		margin-top: 4px;
		padding: 2px 8px;
		border-radius: 4px;
	}

	.true-branch .branch-label {
		background: #dcfce7;
		color: #166534;
	}

	.false-branch .branch-label {
		background: #fee2e2;
		color: #991b1b;
	}

	:global(.handle-target) {
		width: 12px !important;
		height: 12px !important;
		background: #f59e0b !important;
		border: 2px solid white !important;
		top: -6px !important;
	}

	:global(.handle-true) {
		position: relative !important;
		width: 10px !important;
		height: 10px !important;
		background: #22c55e !important;
		border: 2px solid white !important;
		transform: none !important;
		left: auto !important;
	}

	:global(.handle-false) {
		position: relative !important;
		width: 10px !important;
		height: 10px !important;
		background: #ef4444 !important;
		border: 2px solid white !important;
		transform: none !important;
		left: auto !important;
	}
</style>
