import { describe, expect, it } from "vitest";
import { TOW_TRUCK_NEXT_STATUSES, towTruckStatusTone } from "./fleet";

describe("towTruckStatusTone", () => {
  it("marks available as success and suspended/unavailable as danger", () => {
    expect(towTruckStatusTone("available")).toBe("success");
    expect(towTruckStatusTone("suspended")).toBe("danger");
    expect(towTruckStatusTone("unavailable")).toBe("danger");
  });
});

describe("TOW_TRUCK_NEXT_STATUSES", () => {
  it("only offers the legal next step for each status, matching the backend's state machine", () => {
    expect(TOW_TRUCK_NEXT_STATUSES.offline).toEqual(["available", "maintenance"]);
    expect(TOW_TRUCK_NEXT_STATUSES.available).toEqual(["offline", "maintenance"]);
  });

  it("offers no directly-settable transitions from a dispatch/trip-owned status", () => {
    expect(TOW_TRUCK_NEXT_STATUSES.reserved).toEqual([]);
    expect(TOW_TRUCK_NEXT_STATUSES.en_route).toEqual([]);
    expect(TOW_TRUCK_NEXT_STATUSES.on_trip).toEqual([]);
    expect(TOW_TRUCK_NEXT_STATUSES.suspended).toEqual([]);
  });
});
