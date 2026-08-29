import { describe, expect, it } from "vitest";
import { TRIP_NEXT_STATUS, TRIP_NEXT_STATUS_LABEL } from "./trips";

describe("TRIP_NEXT_STATUS", () => {
  it("walks the full driver-advanceable sequence in order", () => {
    expect(TRIP_NEXT_STATUS.provider_assigned).toBe("provider_en_route");
    expect(TRIP_NEXT_STATUS.provider_en_route).toBe("provider_arrived");
    expect(TRIP_NEXT_STATUS.provider_arrived).toBe("vehicle_loading");
    expect(TRIP_NEXT_STATUS.vehicle_loading).toBe("trip_started");
    expect(TRIP_NEXT_STATUS.trip_started).toBe("in_transit");
    expect(TRIP_NEXT_STATUS.in_transit).toBe("vehicle_delivered");
    expect(TRIP_NEXT_STATUS.vehicle_delivered).toBe("completed");
  });

  it("has no next status once completed", () => {
    expect(TRIP_NEXT_STATUS.completed).toBeNull();
  });

  it("has a label for every non-null next status", () => {
    for (const status of Object.values(TRIP_NEXT_STATUS)) {
      if (status === null) continue;
      expect(TRIP_NEXT_STATUS_LABEL[status]).toBeDefined();
    }
  });
});
