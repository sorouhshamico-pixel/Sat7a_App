import { expect, test } from "@playwright/test";

test("an unauthenticated visitor to /admin is redirected to the login page", async ({ page }) => {
  await page.goto("/admin");

  await expect(page).toHaveURL(/\/admin\/login/);
  await expect(page.getByRole("heading", { name: "تسجيل دخول الإدارة" })).toBeVisible();
});

test("the login page renders the credentials form", async ({ page }) => {
  await page.goto("/admin/login");

  await expect(page.getByPlaceholder("البريد الإلكتروني")).toBeVisible();
  await expect(page.getByPlaceholder("كلمة المرور")).toBeVisible();
  await expect(page.getByRole("button", { name: "دخول" })).toBeVisible();
});
