import type { ActionType, TriggerType } from '$lib/api/workflows';
import type { PaletteItem, WorkflowNodeType } from './types';

// Trigger configurations
export const triggerTypes: Array<{
	value: TriggerType;
	label: string;
	description: string;
	icon: string;
}> = [
	{
		value: 'record_created',
		label: 'Record Created',
		description: 'When a new record is created',
		icon: 'FilePlus'
	},
	{
		value: 'record_updated',
		label: 'Record Updated',
		description: 'When a record is modified',
		icon: 'FileEdit'
	},
	{
		value: 'record_deleted',
		label: 'Record Deleted',
		description: 'When a record is removed',
		icon: 'FileX'
	},
	{
		value: 'field_changed',
		label: 'Field Changed',
		description: 'When specific fields change',
		icon: 'FileInput'
	},
	{
		value: 'time_based',
		label: 'Scheduled',
		description: 'Run on a schedule',
		icon: 'Calendar'
	},
	{
		value: 'webhook',
		label: 'Webhook',
		description: 'Triggered by external webhook',
		icon: 'Webhook'
	},
	{
		value: 'manual',
		label: 'Manual',
		description: 'Triggered manually by user',
		icon: 'Hand'
	}
];

// Action configurations
export const actionTypes: Array<{
	value: ActionType;
	label: string;
	description: string;
	icon: string;
	category: string;
	color: string;
}> = [
	// Communication
	{
		value: 'send_email',
		label: 'Send Email',
		description: 'Send an email notification',
		icon: 'Mail',
		category: 'Communication',
		color: '#3b82f6'
	},
	{
		value: 'send_notification',
		label: 'Send Notification',
		description: 'Send an in-app notification',
		icon: 'Bell',
		category: 'Communication',
		color: '#8b5cf6'
	},
	// Record Operations
	{
		value: 'create_record',
		label: 'Create Record',
		description: 'Create a new record',
		icon: 'FilePlus',
		category: 'Records',
		color: '#22c55e'
	},
	{
		value: 'update_record',
		label: 'Update Record',
		description: 'Update current record',
		icon: 'FileEdit',
		category: 'Records',
		color: '#eab308'
	},
	{
		value: 'update_field',
		label: 'Update Field',
		description: 'Update a specific field',
		icon: 'PenLine',
		category: 'Records',
		color: '#f97316'
	},
	{
		value: 'delete_record',
		label: 'Delete Record',
		description: 'Delete a record',
		icon: 'Trash2',
		category: 'Records',
		color: '#ef4444'
	},
	{
		value: 'update_related_record',
		label: 'Update Related',
		description: 'Update related records',
		icon: 'Link',
		category: 'Records',
		color: '#06b6d4'
	},
	// Assignment
	{
		value: 'assign_user',
		label: 'Assign User',
		description: 'Assign to a user',
		icon: 'UserPlus',
		category: 'Assignment',
		color: '#a855f7'
	},
	// Tasks
	{
		value: 'create_task',
		label: 'Create Task',
		description: 'Create a follow-up task',
		icon: 'CheckSquare',
		category: 'Tasks',
		color: '#14b8a6'
	},
	// Pipeline
	{
		value: 'move_stage',
		label: 'Move Stage',
		description: 'Move to pipeline stage',
		icon: 'ArrowRight',
		category: 'Pipeline',
		color: '#f43f5e'
	},
	// Tags
	{
		value: 'add_tag',
		label: 'Add Tag',
		description: 'Add a tag to record',
		icon: 'Tag',
		category: 'Tags',
		color: '#84cc16'
	},
	{
		value: 'remove_tag',
		label: 'Remove Tag',
		description: 'Remove tag from record',
		icon: 'TagOff',
		category: 'Tags',
		color: '#78716c'
	},
	// Integration
	{
		value: 'webhook',
		label: 'Call Webhook',
		description: 'Send data to external URL',
		icon: 'Webhook',
		category: 'Integration',
		color: '#0ea5e9'
	}
];

// Flow control items
export const flowControlItems: Array<{
	type: WorkflowNodeType;
	label: string;
	description: string;
	icon: string;
	color: string;
}> = [
	{
		type: 'condition',
		label: 'Condition',
		description: 'Branch based on conditions',
		icon: 'GitBranch',
		color: '#f59e0b'
	},
	{
		type: 'delay',
		label: 'Delay',
		description: 'Wait before continuing',
		icon: 'Clock',
		color: '#6366f1'
	},
	{
		type: 'end',
		label: 'End',
		description: 'End the workflow',
		icon: 'CircleStop',
		color: '#64748b'
	}
];

// Build palette items from all types
export function buildPaletteItems(): PaletteItem[] {
	const items: PaletteItem[] = [];

	// Add action items
	for (const action of actionTypes) {
		items.push({
			type: 'action',
			actionType: action.value,
			label: action.label,
			description: action.description,
			icon: action.icon,
			category: action.category,
			color: action.color
		});
	}

	// Add flow control items
	for (const flow of flowControlItems) {
		items.push({
			type: flow.type,
			label: flow.label,
			description: flow.description,
			icon: flow.icon,
			category: 'Flow Control',
			color: flow.color
		});
	}

	return items;
}

// Group palette items by category
export function groupPaletteItems(items: PaletteItem[]): Record<string, PaletteItem[]> {
	const grouped: Record<string, PaletteItem[]> = {};

	for (const item of items) {
		if (!grouped[item.category]) {
			grouped[item.category] = [];
		}
		grouped[item.category].push(item);
	}

	return grouped;
}

// Get action info by type
export function getActionInfo(actionType: ActionType) {
	return actionTypes.find((a) => a.value === actionType);
}

// Get trigger info by type
export function getTriggerInfo(triggerType: TriggerType) {
	return triggerTypes.find((t) => t.value === triggerType);
}

// Node colors by type
export const nodeColors: Record<WorkflowNodeType, string> = {
	trigger: '#10b981',
	action: '#3b82f6',
	condition: '#f59e0b',
	delay: '#6366f1',
	end: '#64748b'
};
