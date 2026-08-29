import { expect, test } from "@playwright/test";

test("the customer app serves a valid manifest with the correct icons", async ({ request }) => {
  const response = await request.get("/manifest.webmanifest");
  expect(response.ok()).toBeTruthy();

  const manifest = await response.json();
  expect(manifest.start_url).toBe("/");
  expect(manifest.icons).toHaveLength(2);
});

test("the provider app serves its own distinct manifest, not the customer one", async ({
  request,
}) => {
  const response = await request.get("/provider/manifest.webmanifest");
  expect(response.ok()).toBeTruthy();

  const manifest = await response.json();
  expect(manifest.start_url).toBe("/provider");
  expect(manifest.name).toContain("مزودي الخدمة");
});

test("provider manifest and icons are reachable without authentication", async ({ request }) => {
  for (const path of ["/provider/manifest.webmanifest", "/provider/icon", "/provider/apple-icon"]) {
    const response = await request.get(path, { maxRedirects: 0 });
    expect(response.status(), `${path} should not redirect to login`).toBe(200);
  }
});

test("a protected provider page still redirects to login when signed out", async ({ request }) => {
  const response = await request.get("/provider/fleet", { maxRedirects: 0 });
  expect(response.status()).toBe(307);
  expect(response.headers()["location"]).toContain("/provider/login");
});

test("the service worker registers successfully on the homepage", async ({ page }) => {
  await page.goto("/");

  const registered = await page.evaluate(async () => {
    if (!("serviceWorker" in navigator)) return false;
    const registration = await navigator.serviceWorker.ready;
    return Boolean(registration.active);
  });

  expect(registered).toBe(true);
});

test("a failed navigation while offline falls back to the offline page", async ({
  page,
  context,
}) => {
  await page.goto("/");
  // Give the service worker time to install and precache the offline page.
  await page.evaluate(() => navigator.serviceWorker.ready);
  await page.waitForTimeout(500);

  await context.setOffline(true);
  await page.goto("/vehicles-that-do-not-exist-anywhere", { waitUntil: "load" }).catch(() => {});

  await expect(page.getByRole("heading", { name: "أنت غير متصل بالإنترنت" })).toBeVisible({
    timeout: 5000,
  });

  await context.setOffline(false);
});
