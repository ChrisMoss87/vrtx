/**
 * Field type definitions for form builder
 */

export type FieldCategory =
	| 'basic'
	| 'numeric'
	| 'choice'
	| 'datetime'
	| 'relationship'
	| 'calculated'
	| 'media';

export type FieldType =
	// Basic text fields
	| 'text'
	| 'textarea'
	| 'rich_text'
	| 'email'
	| 'phone'
	| 'url'
	// Numeric fields
	| 'number'
	| 'decimal'
	| 'currency'
	| 'percent'
	// Date/time fields
	| 'date'
	| 'datetime'
	| 'time'
	// Choice fields
	| 'checkbox'
	| 'toggle'
	| 'select'
	| 'multiselect'
	| 'radio'
	// Relationship fields
	| 'lookup'
	// Calculated fields
	| 'formula'
	| 'auto_number'
	// Media fields
	| 'file'
	| 'image'
	// Special fields
	| 'progress_mapper'
	| 'rating'
	| 'signature'
	| 'color';

export interface FieldTypeDefinition {
	type: FieldType;
	label: string;
	description: string;
	icon: string;
	category: FieldCategory;
	isPopular?: boolean;
	requiresOptions?: boolean;
	allowsOptions?: boolean;
	supportsValidation?: boolean;
	supportsConditionalLogic?: boolean;
	supportsDefaultValue?: boolean;
}

