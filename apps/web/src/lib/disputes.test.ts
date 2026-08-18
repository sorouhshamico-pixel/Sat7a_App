import { describe, expect, it } from "vitest";
import { disputeStatusTone } from "./disputes";

describe("disputeStatusTone", () => {
  it("marks resolved as success and rejected as danger", () => {
    expect(disputeStatusTone("resolved")).toBe("success");
    expect(disputeStatusTone("rejected")).toBe("danger");
  });

  it("marks under_review as info and open as warning", () => {
    expect(disputeStatusTone("under_review")).toBe("info");
    expect(disputeStatusTone("open")).toBe("warning");
  });
});
