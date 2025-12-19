import type { FormulaDefinition } from '$lib/api/modules';

/**
 * Formula calculator for evaluating field formulas
 * Supports basic math, text, date, and logical functions
 */

// Helper: Get field value from context, converting to number if needed
function getNumericValue(context: Record<string, any>, fieldName: string): number {
	const value = context[fieldName];
	if (value === null || value === undefined || value === '') {
		return 0;
	}
	const num = Number(value);
	return isNaN(num) ? 0 : num;
}

// Helper: Get string value from context
function getStringValue(context: Record<string, any>, fieldName: string): string {
	const value = context[fieldName];
	return value !== null && value !== undefined ? String(value) : '';
}

// Helper: Parse field references {field_name} from formula
function parseFieldReferences(formula: string): string[] {
	const matches = formula.match(/\{([a-z_][a-z0-9_]*)\}/gi);
	if (!matches) return [];
	return [...new Set(matches.map((m) => m.slice(1, -1)))];
}

// Math functions
function SUM(...args: any[]): number {
	return args.reduce((sum, val) => sum + Number(val || 0), 0);
}

function AVERAGE(...args: any[]): number {
	if (args.length === 0) return 0;
	return SUM(...args) / args.length;
}

function MIN(...args: any[]): number {
	const numbers = args.map((v) => Number(v || 0));
	return Math.min(...numbers);
}

function MAX(...args: any[]): number {
	const numbers = args.map((v) => Number(v || 0));
	return Math.max(...numbers);
}

function ROUND(value: any, decimals: number = 0): number {
	const num = Number(value || 0);
	const multiplier = Math.pow(10, decimals);
	return Math.round(num * multiplier) / multiplier;
}

function ABS(value: any): number {
	return Math.abs(Number(value || 0));
}

function CEILING(value: any): number {
	return Math.ceil(Number(value || 0));
}

function FLOOR(value: any): number {
	return Math.floor(Number(value || 0));
}

// Text functions
function CONCAT(...args: any[]): string {
	return args.map((v) => String(v || '')).join('');
}

function UPPER(value: any): string {
	return String(value || '').toUpperCase();
}

function LOWER(value: any): string {
	return String(value || '').toLowerCase();
}

function TRIM(value: any): string {
	return String(value || '').trim();
}

function LEFT(value: any, count: number): string {
	return String(value || '').substring(0, count);
}

function RIGHT(value: any, count: number): string {
	const str = String(value || '');
	return str.substring(str.length - count);
}

function LENGTH(value: any): number {
	return String(value || '').length;
}

// Logic functions
function IF(condition: any, trueValue: any, falseValue: any): any {
	return condition ? trueValue : falseValue;
}

function AND(...args: any[]): boolean {
	return args.every((v) => Boolean(v));
}

function OR(...args: any[]): boolean {
	return args.some((v) => Boolean(v));
}

function NOT(value: any): boolean {
	return !Boolean(value);
}

// Date functions
function NOW(): Date {
	return new Date();
}

