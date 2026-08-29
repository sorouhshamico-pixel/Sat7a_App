import { NextRequest, NextResponse } from "next/server";
import { SESSION_COOKIE } from "@/lib/session-constants";
import { CUSTOMER_SESSION_COOKIE } from "@/lib/customer-session-constants";
import { PROVIDER_SESSION_COOKIE } from "@/lib/provider-session-constants";

// Lives at src/proxy.ts, not the project root — this app uses a src/
// directory, and Next.js requires proxy.ts to sit alongside app/ (see
// node_modules/next/dist/docs/01-app/03-api-reference/03-file-conventions/proxy.md
// §"Create a proxy.ts ... inside src if applicable"). Also named
// `proxy.ts`, not `middleware.ts` — Next.js 16 renamed the convention and
// silently ignores the old filename.
//
// Presence-only check — the cookie's actual validity (expired/revoked
// token) is verified per-request by Laravel itself when a page's data
// fetch hits src/app/api/backend/[...path]/route.ts; that route returns
// UNAUTHENTICATED and the page-level error handling redirects to login
// (see docs/OPERATIONS_COMMAND_CENTER.md §Authentication). This proxy
// only exists to stop an unauthenticated visitor from ever rendering a
// protected page's shell in the first place.
//
// Three independent gates share this one file: `/admin/*` (staff, MFA
// session), the customer-only route tree (`/orders/*`, `/vehicles/*`,
// phone+OTP session) — see docs/CUSTOMER_WEB_APP.md §Authentication — and
// `/provider/*` (provider-staff, phone+OTP session) — see
// docs/PROVIDER_WEB_APP.md §Authentication. `/` and `/login` stay public,
// since a guest builds a quote before authenticating
// (docs/PRODUCT_REQUIREMENTS.md §Core customer journey).
const CUSTOMER_PROTECTED_PREFIXES = ["/orders", "/vehicles"];

// A browser fetches these — the manifest, the tab/home-screen icons —
// before a visitor is ever authenticated, straight from the <link> tags
// Next.js injects into /provider/login's own <head> (see
// src/app/provider/manifest.webmanifest/route.ts, src/app/provider/
// icon.tsx, src/app/provider/apple-icon.tsx). Real bug caught during
// Phase 21 verification: the /provider/:path* gate below was blocking
// all three, silently breaking installability for any signed-out visitor
// (see docs/PWA.md §Real bug found).
const PROVIDER_PUBLIC_METADATA_PATHS = [
  "/provider/manifest.webmanifest",
  "/provider/icon",
  "/provider/apple-icon",
];

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (PROVIDER_PUBLIC_METADATA_PATHS.includes(pathname)) {
    return NextResponse.next();
  }

  if (pathname.startsWith("/admin")) {
    const hasSession = request.cookies.has(SESSION_COOKIE);
    const isLoginPage = pathname === "/admin/login";

    if (!hasSession && !isLoginPage) {
      const loginUrl = new URL("/admin/login", request.url);
      loginUrl.searchParams.set("next", pathname);

      return NextResponse.redirect(loginUrl);
    }

    if (hasSession && isLoginPage) {
      return NextResponse.redirect(new URL("/admin", request.url));
    }

    return NextResponse.next();
  }

  if (pathname.startsWith("/provider")) {
    const hasProviderSession = request.cookies.has(PROVIDER_SESSION_COOKIE);
    const isLoginPage = pathname === "/provider/login";

    if (!hasProviderSession && !isLoginPage) {
      const loginUrl = new URL("/provider/login", request.url);
      loginUrl.searchParams.set("next", pathname + request.nextUrl.search);

      return NextResponse.redirect(loginUrl);
    }

    if (hasProviderSession && isLoginPage) {
      return NextResponse.redirect(new URL("/provider", request.url));
    }

    return NextResponse.next();
  }

  const hasCustomerSession = request.cookies.has(CUSTOMER_SESSION_COOKIE);
  const isProtected = CUSTOMER_PROTECTED_PREFIXES.some((prefix) => pathname.startsWith(prefix));
  const isLoginPage = pathname === "/login";

  if (isProtected && !hasCustomerSession) {
    const loginUrl = new URL("/login", request.url);
    loginUrl.searchParams.set("next", pathname + request.nextUrl.search);

    return NextResponse.redirect(loginUrl);
  }

  if (hasCustomerSession && isLoginPage) {
    return NextResponse.redirect(new URL("/orders", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*", "/provider/:path*", "/orders/:path*", "/vehicles/:path*", "/login"],
};
