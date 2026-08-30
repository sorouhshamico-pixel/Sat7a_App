// The public origin this app is deployed at — used to build absolute URLs
// for metadata (Open Graph images, sitemap entries, robots.txt's sitemap
// pointer) that only make sense as full URLs, never relative ones, once
// they leave this app (a crawler or a social-media unfurl bot fetches
// them directly). Defaults to the local dev server so nothing breaks
// before a real domain is configured — see docs/MARKETING_SEO.md.
export const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";
