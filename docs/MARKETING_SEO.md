# Marketing & SEO

## Status

Phase 22 — implemented (`apps/web`).

## Scope

Makes the one genuinely public, marketing-relevant page — the customer homepage — discoverable
and shareable, and keeps every transactional/staff-only screen out of search results and crawl
budget. This platform has no separate content site, blog, or landing pages beyond the app itself
(`docs/PRODUCT_REQUIREMENTS.md` describes a transactional marketplace, not a content property),
so this phase is metadata infrastructure, not new marketing pages.

## Only one page is worth ranking

`/orders`, `/vehicles`, and both login forms are transactional or auth-gated — a search result
landing a stranger on someone else's order list, or on a login form with no way to act on it, is
not a good outcome, and every one of those screens is already access-controlled by `src/proxy.ts`
regardless of what a crawler does. The only page a search engine should actually rank is the
customer homepage (`/`) — the quote builder is the entire value proposition, reachable without an
account (`docs/PRODUCT_REQUIREMENTS.md` §Core customer journey step 1-6, before auth is
required). Both `src/app/robots.ts` and `src/app/sitemap.ts` reflect that directly: `sitemap.ts`
lists exactly one URL (padding a sitemap with low-value transactional URLs is a real SEO
anti-pattern, not a missed opportunity), and `robots.ts` disallows `/orders`, `/vehicles`,
`/admin`, and `/provider` while still allowing both login pages to be crawled — a search for the
platform's name should surface a real way in, not a dead end. None of this is a security
boundary; the proxy auth gate already is one. It only keeps private/transactional pages out of
search results and off crawl budget.

## Two installable apps, two distinct social identities

Phase 21 gave the customer and provider apps their own manifest, icons, and theme color. This
phase extends the same split to Open Graph/Twitter Card metadata and page titles:

| | Customer | Provider |
|---|---|---|
| Title | منصة سطحات الرياض | لوحة مزودي الخدمة — سطحات الرياض |
| OG/Twitter image | `src/app/opengraph-image.tsx` (blue) | `src/app/provider/opengraph-image.tsx` (emerald) |

`opengraph-image.tsx` turned out to be discovered per route segment exactly like
`icon.tsx`/`apple-icon.tsx` (see `docs/PWA.md` for the contrast with `manifest.ts`, which is
root-only) — placing one directly under `src/app/provider/` was enough, no workaround needed.

## Two real bugs found and fixed while verifying this phase

1. **The provider app's own metadata routes were unreachable while signed out.** The exact same
   class of bug `docs/PWA.md` already documents for `manifest.webmanifest`/`icon`/`apple-icon`:
   `src/proxy.ts`'s `/provider/:path*` auth gate was also redirecting
   `/provider/opengraph-image` to the login page. A social-share unfurl bot fetches this before
   any visitor is ever authenticated, so every shared `/provider/login` link would have shown a
   broken image. Fixed by adding it to the same `PROVIDER_PUBLIC_METADATA_PATHS` allowlist —
   any future metadata file placed directly under `src/app/provider/` needs the same entry, now
   called out explicitly in that file's comment.
2. **The provider app's page title was double-branded.** After adding provider-specific
   `openGraph`/`twitter` text to `src/app/provider/layout.tsx`, the OG/Twitter tags were correct
   but `<title>` still rendered as `"لوحة مزودي الخدمة — سطحات الرياض | منصة سطحات الرياض"` — the
   root layout's own `title.template` (`"%s | منصة سطحات الرياض"`) was wrapping the provider
   layout's `title.default`, because in Next's metadata resolution, `default` is still subject to
   every ancestor's template; only `title.absolute` bypasses them. Fixed by switching to
   `title: { absolute: PROVIDER_SITE_NAME, template: ... }`. Caught by literally reading the
   rendered `<title>` tag via curl, not by reasoning about the API from memory — the same lesson
   this project has drawn from real bugs in every prior frontend phase (Phase 17's `proxy.ts`
   bug, the response-envelope mismatches in Phases 18-19): verify the actual rendered output,
   never trust that an API behaves the way its shape suggests.

## Structured data

The homepage embeds a schema.org `Service` JSON-LD block (`src/components/json-ld.tsx`, following
Next's own recommended `<script type="application/ld+json">` pattern) — deliberately `Service`,
not `LocalBusiness`: this platform has no single physical storefront address to publish, and
schema.org has no dedicated towing-service type. The block only states what's actually true
(`areaServed: الرياض`, a `provider` `Organization`, a description) — no invented street address,
phone number, or `AggregateRating` fabricated just to fill out the schema, consistent with this
project's broader "no fabricated data" pattern applied everywhere else (fake adapters clearly
labeled fake, no invented test users left ambiguous, etc.).

## Why the OG images use Latin text, not Arabic

`ImageResponse` (`next/og`) needs an explicit font supplied via its `fonts` option to render
Arabic glyphs — its default font only covers Latin script — and fetching one at request time
would add a runtime dependency on an external font host for a route that's rarely hit (a
social-share bot, not a real user). This project avoids exactly that kind of dependency
elsewhere too (see `docs/PWA.md` §Icon design for the identical reasoning behind the PWA app
icons). Both OG images use a bold Latin wordmark ("Riyadh Tow Platform" / "Riyadh Tow — Provider
Portal") instead. This is a real, visible trade-off on an Arabic-primary product, written down
rather than silently shipped — revisit once bundling a real Arabic font file (a one-time static
asset, not a runtime fetch) is worth the size cost.

## Not yet in this phase

- No English-locale content or `hreflang` alternates — `docs/PRODUCT_REQUIREMENTS.md` frames
  English as a "planned secondary locale," not yet built anywhere in either app, so there's
  nothing to add alternate-language metadata for yet.
- No Arabic text in the generated OG images (see above).
- No per-page `<title>` overrides beyond the homepage/provider split — every other screen is
  transactional or behind a login and already excluded from search via `robots.ts`, so a distinct
  title per screen wasn't judged worth the client-component-to-server-wrapper refactor every one
  of those pages would need (they're all `"use client"` components today; a `metadata` export
  requires a server component).
- No Google Search Console / Bing Webmaster Tools verification meta tags — nothing to verify
  against until a real production domain exists.
- No blog, FAQ, or other content pages — this platform has none, and manufacturing SEO content
  with no product substance behind it wasn't in scope for this phase.
