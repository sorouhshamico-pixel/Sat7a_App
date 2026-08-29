import { expect, test } from "@playwright/test";

test("an unauthenticated visitor to /orders is redirected to login", async ({ page }) => {
  await page.goto("/orders");

  await expect(page).toHaveURL(/\/login\?next=%2Forders/);
  await expect(page.getByRole("heading", { name: "تسجيل الدخول" })).toBeVisible();
});

test("an unauthenticated visitor to /vehicles is redirected to login", async ({ page }) => {
  await page.goto("/vehicles");

  await expect(page).toHaveURL(/\/login/);
});

test("the login page renders the phone form", async ({ page }) => {
  await page.goto("/login");

  await expect(page.getByPlaceholder("+9665XXXXXXXX")).toBeVisible();
  await expect(page.getByRole("button", { name: "إرسال رمز التحقق" })).toBeVisible();
});
