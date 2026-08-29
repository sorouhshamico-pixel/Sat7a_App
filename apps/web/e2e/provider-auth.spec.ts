import { expect, test } from "@playwright/test";

test("an unauthenticated visitor to /provider is redirected to login", async ({ page }) => {
  await page.goto("/provider");

  await expect(page).toHaveURL(/\/provider\/login\?next=%2Fprovider/);
  await expect(page.getByRole("heading", { name: "تسجيل دخول مزودي الخدمة" })).toBeVisible();
});

test("an unauthenticated visitor to /provider/fleet is redirected to login", async ({ page }) => {
  await page.goto("/provider/fleet");

  await expect(page).toHaveURL(/\/provider\/login/);
});

test("the provider login page renders the phone form", async ({ page }) => {
  await page.goto("/provider/login");

  await expect(page.getByPlaceholder("+9665XXXXXXXX")).toBeVisible();
  await expect(page.getByRole("button", { name: "إرسال رمز التحقق" })).toBeVisible();
});
