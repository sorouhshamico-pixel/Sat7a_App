import type { Metadata } from "next";

// Transparent pass-through layout whose job is overriding everything the
// customer app's root layout would otherwise leak into the whole
// /provider subtree: the manifest link (see
// src/app/provider/manifest.webmanifest/route.ts and docs/PWA.md §Two
// installable apps, one origin), plus title/description/Open
// Graph/Twitter text — the image already differed correctly per segment
// (src/app/provider/opengraph-image.tsx), but without this the *text*
// next to it stayed the customer app's, a real inconsistency caught
// while verifying this phase (see docs/MARKETING_SEO.md). Wraps both
// provider/login and the provider/(dashboard) route group.
const PROVIDER_SITE_NAME = "لوحة مزودي الخدمة — سطحات الرياض";
const PROVIDER_DESCRIPTION = "إدارة الأسطول والسائقين والرحلات لمزودي خدمة سطحات الرياض.";

export const metadata: Metadata = {
  // `absolute`, not `default` — `default` still gets wrapped by the root
  // layout's own title.template ("%s | منصة سطحات الرياض"), producing a
  // double-branded title like "... — سطحات الرياض | منصة سطحات الرياض".
  // `absolute` is the one title-resolution field that fully bypasses
  // every ancestor template — a second real bug caught while verifying
  // this phase (see docs/MARKETING_SEO.md).
  title: { absolute: PROVIDER_SITE_NAME, template: `%s | ${PROVIDER_SITE_NAME}` },
  description: PROVIDER_DESCRIPTION,
  manifest: "/provider/manifest.webmanifest",
  openGraph: {
    title: PROVIDER_SITE_NAME,
    description: PROVIDER_DESCRIPTION,
    url: "/provider",
    siteName: PROVIDER_SITE_NAME,
    locale: "ar_SA",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: PROVIDER_SITE_NAME,
    description: PROVIDER_DESCRIPTION,
  },
};

export default function ProviderLayout({ children }: { children: React.ReactNode }) {
  return children;
}
