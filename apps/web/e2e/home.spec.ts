import { expect, test } from "@playwright/test";

test("homepage renders the quote builder", async ({ page }) => {
  await page.goto("/");

  await expect(page.getByRole("heading", { name: "اطلب سطحة في الرياض خلال دقائق" })).toBeVisible();
  await expect(page.getByRole("link", { name: "منصة سطحات الرياض" })).toBeVisible();
});
