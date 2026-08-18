// Split out from src/lib/session.ts so middleware.ts (edge runtime) can read
// the cookie name without importing next/headers, which that file uses and
// which isn't available there.
export const SESSION_COOKIE = "admin_session";
