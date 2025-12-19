import {
	Type,
	AlignLeft,
	Hash,
	Mail,
	Phone,
	Link,
	ChevronDown,
	List,
	Circle,
	CheckSquare,
	ToggleLeft,
	Calendar,
	Clock,
	DollarSign,
	Percent,
	Database,
	Calculator,
	FileText,
	Image as ImageIcon,
	FileCode,
	TrendingUp,
	Star,
	Pen,
	Palette,
	ListOrdered
} from 'lucide-svelte';
import type { ComponentType } from 'svelte';

export type FieldType =
	| 'text'
	| 'textarea'
	| 'number'
	| 'decimal'
	| 'email'
	| 'phone'
	| 'url'
	| 'select'
	| 'multiselect'
	| 'radio'
	| 'checkbox'
	| 'toggle'
	| 'date'
	| 'datetime'
	| 'time'
	| 'currency'
	| 'percent'
	| 'lookup'
	| 'formula'
	| 'file'
	| 'image'
	| 'rich_text'
	| 'progress_mapper'
	| 'rating'
	| 'signature'
	| 'color'
	| 'auto_number';

export type FieldCategory =
	| 'text'
	| 'number'
	| 'choice'
	| 'date'
	| 'relationship'
	| 'calculated'
	| 'media';

export interface FieldTypeMetadata {
	value: FieldType;
	label: string;
	description: string;
	icon: ComponentType;
	category: FieldCategory;
	requiresOptions: boolean;
	isNumeric: boolean;
	isRelationship: boolean;
	isCalculated: boolean;
	isAdvanced?: boolean;
	defaultWidth: number;
	commonSettings: string[];
}

export const FIELD_TYPES: Record<FieldType, FieldTypeMetadata> = {
	text: {
		value: 'text',
		label: 'Text',
		description: 'Single line text input',
		icon: Type,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minLength', 'maxLength', 'pattern', 'placeholder']
	},
	textarea: {
		value: 'textarea',
		label: 'Text Area',
		description: 'Multi-line text input',
		icon: AlignLeft,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['minLength', 'maxLength', 'placeholder', 'rows']
	},
	number: {
		value: 'number',
		label: 'Number',
		description: 'Whole number input',
		icon: Hash,
		category: 'number',
		requiresOptions: false,
		isNumeric: true,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minValue', 'maxValue', 'step']
	},
	decimal: {
		value: 'decimal',
		label: 'Decimal',
		description: 'Decimal number input',
		icon: Hash,
		category: 'number',
		requiresOptions: false,
		isNumeric: true,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minValue', 'maxValue', 'precision']
	},
	email: {
		value: 'email',
		label: 'Email',
		description: 'Email address input',
		icon: Mail,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['placeholder']
	},
	phone: {
		value: 'phone',
		label: 'Phone',
		description: 'Phone number input',
		icon: Phone,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['placeholder', 'format']
	},
	url: {
		value: 'url',
		label: 'URL',
		description: 'Website URL input',
		icon: Link,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['placeholder']
	},
	select: {
		value: 'select',
		label: 'Select',
		description: 'Single choice dropdown',
		icon: ChevronDown,
		category: 'choice',
		requiresOptions: true,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['options', 'allowCustom']
	},
	multiselect: {
		value: 'multiselect',
		label: 'Multi Select',
		description: 'Multiple choice dropdown',
		icon: List,
		category: 'choice',
		requiresOptions: true,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['options', 'maxSelections']
	},
	radio: {
		value: 'radio',
		label: 'Radio',
		description: 'Radio button group',
		icon: Circle,
		category: 'choice',
		requiresOptions: true,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['options', 'layout']
	},
	checkbox: {
		value: 'checkbox',
		label: 'Checkbox',
		description: 'Single checkbox',
		icon: CheckSquare,
		category: 'choice',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['defaultValue']
	},
	toggle: {
		value: 'toggle',
		label: 'Toggle',
		description: 'On/off switch',
		icon: ToggleLeft,
		category: 'choice',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['defaultValue', 'labels']
	},
	date: {
		value: 'date',
		label: 'Date',
		description: 'Date picker',
		icon: Calendar,
		category: 'date',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minDate', 'maxDate', 'format']
	},
	datetime: {
		value: 'datetime',
		label: 'Date Time',
		description: 'Date and time picker',
		icon: Calendar,
		category: 'date',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minDate', 'maxDate', 'format', 'timezone']
	},
	time: {
		value: 'time',
		label: 'Time',
		description: 'Time picker',
		icon: Clock,
		category: 'date',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['format', 'step']
	},
	currency: {
		value: 'currency',
		label: 'Currency',
		description: 'Money amount input',
		icon: DollarSign,
		category: 'number',
		requiresOptions: false,
		isNumeric: true,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minValue', 'maxValue', 'currencyCode', 'precision']
	},
	percent: {
		value: 'percent',
		label: 'Percent',
		description: 'Percentage input',
		icon: Percent,
		category: 'number',
		requiresOptions: false,
		isNumeric: true,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['minValue', 'maxValue', 'showSlider']
	},
	lookup: {
		value: 'lookup',
		label: 'Lookup',
		description: 'Relationship to another module',
		icon: Database,
		category: 'relationship',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: true,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['relatedModule', 'displayField', 'searchFields', 'allowCreate', 'dependsOn']
	},
	formula: {
		value: 'formula',
		label: 'Formula',
		description: 'Calculated field',
		icon: Calculator,
		category: 'calculated',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: true,
		defaultWidth: 50,
		commonSettings: ['formula', 'returnType', 'dependencies']
	},
	file: {
		value: 'file',
		label: 'File',
		description: 'File upload',
		icon: FileText,
		category: 'media',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['allowedFileTypes', 'maxFileSize', 'multiple']
	},
	image: {
		value: 'image',
		label: 'Image',
		description: 'Image upload',
		icon: ImageIcon,
		category: 'media',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['allowedFormats', 'maxFileSize', 'dimensions']
	},
	rich_text: {
		value: 'rich_text',
		label: 'Rich Text',
		description: 'Rich text editor',
		icon: FileCode,
		category: 'text',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['maxLength', 'toolbar', 'minHeight']
	},
	progress_mapper: {
		value: 'progress_mapper',
		label: 'Progress Mapper',
		description: 'Status-to-percentage visual mapping (e.g., sales pipeline)',
		icon: TrendingUp,
		category: 'choice',
		requiresOptions: true,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['stages', 'displayStyle', 'showPercentage', 'showLabel']
	},
	rating: {
		value: 'rating',
		label: 'Rating',
		description: 'Star or numeric rating input',
		icon: Star,
		category: 'number',
		requiresOptions: false,
		isNumeric: true,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['maxRating', 'allowHalf', 'ratingIcon']
	},
	signature: {
		value: 'signature',
		label: 'Signature',
		description: 'Digital signature capture',
		icon: Pen,
		category: 'media',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 100,
		commonSettings: ['penColor', 'backgroundColor']
	},
	color: {
		value: 'color',
		label: 'Color',
		description: 'Color picker',
		icon: Palette,
		category: 'choice',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: false,
		defaultWidth: 50,
		commonSettings: ['defaultColor', 'showAlpha', 'presetColors']
	},
	auto_number: {
		value: 'auto_number',
		label: 'Auto Number',
		description: 'Auto-incrementing identifier',
		icon: ListOrdered,
		category: 'calculated',
		requiresOptions: false,
		isNumeric: false,
		isRelationship: false,
		isCalculated: true,
		defaultWidth: 50,
		commonSettings: ['prefix', 'suffix', 'startNumber', 'padLength']
	}
};

