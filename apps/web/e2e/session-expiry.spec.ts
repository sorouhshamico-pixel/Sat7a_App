import { expect, test } from "@playwright/test";

// Regression test for the fix documented in
// src/components/query-provider.tsx and docs/OPERATIONS_COMMAND_CENTER.md
// §Authentication: a stale/revoked session cookie used to leave every
// screen showing a generic "failed to load" state forever instead of
// redirecting to login.
//
// Every current e2e spec (including this one) runs against the Next.js
// app alone, no live backend — see docs/PRODUCTION_READINESS.md's
// post-roadmap CI section on why the `e2e` CI job has no Postgres/Redis/
// backend services. So rather than a real backend rejecting a real bogus
// token, this intercepts the browser's own request to the backend proxy
// and fulfills it directly with the same UNAUTHENTICATED envelope Laravel
// sends for an expired/revoked Sanctum token
// (App\Exceptions\ApiExceptionRenderer, ErrorCode::Unauthenticated) —
// exercising the exact response shape the fix keys off, without needing
// the backend at all.
const UNAUTHENTICATED_ENVELOPE = {
  data: null,
  meta: {},
  errors: [{ code: "UNAUTHENTICATED", message: "Authentication required." }],
};

async function mockUnauthenticated(page: import("@playwright/test").Page) {
  // Only the backend proxy call needs mocking. The logout call the fix
  // makes before redirecting (src/components/query-provider.tsx) runs for
  // real, unmocked — it's local Next.js Route Handler code
  // (src/app/api/auth/*/logout/route.ts) that clears the httpOnly cookie
  // itself; its own best-effort call to the real backend is wrapped in
  // .catch() precisely so it degrades gracefully with no backend running.
  // Mocking it away here was tried first and produced a false pass: the
  // real cookie was never cleared, so navigating to the login page hit
  // src/proxy.ts's own "already logged in" reverse-gate and bounced right
  // back — the exact loop this fix exists to prevent, just relocated by
  // the mock instead of caught.
  await page.route("**/api/backend/**", (route) =>
    route.fulfill({ status: 401, json: UNAUTHENTICATED_ENVELOPE }),
  );
}

test("admin: a stale session redirects to /admin/login instead of hanging on a dead screen", async ({
  page,
  context,
}) => {
  await context.addCookies([
    { name: "admin_session", value: "1|stale", domain: "localhost", path: "/" },
    {
      name: "admin_user",
      value: JSON.stringify({ id: "1", name: "Test Admin", email: "test@example.test" }),
      domain: "localhost",
      path: "/",
    },
  ]);
  await mockUnauthenticated(page);

  await page.goto("/admin/orders");

  await expect(page).toHaveURL(/\/admin\/login/, { timeout: 10_000 });
  await expect(page.getByRole("heading", { name: "تسجيل دخول الإدارة" })).toBeVisible();

  // Settles — proxy.ts's own reverse-gate (bounce a *logged-in* visitor
  // away from /login) doesn't bounce it right back, because the cookie
  // was actually cleared, not just left in place.
  await page.waitForTimeout(1000);
  await expect(page).toHaveURL(/\/admin\/login/);
});

test("provider: a stale session redirects to /provider/login instead of hanging on a dead screen", async ({
  page,
  context,
}) => {
  await context.addCookies([
    { name: "provider_session", value: "1|stale", domain: "localhost", path: "/" },
    {
      name: "provider_user",
      value: JSON.stringify({ id: "1", name: "Test Provider", phone: "+966500000000" }),
      domain: "localhost",
      path: "/",
    },
  ]);
  await mockUnauthenticated(page);

  await page.goto("/provider/documents");

  await expect(page).toHaveURL(/\/provider\/login/, { timeout: 10_000 });

  await page.waitForTimeout(1000);
  await expect(page).toHaveURL(/\/provider\/login/);
});

test("customer: a stale session redirects to /login instead of hanging on a dead screen", async ({
  page,
  context,
}) => {
  await context.addCookies([
    { name: "customer_session", value: "1|stale", domain: "localhost", path: "/" },
    {
      name: "customer_user",
      value: JSON.stringify({ id: "1", name: "Test Customer", phone: "+966500000000" }),
      domain: "localhost",
      path: "/",
    },
  ]);
  await mockUnauthenticated(page);

  await page.goto("/orders");

  await expect(page).toHaveURL(/\/login/, { timeout: 10_000 });

  await page.waitForTimeout(1000);
  await expect(page).toHaveURL(/\/login/);
});
