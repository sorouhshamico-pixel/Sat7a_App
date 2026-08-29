# PWA (Installability & Offline Shell)

## Status

Phase 21 — implemented (`apps/web`).

## Scope

Makes the customer and provider web apps installable Progressive Web Apps: a web app manifest
each, generated app icons, a shared service worker providing an offline fallback for the app
shell, and an install prompt. This phase is installability/offline infrastructure only — it does
not touch Web Push notification *delivery* (see Not yet in this phase). The admin console is
desktop-oriented and deliberately not made installable.

## Two installable apps, one origin

Customer (`/`) and provider (`/provider`) are two separately installable apps sharing one
Next.js project, origin, and (per docs/CUSTOMER_WEB_APP.md and docs/PROVIDER_WEB_APP.md) session
architecture. Each gets its own manifest, icons, name, and theme color:

| | Customer | Provider |
|---|---|---|
| `start_url` / `scope` | `/` | `/provider` |
| Name | منصة سطحات الرياض | لوحة مزودي الخدمة — سطحات الرياض |
| Theme color | `#2563eb` (blue) | `#059669` (emerald) |

Next.js's `manifest.ts`/`icon.tsx`/`apple-icon.tsx` file conventions are both discovered
**per route segment** — but only `icon.tsx`/`apple-icon.tsx` actually work that way in practice;
`manifest.ts` is root-only despite initially appearing to follow the same rule (see Real bug
below). The customer manifest is the standard `src/app/manifest.ts` at the app root. The provider
manifest is a hand-written Route Handler at `src/app/provider/manifest.webmanifest/route.ts`,
pointed to by a small pass-through `src/app/provider/layout.tsx` that sets
`metadata.manifest = "/provider/manifest.webmanifest"` — this plain metadata field *does*
correctly override the inherited root manifest link for the whole `/provider` subtree.
`icon.tsx`/`apple-icon.tsx` needed no such workaround; they're placed directly at
`src/app/icon.tsx`/`apple-icon.tsx` (customer) and `src/app/provider/icon.tsx`/`apple-icon.tsx`
(provider) and Next resolves each correctly per segment.

`/admin` has no manifest/icon override of its own, so it inherits the customer app's — harmless,
since no admin staff installs the desktop console to a home screen.

## Icon design

Icons are generated at request time via `next/og`'s `ImageResponse` (`src/app/pwa-icons/
{customer,provider}/[size]/route.tsx`, parametrized by size so 192×192 and 512×512 share one
file per app) rather than static image assets — no external image-generation dependency, and the
icon never drifts out of sync with the brand color used elsewhere. They're a plain bold Latin
initial ("R" for the customer app, "P" for provider) on a solid brand-color background, not
Arabic text or an emoji: Satori (the engine behind `ImageResponse`) needs an explicit font
supplied via its `fonts` option to render Arabic glyphs, and the default `twemoji` emoji set is
fetched from a CDN at render time — both would add a build-time network dependency this project
avoids elsewhere (see the "no real vendor" pattern in every other adapter). A plain Latin glyph
sidesteps both.

## Service worker

One shared worker (`public/sw.js`, hand-written rather than a library like Serwist — the caching
rules needed here are few and simple) covers both apps' scope, registered from the root layout
(`src/components/service-worker-registration.tsx`). **The one rule that matters**: it never
caches `/api/*` or any non-GET request — every screen in both apps reads session-scoped,
frequently-changing data through the generic backend proxy
(`src/app/api/backend/[...path]/route.ts`) and the auth routes next to it; serving a cached copy
of any of that would be actively wrong, not just stale.

- HTML navigations: network-first, falling back to a cached copy of that exact page, then to a
  precached `/offline` fallback page (`src/app/offline/page.tsx`) if neither exists.
- Static assets (JS/CSS chunks, fonts, generated icons): stale-while-revalidate.
- `/api/*` and non-GET requests: never intercepted, always straight to the network.

`next.config.ts` sets `Cache-Control: no-cache, no-store, must-revalidate` on `/sw.js` itself, so
a new deploy reaches every client's worker promptly rather than being cached indefinitely.

## Install prompt

`src/components/install-prompt.tsx`, shown on the customer homepage and the provider dashboard
home. Chrome/Android fires `beforeinstallprompt`, which the component captures and re-triggers
from its own button; iOS Safari never fires that event (no native prompt exists there), so iOS
visitors instead see text instructions for the manual Share-sheet "Add to Home Screen" flow. Both
branches are hidden once `display-mode: standalone` indicates the app is already installed, and
either state can be dismissed for the session.

## Verification

`localhost` is treated as a secure context by browsers specifically for service worker purposes,
even over plain HTTP — this dev box never needed `next dev --experimental-https` to test any of
this. Verified via Playwright (`e2e/pwa.spec.ts`): both manifests fetch with the correct distinct
`start_url`/`name`; the provider manifest and icons are reachable *without* authentication while
a real protected provider page still correctly redirects to login; the service worker actually
registers and reaches `active` state on the real page; and — the strongest check — setting the
browser context fully offline and navigating to a nonexistent URL genuinely renders the offline
fallback page, not a mock.

## Real bug found and fixed

The `/provider/:path*` auth gate in `src/proxy.ts` (built in Phase 20, before this phase existed)
was blocking `/provider/manifest.webmanifest`, `/provider/icon`, and `/provider/apple-icon` —
redirecting all three to `/provider/login` with a 307. A browser fetches every one of these
*before* a visitor is ever authenticated, straight from the `<link>` tags Next.js injects into
`/provider/login`'s own `<head>` — so this silently broke installability and even the browser-tab
favicon for any signed-out visitor, while every *authenticated* page continued working fine
(masking the bug during casual manual testing). Caught by curling `/provider/manifest.webmanifest`
directly and getting back an HTML login-page redirect instead of JSON. Fixed by exempting an
explicit allowlist of these three public metadata paths at the top of `proxy()`, before the
`/provider/*` gate runs — regression-tested in `e2e/pwa.spec.ts` (all three now return 200
unauthenticated, and a real protected route like `/provider/fleet` still correctly redirects).

## Not yet in this phase

- No Web Push notification **delivery** — that needs VAPID key generation, a push-subscription
  database table, and backend `web-push` library wiring, which is a distinct, heavier feature
  from installability. Phase 16 already scaffolded a `PushProvider` contract with a fake/log
  adapter (`docs/NOTIFICATIONS.md`) matching this project's "no real vendor yet" pattern used
  everywhere else; wiring an actual Web Push channel on top of that contract is a natural future
  addition, not part of this phase.
- No precaching of specific hashed `_next/static/*` build chunks by name at install time — the
  service worker caches them opportunistically (stale-while-revalidate) as they're requested
  during normal use, rather than eagerly precaching an app-shell asset manifest.
- No background sync / periodic background sync.
- No `next dev --experimental-https` setup for testing a *real* device install prompt end to
  end — verified instead via the mechanics (manifest content, icon bytes, service worker
  registration/activation, and genuine offline-navigation fallback), which `localhost`'s
  secure-context exception makes fully testable without HTTPS. `docs/DEPLOYMENT.md`'s production
  setup is already HTTPS, so a real "Add to Home Screen" prompt is expected to work correctly
  once deployed.
- No `themeColor` `<meta>` tag distinct per segment — only the manifest's own `theme_color` field
  is set (which is what an *installed* PWA's chrome/splash-screen coloring actually reads); a
  browser tab's theme-color meta tag would need per-segment `Metadata` exports to differ the same
  way the manifest link does, and wasn't judged worth the extra surface for this phase.
