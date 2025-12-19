/**
 * Module Validation utilities
 */

export interface ValidationError {
	field: string;
	message: string;
	severity: 'error' | 'warning';
	blockIndex?: number;
	fieldIndex?: number;
	fix?: () => void;
}

export interface ValidationResult {
	isValid: boolean;
	errors: ValidationError[];
	warnings: ValidationError[];
}

export function validateModule(module: unknown): ValidationResult {
	const errors: ValidationError[] = [];
	const warnings: ValidationError[] = [];

	// Add validation logic here as needed

	return {
		isValid: errors.length === 0,
		errors,
		warnings
	};
}
