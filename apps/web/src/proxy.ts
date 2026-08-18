import { NextRequest, NextResponse } from "next/server";
import { SESSION_COOKIE } from "@/lib/session-constants";

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
export function proxy(request: NextRequest) {
  const hasSession = request.cookies.has(SESSION_COOKIE);
  const isLoginPage = request.nextUrl.pathname === "/admin/login";

  if (!hasSession && !isLoginPage) {
    const loginUrl = new URL("/admin/login", request.url);
    loginUrl.searchParams.set("next", request.nextUrl.pathname);

    return NextResponse.redirect(loginUrl);
  }

  if (hasSession && isLoginPage) {
    return NextResponse.redirect(new URL("/admin", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/admin/:path*"],
};
