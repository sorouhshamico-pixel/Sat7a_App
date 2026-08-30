import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site";

// Root-only, same as favicon.ico and manifest.ts — robots.txt is a single
// well-known site-wide URI, not something a route segment can override
// (see docs/MARKETING_SEO.md). Every screen behind a login (orders,
// vehicles, the whole admin and provider apps) is already gated by
// src/proxy.ts, so this isn't a security boundary — it just keeps
// transactional/private pages out of search results and search-engine
// crawl budget. Both login pages stay crawlable: a search for the
// platform's name should surface a real way in, not a dead end.
export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: ["/", "/login", "/provider/login", "/admin/login"],
      disallow: ["/orders", "/vehicles", "/admin", "/provider", "/api"],
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
