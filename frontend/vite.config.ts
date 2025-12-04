import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vitest/config';
import { playwright } from '@vitest/browser-playwright';
import { sveltekit } from '@sveltejs/kit/vite';

export default defineConfig({
	plugins: [tailwindcss(), sveltekit()],
	server: {
		host: '0.0.0.0',
		headers: {
			'Access-Control-Allow-Origin': '*' // this is only for the local dev server so it can allow all
		},
		allowedHosts: ['.vrtx.local', 'crm.startup.com', 'localhost'],
		proxy: {
			'/api': {
				target: 'http://localhost:8000',
				changeOrigin: true,
				// Forward the host header from the request to preserve tenant subdomain
				configure: (proxy) => {
					proxy.on('proxyReq', (proxyReq, req) => {
						// Forward the original host to the backend for tenant identification
						const originalHost = req.headers.host;
						if (originalHost) {
							// Replace the frontend port with the backend port
							const backendHost = originalHost.replace(':5173', ':8000').replace(':5174', ':8000');
							proxyReq.setHeader('Host', backendHost);
						}
					});
				}
			}
		}
	},

	test: {
		expect: { requireAssertions: true },
		projects: [
			{
				extends: './vite.config.ts',
				test: {
					name: 'client',
					browser: {
						enabled: true,
						provider: playwright(),
						instances: [{ browser: 'chromium', headless: true }]
					},
					include: ['src/**/*.svelte.{test,spec}.{js,ts}'],
					exclude: ['src/lib/server/**']
				}
			},
			{
				extends: './vite.config.ts',
				test: {
					name: 'server',
					environment: 'node',
					include: ['src/**/*.{test,spec}.{js,ts}'],
					exclude: ['src/**/*.svelte.{test,spec}.{js,ts}']
				}
			}
		]
	}
});
