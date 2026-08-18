import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import AdminLoginPage from "./page";

const pushMock = vi.fn();
const refreshMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock, refresh: refreshMock }),
}));

function jsonResponse(status: number, body: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => body };
}

afterEach(() => {
  vi.unstubAllGlobals();
  pushMock.mockClear();
  refreshMock.mockClear();
});

describe("AdminLoginPage", () => {
  it("shows an error and stays on the credentials step when login fails", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(
        jsonResponse(401, {
          data: null,
          meta: {},
          errors: [{ code: "INVALID_CREDENTIALS", message: "Invalid email or password." }],
        }),
      ),
    );

    const user = userEvent.setup();
    render(<AdminLoginPage />);

    await user.type(screen.getByPlaceholderText("البريد الإلكتروني"), "admin@example.com");
    await user.type(screen.getByPlaceholderText("كلمة المرور"), "wrong-password");
    await user.click(screen.getByRole("button", { name: "دخول" }));

    expect(await screen.findByText("Invalid email or password.")).toBeInTheDocument();
    expect(pushMock).not.toHaveBeenCalled();
  });

  it("walks a returning admin through the MFA challenge step to a full session", async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        jsonResponse(200, {
          data: { stage: "mfa_challenge_required", token: "mfa-challenge-token" },
          meta: {},
          errors: null,
        }),
      )
      .mockResolvedValueOnce(
        jsonResponse(200, {
          data: { user: { id: "u1", name: "Admin", email: "admin@example.com" } },
          meta: {},
          errors: null,
        }),
      );
    vi.stubGlobal("fetch", fetchMock);

    const user = userEvent.setup();
    render(<AdminLoginPage />);

    await user.type(screen.getByPlaceholderText("البريد الإلكتروني"), "admin@example.com");
    await user.type(screen.getByPlaceholderText("كلمة المرور"), "correct-password");
    await user.click(screen.getByRole("button", { name: "دخول" }));

    const codeInput = await screen.findByPlaceholderText("رمز التحقق");
    await user.type(codeInput, "123456");
    await user.click(screen.getByRole("button", { name: "تحقق" }));

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith("/admin"));
    expect(refreshMock).toHaveBeenCalled();
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      "/api/auth/mfa/challenge",
      expect.objectContaining({
        body: JSON.stringify({ token: "mfa-challenge-token", code: "123456" }),
      }),
    );
  });
});
