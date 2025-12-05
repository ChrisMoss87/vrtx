/**
 * Formula Calculator
 *
 * Evaluates formulas defined in the form builder at runtime.
 * Supports field references, mathematical operations, and built-in functions.
 */

export interface FormulaDefinition {
	formula: string;
	formula_type: string;
	return_type: string;
	dependencies: string[];
	recalculate_on: string[];
}

export interface FormulaContext {
	/** Current form data with field values */
	data: Record<string, any>;
	/** Field options for lookup formulas */
	fieldOptions?: Record<string, { value: string; label: string; metadata?: Record<string, any> }[]>;
}

export interface FormulaResult {
	success: boolean;
	value: any;
	error?: string;
}

/**
 * Evaluate a formula with the given context
 */
export function evaluateFormula(
	formulaDefinition: FormulaDefinition,
	context: FormulaContext
): FormulaResult {
	try {
		const { formula, formula_type, return_type } = formulaDefinition;

		if (!formula || formula.trim() === '') {
			return { success: true, value: null };
		}

		// Replace field references with actual values
		let expression = replaceFieldReferences(formula, context.data);

		// Evaluate based on formula type
		let result: any;

		switch (formula_type) {
			case 'lookup':
				result = evaluateLookup(formula, context);
				break;
			case 'date_calculation':
				result = evaluateDateFormula(expression, context.data);
				break;
			case 'text_manipulation':
				result = evaluateTextFormula(expression, context.data);
				break;
			case 'conditional':
				result = evaluateConditionalFormula(expression, context.data);
				break;
			case 'calculation':
			default:
				result = evaluateMathFormula(expression);
				break;
		}

		// Convert result to expected return type
		result = convertToReturnType(result, return_type);

		return { success: true, value: result };
	} catch (error: any) {
		return {
			success: false,
			value: null,
			error: error.message || 'Formula evaluation failed'
		};
	}
}

/**
 * Replace {field_name} references with actual values
 */
function replaceFieldReferences(formula: string, data: Record<string, any>): string {
	return formula.replace(/\{([a-z_][a-z0-9_]*)\}/gi, (match, fieldName) => {
		const value = data[fieldName];

		if (value === null || value === undefined) {
			return '0'; // Default to 0 for numeric calculations
		}

		if (typeof value === 'string') {
			// Escape quotes and wrap in quotes for string values
			return `"${value.replace(/"/g, '\\"')}"`;
		}

		if (typeof value === 'boolean') {
			return value ? 'true' : 'false';
		}

		if (value instanceof Date) {
			return `new Date("${value.toISOString()}")`;
		}

		return String(value);
	});
}

/**
 * Evaluate mathematical formulas
 */
