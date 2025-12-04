import {
	Type,
	AlignLeft,
	Hash,
	DollarSign,
	Percent,
	Mail,
	Phone,
	Link,
	Calendar,
	Clock,
	ChevronDown,
	List,
	Circle,
	CheckSquare,
	ToggleLeft,
	FileText,
	Search,
	Calculator,
	File,
	Image,
	Hash as NumberIcon
} from 'lucide-svelte';

export type FieldType =
	| 'text'
	| 'textarea'
	| 'email'
	| 'phone'
	| 'url'
	| 'rich_text'
	| 'number'
	| 'decimal'
	| 'currency'
	| 'percent'
	| 'date'
	| 'datetime'
	| 'time'
	| 'select'
	| 'multiselect'
	| 'radio'
	| 'checkbox'
	| 'toggle'
	| 'lookup'
	| 'formula'
	| 'file'
	| 'image'
	| 'autonumber';

export type FieldCategory =
	| 'basic'
	| 'numeric'
	| 'choice'
	| 'datetime'
	| 'relationship'
	| 'calculated'
	| 'media';

export interface FieldTypeDefinition {
	type: FieldType;
	label: string;
	description: string;
	icon: any;
	category: FieldCategory;
	requiresOptions?: boolean;
	isAdvanced?: boolean;
	configHints?: string[];
}

export const FIELD_TYPES: Record<FieldType, FieldTypeDefinition> = {
	// Basic Text Fields
	text: {
		type: 'text',
		label: 'Text',
		description: 'Single line text input',
		icon: Type,
		category: 'basic',
		configHints: ['Set min/max length', 'Add pattern validation', 'Set placeholder']
	},
	textarea: {
		type: 'textarea',
		label: 'Textarea',
		description: 'Multi-line text input',
		icon: AlignLeft,
		category: 'basic',
		configHints: ['Set min/max length', 'Set rows', 'Set placeholder']
	},
	email: {
		type: 'email',
		label: 'Email',
		description: 'Email address with validation',
		icon: Mail,
		category: 'basic',
		configHints: ['Auto-validates email format']
	},
	phone: {
		type: 'phone',
		label: 'Phone',
		description: 'Phone number input',
		icon: Phone,
		category: 'basic',
		configHints: ['Add format mask', 'Set country code']
	},
	url: {
		type: 'url',
		label: 'URL',
		description: 'Website URL with validation',
		icon: Link,
		category: 'basic',
		configHints: ['Auto-validates URL format']
	},
	rich_text: {
		type: 'rich_text',
		label: 'Rich Text',
		description: 'WYSIWYG editor with formatting',
		icon: FileText,
		category: 'basic',
		isAdvanced: true,
		configHints: ['Configure toolbar', 'Set max length', 'Enable/disable features']
	},

	// Numeric Fields
	number: {
		type: 'number',
		label: 'Number',
		description: 'Integer number input',
		icon: Hash,
		category: 'numeric',
		configHints: ['Set min/max value', 'Set step increment']
	},
	decimal: {
		type: 'decimal',
		label: 'Decimal',
		description: 'Decimal number with precision',
		icon: NumberIcon,
		category: 'numeric',
		configHints: ['Set precision', 'Set min/max value']
	},
	currency: {
		type: 'currency',
		label: 'Currency',
		description: 'Money amount with symbol',
		icon: DollarSign,
		category: 'numeric',
		configHints: ['Set currency code', 'Set precision', 'Set min/max value']
	},
	percent: {
		type: 'percent',
		label: 'Percent',
		description: 'Percentage value (0-100)',
		icon: Percent,
		category: 'numeric',
		configHints: ['Auto-validates 0-100 range', 'Set precision']
	},

	// Date/Time Fields
	date: {
		type: 'date',
		label: 'Date',
		description: 'Date picker',
		icon: Calendar,
		category: 'datetime',
		configHints: ['Set min/max date', 'Set format', 'Set default to today']
	},
	datetime: {
		type: 'datetime',
		label: 'Date & Time',
		description: 'Date and time picker',
		icon: Calendar,
		category: 'datetime',
		configHints: ['Set min/max datetime', 'Set format', 'Set timezone']
	},
	time: {
		type: 'time',
		label: 'Time',
		description: 'Time picker',
		icon: Clock,
		category: 'datetime',
		configHints: ['Set format (12/24 hour)', 'Set step interval']
	},

	// Choice Fields
	select: {
		type: 'select',
		label: 'Dropdown',
		description: 'Single selection dropdown',
		icon: ChevronDown,
		category: 'choice',
		requiresOptions: true,
		configHints: ['Add options', 'Set default value', 'Allow search']
	},
	multiselect: {
		type: 'multiselect',
		label: 'Multi-Select',
		description: 'Multiple selection dropdown',
		icon: List,
		category: 'choice',
		requiresOptions: true,
		configHints: ['Add options', 'Set max selections', 'Allow search']
	},
	radio: {
		type: 'radio',
		label: 'Radio Buttons',
		description: 'Single selection with radio buttons',
		icon: Circle,
		category: 'choice',
		requiresOptions: true,
		configHints: ['Add options', 'Set layout (horizontal/vertical)']
	},
	checkbox: {
		type: 'checkbox',
		label: 'Checkbox',
		description: 'Single checkbox (true/false)',
		icon: CheckSquare,
		category: 'choice',
		configHints: ['Set default value', 'Set custom labels']
	},
	toggle: {
		type: 'toggle',
		label: 'Toggle',
		description: 'Toggle switch (on/off)',
		icon: ToggleLeft,
		category: 'choice',
		configHints: ['Set default value', 'Set custom labels']
	},

	// Relationship Fields
	lookup: {
		type: 'lookup',
		label: 'Lookup',
		description: 'Link to another module',
		icon: Search,
		category: 'relationship',
		isAdvanced: true,
		configHints: ['Select related module', 'Set display field', 'Configure cascading']
	},

	// Calculated Fields
	formula: {
		type: 'formula',
		label: 'Formula',
		description: 'Auto-calculated field',
		icon: Calculator,
		category: 'calculated',
		isAdvanced: true,
		configHints: ['Write formula', 'Set return type', 'Select dependencies']
	},
	autonumber: {
		type: 'autonumber',
		label: 'Auto Number',
		description: 'Auto-incrementing number',
		icon: Hash,
		category: 'calculated',
		configHints: ['Set prefix', 'Set starting number', 'Set padding']
	},

	// Media Fields
	file: {
		type: 'file',
		label: 'File',
		description: 'File upload',
		icon: File,
		category: 'media',
		configHints: ['Set allowed types', 'Set max size', 'Set max files']
	},
	image: {
		type: 'image',
		label: 'Image',
		description: 'Image upload with preview',
		icon: Image,
		category: 'media',
		configHints: ['Set allowed formats', 'Set max dimensions', 'Set max size']
	}
};

