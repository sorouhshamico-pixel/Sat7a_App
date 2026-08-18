import { afterEach, describe, expect, it, vi } from "vitest";
import { apiGet, apiPost } from "./client";
import { ApiRequestError } from "./types";

function mockFetchOnce(status: number, body: unknown) {
  vi.stubGlobal(
    "fetch",
    vi.fn().mockResolvedValue({
      ok: status >= 200 && status < 300,
      status,
      json: async () => body,
    }),
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("apiGet", () => {
  it("resolves with the envelope's data on success", async () => {
    mockFetchOnce(200, { data: { orders: [] }, meta: { total: 0 }, errors: null });

    const result = await apiGet<{ orders: unknown[] }>("admin/orders");

    expect(result.data).toEqual({ orders: [] });
    expect(result.meta).toEqual({ total: 0 });
  });

  it("calls the local backend proxy, not the Laravel API directly", async () => {
    mockFetchOnce(200, { data: {}, meta: {}, errors: null });

    await apiGet("admin/orders", { status: "completed" });

    const fetchMock = fetch as unknown as ReturnType<typeof vi.fn>;
    expect(fetchMock.mock.calls[0][0]).toBe("/api/backend/admin/orders?status=completed");
  });

  it("throws ApiRequestError with the backend's error code/message on failure", async () => {
    mockFetchOnce(404, {
      data: null,
      meta: {},
      errors: [{ code: "NOT_FOUND", message: "Order not found." }],
    });

    await expect(apiGet("admin/orders/xyz")).rejects.toMatchObject({
      message: "Order not found.",
      code: "NOT_FOUND",
      status: 404,
    });
  });
});

describe("apiPost", () => {
  it("throws an ApiRequestError instance a caller can catch specifically", async () => {
    mockFetchOnce(422, {
      data: null,
      meta: {},
      errors: [{ code: "VALIDATION_FAILED", message: "Invalid." }],
    });

    try {
      await apiPost("admin/orders/xyz/cancel", { reason: "" });
      expect.unreachable();
    } catch (error) {
      expect(error).toBeInstanceOf(ApiRequestError);
    }
  });
});
