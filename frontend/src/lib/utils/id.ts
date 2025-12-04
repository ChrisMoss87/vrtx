/**
 * Generate a unique ID
 * Falls back to timestamp + random if crypto.randomUUID is not available
 */
export function generateId(): string {
	if (typeof crypto !== 'undefined' && crypto.randomUUID) {
		return crypto.randomUUID();
	}
	// Fallback for environments without crypto.randomUUID
	return `${Date.now()}-${Math.random().toString(36).substring(2, 11)}`;
}
