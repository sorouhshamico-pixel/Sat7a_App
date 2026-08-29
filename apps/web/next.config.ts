import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async headers() {
    return [
      {
        // Never cache the service worker script itself — every deploy
        // must reach clients promptly, or they keep running stale
        // caching logic indefinitely (broad site-wide security headers
        // are Phase 23's scope, not this one — see docs/PWA.md).
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