export const FIELD_CATEGORIES: Record<
	FieldCategory,
	{ label: string; description: string; order: number }
> = {
	text: {
		label: 'Text',
		description: 'Text and string fields',
		order: 1
	},
	number: {
		label: 'Number',
		description: 'Numeric fields',
		order: 2
	},
	choice: {
		label: 'Choice',
		description: 'Selection fields',
		order: 3
	},
	date: {
		label: 'Date & Time',
		description: 'Date and time fields',
		order: 4
	},
	relationship: {
		label: 'Relationship',
		description: 'Links to other modules',
		order: 5
	},
	calculated: {
		label: 'Calculated',
		description: 'Formula and computed fields',
		order: 6
	},
	media: {
		label: 'Media',
		description: 'Files and images',
		order: 7
	}
};

export function getFieldTypesByCategory(): Record<FieldCategory, FieldTypeMetadata[]>;
export function getFieldTypesByCategory(category: FieldCategory): FieldTypeMetadata[];
export function getFieldTypesByCategory(category?: FieldCategory): FieldTypeMetadata[] | Record<FieldCategory, FieldTypeMetadata[]> {
	if (category) {
		return Object.values(FIELD_TYPES).filter((ft) => ft.category === category);
	}

	// Return all field types grouped by category
	const grouped: Record<FieldCategory, FieldTypeMetadata[]> = {
		text: [],
		number: [],
		choice: [],
		date: [],
		relationship: [],
		calculated: [],
		media: []
	};

	Object.values(FIELD_TYPES).forEach((ft) => {
		grouped[ft.category].push(ft);
	});

	return grouped;
}

export function getFieldTypeMetadata(type: FieldType): FieldTypeMetadata {
	return FIELD_TYPES[type];
}

// Alias for getFieldTypeMetadata
export function getFieldType(type: FieldType): FieldTypeMetadata | undefined {
	return FIELD_TYPES[type];
}

export function searchFieldTypes(query: string): FieldTypeMetadata[] {
	const lowerQuery = query.toLowerCase();
	return Object.values(FIELD_TYPES).filter(
		(ft) =>
			ft.label.toLowerCase().includes(lowerQuery) ||
			ft.description.toLowerCase().includes(lowerQuery) ||
			ft.value.toLowerCase().includes(lowerQuery)
	);
}

/**
 * Popular field types for quick access
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
