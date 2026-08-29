import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import { AddressSearch } from "./address-search";

function jsonResponse(body: unknown) {
  return { ok: true, status: 200, json: async () => body };
}

afterEach(() => {
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe("AddressSearch", () => {
  it("does not search until at least 3 characters are typed", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValue(jsonResponse({ data: { suggestions: [] }, meta: {}, errors: null }));
    vi.stubGlobal("fetch", fetchMock);

    const user = userEvent.setup();
    render(<AddressSearch label="من" placeholder="من أين؟" onSelect={vi.fn()} />);

    await user.type(screen.getByPlaceholderText("من أين؟"), "ال");
    await new Promise((resolve) => setTimeout(resolve, 400));

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("shows suggestions and calls onSelect with the chosen place's coordinates", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        jsonResponse({
          data: { suggestions: [{ place_id: "p1", description: "الرياض، السعودية" }] },
          meta: {},
          errors: null,
        }),
      )
      .mockResolvedValueOnce(
        jsonResponse({
          data: {
            place_id: "p1",
            formatted_address: "الرياض، السعودية",
            coordinates: { latitude: 24.7136, longitude: 46.6753 },
          },
          meta: {},
          errors: null,
        }),
      );
    vi.stubGlobal("fetch", fetchMock);

    const onSelect = vi.fn();
    const user = userEvent.setup();
    render(<AddressSearch label="من" placeholder="من أين؟" onSelect={onSelect} />);

    await user.type(screen.getByPlaceholderText("من أين؟"), "الرياض");

    const suggestion = await screen.findByText("الرياض، السعودية", {}, { timeout: 2000 });
    await user.click(suggestion);

    await waitFor(() =>
      expect(onSelect).toHaveBeenCalledWith({
        formatted_address: "الرياض، السعودية",
        latitude: 24.7136,
        longitude: 46.6753,
      }),
    );
  });
});
