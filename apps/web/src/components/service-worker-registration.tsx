"use client";

import { useEffect } from "react";

// Registered once from the root layout so it covers every sub-app on this
// origin (customer at "/", provider at "/provider", admin at "/admin") —
// see docs/PWA.md. A single shared worker at scope "/" is simpler than
// standing up three separate workers, and the two installable manifests
// (customer, provider) still give each app its own distinct home-screen
// identity regardless of sharing one worker underneath.
export function ServiceWorkerRegistration() {
  useEffect(() => {
    if (!("serviceWorker" in navigator)) return;

    navigator.serviceWorker.register("/sw.js", { scope: "/" }).catch(() => {
      // Registration failing (unsupported browser, blocked by an
      // extension, etc.) should never break the app itself — the site
      // works identically without an active service worker, just without
      // offline caching.
    });
  }, []);

  return null;
}
