import { expect, test } from "@playwright/test";

test("robots.txt disallows the transactional/staff app trees and points at the sitemap", async ({
  request,
}) => {
  const response = await request.get("/robots.txt");
  expect(response.ok()).toBeTruthy();

  const body = await response.text();
  expect(body).toContain("Disallow: /orders");
  expect(body).toContain("Disallow: /admin");
  expect(body).toContain("Disallow: /provider");
  expect(body).toContain("Allow: /provider/login");
  expect(body).toContain("Sitemap:");
});

test("sitemap.xml lists only the homepage", async ({ request }) => {
  const response = await request.get("/sitemap.xml");
  expect(response.ok()).toBeTruthy();

  const body = await response.text();
  const locCount = (body.match(/<loc>/g) ?? []).length;
  expect(locCount).toBe(1);
});

test("the homepage renders Open Graph tags, a matching image, and JSON-LD structured data", async ({
  page,
}) => {
  await page.goto("/");

  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    "content",
    "منصة سطحات الرياض",
  );
  await expect(page.locator('meta[property="og:image"]')).toHaveAttribute(
    "content",
    /\/opengraph-image/,
  );

  const jsonLd = await page.locator('script[type="application/ld+json"]').textContent();
  const data = JSON.parse(jsonLd ?? "{}");
  expect(data["@type"]).toBe("Service");
  expect(data.areaServed.name).toBe("الرياض");
});

test("the provider app renders its own distinct title and Open Graph text, not the customer app's", async ({
  page,
}) => {
  await page.goto("/provider/login");

  await expect(page).toHaveTitle("لوحة مزودي الخدمة — سطحات الرياض");
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    "content",
    "لوحة مزودي الخدمة — سطحات الرياض",
  );
  await expect(page.locator('meta[property="og:image"]')).toHaveAttribute(
    "content",
    /\/provider\/opengraph-image/,
  );
});

test("provider metadata routes (manifest, icons, opengraph-image) are reachable without authentication", async ({
  request,
}) => {
  for (const path of [
    "/provider/manifest.webmanifest",
    "/provider/icon",
    "/provider/apple-icon",
    "/provider/opengraph-image",
  ]) {
    const response = await request.get(path, { maxRedirects: 0 });
    expect(response.status(), `${path} should not redirect to login`).toBe(200);
  }
});