export const FIELD_CATEGORIES: Record<FieldCategory, { label: string; description: string }> = {
	basic: {
		label: 'Basic',
		description: 'Text and basic input fields'
	},
	numeric: {
		label: 'Numeric',
		description: 'Numbers, currency, and percentages'
	},
	choice: {
		label: 'Choice',
		description: 'Dropdowns, checkboxes, and selections'
	},
	datetime: {
		label: 'Date & Time',
		description: 'Date and time pickers'
	},
	relationship: {
		label: 'Relationship',
		description: 'Link to other modules'
	},
	calculated: {
		label: 'Calculated',
		description: 'Auto-generated values'
	},
	media: {
		label: 'Media',
		description: 'Files and images'
	}
};

/**
 * Get field types grouped by category
 */
export function getFieldTypesByCategory(): Record<FieldCategory, FieldTypeDefinition[]> {
	const grouped: Record<FieldCategory, FieldTypeDefinition[]> = {
		basic: [],
		numeric: [],
		choice: [],
		datetime: [],
		relationship: [],
		calculated: [],
		media: []
	};

	Object.values(FIELD_TYPES).forEach((fieldType) => {
		grouped[fieldType.category].push(fieldType);
	});

	return grouped;
}

/**
 * Get all field types as an array
 */
export function getAllFieldTypes(): FieldTypeDefinition[] {
	return Object.values(FIELD_TYPES);
}

/**
 * Get field type definition by type
 */
export function getFieldType(type: FieldType): FieldTypeDefinition | undefined {
	return FIELD_TYPES[type];
}

/**
 * Check if field type requires options (select, multiselect, radio)
 */
export function requiresOptions(type: FieldType): boolean {
	return FIELD_TYPES[type]?.requiresOptions ?? false;
}

/**
 * Check if field type is advanced (requires special configuration)
 */
export function isAdvancedField(type: FieldType): boolean {
	return FIELD_TYPES[type]?.isAdvanced ?? false;
}

/**
 * Get popular field types for quick access
 */
export const POPULAR_FIELD_TYPES: FieldType[] = [
	'text',
	'email',
	'number',
	'date',
	'select',
	'checkbox',
	'lookup'
];
