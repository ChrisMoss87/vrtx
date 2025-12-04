/**
 * Conditional Logic Evaluator for Wizards
 *
 * Evaluates conditions to determine if steps should be skipped or shown
 */

export type ConditionalOperator =
	| 'equals'
	| 'not_equals'
	| 'contains'
	| 'not_contains'
	| 'greater_than'
	| 'less_than'
	| 'greater_than_or_equal'
	| 'less_than_or_equal'
	| 'is_empty'
	| 'is_not_empty'
	| 'is_true'
	| 'is_false';

export type LogicOperator = 'AND' | 'OR';

export interface Condition {
	field: string;
	operator: ConditionalOperator;
	value?: any;
}

export interface ConditionalRule {
	logic: LogicOperator;
	conditions: Condition[];
}

/**
 * Evaluate a single condition against form data
 */
export function evaluateCondition(condition: Condition, formData: Record<string, any>): boolean {
	const fieldValue = formData[condition.field];
	const compareValue = condition.value;

	switch (condition.operator) {
		case 'equals':
			return fieldValue === compareValue;

		case 'not_equals':
			return fieldValue !== compareValue;

		case 'contains':
			if (typeof fieldValue === 'string') {
				return fieldValue.includes(String(compareValue));
			}
			if (Array.isArray(fieldValue)) {
				return fieldValue.includes(compareValue);
			}
			return false;

		case 'not_contains':
			if (typeof fieldValue === 'string') {
				return !fieldValue.includes(String(compareValue));
			}
			if (Array.isArray(fieldValue)) {
				return !fieldValue.includes(compareValue);
			}
			return true;

		case 'greater_than':
			return Number(fieldValue) > Number(compareValue);

		case 'less_than':
			return Number(fieldValue) < Number(compareValue);

		case 'greater_than_or_equal':
			return Number(fieldValue) >= Number(compareValue);

		case 'less_than_or_equal':
			return Number(fieldValue) <= Number(compareValue);

		case 'is_empty':
			return (
				fieldValue === null ||
				fieldValue === undefined ||
				fieldValue === '' ||
				(Array.isArray(fieldValue) && fieldValue.length === 0)
			);

		case 'is_not_empty':
			return !(
				fieldValue === null ||
				fieldValue === undefined ||
				fieldValue === '' ||
				(Array.isArray(fieldValue) && fieldValue.length === 0)
			);

		case 'is_true':
			return fieldValue === true || fieldValue === 'true';

		case 'is_false':
			return fieldValue === false || fieldValue === 'false';

		default:
			return false;
	}
}

/**
 * Evaluate a conditional rule (multiple conditions with AND/OR logic)
 */
export function evaluateRule(rule: ConditionalRule, formData: Record<string, any>): boolean {
	if (!rule.conditions || rule.conditions.length === 0) {
		return true;
	}

	const results = rule.conditions.map((condition) => evaluateCondition(condition, formData));

	if (rule.logic === 'AND') {
		return results.every((result) => result === true);
	} else {
		// OR
		return results.some((result) => result === true);
	}
}

/**
 * Determine if a step should be skipped based on conditional rules
 */
export function shouldSkipStep(
	stepConditions?: ConditionalRule,
	formData?: Record<string, any>
): boolean {
	if (!stepConditions || !formData) {
		return false;
	}

	return evaluateRule(stepConditions, formData);
}

/**
 * Get the next visible step index based on conditional logic
 */
export function getNextVisibleStepIndex(
	currentIndex: number,
	steps: Array<{ id: string; conditionalLogic?: ConditionalRule }>,
	formData: Record<string, any>,
	direction: 'forward' | 'backward' = 'forward'
): number {
	const increment = direction === 'forward' ? 1 : -1;
	let nextIndex = currentIndex + increment;

	while (nextIndex >= 0 && nextIndex < steps.length) {
		const step = steps[nextIndex];
		if (!shouldSkipStep(step.conditionalLogic, formData)) {
			return nextIndex;
		}
		nextIndex += increment;
	}

	// Return current index if no visible step found
	return currentIndex;
}

/**
 * Get all visible step indices
 */
export function getVisibleStepIndices(
	steps: Array<{ id: string; conditionalLogic?: ConditionalRule }>,
	formData: Record<string, any>
): number[] {
	return steps
		.map((step, index) => ({ step, index }))
		.filter(({ step }) => !shouldSkipStep(step.conditionalLogic, formData))
		.map(({ index }) => index);
}

/**
 * Calculate progress percentage based on visible steps
 */
export function calculateConditionalProgress(
	currentIndex: number,
	steps: Array<{ id: string; conditionalLogic?: ConditionalRule }>,
	formData: Record<string, any>
): number {
	const visibleIndices = getVisibleStepIndices(steps, formData);
	const currentPosition = visibleIndices.indexOf(currentIndex);

	if (currentPosition === -1 || visibleIndices.length === 0) {
		return 0;
	}

	return ((currentPosition + 1) / visibleIndices.length) * 100;
}
