import type { RequestHandler } from './$types';

const site = 'https://vrtx.io';

const pages = [
	{ url: '/', changefreq: 'weekly', priority: 1.0 },
	{ url: '/pricing', changefreq: 'monthly', priority: 0.9 },
	{ url: '/about', changefreq: 'monthly', priority: 0.7 },
	{ url: '/contact', changefreq: 'monthly', priority: 0.7 },
	{ url: '/blog', changefreq: 'weekly', priority: 0.8 },
	{ url: '/privacy', changefreq: 'yearly', priority: 0.3 },
	{ url: '/terms', changefreq: 'yearly', priority: 0.3 },
	{ url: '/security', changefreq: 'yearly', priority: 0.4 },
	{ url: '/docs', changefreq: 'weekly', priority: 0.8 },
	{ url: '/integrations', changefreq: 'monthly', priority: 0.6 },
	{ url: '/changelog', changefreq: 'weekly', priority: 0.5 }
];

export const GET: RequestHandler = async () => {
	const lastmod = new Date().toISOString().split('T')[0];

	const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${pages
	.map(
		(page) => `  <url>
    <loc>${site}${page.url}</loc>
    <lastmod>${lastmod}</lastmod>
    <changefreq>${page.changefreq}</changefreq>
    <priority>${page.priority}</priority>
  </url>`
	)
	.join('\n')}
</urlset>`;

	return new Response(sitemap, {
		headers: {
			'Content-Type': 'application/xml',
			'Cache-Control': 'max-age=3600'
		}
	});
};
