/**
 * A utility function for handling errors in a functional way.
 *
 * Returns a Result object with either { data, error: null } on success
 * or { data: null, error } on failure.
 *
 * @example
 * const { data, error } = await tryCatch(fetchUser(id));
 * if (error) {
 *   console.error('Failed to fetch user:', error.message);
 *   return;
 * }
 * console.log('User:', data);
 *
 * @example
 * // With API calls
 * const { data: users, error } = await tryCatch(getRecords('contacts'));
 * if (error) {
 *   toast.error('Failed to load contacts');
 *   return;
 * }
 * // users is typed correctly here
 */

// Types for the result object with discriminated union
type Success<T> = {
	data: T;
	error: null;
};

type Failure<E> = {
	data: null;
	error: E;
};

type Result<T, E = Error> = Success<T> | Failure<E>;

// Main wrapper function
export async function tryCatch<T, E = Error>(promise: Promise<T>): Promise<Result<T, E>> {
	try {
		const data = await promise;
		return { data, error: null };
	} catch (error) {
		return { data: null, error: error as E };
	}
}

/**
 * Type guard to check if a result is successful.
 *
 * @example
 * const result = await tryCatch(fetchUser(id));
 * if (isSuccess(result)) {
 *   console.log(result.data); // TypeScript knows data is not null
 * }
 */
export function isSuccess<T, E>(result: Result<T, E>): result is Success<T> {
	return result.error === null;
}

/**
 * Type guard to check if a result is a failure.
 *
 * @example
 * const result = await tryCatch(fetchUser(id));
 * if (isFailure(result)) {
 *   console.error(result.error); // TypeScript knows error is not null
 * }
 */
export function isFailure<T, E>(result: Result<T, E>): result is Failure<E> {
	return result.error !== null;
}

/**
 * Unwrap a result, throwing the error if it's a failure.
 *
 * @example
 * const result = await tryCatch(fetchUser(id));
 * const user = unwrap(result); // Throws if error, returns data otherwise
 */
export function unwrap<T, E>(result: Result<T, E>): T {
	if (isFailure(result)) {
		throw result.error;
	}
	return result.data;
}

/**
 * Unwrap a result with a default value if it's a failure.
 *
 * @example
 * const result = await tryCatch(fetchConfig());
 * const config = unwrapOr(result, { theme: 'light' });
 */
export function unwrapOr<T, E>(result: Result<T, E>, defaultValue: T): T {
	if (isFailure(result)) {
		return defaultValue;
	}
	return result.data;
}

/**
 * Map over a successful result.
 *
 * @example
 * const result = await tryCatch(fetchUsers());
 * const mapped = mapResult(result, (users) => users.filter(u => u.active));
 */
export function mapResult<T, U, E>(result: Result<T, E>, fn: (data: T) => U): Result<U, E> {
	if (isFailure(result)) {
		return result;
	}
	return { data: fn(result.data), error: null };
}

// Export types for external use
export type { Result, Success, Failure };

export default tryCatch;
