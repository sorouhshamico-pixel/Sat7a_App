import { expect, test } from "@playwright/test";

// Phase 23 security-hardening fix — see docs/SECURITY.md §CSP. A strict
// `script-src 'self'` with no 'unsafe-inline'/nonce was tried first and
// verified live to genuinely break Next.js's own hydration (a real
// invariant crash from its inline RSC-streaming scripts, not just a
// theoretical CSP violation) — this locks in the header shape that was
// actually confirmed working end-to-end, not just typechecked.
test("every page carries the baseline security headers, including a CSP with no external script/object/frame sources", async ({
  request,
}) => {
  const response = await request.get("/");
  const headers = response.headers();

  expect(headers["x-content-type-options"]).toBe("nosniff");
  expect(headers["x-frame-options"]).toBe("DENY");
  expect(headers["referrer-policy"]).toBe("strict-origin-when-cross-origin");
  expect(headers["permissions-policy"]).toContain("geolocation=(self)");

  const csp = headers["content-security-policy"];
  expect(csp).toBeTruthy();
  expect(csp).toContain("default-src 'self'");
  expect(csp).toContain("object-src 'none'");
  expect(csp).toContain("frame-ancestors 'none'");
  expect(csp).not.toMatch(/script-src[^;]*https?:\/\//);
});

test("the homepage hydrates and functions with zero console errors under the real CSP header", async ({
  page,
}) => {
  const consoleErrors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") consoleErrors.push(msg.text());
  });
  page.on("pageerror", (err) => consoleErrors.push(`PAGE ERROR: ${err.message}`));

  await page.goto("/");
  await expect(page.getByRole("heading", { name: "اطلب سطحة في الرياض خلال دقائق" })).toBeVisible();

  expect(consoleErrors).toEqual([]);
});
