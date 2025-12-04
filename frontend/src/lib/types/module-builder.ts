/**
 * Module builder types and interfaces
 */

import type { FieldType } from './field-types';

export interface FieldOption {
	id: string;
	label: string;
	value: string;
	color?: string;
	isActive: boolean;
	displayOrder: number;
	metadata?: Record<string, any>;
}

export interface ConditionalVisibility {
	enabled: boolean;
	operator: 'and' | 'or';
	conditions: Array<{
		field: string;
		operator: string;
		value: any;
	}>;
}

export interface LookupSettings {
	targetModule: string;
	relationshipType: 'one_to_one' | 'many_to_one' | 'many_to_many';
	displayField: string;
	allowQuickCreate?: boolean;
	dependsOn?: string;
	dependencyFilter?: {
		field: string;
		operator: string;
		staticValue?: any;
	};
	staticFilters?: Array<{
		field: string;
		operator: string;
		value: any;
	}>;
}

export interface FormulaDefinition {
	expression: string;
	returnType: 'number' | 'currency' | 'percent' | 'text' | 'date';
	dependencies: string[];
}

export interface Field {
	id: string;
	label: string;
	apiName: string;
	type: FieldType;
	blockId?: string;
	description?: string;
	helpText?: string;
	placeholder?: string;
	isRequired: boolean;
	isUnique: boolean;
	isSearchable: boolean;
	isFilterable: boolean;
	isSortable: boolean;
	validationRules: string[];
	settings: Record<string, any>;
	conditionalVisibility?: ConditionalVisibility;
	fieldDependency?: any;
	formulaDefinition?: FormulaDefinition;
	lookupSettings?: LookupSettings;
	defaultValue?: string;
	displayOrder: number;
	width: 25 | 33 | 50 | 100;
	options: FieldOption[];
}

export type BlockType = 'section' | 'tab' | 'accordion' | 'card';

export interface Block {
	id: string;
	name: string;
	type: BlockType;
	displayOrder: number;
	settings: Record<string, any>;
	fields: Field[];
}

export interface Module {
	id?: number;
	name: string;
	singularName: string;
	apiName: string;
	icon?: string;
	description?: string;
	isActive: boolean;
	settings: Record<string, any>;
	displayOrder: number;
	blocks: Block[];
	fields: Field[];
}

/**
 * Form builder state
 */
export interface FormBuilderState {
	module: Module;
	selectedFieldId?: string;
	selectedBlockId?: string;
	isDragging: boolean;
	history: Module[];
	historyIndex: number;
}

/**
 * Default values
 */
export const DEFAULT_FIELD: Omit<Field, 'id' | 'label' | 'apiName' | 'type'> = {
	description: '',
	helpText: '',
	placeholder: '',
	isRequired: false,
	isUnique: false,
	isSearchable: true,
	isFilterable: true,
	isSortable: true,
	validationRules: [],
	settings: {},
	displayOrder: 0,
	width: 100,
	options: []
};

export const DEFAULT_BLOCK: Omit<Block, 'id' | 'name'> = {
	type: 'section',
	displayOrder: 0,
	settings: {},
	fields: []
};

export const DEFAULT_MODULE: Omit<Module, 'name' | 'singularName' | 'apiName'> = {
	icon: 'Box',
	description: '',
	isActive: true,
	settings: {},
	displayOrder: 0,
	blocks: [],
	fields: []
};
