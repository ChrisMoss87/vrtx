import type { ConditionalVisibility, Condition } from '$lib/api/modules';

/**
 * Evaluate a single condition against form data
 */
export function evaluateCondition(condition: Condition, formData: Record<string, any>): boolean {
	const fieldValue = formData[condition.field];
	const compareValue = condition.value;

	switch (condition.operator) {
		// Equality operators
		case 'equals':
			return fieldValue == compareValue;

		case 'not_equals':
			return fieldValue != compareValue;

		// Comparison operators (numeric)
		case 'greater_than':
			return Number(fieldValue) > Number(compareValue);

		case 'less_than':
			return Number(fieldValue) < Number(compareValue);

		case 'greater_than_or_equal':
			return Number(fieldValue) >= Number(compareValue);

		case 'less_than_or_equal':
			return Number(fieldValue) <= Number(compareValue);

		// Text operators
		case 'contains':
			return String(fieldValue || '')
				.toLowerCase()
				.includes(String(compareValue).toLowerCase());

		case 'not_contains':
			return !String(fieldValue || '')
				.toLowerCase()
				.includes(String(compareValue).toLowerCase());

		case 'starts_with':
			return String(fieldValue || '')
				.toLowerCase()
				.startsWith(String(compareValue).toLowerCase());

		case 'ends_with':
			return String(fieldValue || '')
				.toLowerCase()
				.endsWith(String(compareValue).toLowerCase());

		// Range operator
		case 'between':
			if (!Array.isArray(compareValue) || compareValue.length !== 2) return false;
			const numValue = Number(fieldValue);
			return numValue >= Number(compareValue[0]) && numValue <= Number(compareValue[1]);

		// List operators
		case 'in':
			if (!Array.isArray(compareValue)) return false;
			return compareValue.includes(fieldValue);

		case 'not_in':
			if (!Array.isArray(compareValue)) return false;
			return !compareValue.includes(fieldValue);

		// Empty/null operators
		case 'is_empty':
			return fieldValue === null || fieldValue === undefined || fieldValue === '';

		case 'is_not_empty':
			return fieldValue !== null && fieldValue !== undefined && fieldValue !== '';

		// Boolean operators
		case 'is_checked':
			return fieldValue === true || fieldValue === 'true' || fieldValue === 1;

		case 'is_not_checked':
			return fieldValue !== true && fieldValue !== 'true' && fieldValue !== 1;

		default:
			console.warn(`Unknown operator: ${condition.operator}`);
			return false;
	}
}

/**
 * Evaluate all conditions in a conditional visibility configuration
 */
export function evaluateConditionalVisibility(
	config: ConditionalVisibility | null | undefined,
	formData: Record<string, any>
): boolean {
	// If no config or disabled, field is visible
	if (!config || !config.enabled) {
		return true;
	}

	// If no conditions, field is visible
	if (!config.conditions || config.conditions.length === 0) {
		return true;
	}

	// Evaluate all conditions
	const results = config.conditions.map((condition) => evaluateCondition(condition, formData));

	// Apply AND/OR logic
	if (config.operator === 'and') {
		return results.every((result) => result === true);
	} else {
		// OR logic
		return results.some((result) => result === true);
	}
}

/**
 * Get list of visible field IDs based on all field visibility rules
 */
export function getVisibleFieldIds(
	fields: Array<{
		id: number;
		settings?: { conditional_visibility?: ConditionalVisibility | null };
	}>,
	formData: Record<string, any>
): Set<number> {
	const visibleFields = new Set<number>();

	fields.forEach((field) => {
		const isVisible = evaluateConditionalVisibility(
			field.settings?.conditional_visibility,
			formData
		);

		if (isVisible) {
			visibleFields.add(field.id);
		}
	});

	return visibleFields;
}

/**
 * Get fields that a given field depends on (for reactivity)
 */
export function getFieldDependencies(
	config: ConditionalVisibility | null | undefined
): Set<string> {
	const dependencies = new Set<string>();

	if (!config || !config.enabled || !config.conditions) {
		return dependencies;
	}

	config.conditions.forEach((condition) => {
		dependencies.add(condition.field);
	});

	return dependencies;
}
