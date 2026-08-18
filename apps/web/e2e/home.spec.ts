import { expect, test } from "@playwright/test";

test("homepage renders the platform heading", async ({ page }) => {
  await page.goto("/");

  await expect(page.getByRole("heading", { name: "منصة سطحات الرياض" })).toBeVisible();
});