function evaluateMathFormula(expression: string): number {
	// Replace function calls with JavaScript equivalents
	expression = expression
		// Math functions
		.replace(/\bSUM\s*\(/gi, 'sum(')
		.replace(/\bAVERAGE\s*\(/gi, 'average(')
		.replace(/\bMIN\s*\(/gi, 'Math.min(')
		.replace(/\bMAX\s*\(/gi, 'Math.max(')
		.replace(/\bROUND\s*\(/gi, 'round(')
		.replace(/\bCEILING\s*\(/gi, 'Math.ceil(')
		.replace(/\bFLOOR\s*\(/gi, 'Math.floor(')
		.replace(/\bABS\s*\(/gi, 'Math.abs(')
		.replace(/\bPOWER\s*\(/gi, 'Math.pow(')
		.replace(/\bSQRT\s*\(/gi, 'Math.sqrt(')
		// Logical
		.replace(/\bIF\s*\(/gi, 'ifFunc(')
		.replace(/\bAND\s*\(/gi, 'andFunc(')
		.replace(/\bOR\s*\(/gi, 'orFunc(')
		.replace(/\bNOT\s*\(/gi, '!')
		.replace(/\bIS_BLANK\s*\(/gi, 'isBlank(')
		.replace(/\bIS_NUMBER\s*\(/gi, 'isNumber(');

	// Define helper functions available in the formula context
	const helpers = `
		function sum(...args) {
			return args.flat().reduce((a, b) => Number(a) + Number(b), 0);
		}
		function average(...args) {
			const flat = args.flat();
			return flat.length > 0 ? sum(flat) / flat.length : 0;
		}
		function round(value, decimals = 0) {
			const factor = Math.pow(10, decimals);
			return Math.round(value * factor) / factor;
		}
		function ifFunc(condition, trueValue, falseValue) {
			return condition ? trueValue : falseValue;
		}
		function andFunc(...conditions) {
			return conditions.every(Boolean);
		}
		function orFunc(...conditions) {
			return conditions.some(Boolean);
		}
		function isBlank(value) {
			return value === null || value === undefined || value === '';
		}
		function isNumber(value) {
			return !isNaN(Number(value));
		}
	`;

	// Use Function constructor instead of eval for better security
	const func = new Function(helpers + 'return ' + expression);
	const result = func();

	return typeof result === 'number' ? result : Number(result) || 0;
}

/**
 * Evaluate lookup formulas
 * LOOKUP(field, path, default)
 */
function evaluateLookup(formula: string, context: FormulaContext): any {
	const lookupMatch = formula.match(/LOOKUP\s*\(\s*(\w+)\s*,\s*['"]([^'"]+)['"]\s*(?:,\s*(.+))?\s*\)/i);

	if (!lookupMatch) {
		// If not a LOOKUP function, try as regular expression
		return evaluateMathFormula(replaceFieldReferences(formula, context.data));
	}

	const [, fieldName, path, defaultValue] = lookupMatch;
	const fieldValue = context.data[fieldName];

	if (fieldValue === null || fieldValue === undefined) {
		return defaultValue !== undefined ? parseDefaultValue(defaultValue) : null;
	}

	// Get options for this field
	const options = context.fieldOptions?.[fieldName];
	if (!options) {
		return defaultValue !== undefined ? parseDefaultValue(defaultValue) : null;
	}

	// Find the matching option
	const option = options.find((opt) => opt.value === fieldValue);
	if (!option) {
		return defaultValue !== undefined ? parseDefaultValue(defaultValue) : null;
	}

	// Navigate the path (e.g., 'options.metadata.probability')
	const pathParts = path.split('.');
	let result: any = option;

	for (const part of pathParts) {
		if (part === 'options') continue; // Skip 'options' as we're already at the option level
		if (result && typeof result === 'object' && part in result) {
			result = result[part];
		} else {
			return defaultValue !== undefined ? parseDefaultValue(defaultValue) : null;
		}
	}

	return result;
}

/**
 * Parse a default value from formula string
 */
function parseDefaultValue(value: string): any {
	value = value.trim();

	// Number
	if (/^-?\d+\.?\d*$/.test(value)) {
		return Number(value);
	}

	// Boolean
	if (value.toLowerCase() === 'true') return true;
	if (value.toLowerCase() === 'false') return false;

	// String (remove quotes)
	if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
		return value.slice(1, -1);
	}

	return value;
}

/**
 * Evaluate date formulas
 */
function evaluateDateFormula(expression: string, data: Record<string, any>): any {
	// Replace date functions
	expression = expression
		.replace(/\bTODAY\s*\(\s*\)/gi, `new Date("${new Date().toDateString()}")`)
		.replace(/\bNOW\s*\(\s*\)/gi, `new Date()`)
		.replace(/\bDAYS_BETWEEN\s*\(/gi, 'daysBetween(')
		.replace(/\bMONTHS_BETWEEN\s*\(/gi, 'monthsBetween(')
		.replace(/\bYEARS_BETWEEN\s*\(/gi, 'yearsBetween(')
		.replace(/\bADD_DAYS\s*\(/gi, 'addDays(')
		.replace(/\bADD_MONTHS\s*\(/gi, 'addMonths(')
		.replace(/\bADD_YEARS\s*\(/gi, 'addYears(')
		.replace(/\bDATE_DIFF\s*\(/gi, 'daysBetween(')
		.replace(/\bDATE_ADD\s*\(/gi, 'addDays(');

	const helpers = `
		function parseDate(val) {
			if (val instanceof Date) return val;
			if (typeof val === 'string') return new Date(val);
			return new Date();
		}
		function daysBetween(date1, date2) {
			const d1 = parseDate(date1);
			const d2 = parseDate(date2);
			const diff = d2.getTime() - d1.getTime();
			return Math.round(diff / (1000 * 60 * 60 * 24));
		}
		function monthsBetween(date1, date2) {
			const d1 = parseDate(date1);
			const d2 = parseDate(date2);
			return (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
		}
		function yearsBetween(date1, date2) {
			const d1 = parseDate(date1);
			const d2 = parseDate(date2);
			return d2.getFullYear() - d1.getFullYear();
		}
		function addDays(date, days) {
			const d = parseDate(date);
			d.setDate(d.getDate() + Number(days));
			return d;
		}
		function addMonths(date, months) {
			const d = parseDate(date);
			d.setMonth(d.getMonth() + Number(months));
			return d;
		}
		function addYears(date, years) {
			const d = parseDate(date);
			d.setFullYear(d.getFullYear() + Number(years));
			return d;
		}
	`;

	const func = new Function(helpers + 'return ' + expression);
	return func();
}

/**
 * Evaluate text manipulation formulas
 */
function evaluateTextFormula(expression: string, data: Record<string, any>): string {
	expression = expression
		.replace(/\bCONCAT\s*\(/gi, 'concat(')
		.replace(/\bUPPER\s*\(/gi, 'upper(')
		.replace(/\bLOWER\s*\(/gi, 'lower(')
		.replace(/\bTRIM\s*\(/gi, 'trim(')
		.replace(/\bLEFT\s*\(/gi, 'left(')
		.replace(/\bRIGHT\s*\(/gi, 'right(')
		.replace(/\bSUBSTRING\s*\(/gi, 'substring(')
		.replace(/\bREPLACE\s*\(/gi, 'replace(')
		.replace(/\bLENGTH\s*\(/gi, 'length(');

	const helpers = `
		function concat(...args) {
			return args.map(a => a ?? '').join('');
		}
		function upper(text) {
			return String(text ?? '').toUpperCase();
		}
		function lower(text) {
			return String(text ?? '').toLowerCase();
		}
		function trim(text) {
			return String(text ?? '').trim();
		}
		function left(text, length) {
			return String(text ?? '').substring(0, length);
		}
		function right(text, length) {
			const str = String(text ?? '');
			return str.substring(str.length - length);
		}
		function substring(text, start, length) {
			return String(text ?? '').substring(start, start + length);
		}
		function replace(text, find, replaceWith) {
			return String(text ?? '').replace(new RegExp(find, 'g'), replaceWith);
		}
		function length(text) {
			return String(text ?? '').length;
		}
	`;

	const func = new Function(helpers + 'return ' + expression);
	const result = func();
	return String(result ?? '');
}

/**
 * Evaluate conditional formulas
 */
function evaluateConditionalFormula(expression: string, data: Record<string, any>): any {
	// Use the math formula evaluator which already handles IF/AND/OR
	return evaluateMathFormula(expression);
}

/**
 * Convert result to the expected return type
 */
function convertToReturnType(value: any, returnType: string): any {
	if (value === null || value === undefined) {
		return null;
	}

	switch (returnType) {
		case 'number':
		case 'currency':
			const num = Number(value);
			return isNaN(num) ? 0 : num;

		case 'text':
			return String(value);

		case 'boolean':
			return Boolean(value);

		case 'date':
			if (value instanceof Date) return value;
			return new Date(value);

		default:
			return value;
	}
}

/**
 * Check if a formula has all required dependencies available
 */
export function hasDependencies(
	formulaDefinition: FormulaDefinition,
	data: Record<string, any>
): boolean {
	const { dependencies } = formulaDefinition;

	for (const dep of dependencies) {
		if (!(dep in data) || data[dep] === undefined) {
			return false;
		}
	}

	return true;
}

/**
 * Get the list of fields that need this formula to recalculate
 */
export function getDependencies(formulaDefinition: FormulaDefinition): string[] {
	return formulaDefinition.dependencies || [];
}

/**
 * Create a dependency graph for multiple formula fields
 */
export function buildDependencyGraph(
	formulaFields: { api_name: string; formula: FormulaDefinition }[]
): Map<string, string[]> {
	const graph = new Map<string, string[]>();

	for (const field of formulaFields) {
		// For each field that this formula depends on, add this formula to its dependents
		for (const dep of field.formula.dependencies) {
			const dependents = graph.get(dep) || [];
			dependents.push(field.api_name);
			graph.set(dep, dependents);
		}
	}

	return graph;
}

/**
 * Get all formulas that need to be recalculated when a field changes
 * Returns them in dependency order (fields with no dependencies first)
 */
export function getRecalculationOrder(
	changedField: string,
	formulaFields: { api_name: string; formula: FormulaDefinition }[],
	dependencyGraph: Map<string, string[]>
): string[] {
	const toRecalculate = new Set<string>();
	const queue = [changedField];

	// Find all formulas affected by this change (BFS)
	while (queue.length > 0) {
		const current = queue.shift()!;
		const dependents = dependencyGraph.get(current) || [];

		for (const dependent of dependents) {
			if (!toRecalculate.has(dependent)) {
				toRecalculate.add(dependent);
				queue.push(dependent);
			}
		}
	}

	// Sort by dependency order (topological sort)
	const result: string[] = [];
	const visited = new Set<string>();

	function visit(fieldName: string) {
		if (visited.has(fieldName)) return;
		visited.add(fieldName);

		const field = formulaFields.find((f) => f.api_name === fieldName);
		if (field) {
			// Visit dependencies first
			for (const dep of field.formula.dependencies) {
				if (toRecalculate.has(dep)) {
					visit(dep);
				}
			}
			result.push(fieldName);
		}
	}

	for (const fieldName of toRecalculate) {
		visit(fieldName);
	}

	return result;
}

/**
 * Detect circular dependencies in formulas
 */
export function detectCircularDependencies(
	formulaFields: { api_name: string; formula: FormulaDefinition }[]
): string[] | null {
	const visiting = new Set<string>();
	const visited = new Set<string>();

	function dfs(fieldName: string, path: string[]): string[] | null {
		if (visiting.has(fieldName)) {
			// Found a cycle
			return [...path, fieldName];
		}

		if (visited.has(fieldName)) {
			return null;
		}

		visiting.add(fieldName);

		const field = formulaFields.find((f) => f.api_name === fieldName);
		if (field) {
			for (const dep of field.formula.dependencies) {
				const cycle = dfs(dep, [...path, fieldName]);
				if (cycle) return cycle;
			}
		}

		visiting.delete(fieldName);
		visited.add(fieldName);

		return null;
	}

	for (const field of formulaFields) {
		const cycle = dfs(field.api_name, []);
		if (cycle) return cycle;
	}

	return null;
}