export const FIELD_TYPES: Record<FieldType, FieldTypeDefinition> = {
	// Basic text fields
	text: {
		type: 'text',
		label: 'Single Line Text',
		description: 'Short text input for names, titles, etc.',
		icon: 'Type',
		category: 'basic',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	textarea: {
		type: 'textarea',
		label: 'Multi-line Text',
		description: 'Large text area for descriptions and notes',
		icon: 'AlignLeft',
		category: 'basic',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	rich_text: {
		type: 'rich_text',
		label: 'Rich Text Editor',
		description: 'Formatted text with bold, italic, lists, etc.',
		icon: 'FileText',
		category: 'basic',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: false
	},
	email: {
		type: 'email',
		label: 'Email',
		description: 'Email address with validation',
		icon: 'Mail',
		category: 'basic',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	phone: {
		type: 'phone',
		label: 'Phone Number',
		description: 'Phone number with formatting',
		icon: 'Phone',
		category: 'basic',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	url: {
		type: 'url',
		label: 'URL',
		description: 'Website URL with validation',
		icon: 'Link',
		category: 'basic',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Numeric fields
	number: {
		type: 'number',
		label: 'Number',
		description: 'Numeric value (integer or decimal)',
		icon: 'Hash',
		category: 'numeric',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	currency: {
		type: 'currency',
		label: 'Currency',
		description: 'Monetary value with currency symbol',
		icon: 'DollarSign',
		category: 'numeric',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	percent: {
		type: 'percent',
		label: 'Percentage',
		description: 'Percentage value (0-100)',
		icon: 'Percent',
		category: 'numeric',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Date and time fields
	date: {
		type: 'date',
		label: 'Date',
		description: 'Date picker (MM/DD/YYYY)',
		icon: 'Calendar',
		category: 'datetime',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	datetime: {
		type: 'datetime',
		label: 'Date & Time',
		description: 'Date and time picker',
		icon: 'Clock',
		category: 'datetime',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	time: {
		type: 'time',
		label: 'Time',
		description: 'Time picker (HH:MM)',
		icon: 'Clock',
		category: 'datetime',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Choice fields
	checkbox: {
		type: 'checkbox',
		label: 'Checkbox',
		description: 'Single checkbox (Yes/No, True/False)',
		icon: 'CheckSquare',
		category: 'choice',
		isPopular: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	select: {
		type: 'select',
		label: 'Dropdown',
		description: 'Single select from list of options',
		icon: 'ChevronDown',
		category: 'choice',
		isPopular: true,
		requiresOptions: true,
		allowsOptions: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	multiselect: {
		type: 'multiselect',
		label: 'Multi-Select',
		description: 'Multiple selections from list',
		icon: 'List',
		category: 'choice',
		requiresOptions: true,
		allowsOptions: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: false
	},
	radio: {
		type: 'radio',
		label: 'Radio Buttons',
		description: 'Single selection from visible options',
		icon: 'Circle',
		category: 'choice',
		requiresOptions: true,
		allowsOptions: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Relationship fields
	lookup: {
		type: 'lookup',
		label: 'Lookup/Relationship',
		description: 'Link to another module',
		icon: 'Search',
		category: 'relationship',
		isPopular: true,
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: false
	},

	// Calculated fields
	formula: {
		type: 'formula',
		label: 'Formula',
		description: 'Auto-calculated based on other fields',
		icon: 'Calculator',
		category: 'calculated',
		supportsConditionalLogic: false,
		supportsDefaultValue: false
	},
	auto_number: {
		type: 'auto_number',
		label: 'Auto Number',
		description: 'Automatically incrementing number',
		icon: 'Hash',
		category: 'calculated',
		supportsConditionalLogic: false,
		supportsDefaultValue: false
	},

	// Media fields
	file: {
		type: 'file',
		label: 'File Upload',
		description: 'Upload any file type',
		icon: 'File',
		category: 'media',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: false
	},
	image: {
		type: 'image',
		label: 'Image Upload',
		description: 'Upload and preview images',
		icon: 'Image',
		category: 'media',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: false
	},

	// Extended numeric fields
	decimal: {
		type: 'decimal',
		label: 'Decimal',
		description: 'Decimal number with precision',
		icon: 'Hash',
		category: 'numeric',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Extended choice fields
	toggle: {
		type: 'toggle',
		label: 'Toggle Switch',
		description: 'On/Off toggle switch',
		icon: 'ToggleLeft',
		category: 'choice',
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},

	// Special fields
	progress_mapper: {
		type: 'progress_mapper',
		label: 'Progress Mapper',
		description: 'Visual progress indicator with stages',
		icon: 'BarChart2',
		category: 'calculated',
		supportsConditionalLogic: false,
		supportsDefaultValue: false
	},
	rating: {
		type: 'rating',
		label: 'Rating',
		description: 'Star or numeric rating field',
		icon: 'Star',
		category: 'choice',
		supportsValidation: true,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	},
	signature: {
		type: 'signature',
		label: 'Signature',
		description: 'Digital signature capture',
		icon: 'PenTool',
		category: 'media',
		supportsValidation: true,
		supportsConditionalLogic: false,
		supportsDefaultValue: false
	},
	color: {
		type: 'color',
		label: 'Color Picker',
		description: 'Color selection field',
		icon: 'Palette',
		category: 'basic',
		supportsValidation: false,
		supportsConditionalLogic: true,
		supportsDefaultValue: true
	}
};

/**
 * Get field types by category
 */
export function getFieldTypesByCategory(category: FieldCategory): FieldTypeDefinition[] {
	return Object.values(FIELD_TYPES).filter((ft) => ft.category === category);
}

/**
 * Get popular field types
 */
export function getPopularFieldTypes(): FieldTypeDefinition[] {
	return Object.values(FIELD_TYPES).filter((ft) => ft.isPopular);
}

/**
 * Get all categories with their field types
 */
export function getFieldTypeCategories(): Record<FieldCategory, FieldTypeDefinition[]> {
	return {
		basic: getFieldTypesByCategory('basic'),
		numeric: getFieldTypesByCategory('numeric'),
		choice: getFieldTypesByCategory('choice'),
		datetime: getFieldTypesByCategory('datetime'),
		relationship: getFieldTypesByCategory('relationship'),
		calculated: getFieldTypesByCategory('calculated'),
		media: getFieldTypesByCategory('media')
	};
}

/**
 * Category labels for display
 */
export const CATEGORY_LABELS: Record<FieldCategory, string> = {
	basic: 'Basic Fields',
	numeric: 'Numeric Fields',
	choice: 'Choice Fields',
	datetime: 'Date & Time',
	relationship: 'Relationships',
	calculated: 'Calculated',
	media: 'Media'
};

/**
 * Get field type definition
 */
export function getFieldType(type: FieldType): FieldTypeDefinition {
	return FIELD_TYPES[type];
}

/**
 * Check if field type requires options
 */
export function requiresOptions(type: FieldType): boolean {
	return FIELD_TYPES[type].requiresOptions === true;
}

/**
 * Check if field type allows options
 */
export function allowsOptions(type: FieldType): boolean {
	return FIELD_TYPES[type].allowsOptions === true || FIELD_TYPES[type].requiresOptions === true;
}