function TODAY(): Date {
	const now = new Date();
	return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function YEAR(date: any): number {
	return new Date(date).getFullYear();
}

function MONTH(date: any): number {
	return new Date(date).getMonth() + 1; // 1-12
}

function DAY(date: any): number {
	return new Date(date).getDate();
}

function DAYS_BETWEEN(date1: any, date2: any): number {
	const d1 = new Date(date1).getTime();
	const d2 = new Date(date2).getTime();
	return Math.floor((d2 - d1) / (1000 * 60 * 60 * 24));
}

function DATE_ADD(date: any, days: number): Date {
	const d = new Date(date);
	d.setDate(d.getDate() + days);
	return d;
}

// Formula evaluation registry
const FUNCTIONS: Record<string, Function> = {
	// Math
	SUM,
	AVERAGE,
	MIN,
	MAX,
	ROUND,
	ABS,
	CEILING,
	FLOOR,
	MULTIPLY: (...args: any[]) => args.reduce((prod, val) => prod * Number(val || 0), 1),
	DIVIDE: (a: any, b: any) => Number(a || 0) / Number(b || 1),
	POWER: (base: any, exp: any) => Math.pow(Number(base || 0), Number(exp || 1)),
	SQRT: (value: any) => Math.sqrt(Number(value || 0)),

	// Text
	CONCAT,
	UPPER,
	LOWER,
	TRIM,
	LEFT,
	RIGHT,
	LENGTH,

	// Logic
	IF,
	AND,
	OR,
	NOT,

	// Date
	NOW,
	TODAY,
	YEAR,
	MONTH,
	DAY,
	DAYS_BETWEEN,
	DATE_ADD
};

/**
 * Evaluate a formula with the given context
 */
export function evaluateFormula(
	formula: FormulaDefinition | null | undefined,
	context: Record<string, any>
): any {
	if (!formula || !formula.formula) {
		return null;
	}

	try {
		let expression = formula.formula;

		// Replace field references with their values
		const fieldRefs = parseFieldReferences(expression);
		fieldRefs.forEach((fieldName) => {
			const value = context[fieldName];
			// Quote strings, keep numbers as-is
			const replacement =
				typeof value === 'string' ? `"${value}"` : String(value !== undefined ? value : 0);
			expression = expression.replace(new RegExp(`\\{${fieldName}\\}`, 'g'), replacement);
		});

		// Simple function evaluation (not a full parser, handles basic cases)
		// For production, consider using a proper expression parser library
		expression = evaluateFunctions(expression);

		// Evaluate simple arithmetic
		// WARNING: Using eval is not recommended for production
		// Consider using a safe expression evaluator library like expr-eval
		const result = new Function('return ' + expression)();

		// Format result based on return type
		if (formula.return_type === 'number' || formula.return_type === 'currency') {
			return Number(result);
		} else if (formula.return_type === 'text') {
			return String(result);
		} else if (formula.return_type === 'date') {
			return new Date(result);
		} else if (formula.return_type === 'boolean') {
			return Boolean(result);
		}

		return result;
	} catch (error) {
		console.error('Formula evaluation error:', error, formula.formula);
		return null;
	}
}

/**
 * Replace function calls with their evaluated results
 */
function evaluateFunctions(expression: string): string {
	let result = expression;

	// Match function calls like SUM(1, 2, 3)
	const functionRegex = /([A-Z_]+)\(([^)]*)\)/g;
	let match;

	while ((match = functionRegex.exec(expression)) !== null) {
		const funcName = match[1];
		const argsString = match[2];

		if (FUNCTIONS[funcName]) {
			// Parse arguments (simple comma split, doesn't handle nested functions)
			const args = argsString.split(',').map((arg) => {
				const trimmed = arg.trim();
				// If it's a number, parse it
				if (/^-?\d+\.?\d*$/.test(trimmed)) {
					return Number(trimmed);
				}
				// If it's a quoted string, remove quotes
				if (trimmed.startsWith('"') && trimmed.endsWith('"')) {
					return trimmed.slice(1, -1);
				}
				return trimmed;
			});

			try {
				const funcResult = FUNCTIONS[funcName](...args);
				result = result.replace(match[0], String(funcResult));
			} catch (error) {
				console.error(`Error evaluating ${funcName}:`, error);
			}
		}
	}

	return result;
}

/**
 * Get list of fields that a formula depends on
 */
export function getFormulaDependencies(formula: FormulaDefinition | null | undefined): string[] {
	if (!formula || !formula.formula) {
		return [];
	}
	return parseFieldReferences(formula.formula);
}

/**
 * Detect circular dependencies in formulas
 */
export function detectCircularDependencies(formulas: Record<string, FormulaDefinition>): string[] {
	const visited = new Set<string>();
	const recursionStack = new Set<string>();
	const circular: string[] = [];

	function visit(fieldName: string): boolean {
		if (recursionStack.has(fieldName)) {
			circular.push(fieldName);
			return true;
		}

		if (visited.has(fieldName)) {
			return false;
		}

		visited.add(fieldName);
		recursionStack.add(fieldName);

		const formula = formulas[fieldName];
		if (formula) {
			const deps = getFormulaDependencies(formula);
			for (const dep of deps) {
				if (visit(dep)) {
					return true;
				}
			}
		}

		recursionStack.delete(fieldName);
		return false;
	}

	Object.keys(formulas).forEach((fieldName) => visit(fieldName));

	return circular;
}
