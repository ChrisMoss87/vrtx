import { browser } from '$app/environment';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Use any for Echo instance to avoid broadcaster type issues between pusher/reverb
type EchoInstance = Echo<'reverb'> | Echo<'pusher'>;

// Extend window type for Echo
declare global {
	interface Window {
		Pusher: typeof Pusher;
		Echo: EchoInstance;
	}
}

let echoInstance: EchoInstance | null = null;

export function initializeEcho(authToken: string): EchoInstance | null {
	if (!browser) return null;

	// Already initialized
	if (echoInstance) return echoInstance;

	// Make Pusher available globally (required by Echo)
	window.Pusher = Pusher;

	// Get Reverb configuration from environment
	const reverbHost = import.meta.env.VITE_REVERB_HOST || 'localhost';
	const reverbPort = import.meta.env.VITE_REVERB_PORT || '8080';
	const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
	const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || '';

	echoInstance = new Echo({
		broadcaster: 'reverb',
		key: reverbKey,
		wsHost: reverbHost,
		wsPort: parseInt(reverbPort, 10),
		wssPort: parseInt(reverbPort, 10),
		forceTLS: reverbScheme === 'https',
		enabledTransports: ['ws', 'wss'],
		authEndpoint: '/api/v1/broadcasting/auth',
		auth: {
			headers: {
				Authorization: `Bearer ${authToken}`
			}
		}
	});

	window.Echo = echoInstance;

	console.log('[Echo] Initialized with Reverb');
	return echoInstance;
}

export function getEcho(): EchoInstance | null {
	if (!browser) return null;
	return echoInstance || window.Echo || null;
}

export function disconnectEcho(): void {
	if (!browser) return;

	if (echoInstance) {
		echoInstance.disconnect();
		echoInstance = null;
	}

	if (window.Echo) {
		window.Echo.disconnect();
	}

	console.log('[Echo] Disconnected');
}

export { Echo, Pusher };
