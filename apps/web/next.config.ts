import type { NextConfig } from "next";

const isProduction = process.env.NODE_ENV === "production";

// script-src needs 'unsafe-inline' — verified empirically, not assumed:
// a strict `script-src 'self'` (no 'unsafe-inline', no nonce) genuinely
// breaks this app. Next.js's App Router streams React Server Component
// payloads into the page via small inline <script> tags (visible as
// `self.__next_f.push(...)`) as part of normal hydration, not something
// this app's own code controls — removing 'unsafe-inline' produced a
// real client-side crash ("Invariant: Expected a request ID to be
// defined for the document via self.__next_r"), confirmed live via
// Playwright console-error capture before writing this config, not
// inferred from documentation. The strict alternative (a per-request
// nonce generated in src/proxy.ts) requires forcing every page in the
// app to dynamic rendering — a real architectural cost across roughly
// 40 routes, several of which are deliberately static today — judged
// out of scope for this hardening pass; see docs/SECURITY.md §CSP.
// This is the same trade-off Next.js's own "Without Nonces" CSP guide
// example makes by default (node_modules/next/dist/docs/01-app/
// 02-guides/content-security-policy.md), not a shortcut invented here.
//
// Every other directive stays strict: no external script/object/frame
// sources, no framing of this app by anyone, forms and fetches confined
// to this origin.
const cspDirectives = [
  "default-src 'self'",
  // 'unsafe-eval' only in dev — React itself uses eval() there to
  // reconstruct cross-environment call stacks for its debugging
  // overlay (confirmed live: removing it produces a console warning on
  // every dev-mode page load, matching Next's own documented guidance —
  // "React will never use eval() in production mode"). Production never
  // gets it.
  isProduction
    ? "script-src 'self' 'unsafe-inline'"
    : "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data:",
  "font-src 'self'",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  // Turbopack's dev-mode HMR client connects over a WebSocket to the
  // same host — 'self' alone doesn't cover the ws: scheme.
  isProduction ? "connect-src 'self'" : "connect-src 'self' ws:",
  "worker-src 'self'",
];

const nextConfig: NextConfig = {
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "Content-Security-Policy", value: cspDirectives.join("; ") },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Permissions-Policy", value: "geolocation=(self), camera=(), microphone=()" },
          // geolocation=(self) — the driver "My Trips" location-sharing
          // toggle (docs/PROVIDER_WEB_APP.md) needs it for this origin;
          // matches the backend's own SecurityHeaders middleware policy
          // (apps/backend/app/Http/Middleware/SecurityHeaders.php).
          ...(isProduction
            ? [{ key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" }]
            : []),
        ],
      },
      {
        // Never cache the service worker script itself — every deploy
        // must reach clients promptly, or they keep running stale
        // caching logic indefinitely.
        source: "/sw.js",
        headers: [
          { key: "Content-Type", value: "application/javascript; charset=utf-8" },
          { key: "Cache-Control", value: "no-cache, no-store, must-revalidate" },
        ],
      },
    ];
  },
};

export default nextConfig;
