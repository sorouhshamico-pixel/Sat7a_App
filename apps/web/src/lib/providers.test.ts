import { describe, expect, it } from "vitest";
import { documentStatusTone, providerStatusTone } from "./providers";

describe("providerStatusTone", () => {
  it("marks approved as success and rejected/suspended as danger", () => {
    expect(providerStatusTone("approved")).toBe("success");
    expect(providerStatusTone("rejected")).toBe("danger");
    expect(providerStatusTone("suspended")).toBe("danger");
  });

  it("marks under_review as info and pending as warning", () => {
    expect(providerStatusTone("under_review")).toBe("info");
    expect(providerStatusTone("pending")).toBe("warning");
  });
});

describe("documentStatusTone", () => {
  it("marks verified as success and rejected as danger", () => {
    expect(documentStatusTone("verified")).toBe("success");
    expect(documentStatusTone("rejected")).toBe("danger");
  });

  it("marks pending as warning", () => {
    expect(documentStatusTone("pending")).toBe("warning");
  });
});
