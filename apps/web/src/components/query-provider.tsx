"use client";

import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";
import { ApiRequestError } from "@/lib/api/types";

// A stale/revoked session cookie makes every apiGet/apiPost/... call
// (src/lib/api/client.ts) throw an ApiRequestError with the backend's
// UNAUTHENTICATED code (App\Support\Enums\ErrorCode::Unauthenticated,
// always HTTP 401) — checked by code, not just status, since 401 is also
// used for a few login-flow-specific failures (wrong password, an expired
// MFA challenge) that must stay as an inline error on the login page
// itself, not force a redirect. Those flows call their own dedicated
// /api/auth/... route handlers directly, never through client.ts, but
// keying off the code rather than the status is the unambiguous check
// either way. Previously every screen just rendered whatever generic
// "failed to load" state its own query.isError produced — see
// docs/OPERATIONS_COMMAND_CENTER.md §Not yet in this phase (pre-fix).
function isSessionExpired(error: unknown): boolean {
  return error instanceof ApiRequestError && error.code === "UNAUTHENTICATED";
}

// Just navigating to the login page is not enough — src/proxy.ts's own
// gate is presence-only (see its top comment) and bounces a *logged-in*
// visitor away from /login back to the dashboard, so a still-present
// (merely invalid) session cookie sends the browser straight back where
// it came from, looping forever. Caught live, not assumed: the first cut
// of this fix used a bare `window.location.href` and produced exactly
// that redirect loop against a real backend rejecting a bogus token.
// Clearing the cookie server-side through the app's own logout route
// first (same one the sign-out button calls) is what actually breaks it.
let redirecting = false;

async function redirectToLogin(logoutPath: string, loginPath: string): Promise<void> {
  if (typeof window === "undefined" || redirecting) return;
  if (window.location.pathname === loginPath) return;

  redirecting = true;

  try {
    await fetch(logoutPath, { method: "POST" });
  } finally {
    window.location.href = loginPath;
  }
}

export function QueryProvider({
  children,
  loginPath,
  logoutPath,
}: {
  children: React.ReactNode;
  loginPath: string;
  logoutPath: string;
}) {
  const [client] = useState(
    () =>
      new QueryClient({
        queryCache: new QueryCache({
          onError: (error) => {
            if (isSessionExpired(error)) void redirectToLogin(logoutPath, loginPath);
          },
        }),
        mutationCache: new MutationCache({
          onError: (error) => {
            if (isSessionExpired(error)) void redirectToLogin(logoutPath, loginPath);
          },
        }),
        defaultOptions: {
          queries: {
            staleTime: 10_000,
            retry: 1,
          },
        },
      }),
  );

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
