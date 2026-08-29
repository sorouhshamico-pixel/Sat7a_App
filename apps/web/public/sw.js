// Shared service worker for the whole origin — one worker underpins both
// installable apps (customer at scope "/", provider at scope "/provider";
// see docs/PWA.md). Hand-written rather than a library (Serwist etc.),
// matching this project's general preference for minimal dependencies,
// since the caching rules needed here are simple and few.
//
// THE ONE RULE THAT MATTERS: never cache `/api/*` or any non-GET request.
// Every screen in this app reads session-scoped, frequently-changing data
// (order status, dispatch offers, balances...) through
// src/app/api/backend/[...path]/route.ts and the auth routes next to it —
// serving a cached copy of any of that would be actively wrong, not just
// stale. Only the static app shell (HTML navigations, JS/CSS chunks,
// fonts, the offline fallback page) is ever cached.

const CACHE_NAME = "riyadh-tow-shell-v1";
const OFFLINE_URL = "/offline";

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.add(OFFLINE_URL))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))),
      )
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Never intercept non-GET requests or anything under /api/ — always go
  // straight to the network, untouched.
  if (request.method !== "GET" || url.pathname.startsWith("/api/")) {
    return;
  }

  // HTML navigations: network-first, falling back to a cached copy of
  // that exact page, then to the offline shell page.
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(async () => (await caches.match(request)) ?? (await caches.match(OFFLINE_URL))),
    );
    return;
  }

  // Static assets (JS/CSS chunks, fonts, generated icons): stale-while-
  // revalidate — serve the cached copy immediately if there is one, and
  // refresh the cache in the background for next time.
  event.respondWith(
    caches.open(CACHE_NAME).then(async (cache) => {
      const cached = await cache.match(request);
      const network = fetch(request)
        .then((response) => {
          cache.put(request, response.clone());
          return response;
        })
        .catch(() => cached);

      return cached ?? network;
    }),
  );
});
