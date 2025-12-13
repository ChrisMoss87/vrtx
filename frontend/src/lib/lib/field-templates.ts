/**
 * Field Templates for quick field creation
 */

import type { FieldType } from '$lib/constants/fieldTypes';

export interface FieldTemplate {
	id: string;
	name: string;
	description: string;
	category: string;
	type: FieldType;
	field: {
		type: FieldType;
		label: string;
		is_required?: boolean;
		is_unique?: boolean;
		is_searchable?: boolean;
		[key: string]: unknown;
	};
	settings?: Record<string, unknown>;
	options?: Array<{ label: string; value: string }>;
}

export const templateCategories = [
	{ id: 'contact', name: 'Contact Info', value: 'contact', label: 'Contact Info' },
	{ id: 'business', name: 'Business', value: 'business', label: 'Business' },
	{ id: 'common', name: 'Common Fields', value: 'common', label: 'Common Fields' },
	{ id: 'dates', name: 'Dates & Times', value: 'dates', label: 'Dates & Times' }
];

export const fieldTemplates: FieldTemplate[] = [
	// Contact Info
	{
		id: 'first_name',
		name: 'First Name',
		description: 'Person\'s first name',
		category: 'contact',
		type: 'text',
		field: { type: 'text', label: 'First Name', is_required: true }
	},
	{
		id: 'last_name',
		name: 'Last Name',
		description: 'Person\'s last name',
		category: 'contact',
		type: 'text',
		field: { type: 'text', label: 'Last Name', is_required: true }
	},
	{
		id: 'email',
		name: 'Email Address',
		description: 'Email address with validation',
		category: 'contact',
		type: 'email',
		field: { type: 'email', label: 'Email Address', is_unique: true, is_searchable: true }
	},
	{
		id: 'phone',
		name: 'Phone Number',
		description: 'Phone number with formatting',
		category: 'contact',
		type: 'phone',
		field: { type: 'phone', label: 'Phone Number', is_searchable: true }
	},
	// Business
	{
		id: 'company_name',
		name: 'Company Name',
		description: 'Business or organization name',
		category: 'business',
		type: 'text',
		field: { type: 'text', label: 'Company Name', is_required: true, is_searchable: true }
	},
	{
		id: 'website',
		name: 'Website',
		description: 'Company website URL',
		category: 'business',
		type: 'url',
		field: { type: 'url', label: 'Website' }
	},
	{
		id: 'industry',
		name: 'Industry',
		description: 'Business industry category',
		category: 'business',
		type: 'select',
		field: { type: 'select', label: 'Industry' },
		options: [
			{ label: 'Technology', value: 'technology' },
			{ label: 'Finance', value: 'finance' },
			{ label: 'Healthcare', value: 'healthcare' },
			{ label: 'Retail', value: 'retail' },
			{ label: 'Manufacturing', value: 'manufacturing' },
			{ label: 'Other', value: 'other' }
		]
	},
	// Common
	{
		id: 'notes',
		name: 'Notes',
		description: 'General notes or comments',
		category: 'common',
		type: 'textarea',
		field: { type: 'textarea', label: 'Notes' }
	},
	{
		id: 'status',
		name: 'Status',
		description: 'Record status',
		category: 'common',
		type: 'select',
		field: { type: 'select', label: 'Status' },
		options: [
			{ label: 'Active', value: 'active' },
			{ label: 'Inactive', value: 'inactive' },
			{ label: 'Pending', value: 'pending' }
		]
	},
	// Dates
	{
		id: 'created_date',
		name: 'Created Date',
		description: 'When the record was created',
		category: 'dates',
		type: 'date',
		field: { type: 'date', label: 'Created Date' }
	},
	{
		id: 'due_date',
		name: 'Due Date',
		description: 'Deadline or due date',
		category: 'dates',
		type: 'date',
		field: { type: 'date', label: 'Due Date' }
	}
];

export function getTemplatesByCategory(categoryId: string): FieldTemplate[] {
	return fieldTemplates.filter((t) => t.category === categoryId);
}

export function getTemplateById(id: string): FieldTemplate | undefined {
	return fieldTemplates.find((t) => t.id === id);
}
