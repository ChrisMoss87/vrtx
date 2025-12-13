import adapterAuto from '@sveltejs/adapter-auto';
import adapterNode from '@sveltejs/adapter-node';
import adapterVercel from '@sveltejs/adapter-vercel';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

// Determine which adapter to use based on environment
function getAdapter() {
	const target = process.env.ADAPTER || 'auto';

	switch (target) {
		case 'node':
			return adapterNode({
				out: 'build',
				precompress: true,
				envPrefix: 'PUBLIC_'
			});
		case 'vercel':
			return adapterVercel({
				runtime: 'nodejs20.x',
				regions: ['iad1'], // US East
				split: false
			});
		default:
			return adapterAuto();
	}
}

/** @type {import('@sveltejs/kit').Config} */
const config = {
	preprocess: vitePreprocess(),

	kit: {
		adapter: getAdapter(),

		// Alias configuration
		alias: {
			$lib: './src/lib',
			$components: './src/lib/components'
		},

		// CSP headers for security
		csp: {
			directives: {
				'default-src': ['self'],
				'script-src': ['self', 'unsafe-inline'],
				'style-src': ['self', 'unsafe-inline'],
				'img-src': ['self', 'data:', 'https:'],
				'font-src': ['self', 'data:'],
				'connect-src': ['self', 'https://api.vrtx.io', 'wss:'],
				'frame-ancestors': ['none']
			}
		},

		// Environment variable prefix
		env: {
			publicPrefix: 'PUBLIC_'
		}
	}
};

export default config;
