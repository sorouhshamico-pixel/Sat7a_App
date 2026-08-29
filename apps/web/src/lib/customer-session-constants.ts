// Split out from customer-session.ts so proxy.ts (edge runtime) can read the
// cookie name without importing next/headers — same reasoning as
// session-constants.ts for the admin side.
export const CUSTOMER_SESSION_COOKIE = "customer_session";
