import { describe, expect, it } from "vitest";
import {
  CANCELLABLE_ORDER_STATUSES,
  DISPATCHABLE_ORDER_STATUSES,
  orderStatusLabel,
  orderStatusTone,
} from "./orders";

describe("orderStatusLabel", () => {
  it("returns the Arabic label for a known status", () => {
    expect(orderStatusLabel("completed")).toBe("مكتمل");
  });

  it("falls back to the raw value for an unknown status", () => {
    expect(orderStatusLabel("something_new")).toBe("something_new");
  });
});

describe("orderStatusTone", () => {
  it("marks completed as success", () => {
    expect(orderStatusTone("completed")).toBe("success");
  });

  it("marks every cancellation and expiry as danger", () => {
    expect(orderStatusTone("cancelled_by_customer")).toBe("danger");
    expect(orderStatusTone("cancelled_by_provider")).toBe("danger");
    expect(orderStatusTone("cancelled_by_admin")).toBe("danger");
    expect(orderStatusTone("expired")).toBe("danger");
  });

  it("marks disputed and refund_pending as warning", () => {
    expect(orderStatusTone("disputed")).toBe("warning");
    expect(orderStatusTone("refund_pending")).toBe("warning");
  });
});

describe("status sets", () => {
  it("only allows dispatch retry/assign before a provider search has resolved", () => {
    expect(DISPATCHABLE_ORDER_STATUSES.has("pending")).toBe(true);
    expect(DISPATCHABLE_ORDER_STATUSES.has("searching_provider")).toBe(true);
    expect(DISPATCHABLE_ORDER_STATUSES.has("provider_assigned")).toBe(false);
  });

  it("blocks cancellation once a trip has physically started", () => {
    expect(CANCELLABLE_ORDER_STATUSES.has("vehicle_loading")).toBe(true);
    expect(CANCELLABLE_ORDER_STATUSES.has("trip_started")).toBe(false);
    expect(CANCELLABLE_ORDER_STATUSES.has("completed")).toBe(false);
  });
});
