// Split out from provider-session.ts so src/proxy.ts (edge runtime) can
// read the cookie name without importing next/headers — same reason
// customer-session-constants.ts exists (see docs/CUSTOMER_WEB_APP.md).
export const PROVIDER_SESSION_COOKIE = "provider_session";
