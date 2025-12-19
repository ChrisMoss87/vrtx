import type { Handle } from '@sveltejs/kit';
import { env } from '$env/dynamic/public';

/**
 * Server hooks for request handling.
 * Gates demo routes behind environment variable.
 */
export const handle: Handle = async ({ event, resolve }) => {
	const path = event.url.pathname;

	// List of demo route patterns that should be gated
	const demoRoutePatterns = [
		'/datatable-demo',
		'/wizard-demo',
		'/wizard-builder-demo',
		'/conditional-wizard-demo',
		'/step-types-demo',
		'/field-types-demo',
		'/editor-demo',
		'/draft-demo',
		'/test-form',
		'/demo/'
	];

	// Check if this is a demo route
	const isDemoRoute = demoRoutePatterns.some((pattern) => path.includes(pattern));

	// Block demo routes if not enabled
	if (isDemoRoute && env.PUBLIC_ENABLE_DEMO_ROUTES !== 'true') {
		return new Response('Not Found', {
			status: 404,
			headers: {
				'Content-Type': 'text/plain'
			}
		});
	}

	return resolve(event);
};
