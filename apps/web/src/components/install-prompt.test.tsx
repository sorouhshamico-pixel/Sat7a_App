import { render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { InstallPrompt } from "./install-prompt";

function stubMatchMedia(standalone: boolean) {
  vi.stubGlobal(
    "matchMedia",
    vi.fn().mockImplementation((query: string) => ({
      matches: query.includes("standalone") ? standalone : false,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("InstallPrompt", () => {
  it("renders nothing by default (no install prompt event, not iOS, not standalone)", async () => {
    stubMatchMedia(false);
    const { container } = render(<InstallPrompt appLabel="سطحات الرياض" />);

    await waitFor(() => expect(container).toBeEmptyDOMElement());
  });

  it("renders nothing when already running as an installed (standalone) app", async () => {
    stubMatchMedia(true);
    const { container } = render(<InstallPrompt appLabel="سطحات الرياض" />);

    await waitFor(() => expect(container).toBeEmptyDOMElement());
  });

  it("shows an install button once the browser fires beforeinstallprompt", async () => {
    stubMatchMedia(false);
    render(<InstallPrompt appLabel="سطحات الرياض" />);

    const event = new Event("beforeinstallprompt", { cancelable: true }) as Event & {
      prompt: () => Promise<void>;
      userChoice: Promise<{ outcome: "accepted" | "dismissed" }>;
    };
    event.prompt = vi.fn().mockResolvedValue(undefined);
    event.userChoice = Promise.resolve({ outcome: "accepted" });
    window.dispatchEvent(event);

    expect(await screen.findByRole("button", { name: "تثبيت" })).toBeVisible();
  });
});
