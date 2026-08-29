import { NextResponse } from "next/server";
import type { MetadataRoute } from "next";

// A hand-written Route Handler, not the special manifest.ts file
// convention — Next.js only auto-discovers manifest.ts at the app ROOT,
// unlike icon.tsx/apple-icon.tsx which are picked up per segment (see
// docs/PWA.md §Two installable apps, one origin). src/app/provider/
// layout.tsx points its metadata.manifest at this URL explicitly, which
// overrides the inherited root manifest link for the whole /provider
// subtree.
function manifest(): MetadataRoute.Manifest {
  return {
    id: "/provider",
    name: "لوحة مزودي الخدمة — سطحات الرياض",
    short_name: "مزودو الخدمة",
    description: "إدارة الأسطول والسائقين والرحلات لمزودي خدمة سطحات الرياض",
    start_url: "/provider",
    scope: "/provider",
    display: "standalone",
    orientation: "portrait",
    lang: "ar",
    dir: "rtl",
    background_color: "#ffffff",
    theme_color: "#059669",
    icons: [
      { src: "/pwa-icons/provider/192", sizes: "192x192", type: "image/png" },
      { src: "/pwa-icons/provider/512", sizes: "512x512", type: "image/png" },
    ],
  };
}

export async function GET() {
  return NextResponse.json(manifest(), {
    headers: { "Content-Type": "application/manifest+json" },
  });
}
