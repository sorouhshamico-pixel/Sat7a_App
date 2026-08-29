import type { Metadata } from "next";

// Transparent pass-through layout whose only job is overriding the
// inherited (customer) manifest link for the whole /provider subtree —
// see src/app/provider/manifest.webmanifest/route.ts and docs/PWA.md
// §Two installable apps, one origin. Wraps both provider/login and the
// provider/(dashboard) route group.
export const metadata: Metadata = {
  manifest: "/provider/manifest.webmanifest",
};

export default function ProviderLayout({ children }: { children: React.ReactNode }) {
  return children;
}
