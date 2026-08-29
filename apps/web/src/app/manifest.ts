import type { MetadataRoute } from "next";

// Placed in src/app/ (the root segment) so it covers the whole customer
// route tree, same discovery rule Next.js uses for icon.tsx — see
// docs/PWA.md §Two installable apps, one origin. /admin has no manifest
// override of its own and inherits this one; harmless since no admin
// staff installs the desktop-oriented admin console to a home screen.
export default function manifest(): MetadataRoute.Manifest {
  return {
    id: "/",
    name: "منصة سطحات الرياض",
    short_name: "سطحات الرياض",
    description: "اطلب سطحة في الرياض خلال دقائق",
    start_url: "/",
    scope: "/",
    display: "standalone",
    orientation: "portrait",
    lang: "ar",
    dir: "rtl",
    background_color: "#ffffff",
    theme_color: "#2563eb",
    icons: [
      { src: "/pwa-icons/customer/192", sizes: "192x192", type: "image/png" },
      { src: "/pwa-icons/customer/512", sizes: "512x512", type: "image/png" },
    ],
  };
}
