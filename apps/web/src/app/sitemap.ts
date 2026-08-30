import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site";

// Deliberately just the homepage. Padding a sitemap with low-value URLs
// (login forms, an app with no content beyond its own transactional
// screens) is a real anti-pattern, not a missed opportunity — this
// platform has exactly one page a search engine should rank: the quote
// builder itself (see docs/MARKETING_SEO.md).
export default function sitemap(): MetadataRoute.Sitemap {
  return [
    {
      url: SITE_URL,
      lastModified: new Date(),
      changeFrequency: "daily",
      priority: 1,
    },
  ];
}
