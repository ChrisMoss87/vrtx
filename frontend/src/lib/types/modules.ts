// Import and re-export FieldType from the canonical source
import type {
	FieldType as BaseFieldType,
	FieldCategory,
	FieldTypeDefinition
} from '$lib/types/field-types';

// Re-export API types for consistency
export type {
	FieldSettings as ApiFieldSettings,
	FieldOption as ApiFieldOption,
	ConditionalVisibility as ApiConditionalVisibility
} from '$lib/api/modules';

// Re-export for consumers
export type { FieldCategory, FieldTypeDefinition };
export type FieldType = BaseFieldType;

export type BlockType = 'section' | 'tab' | 'repeating';

export interface FieldOptionMetadata {
	icon?: string;
	description?: string;
	[key: string]: string | number | boolean | undefined;
}

export interface FieldOption {
	id?: number;
	label: string;
	value: string;
	color?: string | null;
	display_order: number;
	is_active: boolean;
	metadata?: FieldOptionMetadata;
}

export interface ValidationRules {
	rules: string[];
}

export interface ConditionalVisibility {
	enabled: boolean;
	operator: 'and' | 'or';
	conditions: Condition[];
}

export type ConditionValue = string | number | boolean | string[] | number[] | null;

export interface Condition {
	field: string;
	operator: ConditionOperator;
	value?: ConditionValue;
	field_value?: string;
}

export type ConditionOperator =
	| 'equals'
	| 'not_equals'
	| 'contains'
	| 'not_contains'
	| 'starts_with'
	| 'ends_with'
	| 'greater_than'
	| 'less_than'
	| 'greater_than_or_equal'
	| 'less_than_or_equal'
	| 'between'
	| 'in'
	| 'not_in'
	| 'is_empty'
	| 'is_not_empty'
	| 'is_checked'
	| 'is_not_checked';

export interface FieldSettings {
	// Common settings
	placeholder?: string;
	conditional_visibility?: ConditionalVisibility;

	// Text/Textarea
	min_length?: number;
	max_length?: number;
	rows?: number; // Textarea rows

	// Number/Decimal/Currency
	min_value?: number;
	max_value?: number;
	precision?: number;
	currency_code?: string;
	currency_symbol?: string;
	currency?: string; // Alias for currency_code

	// Date/DateTime
	min_date?: string;
	max_date?: string;
	format?: string;

	// Lookup
	related_module_id?: number;
	related_module_name?: string;
	display_field?: string;
	search_fields?: string[];
	allow_create?: boolean;
	allow_multiple?: boolean;
	depends_on?: string;
	dependency_filter?: {
		field: string;
		operator: string;
		target_field: string;
	};

	// Formula
	formula?: string;
	formula_expression?: string; // Formula expression string
	formula_type?:
		| 'calculation'
		| 'lookup'
		| 'date_calculation'
		| 'text_manipulation'
		| 'conditional';
	return_type?: string;
	dependencies?: string[];
	recalculate_on?: string[];

	// File/Image
	allowed_file_types?: string[];
	max_file_size?: number;
	max_files?: number;

	// Progress Mapper
	progress_mapping?: {
		stages: ProgressStage[];
		show_percentage?: boolean;
		show_label?: boolean;
		display_style?: 'bar' | 'steps' | 'funnel';
		allow_backward?: boolean;
		completed_color?: string;
	};

	// Rating
	max_rating?: number;
	allow_half?: boolean;
	rating_icon?: 'star' | 'heart' | 'circle';

	// Auto Number
	prefix?: string;
	suffix?: string;
	start_number?: number;
	pad_length?: number;

	// Additional settings (type-safe but extensible)
	additional_settings?: Record<string, string | number | boolean | null>;
}

export interface ProgressStage {
	value: string;
	label: string;
	percentage: number;
	color?: string;
}

export interface Field {
	id?: number;
	module_id?: number;
	block_id?: number;
	label: string;
	api_name: string;
	type: FieldType;
	description?: string;
	help_text?: string;
	is_required: boolean;
	is_unique: boolean;
	is_searchable: boolean;
	is_filterable: boolean;
	is_sortable: boolean;
	default_value?: string;
	display_order: number;
	width: number; // Percentage: 25, 33, 50, 100
	validation_rules: ValidationRules;
	settings: FieldSettings;
	options?: FieldOption[];
	created_at?: string;
	updated_at?: string;
}

export interface Block {
	id?: number;
	module_id?: number;
	name: string;
	type: BlockType;
	display_order: number;
	settings: {
		collapsible?: boolean;
		default_collapsed?: boolean;
		columns?: number;
		conditional_visibility?: ConditionalVisibility;
	};
	fields?: Field[];
	created_at?: string;
	updated_at?: string;
}

export interface ModuleSettings {
	has_import?: boolean;
	has_export?: boolean;
	has_mass_actions?: boolean;
	has_comments?: boolean;
	has_attachments?: boolean;
	has_activity_log?: boolean;
	has_custom_views?: boolean;
	record_name_field?: string;
	additional_settings?: Record<string, string | number | boolean | null>;
}

export interface Module {
	id?: number;
	name: string;
	singular_name: string;
	api_name: string;
	icon?: string;
	description?: string;
	is_active: boolean;
	settings: ModuleSettings;
	display_order: number;
	blocks: Block[];
	fields: Field[];
	created_at?: string;
	updated_at?: string;
	deleted_at?: string;
}

/**
 * Record field value types - supports all CRM field types
 */
export type RecordFieldValue =
	| string
	| number
	| boolean
	| null
	| string[]
	| number[]
	| Record<string, unknown>
	| { id: number; name?: string }; // For lookup fields

/**
 * Generic record data with type-safe field access
 */
export type RecordData = Record<string, RecordFieldValue>;

export interface ModuleRecord {
	id: number;
	module_id: number;
	data: RecordData;
	created_by?: number;
	updated_by?: number;
	created_at: string;
	updated_at?: string;
	deleted_at?: string;
}

export interface PaginatedRecords {
	records: ModuleRecord[];
	meta: {
		total: number;
		per_page: number;
		current_page: number;
		last_page: number;
	};
}

export type FilterOperator =
	| 'eq'
	| 'neq'
	| 'gt'
	| 'gte'
	| 'lt'
	| 'lte'
	| 'contains'
	| 'starts'
	| 'ends'
	| 'in'
	| 'not_in'
	| 'null'
	| 'not_null'
	| 'between';

export type FilterValue = RecordFieldValue | [RecordFieldValue, RecordFieldValue]; // between takes two values

export interface FilterConfig {
	field: string;
	operator: FilterOperator;
	value: FilterValue;
}

export interface SortConfig {
	field: string;
	direction: 'asc' | 'desc';
}

// API Request/Response types
export interface CreateModuleRequest {
	name: string;
	singular_name: string;
	icon?: string;
	description?: string;
	settings?: ModuleSettings;
	display_order?: number;
	blocks?: Omit<Block, 'id' | 'module_id' | 'created_at' | 'updated_at'>[];
	fields?: Omit<Field, 'id' | 'module_id' | 'created_at' | 'updated_at'>[];
}

export interface UpdateModuleRequest {
	name?: string;
	singular_name?: string;
	icon?: string;
	description?: string;
	settings?: ModuleSettings;
	display_order?: number;
	is_active?: boolean;
}

export interface CreateRecordRequest {
	data: RecordData;
}

export interface UpdateRecordRequest {
	data: Partial<RecordData>;
}

export interface ApiResponse<T> {
	success: boolean;
	message?: string;
	data?: T;
	error?: string;
}
