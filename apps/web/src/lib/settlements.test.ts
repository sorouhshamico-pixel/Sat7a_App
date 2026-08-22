import { describe, expect, it } from "vitest";
import { SETTLEMENT_NEXT_STATUS, settlementStatusTone } from "./settlements";

describe("settlementStatusTone", () => {
  it("marks paid as success and failed/cancelled as danger", () => {
    expect(settlementStatusTone("paid")).toBe("success");
    expect(settlementStatusTone("failed")).toBe("danger");
    expect(settlementStatusTone("cancelled")).toBe("danger");
  });
});

describe("SETTLEMENT_NEXT_STATUS", () => {
  it("only offers the legal next step for each status, matching the backend's state machine", () => {
    expect(SETTLEMENT_NEXT_STATUS.draft).toEqual(["pending_approval", "cancelled"]);
    expect(SETTLEMENT_NEXT_STATUS.processing).toEqual(["paid", "failed"]);
  });

  it("offers no further transitions from a terminal status", () => {
    expect(SETTLEMENT_NEXT_STATUS.paid).toEqual([]);
    expect(SETTLEMENT_NEXT_STATUS.failed).toEqual([]);
    expect(SETTLEMENT_NEXT_STATUS.cancelled).toEqual([]);
  });
});
